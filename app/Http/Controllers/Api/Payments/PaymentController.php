<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InitializePaymentRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Models\Tenant\Payment;
use App\Services\Payment\PaystackService;
use App\Services\Payment\RemitaService;
use App\Services\Payment\StripeService;
use App\Services\Tenant\TenantPaymentService;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected RemitaService $remitaService,
        protected StripeService $stripeService,
        protected NotificationService $notificationService
    ) {}

    public function initialize(InitializePaymentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $reference = 'PAY_' . time() . '_' . Str::random(10);
        
        $payment = Payment::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'NGN',
            'payment_method' => $request->payment_method,
            'status' => 'pending',
            'description' => $request->description,
            'metadata' => $request->metadata ?? [],
        ]);

        $response = match($request->payment_method) {
            'paystack' => $this->initializePaystack($payment, $request),
            'remita' => $this->initializeRemita($payment, $request),
            'stripe' => $this->initializeStripe($payment, $request),
            'wallet' => $this->processWalletPayment($payment, $request),
            'bank_transfer' => $this->initializeBankTransfer($payment, $request),
            default => throw new \InvalidArgumentException('Unsupported payment method')
        };

        return response()->json($response);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:paystack,remita,stripe',
            'reference' => 'required|string'
        ]);

        $payment = Payment::where('reference', $request->reference)->firstOrFail();

        $response = match($request->provider) {
            'paystack' => $this->verifyPaystack($payment),
            'remita' => $this->verifyRemita($payment),
            'stripe' => $this->verifyStripe($payment),
            default => throw new \InvalidArgumentException('Unsupported payment provider')
        };

        return response()->json($response);
    }

    public function callback(Request $request): JsonResponse
    {
        $reference = $request->get('reference');
        $status = $request->get('status', 'failed');

        $payment = Payment::where('reference', $reference)->firstOrFail();
        
        // For Paystack, verify the payment first
        if ($payment->payment_method === 'paystack' && $payment->gateway_reference) {
            try {
                $verification = $this->verifyPaystack($payment);
                $status = $verification['success'] ? 'success' : 'failed';
            } catch (\Exception $e) {
                \Log::error('Paystack verification failed in callback: ' . $e->getMessage());
                $status = 'failed';
            }
        }
        
        $payment->update([
            'status' => $status === 'success' ? 'completed' : 'failed',
            'completed_at' => now(),
        ]);

        // Handle wallet funding if payment is successful
        if ($status === 'success' && isset($payment->metadata['type']) && $payment->metadata['type'] === 'wallet_funding') {
            try {
                $wallet = \App\Models\Tenant\Wallet::find($payment->metadata['wallet_id']);
                if ($wallet) {
                    $wallet->deposit($payment->amount);

                    // Update wallet transaction
                    $walletTransaction = \App\Models\Tenant\WalletTransaction::where('payment_reference', $payment->reference)
                        ->where('wallet_id', $wallet->id)
                        ->first();

                    if ($walletTransaction) {
                        $walletTransaction->update([
                            'status' => 'completed',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to credit wallet after payment callback: ' . $e->getMessage());
            }
        }

        if ($status === 'success') {
            $paymentType = $payment->metadata['type'] ?? null;

            if ($paymentType === 'contribution') {
                app(TenantPaymentService::class)->finalizeContributionPayment($payment);
            } elseif ($paymentType === 'equity_contribution') {
                app(TenantPaymentService::class)->finalizeEquityContributionPayment($payment);
            }
        }

        // Notify admins about payment failure
        if ($status !== 'success' && $payment->user) {
            $member = $payment->user->member;
            if ($member) {
                $memberName = $member->first_name . ' ' . $member->last_name;
                $this->notificationService->notifyAdminsPaymentFailure(
                    $payment->id,
                    $memberName,
                    $payment->amount,
                    'Payment callback returned failed status'
                );
            }
        }

        // Notify user about payment success
        if ($status === 'success' && $payment->user) {
            $this->notificationService->notifyPaymentReceived(
                $payment->user,
                $payment->id,
                ['amount' => $payment->amount, 'reference' => $reference]
            );
        }

        return response()->json([
            'success' => $status === 'success',
            'message' => $status === 'success' ? 'Payment successful' : 'Payment failed',
            'reference' => $reference
        ]);
    }

    private function initializePaystack(Payment $payment, InitializePaymentRequest $request): array
    {
        $response = $this->paystackService->initialize([
            'amount' => $payment->amount * 100, // Convert to kobo
            'email' => Auth::user()->email,
            'reference' => $payment->reference,
            'callback_url' => config('app.url') . '/api/payments/callback',
        ]);

        $payment->update([
            'gateway_reference' => $response['data']['reference'],
            'gateway_url' => $response['data']['authorization_url'],
        ]);

        return [
            'success' => true,
            'message' => 'Payment initialized successfully',
            'data' => [
                'reference' => $payment->reference,
                'status' => 'pending',
                'payment_url' => $response['data']['authorization_url']
            ]
        ];
    }

    private function initializeRemita(Payment $payment, InitializePaymentRequest $request): array
    {
        $response = $this->remitaService->initialize([
            'amount' => $payment->amount,
            'customer_email' => Auth::user()->email,
            'customer_name' => Auth::user()->full_name,
            'description' => $payment->description,
        ]);

        $payment->update([
            'gateway_reference' => $response['rrr'],
            'gateway_url' => $response['payment_url'],
        ]);

        return [
            'success' => true,
            'message' => 'Payment initialized successfully',
            'data' => [
                'rrr' => $response['rrr'],
                'status' => 'pending'
            ]
        ];
    }

    private function initializeStripe(Payment $payment, InitializePaymentRequest $request): array
    {
        $response = $this->stripeService->createPaymentIntent([
            'amount' => $payment->amount * 100, // Convert to cents
            'currency' => strtolower($payment->currency),
            'metadata' => [
                'reference' => $payment->reference,
                'user_id' => $payment->user_id,
            ]
        ]);

        $payment->update([
            'gateway_reference' => $response['id'],
        ]);

        return [
            'success' => true,
            'message' => 'Payment initialized successfully',
            'data' => [
                'reference' => $payment->reference,
                'status' => 'pending',
                'client_secret' => $response['client_secret']
            ]
        ];
    }

    private function processWalletPayment(Payment $payment, InitializePaymentRequest $request): array
    {
        $user = Auth::user();
        $wallet = $user->wallet;

        if (!$wallet || $wallet->balance < $payment->amount) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance'
            ];
        }

        $wallet->withdraw($payment->amount);
        $payment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Payment successful from wallet',
            'data' => [
                'reference' => $payment->reference,
                'status' => 'completed'
            ]
        ];
    }

    private function initializeBankTransfer(Payment $payment, InitializePaymentRequest $request): array
    {
        $payment->update([
            'status' => 'pending',
            'metadata' => array_merge($payment->metadata, [
                'bank_details' => [
                    'bank_name' => 'First Bank of Nigeria',
                    'account_number' => '1234567890',
                    'account_name' => 'FRSC Housing Cooperative'
                ]
            ])
        ]);

        return [
            'success' => true,
            'message' => 'Please complete bank transfer and upload evidence',
            'data' => [
                'reference' => $payment->reference,
                'status' => 'pending',
                'bank_details' => [
                    'bank_name' => 'First Bank of Nigeria',
                    'account_number' => '1234567890',
                    'account_name' => 'FRSC Housing Cooperative'
                ]
            ]
        ];
    }

    private function verifyPaystack(Payment $payment): array
    {
        $response = $this->paystackService->verify($payment->gateway_reference);
        
        if ($response['status']) {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gateway_response' => $response,
            ]);
        }

        return [
            'success' => $response['status'],
            'data' => [
                'status' => $response['status'] ? 'success' : 'failed',
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $response['data']['paid_at'] ?? null,
                'channel' => $response['data']['channel'] ?? null,
            ]
        ];
    }

    private function verifyRemita(Payment $payment): array
    {
        $response = $this->remitaService->verify($payment->gateway_reference);
        
        if ($response['status'] === 'success') {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gateway_response' => $response,
            ]);
        }

        return [
            'success' => $response['status'] === 'success',
            'data' => [
                'status' => $response['status'],
                'rrr' => $payment->gateway_reference,
                'amount' => $payment->amount,
                'transaction_time' => $response['transaction_time'] ?? null,
            ]
        ];
    }

    private function verifyStripe(Payment $payment): array
    {
        $response = $this->stripeService->verify($payment->gateway_reference);
        
        if ($response['status'] === 'succeeded') {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gateway_response' => $response,
            ]);
        }

        return [
            'success' => $response['status'] === 'succeeded',
            'data' => [
                'status' => $response['status'],
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]
        ];
    }
}
