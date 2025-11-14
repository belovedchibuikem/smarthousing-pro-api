<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Loan::with(['member.user', 'repayments']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('member', function($q) use ($search) {
                $q->where('member_id', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $loans = $query->latest('application_date')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $loans->items(),
            'pagination' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ]
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $loan = Loan::with(['member.user', 'repayments'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $loan
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $loan = Loan::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'interest_rate' => 'sometimes|numeric|min:0|max:100',
            'duration_months' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string',
            'purpose' => 'nullable|string',
        ]);

        $loan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Loan updated successfully',
            'data' => $loan->fresh()->load(['member.user', 'repayments'])
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $loan = Loan::findOrFail($id);

        if ($loan->repayments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete loan with repayments'
            ], 400);
        }

        $loan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Loan deleted successfully'
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $loan = Loan::findOrFail($id);

        if ($loan->status === 'approved' || $loan->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Loan is already approved or active'
            ], 400);
        }

        $loan->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan approved successfully',
            'data' => $loan->fresh()->load(['member.user', 'repayments'])
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $loan = Loan::findOrFail($id);

        if ($loan->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Loan is already rejected'
            ], 400);
        }

        if ($loan->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject an active loan'
            ], 400);
        }

        $loan->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejected_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan rejected successfully',
            'data' => $loan->fresh()->load(['member.user', 'repayments'])
        ]);
    }

    public function disburse(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
       
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $loan = Loan::with('member.user')->findOrFail($id);

        if ($loan->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved loans can be disbursed'
            ], 400);
        }

        $loan->update([
            'status' => 'active',
            'disbursed_at' => now(),
            'disbursed_by' => $user->id,
        ]);

        app(\App\Services\ActivityLogService::class)->logModelAction(
            'disburse',
            $loan,
            $user,
            [
                'member_id' => $loan->member_id,
                'loan_id' => $loan->id,
                'amount' => $loan->amount,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Loan disbursed successfully',
            'data' => $loan->fresh()->load(['member.user', 'repayments'])
        ]);
    }
}

