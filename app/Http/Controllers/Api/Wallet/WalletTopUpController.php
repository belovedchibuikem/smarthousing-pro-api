<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\WalletTopUpRequest;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use App\Models\Tenant\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletTopUpController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function topUp(WalletTopUpRequest $request): JsonResponse
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

            $wallet = $member->wallet;
            if (!$wallet) {
                return response()->json([
                    'message' => 'Wallet not found'
                ], 404);
            }

            // Create payment record
            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'currency' => 'NGN',
                'type' => 'wallet_topup',
                'status' => 'pending',
                'reference' => 'TOPUP-' . time() . '-' . rand(1000, 9999),
                'description' => "Wallet top-up of ₦" . number_format($request->amount),
                'metadata' => [
                    'payment_method' => $request->payment_method,
                    'wallet_id' => $wallet->id,
                ],
            ]);

            // Initialize payment based on method
            $paymentResult = $this->initializePayment($payment, $request->payment_method);

            if (!$paymentResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'],
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet top-up initiated successfully',
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                ],
                'payment_data' => $paymentResult['data'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Wallet top-up failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyTopUp(string $reference): JsonResponse
    {
        try {
            $payment = Payment::where('reference', $reference)
                ->where('type', 'wallet_topup')
                ->first();

            if (!$payment) {
                return response()->json([
                    'message' => 'Payment not found'
                ], 404);
            }

            // Verify payment with gateway
            $verificationResult = $this->paymentService->verifyPayment($payment);

            if ($verificationResult['success']) {
                DB::beginTransaction();

                // Update payment status
                $payment->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Credit wallet
                $wallet = Wallet::find($payment->metadata['wallet_id']);
                $wallet->increment('balance', $payment->amount);

                // Create wallet transaction
                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'topup',
                    'amount' => $payment->amount,
                    'balance_after' => $wallet->fresh()->balance,
                    'reference' => $payment->reference,
                    'description' => 'Wallet top-up',
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'payment_method' => $payment->metadata['payment_method'],
                    ],
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Wallet top-up successful',
                    'wallet_balance' => $wallet->fresh()->balance,
                ]);
            } else {
                $payment->update(['status' => 'failed']);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'error' => $verificationResult['message'],
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Top-up verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTopUpHistory(): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || !$member->wallet) {
            return response()->json([
                'message' => 'Wallet not found'
            ], 404);
        }

        $transactions = $member->wallet->transactions()
            ->where('type', 'topup')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

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

    private function initializePayment(Payment $payment, string $method): array
    {
        switch ($method) {
            case 'card':
                return $this->paymentService->initializeCardPayment($payment);
            case 'bank_transfer':
                return $this->paymentService->initializeBankTransfer($payment);
            case 'wallet':
                return $this->paymentService->initializeWalletPayment($payment);
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported payment method',
                ];
        }
    }
}
