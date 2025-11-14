<?php

namespace App\Http\Controllers\Api\Blockchain;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blockchain\BlockchainTransactionResource;
use App\Models\Tenant\BlockchainTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockchainLedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = BlockchainTransaction::query();

        // Filter by user if not admin
        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Filter by transaction type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by hash or reference
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('hash', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $transactions = $query->with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'transactions' => BlockchainTransactionResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    public function show(BlockchainTransaction $transaction): JsonResponse
    {
        $user = Auth::user();

        // Check if user can view this transaction
        if (!$user->isAdmin() && $transaction->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction->load(['user']);

        return response()->json([
            'transaction' => new BlockchainTransactionResource($transaction)
        ]);
    }

    public function getStats(): JsonResponse
    {
        $user = Auth::user();

        $query = BlockchainTransaction::query();

        // Filter by user if not admin
        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $totalTransactions = $query->count();
        $pendingTransactions = $query->where('status', 'pending')->count();
        $confirmedTransactions = $query->where('status', 'confirmed')->count();
        $failedTransactions = $query->where('status', 'failed')->count();

        $totalVolume = $query->where('status', 'confirmed')->sum('amount');

        return response()->json([
            'stats' => [
                'total_transactions' => $totalTransactions,
                'pending_transactions' => $pendingTransactions,
                'confirmed_transactions' => $confirmedTransactions,
                'failed_transactions' => $failedTransactions,
                'total_volume' => $totalVolume,
            ]
        ]);
    }

    public function verifyTransaction(string $hash): JsonResponse
    {
        $transaction = BlockchainTransaction::where('hash', $hash)->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found'
            ], 404);
        }

        // Simulate blockchain verification
        $isValid = $this->verifyBlockchainTransaction($transaction);

        if ($isValid) {
            $transaction->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        } else {
            $transaction->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);
        }

        return response()->json([
            'valid' => $isValid,
            'transaction' => new BlockchainTransactionResource($transaction)
        ]);
    }

    private function verifyBlockchainTransaction(BlockchainTransaction $transaction): bool
    {
        // This would typically verify against a real blockchain
        // For now, we'll simulate verification
        return rand(0, 1) === 1;
    }
}
