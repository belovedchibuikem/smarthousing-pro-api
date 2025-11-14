<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ContributionPlan;
use App\Models\Tenant\Contribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContributionPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        
        $user = $request->user();
       
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = ContributionPlan::query();

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

        if ($request->has('frequency') && $request->frequency !== 'all') {
            $query->where('frequency', $request->frequency);
        }
        
        try {
            $plans = $query
                ->withCount('contributions')
                ->orderByDesc('created_at')
                ->paginate((int) $request->get('per_page', 15));
        } catch (\Throwable $e) {
            Log::error('Failed to load contribution plans', [
                'error' => $e->getMessage(),
                'trace' => app()->environment('production') ? null : $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load contribution plans',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
        
        $collection = $plans->getCollection()->map(function($plan) {
            $totalContributions = Contribution::where('plan_id', $plan->id)->sum('amount');
            $totalMembers = Contribution::where('plan_id', $plan->id)->distinct('member_id')->count('member_id');
      
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'amount' => (float) $plan->amount,
                'minimum_amount' => (float) $plan->minimum_amount,
                'frequency' => $plan->frequency,
                'is_mandatory' => (bool) $plan->is_mandatory,
                'is_active' => (bool) $plan->is_active,
                'contributions_count' => $plan->contributions_count,
                'total_contributions' => $totalContributions,
                'total_members' => $totalMembers,
                'created_at' => $plan->created_at,
                'updated_at' => $plan->updated_at,
            ];
        });

        $plans->setCollection($collection);

        return response()->json([
            'success' => true,
            'data' => $plans->items(),
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
            'amount' => 'required|numeric|min:0',
            'minimum_amount' => 'required|numeric|min:0|lte:amount',
            'frequency' => 'required|string|in:monthly,quarterly,annually,one_time',
            'is_mandatory' => 'boolean',
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
            $data = $validator->validated();

            $plan = ContributionPlan::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'minimum_amount' => $data['minimum_amount'],
                'frequency' => $data['frequency'],
                'is_mandatory' => $data['is_mandatory'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contribution plan created successfully',
                'data' => $plan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create contribution plan',
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
            $plan = ContributionPlan::with(['contributions.member.user'])->findOrFail($id);
            $totalContributions = Contribution::where('plan_id', $plan->id)->sum('amount');
            $totalMembers = Contribution::where('plan_id', $plan->id)->distinct('member_id')->count('member_id');

            $data = [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'amount' => (float) $plan->amount,
                'minimum_amount' => (float) $plan->minimum_amount,
                'frequency' => $plan->frequency,
                'is_mandatory' => (bool) $plan->is_mandatory,
                'is_active' => (bool) $plan->is_active,
                'total_contributions' => $totalContributions,
                'total_members' => $totalMembers,
                'contributions' => $plan->contributions,
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
                'message' => 'Failed to fetch contribution plan',
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

        $plan = ContributionPlan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric|min:0',
            'minimum_amount' => 'sometimes|required|numeric|min:0',
            'frequency' => 'sometimes|required|string|in:monthly,quarterly,annually,one_time',
            'is_mandatory' => 'boolean',
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
            $data = $validator->validated();

            $amount = $data['amount'] ?? $plan->amount;
            if (isset($data['minimum_amount']) && $data['minimum_amount'] > $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimum amount cannot be greater than the contribution amount.',
                ], 422);
            }

            if (isset($data['minimum_amount']) && !isset($data['amount'])) {
                $data['amount'] = $plan->amount;
            }

            $plan->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Contribution plan updated successfully',
                'data' => $plan->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update contribution plan',
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

        $plan = ContributionPlan::findOrFail($id);

        if ($plan->contributions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with associated contributions'
            ], 400);
        }

        try {
            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contribution plan deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete contribution plan',
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
            $plan = ContributionPlan::findOrFail($id);
            $plan->update(['is_active' => !$plan->is_active]);

            return response()->json([
                'success' => true,
                'message' => 'Contribution plan status updated successfully',
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
}

