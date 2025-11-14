<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\PropertyManagementRequest;
use App\Http\Requests\Properties\StorePropertyPaymentRequest;
use App\Http\Resources\Properties\PropertyManagementResource;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\Payment;
use App\Models\Tenant\EquityWalletBalance;
use App\Models\Tenant\Member;
use App\Models\Tenant\PropertyPaymentPlan;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\InternalMortgagePlan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Property::with(['allocations.member.user', 'images']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by location
        if ($request->has('location')) {
            $query->where('location', 'like', "%{$request->location}%");
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $properties = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'properties' => PropertyManagementResource::collection($properties),
            'pagination' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ]
        ]);
    }

    public function store(PropertyManagementRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $property = Property::create([
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->type,
                'location' => $request->location,
                'price' => $request->price,
                'size' => $request->size,
                'bedrooms' => $request->bedrooms,
                'bathrooms' => $request->bathrooms,
                'features' => $request->features,
                'status' => 'available',
                'created_by' => Auth::id(),
            ]);

            // Handle image uploads
            if ($request->has('images')) {
                foreach ($request->images as $image) {
                    $property->images()->create([
                        'image_url' => $image['url'],
                        'caption' => $image['caption'] ?? null,
                        'is_primary' => $image['is_primary'] ?? false,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property created successfully',
                'property' => new PropertyManagementResource($property->load(['images']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Property creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Property $property): JsonResponse
    {
        $property->load(['allocations.member.user', 'images']);

        return response()->json([
            'property' => new PropertyManagementResource($property)
        ]);
    }

    public function update(PropertyManagementRequest $request, Property $property): JsonResponse
    {
        try {
            DB::beginTransaction();

            $property->update([
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->type,
                'location' => $request->location,
                'price' => $request->price,
                'size' => $request->size,
                'bedrooms' => $request->bedrooms,
                'bathrooms' => $request->bathrooms,
                'features' => $request->features,
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property updated successfully',
                'property' => new PropertyManagementResource($property->load(['images']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Property update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function allocate(Request $request, Property $property): JsonResponse
    {
        $request->validate([
            'member_id' => 'required|uuid|exists:members,id',
            'allocation_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Check if property is available
            if ($property->status !== 'available') {
                return response()->json([
                    'message' => 'Property is not available for allocation'
                ], 400);
            }

            // Check if member is eligible
            $member = Member::find($request->member_id);
            if (!$member || $member->status !== 'active') {
                return response()->json([
                    'message' => 'Member is not eligible for property allocation'
                ], 400);
            }

            // Create allocation
            $allocation = PropertyAllocation::create([
                'property_id' => $property->id,
                'member_id' => $member->id,
                'allocation_date' => $request->allocation_date,
                'status' => 'active',
                'notes' => $request->notes,
                'allocated_by' => Auth::id(),
            ]);

            // Update property status
            $property->update(['status' => 'allocated']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property allocated successfully',
                'allocation' => $allocation
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Property allocation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deallocate(Property $property): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Get active allocation
            $allocation = $property->allocations()->where('status', 'active')->first();
            
            if (!$allocation) {
                return response()->json([
                    'message' => 'No active allocation found for this property'
                ], 404);
            }

            // Update allocation status
            $allocation->update([
                'status' => 'deallocated',
                'deallocated_at' => now(),
                'deallocated_by' => Auth::id(),
            ]);

            // Update property status
            $property->update(['status' => 'available']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property deallocated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Property deallocation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAvailableProperties(Request $request): JsonResponse
    {
        $query = Property::where('status', 'available')
            ->with(['images']);

        // Filter by property type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $properties = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'properties' => PropertyManagementResource::collection($properties)
        ]);
    }

    public function getPropertyAllocations(Property $property): JsonResponse
    {
        $allocations = $property->allocations()
            ->with(['member.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'allocations' => $allocations
        ]);
    }

    public function paymentSetup(Request $request, string $propertyId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $member = $user->member;

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found',
            ], 404);
        }

        try {
            $data = $this->buildPaymentSetupData($propertyId, $member, $user->id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'You do not have an active interest in this property yet.',
            ], 404);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load payment setup at this time.',
            ], 500);
        }
    }

    public function myProperties(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $member = $user->member;

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found',
            ], 404);
        }

        $query = PropertyInterest::where('member_id', $member->id)
            ->with(['property.images']);

        // Filter by property type if provided
        if ($request->has('type')) {
            $query->whereHas('property', function($q) use ($request) {
                $q->where('type', $request->type);
            });
        }

        $interests = $query->orderBy('created_at', 'desc')->get();

        $allocationsByProperty = PropertyAllocation::where('member_id', $member->id)
            ->get()
            ->keyBy('property_id');

        $paymentTotals = $this->getPropertyPaymentTotals($user->id);

        $properties = [];

        foreach ($interests as $interest) {
            $property = $interest->property;

            if (!$property) {
                continue;
            }

            $propertyId = $property->id;
            $price = (float) ($property->price ?? 0);
            $totalPaid = (float) ($paymentTotals[$propertyId] ?? 0);
            $currentValue = $this->calculateCurrentValue($property);
            $predictiveValue = $this->calculatePredictiveValue($property, $currentValue);
            $progress = $price > 0 ? min(1, $totalPaid / $price) : 0;
            $allocation = $allocationsByProperty->get($propertyId);

            $properties[] = [
                'id' => $propertyId,
                'title' => $property->title,
                'type' => $property->type,
                'location' => $property->location,
                'price' => $price,
                'description' => $property->description,
                'features' => $property->features,
                'size' => $property->size,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'created_at' => optional($property->created_at)->toDateTimeString(),
                'total_paid' => round($totalPaid, 2),
                'current_value' => round($currentValue, 2),
                'predictive_value' => round($predictiveValue, 2),
                'progress' => round($progress * 100, 2),
                'status' => $property->status,
                'interest_status' => $interest->status,
                'interest_id' => $interest->id,
                'interest_type' => $interest->interest_type,
                'interest_created_at' => optional($interest->created_at)->toDateTimeString(),
                'funding_option' => $interest->funding_option,
                'preferred_payment_methods' => $interest->preferred_payment_methods,
                'mortgage_preferences' => $interest->mortgage_preferences,
                'mortgage_flagged' => (bool) $interest->mortgage_flagged,
                'allocation_status' => $allocation?->status,
                'allocation_date' => optional($allocation?->allocation_date)->toDateString(),
                'images' => $property->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $image->url ?? $image->image_url ?? ($image->path ? Storage::url($image->path) : null),
                        'is_primary' => $image->is_primary,
                    ];
                })->values(),
            ];
        }

        $propertiesCollection = collect($properties);

        $summary = [
            'total_properties' => $propertiesCollection->count(),
            'houses_owned' => $propertiesCollection->where('type', 'house')->count(),
            'lands_owned' => $propertiesCollection->where('type', 'land')->count(),
            'total_paid' => round($propertiesCollection->sum('total_paid'), 2),
            'current_value' => round($propertiesCollection->sum('current_value'), 2),
            'predictive_value' => round($propertiesCollection->sum('predictive_value'), 2),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'properties' => $propertiesCollection->values(),
        ]);
    }

    public function recordPayment(StorePropertyPaymentRequest $request, string $propertyId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $member = $user->member;

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found',
            ], 404);
        }

        $property = Property::findOrFail($propertyId);
        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found',
            ], 404);
        }

        $validated = $request->validated();
        $method = $validated['method'];
        $amount = (float) $validated['amount'];

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Amount must be greater than zero.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $interest = PropertyInterest::where('member_id', $member->id)
                ->where('property_id', $property->id)
                ->latest('created_at')
                ->first();

            if (!$interest) {
                throw new ModelNotFoundException('You do not have an active interest in this property yet.');
            }

            $plan = PropertyPaymentPlan::where('property_id', $property->id)
                ->where('member_id', $member->id)
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            $transactions = PropertyPaymentTransaction::where('property_id', $property->id)
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->get();

            $creditsByMethod = $transactions
                ->where('direction', 'credit')
                ->groupBy('source')
                ->map(function (Collection $group) {
                    return (float) $group->sum('amount');
                });

            $allowedMethods = [];
            $targetAmount = null;
            $remainingForMethod = null;
            $planTotalTarget = null;
            $planRemainingBefore = null;

            if ($plan) {
                $configuredMethods = array_values(array_filter($plan->selected_methods ?? []));

                if ($plan->funding_option === 'mix') {
                    $configuration = $plan->configuration ?? [];
                    $percentages = $configuration['mix_allocations']['percentages'] ?? ($configuration['mix_allocations'] ?? []);
                    if (!is_array($percentages)) {
                        $percentages = [];
                    }

                    $allowedMethods = array_values(array_filter(array_keys($percentages)));

                    if (!in_array($method, $allowedMethods, true)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Selected payment method is not part of the configured mix.',
                        ], 422);
                    }

                    $totalForMix = (float) ($configuration['mix_allocations']['total_amount'] ?? ($plan->total_amount ?? $property->price ?? 0));
                    $percentage = (float) ($percentages[$method] ?? 0);
                    $targetAmount = round(($percentage / 100) * $totalForMix, 2);
                    $creditedAmount = (float) ($creditsByMethod[$method] ?? 0.0);
                    $remainingForMethod = max(0.0, round($targetAmount - $creditedAmount, 2));

                    if ($remainingForMethod <= 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'The allocation for this payment method has already been fulfilled.',
                        ], 422);
                    }

                    if ($amount - $remainingForMethod > 0.01) {
                        return response()->json([
                            'success' => false,
                            'message' => 'The amount exceeds the remaining allocation for this payment method.',
                        ], 422);
                    }
                } else {
                    $allowedMethods = !empty($configuredMethods)
                        ? $configuredMethods
                        : array_filter([$plan->funding_option]);

                    if (!empty($allowedMethods) && !in_array($method, $allowedMethods, true)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This payment method is not configured for the current plan.',
                        ], 422);
                    }

                    $planTotalTarget = (float) ($plan->total_amount ?? $property->price ?? 0);
                    $creditedTotal = (float) $transactions
                        ->where('direction', 'credit')
                        ->sum('amount');
                    $planRemainingBefore = max(0.0, round($planTotalTarget - $creditedTotal, 2));

                    if ($planRemainingBefore <= 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This payment plan is already fully funded.',
                        ], 422);
                    }

                    if ($amount - $planRemainingBefore > 0.01) {
                        return response()->json([
                            'success' => false,
                            'message' => 'The amount exceeds the remaining balance for this plan.',
                        ], 422);
                    }
                }
            } else {
                $interestPreferred = array_values(array_filter($interest->preferred_payment_methods ?? []));

                if (!empty($interestPreferred)) {
                    $allowedMethods = $interestPreferred;
                } else {
                    $allowedMethods = match ($interest->funding_option) {
                        'equity_wallet' => ['equity_wallet'],
                        'mortgage' => ['mortgage'],
                        'loan' => ['loan'],
                        'cooperative' => ['cooperative'],
                        default => ['cash'],
                    };
                }

                if (!in_array($method, $allowedMethods, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This payment method is not allowed for the selected property.',
                    ], 422);
                }

                $propertyTarget = (float) ($property->price ?? 0);
                $creditedTotal = (float) $transactions
                    ->where('direction', 'credit')
                    ->sum('amount');
                $planRemainingBefore = max(0.0, round($propertyTarget - $creditedTotal, 2));

                if ($planRemainingBefore <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This property is already fully funded.',
                    ], 422);
                }

                if ($amount - $planRemainingBefore > 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The amount exceeds the remaining balance for this property.',
                    ], 422);
                }
            }

            $reference = $validated['reference'] ?? ('PROP-' . Str::upper(Str::random(10)));
            $notes = $validated['notes'] ?? null;
            $metadata = [
                'notes' => $notes,
                'payer_name' => $validated['payer_name'] ?? null,
                'payer_phone' => $validated['payer_phone'] ?? null,
                'payment_date' => $validated['payment_date'] ?? null,
                'mix_target_amount' => $targetAmount,
                'mix_remaining_before' => $remainingForMethod,
                'plan_remaining_before' => $planRemainingBefore,
                'plan_target_total' => $planTotalTarget,
            ];

            if ($request->hasFile('evidence')) {
                $path = $request->file('evidence')->store('property-payments/evidence', 'public');
                $metadata['evidence_path'] = $path;
                $metadata['evidence_url'] = Storage::url($path);
            }

            if (!empty($validated['metadata']) && is_array($validated['metadata'])) {
                $metadata['additional'] = $validated['metadata'];
            }

            $status = 'completed';
            $paidAt = now();

            switch ($method) {
                case 'equity_wallet':
                    $wallet = EquityWalletBalance::where('member_id', $member->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$wallet || !$wallet->canUse($amount)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Insufficient equity wallet balance for this payment.',
                        ], 422);
                    }

                    if (!$wallet->use($amount, $reference, 'property_payment', "Property payment for {$property->title}")) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unable to deduct funds from equity wallet.',
                        ], 422);
                    }
                    break;
                case 'cash':
                    $status = 'pending';
                    $paidAt = null;
                    break;
                case 'cooperative':
                case 'mortgage':
                case 'loan':
                    $status = 'scheduled';
                    $paidAt = null;
                    break;
            }

            $metadata['mix_remaining_after'] = isset($remainingForMethod)
                ? max(0.0, round($remainingForMethod - $amount, 2))
                : null;

            $transaction = PropertyPaymentTransaction::create([
                'property_id' => $property->id,
                'member_id' => $member->id,
                'plan_id' => $plan?->id,
                'source' => $method,
                'amount' => $amount,
                'direction' => 'credit',
                'reference' => $reference,
                'status' => $status,
                'paid_at' => $paidAt,
                'metadata' => array_filter($metadata, static fn ($value) => $value !== null && $value !== ''),
            ]);

            if ($plan && in_array($status, ['completed', 'success'], true)) {
                $baseRemaining = (float) ($plan->remaining_balance ?? ($plan->total_amount ?? $property->price ?? 0));
                $plan->remaining_balance = max(0, round($baseRemaining - $amount, 2));
                $plan->save();
            }

            DB::commit();

            $data = $this->buildPaymentSetupData($property->id, $member, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Property payment recorded successfully.',
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                    'reference' => $transaction->reference,
                ],
                'data' => $data,
            ], 201);
        } catch (ModelNotFoundException $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Unable to record payment for this property.',
            ], 404);
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to record payment at this time.',
            ], 500);
        }
    }

    protected function buildPaymentSetupData(string $propertyId, Member $member, string $userId): array
    {
        $interest = PropertyInterest::with('property')
            ->where('member_id', $member->id)
            ->where('property_id', $propertyId)
            ->latest('created_at')
            ->first();

        if (!$interest || !$interest->property) {
            throw new ModelNotFoundException('You do not have an active interest in this property yet.');
        }

        $property = $interest->property;
        $price = (float) ($property->price ?? 0);

        $paymentTotals = $this->getPropertyPaymentTotals($userId);
        $totalPaid = (float) ($paymentTotals[$propertyId] ?? 0);
        $balance = max(0, $price - $totalPaid);
        $progress = $price > 0 ? round(($totalPaid / $price) * 100, 2) : 0;

        $equityWallet = EquityWalletBalance::where('member_id', $member->id)->first();

        $activePlan = PropertyPaymentPlan::where('property_id', $propertyId)
            ->where('member_id', $member->id)
            ->orderByDesc('created_at')
            ->first();

        $planData = null;
        if ($activePlan) {
            $planData = $activePlan->toArray();
            $planConfiguration = $planData['configuration'] ?? [];

            if (($planData['funding_option'] ?? null) === 'mix') {
                $percentages = $planConfiguration['mix_allocations']['percentages'] ?? ($planConfiguration['mix_allocations'] ?? []);
                if (!is_array($percentages)) {
                    $percentages = [];
                }

                $totalForMix = (float) ($planConfiguration['mix_allocations']['total_amount'] ?? ($planData['total_amount'] ?? $price));
                $amounts = $planConfiguration['mix_allocations']['amounts'] ?? [];

                foreach ($percentages as $method => $percentage) {
                    $amounts[$method] = round(((float) $percentage / 100) * $totalForMix, 2);
                }

                $planConfiguration['mix_allocations'] = [
                    'percentages' => $percentages,
                    'amounts' => $amounts,
                    'total_amount' => $totalForMix,
                ];
            }

            $planData['configuration'] = $planConfiguration ?: null;
        }

        $payments = Payment::where('user_id', $userId)
            ->where(function ($query) use ($propertyId) {
                $query->where(function ($typeQuery) use ($propertyId) {
                    $typeQuery->where('metadata->type', 'property_payment')
                        ->where(function ($metaQuery) use ($propertyId) {
                            $metaQuery->where('metadata->property_id', $propertyId)
                                ->orWhere('metadata->propertyId', $propertyId);
                        });
                })->orWhere(function ($fallbackQuery) use ($propertyId) {
                    $fallbackQuery->whereNull('metadata->type')
                        ->where(function ($metaQuery) use ($propertyId) {
                            $metaQuery->where('metadata->property_id', $propertyId)
                                ->orWhere('metadata->propertyId', $propertyId);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $transactions = PropertyPaymentTransaction::where('property_id', $propertyId)
            ->where('member_id', $member->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->get();

        $ledgerEntries = $transactions->map(function (PropertyPaymentTransaction $transaction) {
            return [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'direction' => $transaction->direction,
                'source' => $transaction->source,
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'paid_at' => optional($transaction->paid_at)->toDateTimeString(),
                'metadata' => $transaction->metadata ?? [],
                'payment_id' => $transaction->payment_id,
                'plan_id' => $transaction->plan_id,
                'mortgage_plan_id' => $transaction->mortgage_plan_id,
                'created_at' => optional($transaction->created_at)->toDateTimeString(),
            ];
        });

        $ledgerTotalPaid = $transactions->reduce(function ($carry, PropertyPaymentTransaction $transaction) {
            $amount = (float) $transaction->amount;
            return $carry + ($transaction->direction === 'credit' ? $amount : -$amount);
        }, 0.0);

        if ($ledgerTotalPaid > 0) {
            $totalPaid = max($totalPaid, $ledgerTotalPaid);
        }

        $paymentHistory = $payments->map(function (Payment $payment) {
            return [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'approval_status' => $payment->approval_status,
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'description' => $payment->description,
                'created_at' => optional($payment->created_at)->toDateTimeString(),
                'metadata' => $payment->metadata,
            ];
        });

        // Add ledger entries to payment history
        $ledgerHistory = $transactions->map(function (PropertyPaymentTransaction $transaction) {
            return [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status ?? 'completed',
                'approval_status' => null,
                'payment_method' => $transaction->source ?? 'unknown',
                'reference' => $transaction->reference,
                'description' => "Property payment via {$transaction->source}",
                'created_at' => optional($transaction->paid_at ?? $transaction->created_at)->toDateTimeString(),
                'metadata' => $transaction->metadata ?? [],
            ];
        });

        // Merge and sort by date (most recent first)
        $paymentHistory = $paymentHistory->concat($ledgerHistory)
            ->sortByDesc(function ($entry) {
                return $entry['created_at'] ?? '';
            })
            ->values()
            ->take(50);

        $preferredMethods = [];
        if ($planData && !empty($planData['selected_methods'])) {
            $preferredMethods = array_values(array_filter($planData['selected_methods']));
        }

        if (empty($preferredMethods)) {
            $preferredMethods = collect($interest->preferred_payment_methods ?? [])
                ->filter()
                ->values()
                ->all();
        }

        if (empty($preferredMethods)) {
            $preferredMethods = match ($interest->funding_option) {
                'equity_wallet' => ['equity_wallet'],
                'mortgage' => ['mortgage'],
                'loan' => ['loan'],
                'cooperative' => ['cooperative'],
                'mix' => ['equity_wallet', 'cooperative'],
                default => ['cash'],
            };
        }

        // Get repayment schedules for loans, mortgages, and internal mortgages tied to this property
        $repaymentSchedules = $this->getRepaymentSchedulesForProperty($propertyId, $member->id);

        return [
            'property' => [
                'id' => $property->id,
                'title' => $property->title,
                'price' => $price,
                'total_paid' => round($totalPaid, 2),
                'balance' => round($balance, 2),
                'progress' => $progress,
                'status' => $interest->status,
                'location' => $property->location,
            ],
            'funding_option' => $interest->funding_option,
            'preferred_payment_methods' => $preferredMethods,
            'mortgage_preferences' => $interest->mortgage_preferences,
            'equity_wallet' => [
                'balance' => (float) ($equityWallet->balance ?? 0),
                'currency' => $equityWallet->currency ?? 'NGN',
                'is_active' => (bool) ($equityWallet->is_active ?? false),
                'total_contributed' => (float) ($equityWallet->total_contributed ?? 0),
                'total_used' => (float) ($equityWallet->total_used ?? 0),
            ],
            'payment_history' => $paymentHistory,
            'ledger_entries' => $ledgerEntries,
            'ledger_total_paid' => round($ledgerTotalPaid, 2),
            'payment_plan' => $planData,
            'repayment_schedules' => $repaymentSchedules,
        ];
    }

    protected function getPropertyPaymentTotals(string $userId): array
    {
        $payments = Payment::where('user_id', $userId)
            ->where('status', 'completed')
            ->get();

        $totals = [];

        foreach ($payments as $payment) {
            $metadata = $payment->metadata ?? [];

            if (($metadata['type'] ?? null) === 'property_payment' && !empty($metadata['property_id'])) {
                $propertyId = $metadata['property_id'];
                $totals[$propertyId] = ($totals[$propertyId] ?? 0) + (float) $payment->amount;
            }
        }

        return $totals;
    }

    protected function calculateCurrentValue(Property $property): float
    {
        $basePrice = (float) ($property->price ?? 0);

        if ($basePrice <= 0) {
            return 0.0;
        }

        $appreciationRate = match ($property->type) {
            'house', 'duplex', 'bungalow' => 0.08,
            'apartment' => 0.06,
            'land' => 0.12,
            default => 0.05,
        };

        return $basePrice * (1 + $appreciationRate);
    }

    protected function calculatePredictiveValue(Property $property, float $currentValue): float
    {
        $growthRate = match ($property->type) {
            'house', 'duplex', 'bungalow' => 0.12,
            'apartment' => 0.10,
            'land' => 0.18,
            default => 0.08,
        };

        if ($currentValue <= 0) {
            $currentValue = (float) ($property->price ?? 0);
        }

        return $currentValue * (1 + $growthRate);
    }

    /**
     * Get repayment schedules for loans, mortgages, and internal mortgages tied to property
     */
    protected function getRepaymentSchedulesForProperty(string $propertyId, string $memberId): array
    {
        $schedules = [];

        // Get loans tied to this property
        $loans = Loan::where('property_id', $propertyId)
            ->where('member_id', $memberId)
            ->whereIn('status', ['approved', 'active'])
            ->get();

        foreach ($loans as $loan) {
            $schedule = $this->calculateLoanSchedule($loan);
            if ($schedule) {
                $schedules['loan'] = $schedule;
            }
        }

        // Get mortgages tied to this property
        $mortgages = Mortgage::with('provider')->where('property_id', $propertyId)
            ->where('member_id', $memberId)
            ->whereIn('status', ['approved', 'active'])
            ->get();

        foreach ($mortgages as $mortgage) {
            $schedule = $this->calculateMortgageSchedule($mortgage);
            if ($schedule) {
                $schedules['mortgage'] = $schedule;
            }
        }

        // Get internal mortgage plans tied to this property
        $internalMortgages = InternalMortgagePlan::where('property_id', $propertyId)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->get();

        foreach ($internalMortgages as $plan) {
            $schedule = $this->calculateInternalMortgageSchedule($plan);
            if ($schedule) {
                $schedules['cooperative'] = $schedule;
            }
        }

        return $schedules;
    }

    /**
     * Calculate loan repayment schedule
     */
    protected function calculateLoanSchedule(Loan $loan): ?array
    {
        try {
            $loanAmount = $loan->amount;
            $interestRate = $loan->interest_rate / 100;
            $tenureMonths = $loan->duration_months;
            $monthlyRate = $interestRate / 12;
            $monthlyPayment = $loan->monthly_payment ?? ($loan->total_amount / $tenureMonths);
            
            $schedule = [];
            $remainingBalance = $loanAmount;
            $startDate = $loan->application_date ?? now();

            for ($month = 1; $month <= $tenureMonths; $month++) {
                $interestPortion = $remainingBalance * $monthlyRate;
                $principalPortion = $monthlyPayment - $interestPortion;
                
                if ($remainingBalance < $principalPortion) {
                    $principalPortion = $remainingBalance;
                    $monthlyPayment = $principalPortion + $interestPortion;
                }

                $dueDate = $startDate->copy()->addMonths($month);
                
                $repayment = $loan->repayments()
                    ->where('due_date', '<=', $dueDate)
                    ->where('status', 'paid')
                    ->orderBy('due_date', 'desc')
                    ->first();

                $isPaid = $repayment && $repayment->principal_paid >= $principalPortion * 0.99;

                $schedule[] = [
                    'installment' => $month,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'principal' => round($principalPortion, 2),
                    'interest' => round($interestPortion, 2),
                    'total' => round($monthlyPayment, 2),
                    'remaining_balance' => round($remainingBalance - $principalPortion, 2),
                    'status' => $isPaid ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'pending'),
                    'paid_date' => $repayment ? $repayment->paid_at?->format('Y-m-d') : null,
                ];

                $remainingBalance -= $principalPortion;
                if ($remainingBalance <= 0) {
                    break;
                }
            }

            return [
                'loan_id' => $loan->id,
                'loan_amount' => (float) $loanAmount,
                'interest_rate' => (float) $loan->interest_rate,
                'duration_months' => $tenureMonths,
                'monthly_payment' => (float) $monthlyPayment,
                'total_principal_repaid' => (float) $loan->getTotalPrincipalRepaid(),
                'total_interest_paid' => (float) $loan->getTotalInterestPaid(),
                'remaining_principal' => (float) $loan->getRemainingPrincipal(),
                'is_fully_repaid' => $loan->isFullyRepaid(),
                'schedule' => $schedule,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate mortgage repayment schedule
     */
    protected function calculateMortgageSchedule(Mortgage $mortgage): ?array
    {
        try {
            $loanAmount = $mortgage->loan_amount;
            $interestRate = $mortgage->interest_rate / 100;
            $tenureMonths = $mortgage->tenure_years * 12;
            $monthlyRate = $interestRate / 12;
            $monthlyPayment = $mortgage->monthly_payment;

            $schedule = [];
            $remainingBalance = $loanAmount;
            $startDate = $mortgage->application_date ?? now();

            for ($month = 1; $month <= $tenureMonths; $month++) {
                $interestPortion = $remainingBalance * $monthlyRate;
                $principalPortion = $monthlyPayment - $interestPortion;
                
                if ($remainingBalance < $principalPortion) {
                    $principalPortion = $remainingBalance;
                    $monthlyPayment = $principalPortion + $interestPortion;
                }

                $dueDate = $startDate->copy()->addMonths($month);
                
                $repayment = $mortgage->repayments()
                    ->where('due_date', '<=', $dueDate)
                    ->where('status', 'paid')
                    ->orderBy('due_date', 'desc')
                    ->first();

                $isPaid = $repayment && $repayment->principal_paid >= $principalPortion * 0.99;

                $schedule[] = [
                    'month' => $month,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'principal' => round($principalPortion, 2),
                    'interest' => round($interestPortion, 2),
                    'total' => round($monthlyPayment, 2),
                    'remaining_balance' => round($remainingBalance - $principalPortion, 2),
                    'status' => $isPaid ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'pending'),
                    'paid_date' => $repayment ? $repayment->paid_at?->format('Y-m-d') : null,
                ];

                $remainingBalance -= $principalPortion;
                if ($remainingBalance <= 0) {
                    break;
                }
            }

            return [
                'mortgage_id' => $mortgage->id,
                'loan_amount' => (float) $loanAmount,
                'interest_rate' => (float) $mortgage->interest_rate,
                'tenure_years' => $mortgage->tenure_years,
                'monthly_payment' => (float) $monthlyPayment,
                'total_principal_repaid' => (float) $mortgage->getTotalPrincipalRepaid(),
                'total_interest_paid' => (float) $mortgage->getTotalInterestPaid(),
                'remaining_principal' => (float) $mortgage->getRemainingPrincipal(),
                'is_fully_repaid' => $mortgage->isFullyRepaid(),
                'schedule_approved' => $mortgage->schedule_approved ?? false,
                'schedule_approved_at' => $mortgage->schedule_approved_at?->toIso8601String(),
                'provider' => $mortgage->provider ? [
                    'id' => $mortgage->provider->id,
                    'name' => $mortgage->provider->name,
                    'code' => $mortgage->provider->code,
                    'contact_email' => $mortgage->provider->contact_email,
                    'contact_phone' => $mortgage->provider->contact_phone,
                    'address' => $mortgage->provider->address,
                ] : null,
                'schedule' => $schedule,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate internal mortgage repayment schedule
     */
    protected function calculateInternalMortgageSchedule(InternalMortgagePlan $plan): ?array
    {
        try {
            $principal = $plan->principal;
            $interestRate = $plan->interest_rate / 100;
            $tenureMonths = $plan->tenure_months;
            $frequency = $plan->frequency;
            
            $frequencyMultiplier = match($frequency) {
                'monthly' => 1,
                'quarterly' => 3,
                'biannually' => 6,
                'annually' => 12,
                default => 1,
            };

            $paymentsPerYear = 12 / $frequencyMultiplier;
            $periodicRate = $interestRate / $paymentsPerYear;
            $numberOfPayments = $tenureMonths / $frequencyMultiplier;

            $factor = pow(1 + $periodicRate, $numberOfPayments);
            $periodicPayment = $principal * ($periodicRate * $factor) / ($factor - 1);

            $schedule = [];
            $remainingBalance = $principal;
            $startDate = $plan->starts_on ? \Carbon\Carbon::parse($plan->starts_on) : now();

            for ($period = 1; $period <= $numberOfPayments; $period++) {
                $interestPortion = $remainingBalance * $periodicRate;
                $principalPortion = $periodicPayment - $interestPortion;
                
                if ($remainingBalance < $principalPortion) {
                    $principalPortion = $remainingBalance;
                    $periodicPayment = $principalPortion + $interestPortion;
                }

                $dueDate = $startDate->copy()->addMonths($period * $frequencyMultiplier);
                
                $repayment = $plan->repayments()
                    ->where('due_date', '<=', $dueDate)
                    ->where('status', 'paid')
                    ->orderBy('due_date', 'desc')
                    ->first();

                $isPaid = $repayment && $repayment->principal_paid >= $principalPortion * 0.99;

                $schedule[] = [
                    'period' => $period,
                    'frequency' => $frequency,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'principal' => round($principalPortion, 2),
                    'interest' => round($interestPortion, 2),
                    'total' => round($periodicPayment, 2),
                    'remaining_balance' => round($remainingBalance - $principalPortion, 2),
                    'status' => $isPaid ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'pending'),
                    'paid_date' => $repayment ? $repayment->paid_at?->format('Y-m-d') : null,
                ];

                $remainingBalance -= $principalPortion;
                if ($remainingBalance <= 0) {
                    break;
                }
            }

            return [
                'plan_id' => $plan->id,
                'title' => $plan->title,
                'principal' => (float) $principal,
                'interest_rate' => (float) $plan->interest_rate,
                'tenure_months' => $tenureMonths,
                'tenure_years' => round($tenureMonths / 12, 1),
                'frequency' => $frequency,
                'periodic_payment' => (float) $periodicPayment,
                'starts_on' => $plan->starts_on?->format('Y-m-d'),
                'notes' => $plan->notes,
                'total_principal_repaid' => (float) $plan->getTotalPrincipalRepaid(),
                'total_interest_paid' => (float) $plan->getTotalInterestPaid(),
                'remaining_principal' => (float) $plan->getRemainingPrincipal(),
                'is_fully_repaid' => $plan->isFullyRepaid(),
                'schedule_approved' => $plan->schedule_approved ?? false,
                'schedule_approved_at' => $plan->schedule_approved_at?->toIso8601String(),
                'schedule' => $schedule,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Approve mortgage repayment schedule
     */
    public function approveMortgageSchedule(Request $request, string $mortgageId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated or member not found'
                ], 401);
            }

            $mortgage = Mortgage::where('id', $mortgageId)
                ->where('member_id', $user->member->id)
                ->firstOrFail();

            if ($mortgage->status !== 'approved' && $mortgage->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Mortgage must be approved or active before schedule can be approved'
                ], 400);
            }

            if ($mortgage->schedule_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule has already been approved'
                ], 400);
            }

            $mortgage->update([
                'schedule_approved' => true,
                'schedule_approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mortgage repayment schedule approved successfully',
                'data' => [
                    'mortgage' => $mortgage->fresh(),
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mortgage not found or you do not have permission to approve this schedule'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve internal mortgage repayment schedule
     */
    public function approveInternalMortgageSchedule(Request $request, string $planId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated or member not found'
                ], 401);
            }

            $plan = InternalMortgagePlan::where('id', $planId)
                ->where('member_id', $user->member->id)
                ->firstOrFail();

            if ($plan->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Internal mortgage plan must be active before schedule can be approved'
                ], 400);
            }

            if ($plan->schedule_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule has already been approved'
                ], 400);
            }

            $plan->update([
                'schedule_approved' => true,
                'schedule_approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Internal mortgage repayment schedule approved successfully',
                'data' => [
                    'plan' => $plan->fresh(),
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal mortgage plan not found or you do not have permission to approve this schedule'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
