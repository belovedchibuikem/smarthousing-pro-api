<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Wallet::with(['user.member']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('user.member', function($q) use ($search) {
                $q->where('member_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function($u) use ($search) {
                      $u->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            // Wallet status filtering can be added here
        }

        $wallets = $query->withSum('transactions as total_deposits', 'amount', function($q) {
            $q->where('type', 'credit');
        })->withSum('transactions as total_withdrawals', 'amount', function($q) {
            $q->where('type', 'debit');
        })->paginate($request->get('per_page', 15));

        $data = $wallets->map(function($wallet) {
            $member = $wallet->user->member ?? null;
            return [
                'id' => $wallet->id,
                'member' => $member ? [
                    'id' => $member->id,
                    'name' => ($wallet->user->first_name ?? '') . ' ' . ($wallet->user->last_name ?? ''),
                    'member_id' => $member->member_id ?? $member->staff_id ?? '—',
                ] : null,
                'balance' => $wallet->balance ?? 0,
                'total_deposits' => $wallet->total_deposits ?? 0,
                'total_withdrawals' => abs($wallet->total_withdrawals ?? 0),
                'status' => 'active',
                'last_transaction' => $wallet->transactions()->latest()->first()?->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $wallets->currentPage(),
                'total' => $wallets->total(),
                'per_page' => $wallets->perPage(),
                'total_balance' => Wallet::sum('balance'),
            ]
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = WalletTransaction::with(['wallet.user.member']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('wallet.user.member', function($m) use ($search) {
                      $m->where('member_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type === 'deposit' ? 'credit' : 'debit');
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $transactions = $query->latest()->paginate($request->get('per_page', 15));

        $data = $transactions->map(function($tx) {
            $member = $tx->wallet->user->member ?? null;
            return [
                'id' => $tx->id,
                'member' => $member ? [
                    'name' => ($tx->wallet->user->first_name ?? '') . ' ' . ($tx->wallet->user->last_name ?? ''),
                    'member_id' => $member->member_id ?? $member->staff_id ?? '—',
                ] : null,
                'type' => $tx->type === 'credit' ? 'deposit' : 'withdrawal',
                'amount' => abs($tx->amount ?? 0),
                'method' => $tx->metadata['payment_method'] ?? 'N/A',
                'status' => $tx->status ?? 'completed',
                'date' => $tx->created_at,
                'reference' => $tx->reference ?? $tx->id,
            ];
        });

        return response()->json([
            'success' => true,
            'transactions' => [
                'data' => $data,
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $transactions = WalletTransaction::with(['wallet.user.member'])
            ->where('type', 'debit')
            ->where('status', 'pending')
            ->latest()
            ->get();

        $data = $transactions->map(function($tx) {
            $member = $tx->wallet->user->member ?? null;
            $metadata = $tx->metadata ?? [];
            return [
                'id' => $tx->id,
                'withdrawal_id' => $tx->id,
                'member' => $member ? [
                    'name' => ($tx->wallet->user->first_name ?? '') . ' ' . ($tx->wallet->user->last_name ?? ''),
                    'member_id' => $member->member_id ?? $member->staff_id ?? '—',
                ] : null,
                'amount' => abs($tx->amount ?? 0),
                'method' => $metadata['payment_method'] ?? 'Bank Transfer',
                'account_number' => $metadata['account_number'] ?? null,
                'bank_name' => $metadata['bank_name'] ?? null,
                'date' => $tx->created_at,
                'reference' => $tx->reference ?? $tx->id,
            ];
        });

        return response()->json([
            'success' => true,
            'transactions' => $data,
            'data' => $data,
        ]);
    }

    public function show(Request $request, string $walletId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = Wallet::with(['user.member', 'transactions'])->findOrFail($walletId);

        return response()->json([
            'success' => true,
            'data' => $wallet
        ]);
    }

    public function approveWithdrawal(Request $request, string $withdrawalId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $transaction = WalletTransaction::findOrFail($withdrawalId);

        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is not pending'
            ], 400);
        }

        $transaction->update([
            'status' => 'completed',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'approved_by' => $user->id,
                'approved_at' => now(),
                'notes' => $request->notes ?? 'Approved via admin panel',
            ])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal approved successfully',
            'data' => $transaction
        ]);
    }

    public function rejectWithdrawal(Request $request, string $withdrawalId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $transaction = WalletTransaction::findOrFail($withdrawalId);

        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is not pending'
            ], 400);
        }

        // Refund the amount back to wallet
        $wallet = $transaction->wallet;
        $wallet->increment('balance', abs($transaction->amount));

        $transaction->update([
            'status' => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'notes' => $request->notes ?? 'Rejected via admin panel',
            ])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal rejected successfully',
            'data' => $transaction
        ]);
    }
}

