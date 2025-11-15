<?php

namespace App\Http\Controllers\Api\Statutory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Statutory\StatutoryChargeRequest;
use App\Http\Resources\Statutory\StatutoryChargeResource;
use App\Models\Tenant\StatutoryCharge;
use App\Models\Tenant\StatutoryChargeType;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\PropertyPaymentPlan;
use App\Services\Tenant\TenantPaymentService;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StatutoryChargeController extends Controller
{
    public function __construct(
        protected TenantPaymentService $tenantPaymentService,
        protected NotificationService $notificationService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $query = StatutoryCharge::with(['member.user', 'payments']);

        // Filter by user if not admin
        if (!$request->user()->isAdmin()) {
            $query->whereHas('member', function($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Limit pagination to prevent memory issues
        $perPage = min((int) $request->get('per_page', 15), 100);
        $charges = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'charges' => StatutoryChargeResource::collection($charges),
            'pagination' => [
                'current_page' => $charges->currentPage(),
                'last_page' => $charges->lastPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
            ]
        ]);
    }

    public function store(StatutoryChargeRequest $request): JsonResponse
    {
        $member = $request->user()->member;
        
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $charge = StatutoryCharge::create([
            'member_id' => $member->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        // Notify admins about new statutory charge submission
        $memberName = trim($member->first_name . ' ' . $member->last_name);
        $this->notificationService->notifyAdmins(
            'info',
            'New Statutory Charge Submission',
            "A new statutory charge of ₦" . number_format($charge->amount, 2) . " has been submitted by {$memberName}",
            [
                'charge_id' => $charge->id,
                'member_id' => $member->id,
                'member_name' => $memberName,
                'amount' => $charge->amount,
                'type' => $charge->type,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Statutory charge created successfully',
            'charge' => new StatutoryChargeResource($charge->load('member.user'))
        ], 201);
    }

    public function show(Request $request, string $chargeId): JsonResponse
    {
        // Check if user can view this charge
        $charge = StatutoryCharge::findOrFail($chargeId);
        if (!$request->user()->isAdmin() && $charge->member->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $charge->load(['member.user', 'payments']);

        return response()->json([
            'charge' => new StatutoryChargeResource($charge)
        ]);
    }

    public function update(StatutoryChargeRequest $request, string $chargeId): JsonResponse
    {
        $charge = StatutoryCharge::findOrFail($chargeId);
        // Only allow updates if charge is pending
        if ($charge->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update charge that is not pending'
            ], 400);
        }

        $charge->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Statutory charge updated successfully',
            'charge' => new StatutoryChargeResource($charge->load('member.user'))
        ]);
    }

    public function approve(Request $request, string $chargeId): JsonResponse
    {
        $charge = StatutoryCharge::findOrFail($chargeId);
        if ($charge->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending charges can be approved'
            ], 400);
        }

        $charge->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        $charge->load('member.user');

        // Notify the member about statutory charge approval
        if ($charge->member && $charge->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$charge->member->user->id],
                'success',
                'Statutory Charge Approved',
                'Your statutory charge of ₦' . number_format($charge->amount, 2) . ' has been approved.',
                [
                    'charge_id' => $charge->id,
                    'amount' => $charge->amount,
                    'type' => $charge->type,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Statutory charge approved successfully'
        ]);
    }

    public function reject(StatutoryCharge $charge): JsonResponse
    {
        $request = request();
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($charge->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending charges can be rejected'
            ], 400);
        }

        $charge->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
        ]);

        $charge->load('member.user');

        // Notify the member about statutory charge rejection
        if ($charge->member && $charge->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$charge->member->user->id],
                'warning',
                'Statutory Charge Rejected',
                'Your statutory charge of ₦' . number_format($charge->amount, 2) . ' has been rejected. Reason: ' . $request->reason,
                [
                    'charge_id' => $charge->id,
                    'amount' => $charge->amount,
                    'type' => $charge->type,
                    'reason' => $request->reason,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Statutory charge rejected successfully'
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        try {
            $methods = $this->tenantPaymentService->getAvailablePaymentMethods('statutory_charge');

            return response()->json([
                'success' => true,
                'payment_methods' => $methods,
            ]);
        } catch (\Throwable $exception) {
            Log::error('StatutoryChargeController::paymentMethods() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to load payment methods at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function getChargeTypes(Request $request): JsonResponse
    {
        try {
            // Use StatutoryChargeType model if available, otherwise fallback to charges
            if (class_exists(\App\Models\Tenant\StatutoryChargeType::class)) {
                // Show all active charge types to members (they can pay any type)
                $query = \App\Models\Tenant\StatutoryChargeType::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('type');

                $types = $query->get()
                    ->map(function($type) {
                        return [
                            'type' => $type->type,
                            'description' => $type->description,
                            'default_amount' => $type->default_amount ? (float) $type->default_amount : null,
                            'frequency' => $type->frequency,
                            'frequency_display' => $this->formatFrequency($type->frequency),
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'data' => $types
                ]);
            }

            // Fallback: Get unique charge types from existing charges
            $query = StatutoryCharge::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->orderBy('type');

            // Filter by user if not admin (members see types from their charges)
            if (!$request->user()->isAdmin()) {
                $query->whereHas('member', function($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                });
            }

            $types = $query->get()
                ->map(function($item) {
                    return [
                        'type' => $item->type,
                        'count' => $item->count,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Throwable $exception) {
            Log::error('StatutoryChargeController::getChargeTypes() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to load charge types at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    private function formatFrequency(string $frequency): string
    {
        return match($frequency) {
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'bi_annually' => 'Bi-Annually',
            'annually' => 'Annual',
            default => ucfirst($frequency),
        };
    }

    public function createAndPay(Request $request): JsonResponse
    {
        $request->validate([
            'charge_type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'property_id' => 'nullable|uuid|exists:properties,id',
            'description' => 'nullable|string',
            'reference' => 'nullable|string',
        ]);

        $member = $request->user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        // Verify property belongs to member if provided
        if ($request->property_id) {
            $property = \App\Models\Tenant\Property::find($request->property_id);
            if (!$property) {
                return response()->json(['message' => 'Property not found'], 404);
            }
            // Check if member has approved interest or allocation for this property
            $hasAccess = PropertyInterest::where('property_id', $property->id)
                ->where('member_id', $member->id)
                ->where('status', 'approved')
                ->exists();
            
            if (!$hasAccess) {
                return response()->json(['message' => 'Property does not belong to you'], 403);
            }
        }

        try {
            DB::beginTransaction();

            // Fetch the charge type to check frequency
            $chargeType = StatutoryChargeType::where('type', $request->charge_type)
                ->where('is_active', true)
                ->first();

            if (!$chargeType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid charge type'
                ], 400);
            }

            // Calculate due dates based on frequency
            $charges = $this->createChargesForFrequency(
                $member->id,
                $request->charge_type,
                $request->amount,
                $request->description,
                $chargeType->frequency,
                $request->user()->id
            );

            if (empty($charges)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create charges'
                ], 500);
            }

            // Process payment for the first charge (current payment)
            // Other charges will be created with pending status and can be paid later
            $firstCharge = $charges[0];
            
            // Store property_id in request for processPayment to use
            $request->merge(['property_id' => $request->property_id]);

            // Process payment (reuse pay logic)
            $result = $this->processPayment($firstCharge, $request);
            
            // If payment is successful and there are multiple charges, include info about future charges
            if (count($charges) > 1 && $result->getData(true)['success'] ?? false) {
                $resultData = $result->getData(true);
                $resultData['future_charges'] = count($charges) - 1;
                $resultData['charges'] = array_map(function($charge) {
                    return [
                        'id' => $charge->id,
                        'due_date' => $charge->due_date?->toDateString(),
                        'amount' => $charge->amount,
                        'status' => $charge->status,
                    ];
                }, $charges);
                return response()->json($resultData);
            }

            return $result;

        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('StatutoryChargeController::createAndPay() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to process payment at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Create charges based on frequency
     * For periodic charges, creates multiple charges with different due dates
     * For one-time charges, creates a single charge with default due date
     */
    private function createChargesForFrequency(
        string $memberId,
        string $chargeType,
        float $amount,
        ?string $description,
        string $frequency,
        string $createdBy
    ): array {
        $charges = [];
        $startDate = Carbon::now();

        // Calculate due dates based on frequency
        switch ($frequency) {
            case 'monthly':
                // Create 12 charges (one for each month of the year)
                for ($i = 0; $i < 12; $i++) {
                    $dueDate = $startDate->copy()->addMonths($i + 1)->startOfDay();
                    $charges[] = StatutoryCharge::create([
                        'member_id' => $memberId,
                        'type' => $chargeType,
                        'amount' => $amount,
                        'description' => $description ?? "Monthly {$chargeType} - " . $dueDate->format('F Y'),
                        'due_date' => $dueDate,
                        'status' => $i === 0 ? 'approved' : 'pending', // First charge is approved, others pending
                        'approved_at' => $i === 0 ? now() : null,
                        'approved_by' => $i === 0 ? $createdBy : null,
                        'created_by' => $createdBy,
                    ]);
                }
                break;

            case 'quarterly':
                // Create 4 charges (one for each quarter)
                for ($i = 0; $i < 4; $i++) {
                    $dueDate = $startDate->copy()->addMonths(($i + 1) * 3)->startOfDay();
                    $charges[] = StatutoryCharge::create([
                        'member_id' => $memberId,
                        'type' => $chargeType,
                        'amount' => $amount,
                        'description' => $description ?? "Quarterly {$chargeType} - Q" . ($i + 1) . " " . $dueDate->format('Y'),
                        'due_date' => $dueDate,
                        'status' => $i === 0 ? 'approved' : 'pending',
                        'approved_at' => $i === 0 ? now() : null,
                        'approved_by' => $i === 0 ? $createdBy : null,
                        'created_by' => $createdBy,
                    ]);
                }
                break;

            case 'bi_annually':
                // Create 2 charges (one every 6 months)
                for ($i = 0; $i < 2; $i++) {
                    $dueDate = $startDate->copy()->addMonths(($i + 1) * 6)->startOfDay();
                    $charges[] = StatutoryCharge::create([
                        'member_id' => $memberId,
                        'type' => $chargeType,
                        'amount' => $amount,
                        'description' => $description ?? "Bi-annual {$chargeType} - " . $dueDate->format('F Y'),
                        'due_date' => $dueDate,
                        'status' => $i === 0 ? 'approved' : 'pending',
                        'approved_at' => $i === 0 ? now() : null,
                        'approved_by' => $i === 0 ? $createdBy : null,
                        'created_by' => $createdBy,
                    ]);
                }
                break;

            case 'annually':
                // Create 1 charge per year for the next 3 years
                for ($i = 0; $i < 3; $i++) {
                    $dueDate = $startDate->copy()->addYears($i + 1)->startOfDay();
                    $charges[] = StatutoryCharge::create([
                        'member_id' => $memberId,
                        'type' => $chargeType,
                        'amount' => $amount,
                        'description' => $description ?? "Annual {$chargeType} - " . $dueDate->format('Y'),
                        'due_date' => $dueDate,
                        'status' => $i === 0 ? 'approved' : 'pending',
                        'approved_at' => $i === 0 ? now() : null,
                        'approved_by' => $i === 0 ? $createdBy : null,
                        'created_by' => $createdBy,
                    ]);
                }
                break;

            default:
                // One-time charge - default due date is 30 days from now
                $dueDate = $startDate->copy()->addDays(30)->startOfDay();
                $charges[] = StatutoryCharge::create([
                    'member_id' => $memberId,
                    'type' => $chargeType,
                    'amount' => $amount,
                    'description' => $description,
                    'due_date' => $dueDate,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $createdBy,
                    'created_by' => $createdBy,
                ]);
                break;
        }

        return $charges;
    }

    public function pay(StatutoryCharge $charge): JsonResponse
    {
        $request = request();
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        // Check authorization
        if (!$request->user()->isAdmin() && $charge->member->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $this->processPayment($charge, $request);
    }

    private function processPayment(StatutoryCharge $charge, Request $request): JsonResponse
    {

        if ($charge->status !== 'approved' && $charge->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved or pending charges can be paid'
            ], 400);
        }

        // Load payments relationship to calculate remaining amount efficiently
        if (!$charge->relationLoaded('payments')) {
            $charge->load('payments');
        }
        
        $remainingAmount = $charge->remaining_amount;
        if ($request->amount > $remainingAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount exceeds remaining balance'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Get available payment methods
            $availableMethods = collect($this->tenantPaymentService->getAvailablePaymentMethods('statutory_charge'));
            $requestedMethod = $request->payment_method;
            $methodExists = $availableMethods->firstWhere('id', $requestedMethod);
            
            if (!$methodExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected payment method is not available.',
                ], 422);
            }

            $normalizedMethod = $requestedMethod === 'bank_transfer' ? 'manual' : $requestedMethod;

            if (!in_array($normalizedMethod, ['paystack', 'remita', 'stripe', 'manual', 'wallet'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported payment method selected.',
                ], 422);
            }

            // Check wallet balance if using wallet
            if ($normalizedMethod === 'wallet') {

                $member = $request->user();

                if (!$member || !$member->wallet) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Wallet not found'
                    ], 404);
                }
                
                if ($member->wallet->balance < $request->amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient wallet balance'
                    ], 400);
                }
            }

            // Create payment metadata
            $paymentMetadata = [
                'type' => 'statutory_charge',
                'statutory_charge_id' => $charge->id,
                'member_id' => $charge->member_id,
            ];

            // Initialize payment using TenantPaymentService
            $paymentPayload = [
                'user_id' => $charge->member->user_id,
                'amount' => (float) $request->amount,
                'payment_method' => $normalizedMethod,
                'description' => "Payment for statutory charge: {$charge->type}",
                'payment_type' => 'statutory_charge',
                'metadata' => $paymentMetadata,
            ];

            if ($normalizedMethod === 'wallet') {
                $paymentPayload['metadata']['wallet_impact'] = 'Wallet';
            }

            $paymentResult = $this->tenantPaymentService->initializePayment($paymentPayload);

            if (!$paymentResult['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'] ?? 'Failed to initialize payment'
                ], 400);
            }

            /** @var \App\Models\Tenant\Payment $payment */
            $payment = $paymentResult['payment'];

            // Create statutory charge payment record
            $statutoryPayment = $charge->payments()->create([
                'amount' => $request->amount,
                'payment_method' => $requestedMethod,
                'reference' => $payment->reference,
                'status' => $payment->status === 'completed' ? 'completed' : 'pending',
                'paid_at' => $payment->status === 'completed' ? now() : null,
            ]);

            // Create PropertyPaymentTransaction if property is selected and payment is completed
            // For pending payments (gateway), the transaction will be created when payment is confirmed
            $propertyId = $request->property_id ?? $request->input('property_id');
            if ($propertyId && $payment->status === 'completed') {
                $this->createPropertyTransaction(
                    $propertyId,
                    $charge->member_id,
                    $request->amount,
                    $payment->reference,
                    $payment->id,
                    $charge->id
                );
            }

            // Update charge status if fully paid
            $charge->refresh();
            if ($charge->total_paid >= $charge->amount) {
                $charge->update(['status' => 'paid']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $paymentResult['message'] ?? 'Payment processed successfully',
                'payment' => $statutoryPayment,
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'payment_url' => $paymentResult['payment_url'] ?? null,
                'requires_approval' => $paymentResult['requires_approval'] ?? ($normalizedMethod === 'manual'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing statutory charge payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'charge_id' => $charge->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Create PropertyPaymentTransaction record for statutory charge payment
     */
    private function createPropertyTransaction(
        string $propertyId,
        string $memberId,
        float $amount,
        string $reference,
        string $paymentId,
        string $chargeId
    ): void {
        // Find the property payment plan for this property and member (if exists)
        $plan = PropertyPaymentPlan::where('property_id', $propertyId)
            ->whereHas('interest', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->first();

        PropertyPaymentTransaction::create([
            'property_id' => $propertyId,
            'member_id' => $memberId,
            'payment_id' => $paymentId,
            'plan_id' => $plan?->id,
            'source' => 'statutory_charge',
            'amount' => $amount,
            'direction' => 'credit',
            'reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => [
                'charge_id' => $chargeId,
                'charge_type' => 'statutory_charge',
                'description' => 'Statutory charge payment',
            ],
        ]);
    }

    public function destroy(StatutoryCharge $charge): JsonResponse
    {
        if ($charge->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete charge that is not pending'
            ], 400);
        }

        $charge->delete();

        return response()->json([
            'success' => true,
            'message' => 'Statutory charge deleted successfully'
        ]);
    }
}
