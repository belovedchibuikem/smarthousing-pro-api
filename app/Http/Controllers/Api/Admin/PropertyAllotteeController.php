<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyAllotteeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = PropertyAllocation::with(['property', 'member.user']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('property', function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $allottees = $query->orderBy('allocation_date', 'desc')->paginate($request->get('per_page', 15));

        $data = $allottees->map(function($allocation) {
            $member = $allocation->member->user ?? null;
            return [
                'id' => $allocation->id,
                'property' => [
                    'id' => $allocation->property->id,
                    'title' => $allocation->property->title,
                    'location' => $allocation->property->location,
                    'type' => $allocation->property->type,
                ],
                'member' => $member ? [
                    'id' => $member->id,
                    'name' => ($member->first_name ?? '') . ' ' . ($member->last_name ?? ''),
                    'member_id' => $allocation->member->member_id ?? $allocation->member->staff_id ?? '—',
                    'email' => $member->email,
                ] : null,
                'allocation_date' => $allocation->allocation_date,
                'status' => $allocation->status,
                'notes' => $allocation->notes,
                'rejection_reason' => $allocation->rejection_reason,
                'created_at' => $allocation->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $allottees->currentPage(),
                'last_page' => $allottees->lastPage(),
                'per_page' => $allottees->perPage(),
                'total' => $allottees->total(),
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
            'property_id' => 'required|uuid|exists:properties,id',
            'member_id' => 'required|uuid|exists:members,id',
            'allocation_date' => 'required|date',
            'status' => 'sometimes|string|in:pending,approved,rejected',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if property is already allocated
        $existing = PropertyAllocation::where('property_id', $request->property_id)
            ->where('status', 'approved')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Property is already allocated to another member'
            ], 400);
        }

        try {
            $allocation = PropertyAllocation::create([
                'property_id' => $request->property_id,
                'member_id' => $request->member_id,
                'allocation_date' => $request->allocation_date,
                'status' => $request->status ?? 'pending',
                'notes' => $request->notes,
            ]);

            // Update property status if approved
            if ($allocation->status === 'approved') {
                Property::where('id', $request->property_id)->update(['status' => 'allocated']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Allocation created successfully',
                'data' => $allocation->load(['property', 'member.user'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create allocation',
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
            $allocation = PropertyAllocation::with(['property', 'member.user'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $allocation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch allocation',
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

        $allocation = PropertyAllocation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:pending,approved,rejected',
            'allocation_date' => 'sometimes|date',
            'notes' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldStatus = $allocation->status;
            $allocation->update($request->all());

            // Update property status based on allocation status
            if ($request->has('status')) {
                if ($request->status === 'approved') {
                    Property::where('id', $allocation->property_id)->update(['status' => 'allocated']);
                } elseif ($oldStatus === 'approved' && $request->status !== 'approved') {
                    Property::where('id', $allocation->property_id)->update(['status' => 'available']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Allocation updated successfully',
                'data' => $allocation->fresh()->load(['property', 'member.user'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update allocation',
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

        $allocation = PropertyAllocation::findOrFail($id);

        try {
            $propertyId = $allocation->property_id;
            $allocation->delete();

            // Check if property should be marked as available
            $remaining = PropertyAllocation::where('property_id', $propertyId)
                ->where('status', 'approved')
                ->count();

            if ($remaining === 0) {
                Property::where('id', $propertyId)->update(['status' => 'available']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Allocation deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete allocation',
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

        $totalAllottees = PropertyAllocation::count();
        $approvedAllottees = PropertyAllocation::where('status', 'approved')->count();
        $pendingAllottees = PropertyAllocation::where('status', 'pending')->count();
        $rejectedAllottees = PropertyAllocation::where('status', 'rejected')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_allottees' => $totalAllottees,
                'approved_allottees' => $approvedAllottees,
                'pending_allottees' => $pendingAllottees,
                'rejected_allottees' => $rejectedAllottees,
            ]
        ]);
    }
}

