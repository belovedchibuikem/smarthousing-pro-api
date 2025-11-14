<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\EquityWalletBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EquityContributionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EquityContribution::with(['member.user', 'plan']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('member', function($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        $contributions = $query->latest('created_at')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $contributions->items(),
            'pagination' => [
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
                'per_page' => $contributions->perPage(),
                'total' => $contributions->total(),
            ]
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $contribution = EquityContribution::with(['member.user', 'plan', 'approver'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contribution
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $contribution = EquityContribution::findOrFail($id);

            if ($contribution->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equity contribution is already approved'
                ], 400);
            }

            if ($contribution->status === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot approve a rejected contribution'
                ], 400);
            }

            $contribution->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'paid_at' => now(),
            ]);

            // Add to equity wallet
            $this->addToEquityWallet($contribution);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equity contribution approved successfully',
                'data' => $contribution->fresh()->load(['member.user', 'plan'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving equity contribution', [
                'error' => $e->getMessage(),
                'contribution_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve equity contribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $contribution = EquityContribution::findOrFail($id);

        if ($contribution->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Equity contribution is already rejected'
            ], 400);
        }

        if ($contribution->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject an approved contribution'
            ], 400);
        }

        $contribution->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Equity contribution rejected successfully',
            'data' => $contribution->fresh()->load(['member.user', 'plan'])
        ]);
    }

    private function addToEquityWallet(EquityContribution $contribution): void
    {
        $wallet = EquityWalletBalance::firstOrCreate(
            ['member_id' => $contribution->member_id],
            [
                'balance' => 0,
                'total_contributed' => 0,
                'total_used' => 0,
                'currency' => 'NGN',
                'is_active' => true,
            ]
        );

        $wallet->add(
            $contribution->amount,
            $contribution->id,
            'contribution',
            "Equity contribution approved - {$contribution->payment_reference}"
        );
    }
}

