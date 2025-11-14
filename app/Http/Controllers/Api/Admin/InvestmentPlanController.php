<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\InvestmentPlan;
use App\Models\Tenant\Investment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvestmentPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = InvestmentPlan::query();

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'true');
        }

        if ($request->has('risk_level') && $request->risk_level !== 'all') {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->has('return_type') && $request->return_type !== 'all') {
            $query->where('return_type', $request->return_type);
        }

        $plans = $query->withCount('investments')->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $data = $plans->map(function($plan) {
            $totalInvested = Investment::where('plan_id', $plan->id)->sum('amount');
            $totalInvestors = Investment::where('plan_id', $plan->id)->distinct('member_id')->count('member_id');
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'min_amount' => (float) $plan->min_amount,
                'max_amount' => (float) $plan->max_amount,
                'expected_return_rate' => (float) $plan->expected_return_rate,
                'min_duration_months' => $plan->min_duration_months,
                'max_duration_months' => $plan->max_duration_months,
                'return_type' => $plan->return_type,
                'risk_level' => $plan->risk_level,
                'features' => $plan->features ?? [],
                'terms_and_conditions' => $plan->terms_and_conditions ?? [],
                'is_active' => (bool) $plan->is_active,
                'investments_count' => $plan->investments_count,
                'total_invested' => $totalInvested,
                'total_investors' => $totalInvestors,
                'created_at' => $plan->created_at,
                'updated_at' => $plan->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|min:0|gte:min_amount',
            'expected_return_rate' => 'required|numeric|min:0|max:100',
            'min_duration_months' => 'required|integer|min:1',
            'max_duration_months' => 'required|integer|min:1|gte:min_duration_months',
            'return_type' => 'required|string|in:monthly,quarterly,annual,lump_sum',
            'risk_level' => 'required|string|in:low,medium,high',
            'features' => 'nullable|array',
            'terms_and_conditions' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $plan = InvestmentPlan::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Investment plan created successfully',
                'data' => $plan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create investment plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $plan = InvestmentPlan::with(['investments.member.user'])->findOrFail($id);
            $totalInvested = Investment::where('plan_id', $plan->id)->sum('amount');
            $totalInvestors = Investment::where('plan_id', $plan->id)->distinct('member_id')->count('member_id');

            $data = [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'min_amount' => (float) $plan->min_amount,
                'max_amount' => (float) $plan->max_amount,
                'expected_return_rate' => (float) $plan->expected_return_rate,
                'min_duration_months' => $plan->min_duration_months,
                'max_duration_months' => $plan->max_duration_months,
                'return_type' => $plan->return_type,
                'risk_level' => $plan->risk_level,
                'features' => $plan->features ?? [],
                'terms_and_conditions' => $plan->terms_and_conditions ?? [],
                'is_active' => (bool) $plan->is_active,
                'total_invested' => $totalInvested,
                'total_investors' => $totalInvestors,
                'investments' => $plan->investments,
                'created_at' => $plan->created_at,
                'updated_at' => $plan->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investment plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $plan = InvestmentPlan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'sometimes|required|numeric|min:0',
            'expected_return_rate' => 'sometimes|required|numeric|min:0|max:100',
            'min_duration_months' => 'sometimes|required|integer|min:1',
            'max_duration_months' => 'sometimes|required|integer|min:1',
            'return_type' => 'sometimes|required|string|in:monthly,quarterly,annual,lump_sum',
            'risk_level' => 'sometimes|required|string|in:low,medium,high',
            'features' => 'nullable|array',
            'terms_and_conditions' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('max_amount') && $request->has('min_amount') && 
                $request->max_amount < $request->min_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['max_amount' => ['Max amount must be greater than or equal to min amount']]
                ], 422);
            }

            if ($request->has('max_duration_months') && $request->has('min_duration_months') && 
                $request->max_duration_months < $request->min_duration_months) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['max_duration_months' => ['Max duration must be greater than or equal to min duration']]
                ], 422);
            }

            $plan->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Investment plan updated successfully',
                'data' => $plan->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update investment plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $plan = InvestmentPlan::findOrFail($id);

        if ($plan->investments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with associated investments'
            ], 400);
        }

        try {
            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Investment plan deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete investment plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $plan = InvestmentPlan::findOrFail($id);
            $plan->update(['is_active' => !$plan->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Investment plan status updated successfully',
                'data' => $plan->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $activePlans = InvestmentPlan::where('is_active', true)->count();
        $totalInvested = Investment::sum('amount');
        $totalInvestors = Investment::distinct('member_id')->count('member_id');

        return response()->json([
            'success' => true,
            'data' => [
                'active_plans' => $activePlans,
                'total_invested' => $totalInvested,
                'total_investors' => $totalInvestors,
            ]
        ]);
    }
}

