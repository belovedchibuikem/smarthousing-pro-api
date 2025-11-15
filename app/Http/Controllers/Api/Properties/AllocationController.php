<?php

namespace App\Http\Controllers\Api\Properties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\AllocationRequest;
use App\Http\Resources\Properties\AllocationResource;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\Property;
use App\Models\Tenant\Member;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllocationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

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
        $allocation->load(['property', 'member.user']);

        // Notify admins about new property allocation request
        if ($allocation->member && $allocation->member->user) {
            $memberName = trim($allocation->member->first_name . ' ' . $allocation->member->last_name);
            $propertyTitle = $allocation->property->title ?? 'property';
            
            $this->notificationService->notifyAdmins(
                'info',
                'New Property Allocation Request',
                "A new property allocation request for {$propertyTitle} has been submitted by {$memberName}",
                [
                    'allocation_id' => $allocation->id,
                    'property_id' => $allocation->property_id,
                    'property_title' => $propertyTitle,
                    'member_id' => $allocation->member_id,
                    'member_name' => $memberName,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Property allocated successfully',
            'allocation' => new AllocationResource($allocation)
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
        $allocation->load(['property', 'member.user']);

        // Notify the member about property allocation approval
        if ($allocation->member && $allocation->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$allocation->member->user->id],
                'success',
                'Property Allocation Approved',
                'Your property allocation for ' . ($allocation->property->title ?? 'property') . ' has been approved.',
                [
                    'allocation_id' => $allocation->id,
                    'property_id' => $allocation->property_id,
                    'property_title' => $allocation->property->title ?? null,
                ]
            );
        }

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

        $allocation->load(['property', 'member.user']);

        // Notify the member about property allocation rejection
        if ($allocation->member && $allocation->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$allocation->member->user->id],
                'warning',
                'Property Allocation Rejected',
                'Your property allocation for ' . ($allocation->property->title ?? 'property') . ' has been rejected. Reason: ' . $request->reason,
                [
                    'allocation_id' => $allocation->id,
                    'property_id' => $allocation->property_id,
                    'property_title' => $allocation->property->title ?? null,
                    'reason' => $request->reason,
                ]
            );
        }

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
