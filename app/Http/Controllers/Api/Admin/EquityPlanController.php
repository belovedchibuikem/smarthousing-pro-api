<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EquityPlan;
use App\Models\Tenant\EquityContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EquityPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EquityPlan::query();

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

        $plans = $query->withCount('contributions')->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $data = $plans->map(function($plan) {
            $totalContributions = EquityContribution::where('plan_id', $plan->id)
                ->where('status', 'approved')
                ->sum('amount');
            $totalMembers = EquityContribution::where('plan_id', $plan->id)
                ->where('status', 'approved')
                ->distinct('member_id')
                ->count('member_id');
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'min_amount' => (float) $plan->min_amount,
                'max_amount' => $plan->max_amount ? (float) $plan->max_amount : null,
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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
            'frequency' => 'required|string|in:monthly,quarterly,annually,one_time,custom',
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

        $plan = EquityPlan::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Equity plan created successfully',
            'data' => $plan
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $plan = EquityPlan::with(['contributions.member.user'])->findOrFail($id);
        $totalContributions = EquityContribution::where('plan_id', $plan->id)
            ->where('status', 'approved')
            ->sum('amount');
        $totalMembers = EquityContribution::where('plan_id', $plan->id)
            ->where('status', 'approved')
            ->distinct('member_id')
            ->count('member_id');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'min_amount' => (float) $plan->min_amount,
                'max_amount' => $plan->max_amount ? (float) $plan->max_amount : null,
                'frequency' => $plan->frequency,
                'is_mandatory' => (bool) $plan->is_mandatory,
                'is_active' => (bool) $plan->is_active,
                'total_contributions' => $totalContributions,
                'total_members' => $totalMembers,
                'contributions' => $plan->contributions,
                'created_at' => $plan->created_at,
                'updated_at' => $plan->updated_at,
            ]
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $plan = EquityPlan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
            'frequency' => 'sometimes|required|string|in:monthly,quarterly,annually,one_time,custom',
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

        $plan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Equity plan updated successfully',
            'data' => $plan->fresh()
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $plan = EquityPlan::findOrFail($id);

        if ($plan->contributions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with associated contributions'
            ], 400);
        }

        $plan->delete();
        return response()->json([
            'success' => true,
            'message' => 'Equity plan deleted successfully'
        ]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $plan = EquityPlan::findOrFail($id);
        $plan->is_active = !$plan->is_active;
        $plan->save();

        return response()->json([
            'success' => true,
            'message' => 'Equity plan status updated successfully',
            'data' => $plan->fresh()
        ]);
    }
}

