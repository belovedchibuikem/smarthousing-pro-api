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
        $user =$request->user();

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

    public function show(Request $request,BlockchainTransaction $transaction): JsonResponse
    {
        $user = $request->user();

        // Check if user can view this transaction
        if (!$user->isAdmin() && $transaction->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction->load(['user']);

        return response()->json([
            'transaction' => new BlockchainTransactionResource($transaction)
        ]);
    }

    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = BlockchainTransaction::query();

        // Filter by user if not admin
        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $totalTransactions = $query->count();
        $pendingTransactions = (clone $query)->where('status', 'pending')->count();
        $confirmedTransactions = (clone $query)->where('status', 'confirmed')->count();
        $failedTransactions = (clone $query)->where('status', 'failed')->count();

        $totalVolume = (clone $query)->where('status', 'confirmed')->sum('amount');

        // Get blockchain network info (from settings or metadata)
        $networkInfo = [
            'network' => 'Ethereum', // This could come from tenant settings
            'network_type' => 'Mainnet',
            'last_sync' => now()->subMinutes(2)->diffForHumans(),
        ];

        return response()->json([
            'stats' => [
                'total_transactions' => $totalTransactions,
                'pending_transactions' => $pendingTransactions,
                'confirmed_transactions' => $confirmedTransactions,
                'failed_transactions' => $failedTransactions,
                'total_volume' => $totalVolume,
                'network_info' => $networkInfo,
            ]
        ]);
    }

    /**
     * Get property ownership records for authenticated user
     */
    public function getPropertyOwnership(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        // Get property interests with blockchain transactions
        $propertyInterests = \App\Models\Tenant\PropertyInterest::where('member_id', $member->id)
            ->where('status', 'approved')
            ->with(['property:id,title,type,location,price'])
            ->get();

        $ownershipRecords = [];

        foreach ($propertyInterests as $interest) {
            $property = $interest->property;
            if (!$property) continue;

            // Get blockchain transactions for this property
            $blockchainTx = BlockchainTransaction::where('user_id', $user->id)
                ->where('type', 'payment')
                ->whereJsonContains('metadata->property_id', $property->id)
                ->where('status', 'confirmed')
                ->orderBy('created_at', 'desc')
                ->first();

            // Calculate ownership percentage and amount paid
            $totalPaid = \App\Models\Tenant\PropertyPaymentTransaction::where('property_id', $property->id)
                ->where('member_id', $member->id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->sum('amount');

            $totalPropertyValue = (float) $property->price;
            $ownershipPercentage = $totalPropertyValue > 0 ? ($totalPaid / $totalPropertyValue) * 100 : 0;

            $ownershipRecords[] = [
                'property_id' => $property->id,
                'property_title' => $property->title,
                'property_type' => $property->type,
                'property_location' => $property->location,
                'property_price' => $totalPropertyValue,
                'ownership_percentage' => round($ownershipPercentage, 2),
                'amount_paid' => (float) $totalPaid,
                'blockchain_hash' => $blockchainTx?->hash,
                'certificate_date' => $blockchainTx?->confirmed_at?->toDateString() ?? $interest->created_at->toDateString(),
                'is_verified' => $blockchainTx !== null,
                'transactions' => BlockchainTransaction::where('user_id', $user->id)
                    ->where('type', 'payment')
                    ->whereJsonContains('metadata->property_id', $property->id)
                    ->orderBy('created_at', 'desc')
                    ->get(['id', 'hash', 'amount', 'status', 'confirmed_at', 'created_at'])
                    ->map(function ($tx) {
                        return [
                            'hash' => $tx->hash,
                            'amount' => (float) $tx->amount,
                            'status' => $tx->status,
                            'date' => $tx->created_at->toDateString(),
                            'confirmed_at' => $tx->confirmed_at?->toDateString(),
                        ];
                    }),
            ];
        }

        return response()->json([
            'success' => true,
            'ownership_records' => $ownershipRecords,
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
