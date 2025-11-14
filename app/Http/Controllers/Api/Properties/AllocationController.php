<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\AllocationRequest;
use App\Http\Resources\Properties\AllocationResource;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\Property;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PropertyAllocation::with(['property', 'member.user']);

        // Filter by property
        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        // Filter by member
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $allocations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'allocations' => AllocationResource::collection($allocations),
            'pagination' => [
                'current_page' => $allocations->currentPage(),
                'last_page' => $allocations->lastPage(),
                'per_page' => $allocations->perPage(),
                'total' => $allocations->total(),
            ]
        ]);
    }

    public function store(AllocationRequest $request): JsonResponse
    {
        $allocation = PropertyAllocation::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Property allocated successfully',
            'allocation' => new AllocationResource($allocation->load(['property', 'member.user']))
        ], 201);
    }

    public function show(PropertyAllocation $allocation): JsonResponse
    {
        $allocation->load(['property', 'member.user']);
        
        return response()->json([
            'allocation' => new AllocationResource($allocation)
        ]);
    }

    public function update(AllocationRequest $request, PropertyAllocation $allocation): JsonResponse
    {
        $allocation->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Allocation updated successfully',
            'allocation' => new AllocationResource($allocation->load(['property', 'member.user']))
        ]);
    }

    public function approve(PropertyAllocation $allocation): JsonResponse
    {
        $allocation->update(['status' => 'approved']);

        return response()->json([
            'success' => true,
            'message' => 'Allocation approved successfully'
        ]);
    }

    public function reject(PropertyAllocation $allocation): JsonResponse
    {
        $request = request();
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $allocation->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Allocation rejected successfully'
        ]);
    }

    public function destroy(PropertyAllocation $allocation): JsonResponse
    {
        $allocation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Allocation deleted successfully'
        ]);
    }
}
