<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\WalletTransferRequest;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletTransferController extends Controller
{
    public function transfer(WalletTransferRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $member = $user->member;

            if (!$member) {
                return response()->json([
                    'message' => 'Member profile not found'
                ], 404);
            }

            $senderWallet = $member->wallet;
            if (!$senderWallet) {
                return response()->json([
                    'message' => 'Wallet not found'
                ], 404);
            }

            // Check if sender has sufficient balance
            if ($senderWallet->balance < $request->amount) {
                return response()->json([
                    'message' => 'Insufficient wallet balance'
                ], 400);
            }

            // Find recipient by member number or email
            $recipient = null;
            if ($request->recipient_type === 'member_number') {
                $recipient = Member::where('member_number', $request->recipient_identifier)->first();
            } elseif ($request->recipient_type === 'email') {
                $recipient = Member::whereHas('user', function($query) use ($request) {
                    $query->where('email', $request->recipient_identifier);
                })->first();
            }

            if (!$recipient) {
                return response()->json([
                    'message' => 'Recipient not found'
                ], 404);
            }

            $recipientWallet = $recipient->wallet;
            if (!$recipientWallet) {
                return response()->json([
                    'message' => 'Recipient wallet not found'
                ], 404);
            }

            // Perform transfer
            $senderWallet->decrement('balance', $request->amount);
            $recipientWallet->increment('balance', $request->amount);

            // Create transaction records
            $transactionId = 'TXN-' . time() . '-' . rand(1000, 9999);

            // Sender transaction (debit)
            WalletTransaction::create([
                'wallet_id' => $senderWallet->id,
                'type' => 'transfer_out',
                'amount' => $request->amount,
                'balance_after' => $senderWallet->fresh()->balance,
                'reference' => $transactionId,
                'description' => "Transfer to {$recipient->member_number}",
                'metadata' => [
                    'recipient_id' => $recipient->id,
                    'recipient_member_number' => $recipient->member_number,
                    'note' => $request->note,
                ],
            ]);

            // Recipient transaction (credit)
            WalletTransaction::create([
                'wallet_id' => $recipientWallet->id,
                'type' => 'transfer_in',
                'amount' => $request->amount,
                'balance_after' => $recipientWallet->fresh()->balance,
                'reference' => $transactionId,
                'description' => "Transfer from {$member->member_number}",
                'metadata' => [
                    'sender_id' => $member->id,
                    'sender_member_number' => $member->member_number,
                    'note' => $request->note,
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'transaction_id' => $transactionId,
                'amount' => $request->amount,
                'recipient' => [
                    'member_number' => $recipient->member_number,
                    'name' => $recipient->user->first_name . ' ' . $recipient->user->last_name,
                ],
                'sender_balance' => $senderWallet->fresh()->balance,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Transfer failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTransferHistory(Request $request): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || !$member->wallet) {
            return response()->json([
                'message' => 'Wallet not found'
            ], 404);
        }

        $transactions = $member->wallet->transactions()
            ->whereIn('type', ['transfer_in', 'transfer_out'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }
}
