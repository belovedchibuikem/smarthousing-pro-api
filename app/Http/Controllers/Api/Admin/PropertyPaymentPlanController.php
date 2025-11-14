<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePropertyPaymentPlanRequest;
use App\Http\Requests\Admin\UpdatePropertyPaymentPlanRequest;
use App\Models\Tenant\Member;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\PropertyPaymentPlan;
use App\Models\Tenant\PropertyPaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyPaymentPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PropertyPaymentPlan::with(['property', 'member.user', 'interest', 'configuredBy']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        $plans = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $plans->items(),
            'pagination' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function pendingInterests(Request $request): JsonResponse
    {
        try {
            
            $query = PropertyInterest::with(['property.images', 'member.user', 'paymentPlan'])
                ->where('status', 'approved')
                ->whereHas('property')
                ->where(function ($q) {
                    // Include properties without payment plans (pending setup - can be set up with cooperative)
                    $q->whereDoesntHave('paymentPlan')
                        // OR properties with payment plans that have cooperative deduction as a payment method
                        ->orWhereHas('paymentPlan', function ($planQuery) {
                            $planQuery->where(function ($pq) {
                                // Check if 'cooperative' is in the selected_methods array
                                $pq->whereJsonContains('selected_methods', 'cooperative')
                                    // OR funding_option is 'cooperative' (single method)
                                    ->orWhere('funding_option', 'cooperative')
                                    // OR funding_option is 'mix' and cooperative is in selected_methods
                                    ->orWhere(function ($mixQuery) {
                                        $mixQuery->where('funding_option', 'mix')
                                            ->whereJsonContains('selected_methods', 'cooperative');
                                    });
                            });
                        });
                });
            
            if ($request->filled('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            if ($request->filled('member_id')) {
                $query->where('member_id', $request->member_id);
            }

            $interests = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $interests->items(),
                'pagination' => [
                    'current_page' => $interests->currentPage(),
                    'last_page' => $interests->lastPage(),
                    'per_page' => $interests->perPage(),
                    'total' => $interests->total(),
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Unable to load pending property payment interests.', ['error' => $exception->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load pending property payment interests at this time.',
            ], 500);
        }
    }

    public function getPropertyPaymentPlanDetails(Request $request): JsonResponse
    {
        try {
            $propertyId = $request->get('property_id');
            $memberId = $request->get('member_id');

            if (!$propertyId || !$memberId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property ID and Member ID are required.',
                ], 422);
            }

            $plan = PropertyPaymentPlan::where('property_id', $propertyId)
                ->where('member_id', $memberId)
                ->with(['property'])
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment plan found for this property and member.',
                ], 404);
            }

            // Extract cooperative deduction allocation
            $cooperativeAmount = null;
            $mortgageAmount = null;
            $configuration = $plan->configuration ?? [];

            if ($plan->funding_option === 'cooperative') {
                // Single cooperative method - use total_amount
                $cooperativeAmount = $plan->total_amount;
            } elseif ($plan->funding_option === 'mix' && isset($configuration['mix_allocations'])) {
                // Mix funding - get cooperative amount from allocations
                $mixAllocations = $configuration['mix_allocations'];
                if (isset($mixAllocations['amounts']['cooperative'])) {
                    $cooperativeAmount = $mixAllocations['amounts']['cooperative'];
                }
                if (isset($mixAllocations['amounts']['mortgage'])) {
                    $mortgageAmount = $mixAllocations['amounts']['mortgage'];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'property' => [
                        'id' => $plan->property->id,
                        'title' => $plan->property->title,
                    ],
                    'cooperative_amount' => $cooperativeAmount,
                    'mortgage_amount' => $mortgageAmount,
                    'funding_option' => $plan->funding_option,
                    'selected_methods' => $plan->selected_methods ?? [],
                ],
            ]);
        } catch (\Exception $exception) {
            Log::error('Unable to load property payment plan details.', ['error' => $exception->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load property payment plan details at this time.',
            ], 500);
        }
    }

    public function store(StorePropertyPaymentPlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $admin = $request->user();

        /** @var Property $property */
        $property = Property::findOrFail($data['property_id']);
        /** @var Member $member */
        $member = Member::findOrFail($data['member_id']);

        $interest = null;
        if (!empty($data['interest_id'])) {
            /** @var PropertyInterest $interest */
            $interest = PropertyInterest::with('paymentPlan')
                ->where('id', $data['interest_id'])
                ->where('property_id', $property->id)
                ->where('member_id', $member->id)
                ->firstOrFail();

            if ($interest->paymentPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'A payment plan already exists for this interest.',
                ], 422);
            }
        }

        $selectedMethods = $data['selected_methods'] ?? ($data['funding_option'] ? [$data['funding_option']] : []);
        $selectedMethods = array_values(array_unique($selectedMethods));

        if ($data['funding_option'] === 'mix') {
            if (count($selectedMethods) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide the payment methods selected by the member for the mix funding plan.',
                ], 422);
            }

            $mixAllocations = $data['mix_allocations'] ?? [];
            $mixAllocations = array_intersect_key($mixAllocations, array_flip($selectedMethods));

            $totalAllocation = array_reduce($mixAllocations, static function ($carry, $value) {
                return $carry + (float) $value;
            }, 0.0);

            if (abs($totalAllocation - 100.0) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mix funding allocations must total 100%.',
                ], 422);
            }

            $data['mix_allocations'] = $mixAllocations;
        }

        $configuration = $data['configuration'] ?? [];
        $totalAmount = $data['total_amount'] ?? $property->price;
        $initialBalance = $data['initial_balance'] ?? $property->price;
        $remainingBalance = $data['remaining_balance'] ?? $property->price;

        if (($data['funding_option'] ?? null) === 'mix') {
            $mixAllocations = $data['mix_allocations'] ?? [];
            $mixAmounts = [];
            foreach ($mixAllocations as $method => $percentage) {
                $mixAmounts[$method] = round(((float) $percentage / 100) * (float) $totalAmount, 2);
            }

            $configuration = array_merge($configuration, [
                'mix_allocations' => [
                    'percentages' => $mixAllocations,
                    'amounts' => $mixAmounts,
                    'total_amount' => (float) $totalAmount,
                ],
            ]);
        }

        $plan = DB::transaction(function () use ($data, $admin, $property, $member, $interest, $selectedMethods, $configuration, $totalAmount, $initialBalance, $remainingBalance) {
            return PropertyPaymentPlan::create([
                'property_id' => $property->id,
                'member_id' => $member->id,
                'interest_id' => $interest?->id,
                'configured_by' => $admin->id,
                'status' => $data['status'] ?? 'draft',
                'funding_option' => $data['funding_option'] ?? ($interest?->funding_option ?? null),
                'selected_methods' => $selectedMethods,
                'configuration' => $configuration ?: null,
                'schedule' => $data['schedule'] ?? null,
                'total_amount' => $totalAmount,
                'initial_balance' => $initialBalance,
                'remaining_balance' => $remainingBalance,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Property payment plan created successfully.',
            'data' => $plan->load(['property', 'member.user', 'interest', 'configuredBy']),
        ], 201);
    }

    public function update(UpdatePropertyPaymentPlanRequest $request, string $planId): JsonResponse
    {
        $data = $request->validated();
        $admin = $request->user();

        $plan = PropertyPaymentPlan::findOrFail($planId);
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Property payment plan not found.',
            ], 404);
        }

        /** @var Property $property */
        $property = Property::findOrFail($plan->property_id);
        /** @var Member $member */
        $member = Member::findOrFail($plan->member_id);

        $interest = $plan->interest_id ? PropertyInterest::with('paymentPlan')->find($plan->interest_id) : null;

        $selectedMethods = $data['selected_methods'] ?? $plan->selected_methods ?? [];
        $selectedMethods = array_values(array_unique(array_filter($selectedMethods)));

        $existingTransactions = PropertyPaymentTransaction::where('plan_id', $plan->id)->get();
        $totalCredited = (float) $existingTransactions
            ->where('direction', 'credit')
            ->sum('amount');

        $fundingOption = $data['funding_option'] ?? $plan->funding_option ?? ($interest?->funding_option ?? null);
        $totalAmount = $data['total_amount'] ?? $plan->total_amount ?? $property->price;
        $initialBalance = $data['initial_balance'] ?? $plan->initial_balance ?? $property->price;
        $configuration = $data['configuration'] ?? $plan->configuration ?? [];

        $mixAllocations = [];

        if ($fundingOption === 'mix') {
            if (empty($selectedMethods)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide the payment methods selected for the mix funding plan.',
                ], 422);
            }

            $mixAllocations = $data['mix_allocations'] ?? ($plan->configuration['mix_allocations']['percentages'] ?? []);
            $mixAllocations = array_intersect_key($mixAllocations, array_flip($selectedMethods));

            $totalAllocation = array_reduce($mixAllocations, static function ($carry, $value) {
                return $carry + (float) $value;
            }, 0.0);

            if (abs($totalAllocation - 100.0) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mix funding allocations must total 100%.',
                ], 422);
            }

            $creditsByMethod = $existingTransactions
                ->where('direction', 'credit')
                ->groupBy('source')
                ->map(static function ($group) {
                    return (float) $group->sum('amount');
                });

            foreach ($mixAllocations as $method => $percentage) {
                $target = round(((float) $percentage / 100) * (float) $totalAmount, 2);
                $credited = (float) ($creditsByMethod[$method] ?? 0.0);
                if ($target + 0.01 < $credited) {
                    return response()->json([
                        'success' => false,
                        'message' => "The allocation for {$method} cannot be less than the amount already credited ({$credited}).",
                    ], 422);
                }
            }

            $configuration = array_merge($configuration ?? [], [
                'mix_allocations' => [
                    'percentages' => $mixAllocations,
                    'amounts' => collect($mixAllocations)->mapWithKeys(function ($percentage, $method) use ($totalAmount) {
                        return [$method => round(((float) $percentage / 100) * (float) $totalAmount, 2)];
                    })->toArray(),
                    'total_amount' => (float) $totalAmount,
                ],
            ]);
        } else {
            if ($totalAmount + 0.01 < $totalCredited) {
                return response()->json([
                    'success' => false,
                    'message' => 'The total amount cannot be less than the amount already credited on this plan.',
                ], 422);
            }
        }

        $remainingBalance = $data['remaining_balance'] ?? max(0, round((float) $totalAmount - $totalCredited, 2));

        $plan = DB::transaction(function () use (
            $plan,
            $admin,
            $data,
            $fundingOption,
            $selectedMethods,
            $configuration,
            $totalAmount,
            $initialBalance,
            $remainingBalance
        ) {
            $plan->update([
                'configured_by' => $admin->id,
                'status' => $data['status'] ?? $plan->status,
                'funding_option' => $fundingOption,
                'selected_methods' => $selectedMethods,
                'configuration' => $configuration ?: null,
                'schedule' => $data['schedule'] ?? $plan->schedule,
                'total_amount' => $totalAmount,
                'initial_balance' => $initialBalance,
                'remaining_balance' => $remainingBalance,
                'starts_on' => $data['starts_on'] ?? $plan->starts_on,
                'ends_on' => $data['ends_on'] ?? $plan->ends_on,
                'metadata' => $data['metadata'] ?? $plan->metadata,
            ]);

            return $plan;
        });

        return response()->json([
            'success' => true,
            'message' => 'Property payment plan updated successfully.',
            'data' => $plan->load(['property', 'member.user', 'interest', 'configuredBy']),
        ]);
    }

    public function show(string $planId): JsonResponse
    {
        $plan = PropertyPaymentPlan::findOrFail($planId);
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Property payment plan not found.',
            ], 404);
        }
        $plan->load(['property.images', 'member.user', 'interest', 'configuredBy']);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }
}



