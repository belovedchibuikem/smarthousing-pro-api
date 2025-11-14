<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaystackDedicatedAccount;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function paystack(Request $request): Response
    {
        try {
            $payload = $request->all();
            $event = $payload['event'] ?? null;
            $data = $payload['data'] ?? [];
            $gatewayReference = $data['reference'] ?? null;

            if ($event === 'charge.success' && $gatewayReference) {
                $payment = Payment::where('gateway_reference', $gatewayReference)->first();
                if ($payment && $payment->status !== 'completed') {
                    $payment->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'gateway_response' => $payload,
                    ]);
                    $this->creditWalletIfFunding($payment);
                }
            }

            return response('ok', 200);
        } catch (\Throwable $e) {
            Log::error('Paystack webhook error', ['error' => $e->getMessage()]);
            return response('error', 500);
        }
    }

    public function stripe(Request $request): Response
    {
        try {
            $payload = $request->all();
            $type = $payload['type'] ?? null;
            $object = $payload['data']['object'] ?? [];
            $gatewayReference = $object['id'] ?? null;

            if (in_array($type, ['payment_intent.succeeded', 'charge.succeeded'], true) && $gatewayReference) {
                $payment = Payment::where('gateway_reference', $gatewayReference)->first();
                if ($payment && $payment->status !== 'completed') {
                    $payment->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'gateway_response' => $payload,
                    ]);
                    $this->creditWalletIfFunding($payment);
                }
            }

            return response('ok', 200);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
            return response('error', 500);
        }
    }

    public function remita(Request $request): Response
    {
        try {
            $payload = $request->all();
            $rrr = $payload['RRR'] ?? $payload['rrr'] ?? null;
            $status = strtolower($payload['status'] ?? '');

            if ($rrr && in_array($status, ['success', 'successful'], true)) {
                $payment = Payment::where('gateway_reference', $rrr)->first();
                if ($payment && $payment->status !== 'completed') {
                    $payment->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'gateway_response' => $payload,
                    ]);
                    $this->creditWalletIfFunding($payment);
                }
            }

            return response('ok', 200);
        } catch (\Throwable $e) {
            Log::error('Remita webhook error', ['error' => $e->getMessage()]);
            return response('error', 500);
        }
    }

    private function creditWalletIfFunding(Payment $payment): void
    {
        try {
            $isFunding = ($payment->description && stripos($payment->description, 'wallet') !== false)
                || ($payment->metadata['purpose'] ?? null) === 'wallet_funding';
            if (!$isFunding) {
                return;
            }

            $wallet = Wallet::where('user_id', $payment->user_id)->first();
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id' => $payment->user_id,
                    'balance' => 0,
                    'currency' => $payment->currency ?? 'NGN',
                ]);
            }

            // Avoid double-credit: ensure we haven't already recorded a successful funding for this reference
            $alreadyCredited = WalletTransaction::where('payment_reference', $payment->reference)
                ->where('type', 'credit')
                ->exists();
            if ($alreadyCredited) {
                return;
            }

            $wallet->deposit((float) $payment->amount);
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $payment->amount,
                'status' => 'success',
                'payment_method' => $payment->payment_method,
                'payment_reference' => $payment->reference,
                'description' => 'Wallet funding via ' . $payment->payment_method,
                'balance_after' => $wallet->fresh()->balance,
                'metadata' => [
                    'gateway_reference' => $payment->gateway_reference,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Wallet crediting failed', ['error' => $e->getMessage(), 'payment_id' => $payment->id ?? null]);
        }
    }
}
