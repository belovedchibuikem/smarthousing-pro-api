<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\PropertyPaymentPlan;
use App\Models\Tenant\PropertyPaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertySubscriptionController extends Controller
{
    /**
     * Get all property subscriptions with payment details
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PropertyAllocation::with([
                'property',
                'member.user',
                'member.equityWalletBalance'
            ]);

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by property
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by member
            if ($request->has('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('member.user', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('member', function($q) use ($search) {
                    $q->where('member_id', 'like', "%{$search}%")
                      ->orWhere('staff_id', 'like', "%{$search}%");
                })->orWhereHas('property', function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            }

            $allocations = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            // Get all property IDs and member IDs for batch queries
            $propertyIds = $allocations->pluck('property_id')->unique()->toArray();
            $memberIds = $allocations->pluck('member_id')->unique()->toArray();

            // Get payment plans for these properties/members
            $paymentPlans = PropertyPaymentPlan::whereIn('property_id', $propertyIds)
                ->whereIn('member_id', $memberIds)
                ->get()
                ->keyBy(function($plan) {
                    return $plan->property_id . '_' . $plan->member_id;
                });

            // Get payment transactions for these properties/members
            $transactions = PropertyPaymentTransaction::whereIn('property_id', $propertyIds)
                ->whereIn('member_id', $memberIds)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->get()
                ->groupBy(function($transaction) {
                    return $transaction->property_id . '_' . $transaction->member_id;
                });

            // Transform allocations with payment data
            $subscriptions = $allocations->map(function($allocation) use ($paymentPlans, $transactions) {
                $key = $allocation->property_id . '_' . $allocation->member_id;
                $plan = $paymentPlans->get($key);
                $memberTransactions = $transactions->get($key, collect());

                // Calculate amount paid
                $amountPaid = $memberTransactions->sum('amount');

                // Get property price
                $propertyPrice = $allocation->property->price ?? 0;

                // Determine status based on payment completion
                $status = 'In Progress';
                if ($plan) {
                    if ($plan->remaining_balance <= 0) {
                        $status = 'Completed';
                    } elseif ($plan->status === 'completed') {
                        $status = 'Completed';
                    } elseif ($plan->status === 'cancelled') {
                        $status = 'Cancelled';
                    }
                } elseif ($allocation->status === 'completed') {
                    $status = 'Completed';
                } elseif ($allocation->status === 'rejected') {
                    $status = 'Rejected';
                }

                // Get payment methods from transactions
                $paymentMethods = $memberTransactions->pluck('source')
                    ->unique()
                    ->values()
                    ->toArray();

                return [
                    'id' => $allocation->id,
                    'allocation_id' => $allocation->id,
                    'property_id' => $allocation->property_id,
                    'member_id' => $allocation->member_id,
                    'member_name' => trim(($allocation->member->user->first_name ?? '') . ' ' . ($allocation->member->user->last_name ?? '')),
                    'member_number' => $allocation->member->member_id ?? $allocation->member->staff_id ?? '—',
                    'member_email' => $allocation->member->user->email ?? null,
                    'member_phone' => $allocation->member->user->phone ?? null,
                    'property_title' => $allocation->property->title ?? '—',
                    'property_address' => $allocation->property->address ?? '—',
                    'property_price' => (float) $propertyPrice,
                    'total_price' => (float) $propertyPrice,
                    'amount_paid' => (float) $amountPaid,
                    'balance' => max(0, (float) $propertyPrice - (float) $amountPaid),
                    'payment_method' => !empty($paymentMethods) ? implode(', ', $paymentMethods) : 'Not specified',
                    'payment_methods' => $paymentMethods,
                    'status' => $status,
                    'allocation_status' => $allocation->status,
                    'allocation_date' => $allocation->allocation_date?->toDateString(),
                    'payment_plan_id' => $plan?->id,
                    'payment_plan_status' => $plan?->status,
                    'has_certificate' => $status === 'Completed' && $plan && $plan->remaining_balance <= 0,
                    'allocation' => [
                        'id' => $allocation->id,
                        'property_id' => $allocation->property_id,
                        'member_id' => $allocation->member_id,
                        'status' => $allocation->status,
                        'allocation_date' => $allocation->allocation_date?->toDateString(),
                        'notes' => $allocation->notes,
                    ],
                    'payment_plan' => $plan ? [
                        'id' => $plan->id,
                        'status' => $plan->status,
                        'total_amount' => (float) $plan->total_amount,
                        'remaining_balance' => (float) $plan->remaining_balance,
                        'starts_on' => $plan->starts_on?->toDateString(),
                        'ends_on' => $plan->ends_on?->toDateString(),
                    ] : null,
                    'created_at' => $allocation->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'pagination' => [
                    'current_page' => $allocations->currentPage(),
                    'last_page' => $allocations->lastPage(),
                    'per_page' => $allocations->perPage(),
                    'total' => $allocations->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertySubscriptionController index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscriptions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscription details with full payment history
     */
    public function show(string $allocationId): JsonResponse
    {
        try {
            $allocation = PropertyAllocation::with([
                'property.images',
                'member.user',
                'member.equityWalletBalance'
            ])->findOrFail($allocationId);

            // Get payment plan
            $paymentPlan = PropertyPaymentPlan::where('property_id', $allocation->property_id)
                ->where('member_id', $allocation->member_id)
                ->first();

            // Get payment transactions
            $transactions = PropertyPaymentTransaction::where('property_id', $allocation->property_id)
                ->where('member_id', $allocation->member_id)
                ->where('direction', 'credit')
                ->with('payment')
                ->orderBy('paid_at', 'desc')
                ->get();

            // Calculate totals
            $propertyPrice = $allocation->property->price ?? 0;
            $amountPaid = $transactions->where('status', 'completed')->sum('amount');
            $balance = max(0, $propertyPrice - $amountPaid);

            // Payment schedule from plan
            $paymentSchedule = [];
            if ($paymentPlan && isset($paymentPlan->schedule) && is_array($paymentPlan->schedule)) {
                $paymentSchedule = $paymentPlan->schedule;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'allocation' => [
                        'id' => $allocation->id,
                        'property_id' => $allocation->property_id,
                        'member_id' => $allocation->member_id,
                        'status' => $allocation->status,
                        'allocation_date' => $allocation->allocation_date?->toDateString(),
                        'notes' => $allocation->notes,
                        'created_at' => $allocation->created_at?->toIso8601String(),
                    ],
                    'property' => [
                        'id' => $allocation->property->id,
                        'title' => $allocation->property->title,
                        'address' => $allocation->property->address,
                        'city' => $allocation->property->city,
                        'state' => $allocation->property->state,
                        'price' => (float) $propertyPrice,
                        'size' => $allocation->property->size,
                        'bedrooms' => $allocation->property->bedrooms,
                        'bathrooms' => $allocation->property->bathrooms,
                        'features' => $allocation->property->features,
                        'images' => $allocation->property->images->map(function($img) {
                            return ['id' => $img->id, 'url' => $img->url, 'is_primary' => $img->is_primary];
                        }),
                    ],
                    'member' => [
                        'id' => $allocation->member->id,
                        'member_id' => $allocation->member->member_id,
                        'staff_id' => $allocation->member->staff_id,
                        'first_name' => $allocation->member->user->first_name ?? '',
                        'last_name' => $allocation->member->user->last_name ?? '',
                        'email' => $allocation->member->user->email ?? null,
                        'phone' => $allocation->member->user->phone ?? null,
                    ],
                    'payment_summary' => [
                        'total_price' => (float) $propertyPrice,
                        'amount_paid' => (float) $amountPaid,
                        'balance' => (float) $balance,
                        'completion_percentage' => $propertyPrice > 0 ? round(($amountPaid / $propertyPrice) * 100, 2) : 0,
                    ],
                    'payment_plan' => $paymentPlan ? [
                        'id' => $paymentPlan->id,
                        'status' => $paymentPlan->status,
                        'total_amount' => (float) $paymentPlan->total_amount,
                        'initial_balance' => (float) $paymentPlan->initial_balance,
                        'remaining_balance' => (float) $paymentPlan->remaining_balance,
                        'funding_option' => $paymentPlan->funding_option,
                        'selected_methods' => $paymentPlan->selected_methods,
                        'starts_on' => $paymentPlan->starts_on?->toDateString(),
                        'ends_on' => $paymentPlan->ends_on?->toDateString(),
                        'schedule' => $paymentSchedule,
                    ] : null,
                    'payment_history' => $transactions->map(function($transaction) {
                        return [
                            'id' => $transaction->id,
                            'amount' => (float) $transaction->amount,
                            'source' => $transaction->source,
                            'status' => $transaction->status,
                            'reference' => $transaction->reference,
                            'paid_at' => $transaction->paid_at?->toIso8601String(),
                            'payment_reference' => $transaction->payment->reference ?? null,
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertySubscriptionController show error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscription details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate property ownership certificate
     */
    public function generateCertificate(string $allocationId): JsonResponse
    {
        try {
            $allocation = PropertyAllocation::with([
                'property',
                'member.user',
            ])->findOrFail($allocationId);

            // Verify subscription is completed
            $paymentPlan = PropertyPaymentPlan::where('property_id', $allocation->property_id)
                ->where('member_id', $allocation->member_id)
                ->first();

            $propertyPrice = $allocation->property->price ?? 0;
            $amountPaid = PropertyPaymentTransaction::where('property_id', $allocation->property_id)
                ->where('member_id', $allocation->member_id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->sum('amount');

            if ($amountPaid < $propertyPrice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property subscription is not fully paid. Cannot generate certificate.',
                ], 400);
            }

            // Generate certificate data
            $certificateData = [
                'certificate_number' => 'CERT-' . strtoupper(substr($allocation->id, 0, 8)) . '-' . date('Y'),
                'issue_date' => now()->toDateString(),
                'property' => [
                    'title' => $allocation->property->title,
                    'address' => $allocation->property->address,
                    'city' => $allocation->property->city,
                    'state' => $allocation->property->state,
                    'price' => $propertyPrice,
                ],
                'member' => [
                    'name' => trim(($allocation->member->user->first_name ?? '') . ' ' . ($allocation->member->user->last_name ?? '')),
                    'member_id' => $allocation->member->member_id ?? $allocation->member->staff_id ?? 'N/A',
                    'email' => $allocation->member->user->email ?? null,
                ],
                'allocation_date' => $allocation->allocation_date?->toDateString(),
                'completion_date' => now()->toDateString(),
            ];

            // For now, return JSON. In production, you'd generate a PDF here
            return response()->json([
                'success' => true,
                'message' => 'Certificate generated successfully',
                'certificate' => $certificateData,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PropertySubscriptionController generateCertificate error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate certificate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

