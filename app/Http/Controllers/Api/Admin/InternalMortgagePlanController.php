<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InternalMortgagePlanRequest;
use App\Models\Tenant\InternalMortgagePlan;
use App\Models\Tenant\Member;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalMortgagePlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = InternalMortgagePlan::with(['property', 'member.user', 'configuredBy']);

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
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load internal mortgage plans at this time.',
            ], 500);
        }
    }

    public function store(InternalMortgagePlanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $admin = $request->user();

        $propertyId = $validated['property_id'] ?? null;
        $memberId = $validated['member_id'] ?? null;

        $property = $propertyId ? Property::findOrFail($propertyId) : null;
        $member = $memberId ? Member::findOrFail($memberId) : null;

        $plan = DB::transaction(function () use ($validated, $admin, $property, $member) {
            $principal = (float) $validated['principal'];
            $rate = (float) $validated['interest_rate'];
            $tenureYears = (int) $validated['tenure_years'];
            $tenureMonths = $tenureYears * 12; // Convert years to months
            $frequency = $validated['frequency'];

            $periodsPerYear = match ($frequency) {
                'monthly' => 12,
                'quarterly' => 4,
                'biannually' => 2,
                'annually' => 1,
                default => 12,
            };

            // Calculate monthly payment using amortization formula (PMT)
            // Always calculate monthly payment regardless of frequency
            $numberOfPayments = $tenureMonths; // Total number of monthly payments
            $monthlyRate = $rate > 0 ? ($rate / 100) / 12 : 0;

            $monthlyPayment = null;
            if ($monthlyRate > 0 && $numberOfPayments > 0) {
                $factor = pow(1 + $monthlyRate, $numberOfPayments);
                if ($factor === 1.0) {
                    $monthlyPayment = $principal / $numberOfPayments;
                } else {
                    $monthlyPayment = $principal * ($monthlyRate * $factor) / ($factor - 1);
                }
            } elseif ($numberOfPayments > 0) {
                $monthlyPayment = $principal / $numberOfPayments;
            }

            return InternalMortgagePlan::create([
                'property_id' => $property?->id,
                'member_id' => $member?->id,
                'configured_by' => $admin->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'principal' => $principal,
                'interest_rate' => $rate,
                'tenure_months' => $tenureMonths,
                'monthly_payment' => $monthlyPayment,
                'frequency' => $frequency,
                'status' => $validated['status'] ?? 'draft',
                'starts_on' => $validated['starts_on'] ?? null,
                'ends_on' => $validated['ends_on'] ?? null,
                'schedule' => null,
                'metadata' => $validated['metadata'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Internal mortgage plan created successfully.',
            'data' => $plan->load(['property', 'member.user', 'configuredBy']),
        ], 201);
    }

    public function show(string $planId): JsonResponse
    {

        $plan = InternalMortgagePlan::findOrFail($planId);
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Internal mortgage plan not found.',
            ], 404);
        }

        $plan->load(['property', 'member.user', 'configuredBy', 'repayments']);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }
}



