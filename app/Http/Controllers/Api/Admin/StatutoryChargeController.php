<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StatutoryCharge;
use App\Models\Tenant\StatutoryChargePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class StatutoryChargeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = StatutoryCharge::with(['member.user', 'payments']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('member.user', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $charges = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $data = $charges->map(function($charge) {
            $member = $charge->member->user ?? null;
            return [
                'id' => $charge->id,
                'member_id' => $charge->member_id,
                'member' => $member ? [
                    'id' => $member->id,
                    'name' => ($member->first_name ?? '') . ' ' . ($member->last_name ?? ''),
                    'member_id' => $charge->member->member_id ?? $charge->member->staff_id ?? '—',
                ] : null,
                'type' => $charge->type,
                'amount' => (float) $charge->amount,
                'description' => $charge->description,
                'due_date' => $charge->due_date,
                'status' => $charge->status,
                'approved_at' => $charge->approved_at,
                'approved_by' => $charge->approved_by,
                'rejection_reason' => $charge->rejection_reason,
                'rejected_at' => $charge->rejected_at,
                'rejected_by' => $charge->rejected_by,
                'total_paid' => (float) $charge->total_paid,
                'remaining_amount' => (float) $charge->remaining_amount,
                'is_overdue' => $charge->isOverdue(),
                'payments' => $charge->payments,
                'created_at' => $charge->created_at,
                'updated_at' => $charge->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $charges->currentPage(),
                'last_page' => $charges->lastPage(),
                'per_page' => $charges->perPage(),
                'total' => $charges->total(),
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
            'member_id' => 'required|uuid|exists:members,id',
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'status' => 'sometimes|string|in:pending,approved,rejected,paid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $charge = StatutoryCharge::create([
                'member_id' => $request->member_id,
                'type' => $request->type,
                'amount' => $request->amount,
                'description' => $request->description,
                'due_date' => $request->due_date,
                'status' => $request->status ?? 'pending',
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statutory charge created successfully',
                'data' => $charge->load(['member.user'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create statutory charge',
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
            $charge = StatutoryCharge::with(['member.user', 'payments'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $charge
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statutory charge',
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

        $charge = StatutoryCharge::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'due_date' => 'sometimes|required|date',
            'status' => 'sometimes|string|in:pending,approved,rejected,paid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $charge->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Statutory charge updated successfully',
                'data' => $charge->fresh()->load(['member.user', 'payments'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update statutory charge',
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

        $charge = StatutoryCharge::findOrFail($id);

        if ($charge->payments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete charge with associated payments'
            ], 400);
        }

        try {
            $charge->delete();

            return response()->json([
                'success' => true,
                'message' => 'Statutory charge deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete statutory charge',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $charge = StatutoryCharge::findOrFail($id);

        if ($charge->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Charge is not pending approval'
            ], 400);
        }

        $charge->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statutory charge approved successfully',
            'data' => $charge->fresh()
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $charge = StatutoryCharge::findOrFail($id);

        if ($charge->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Charge is not pending approval'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $charge->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statutory charge rejected successfully',
            'data' => $charge->fresh()
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $totalCharges = StatutoryCharge::sum('amount');
        $paidCharges = StatutoryCharge::where('status', 'paid')->sum('amount');
        $pendingCharges = StatutoryCharge::where('status', 'pending')->sum('amount');
        $overdueCharges = StatutoryCharge::where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->sum('amount');
        $overdueCount = StatutoryCharge::where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_charges' => $totalCharges,
                'paid_charges' => $paidCharges,
                'pending_charges' => $pendingCharges,
                'overdue_charges' => $overdueCharges,
                'overdue_count' => $overdueCount,
                'collection_rate' => $totalCharges > 0 ? ($paidCharges / $totalCharges) * 100 : 0,
            ]
        ]);
    }
}

