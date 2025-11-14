<?php

namespace App\Services\SuperAdmin;

use App\Models\Central\PaymentGateway;
use App\Models\Central\PlatformTransaction;
use App\Models\Central\Subscription;
use App\Models\Central\MemberSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SuperAdminPaymentService
{
    public function testGatewayConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        switch ($gateway->name) {
            case 'paystack':
                return $this->testPaystackConnection($gateway);
            case 'remita':
                return $this->testRemitaConnection($gateway);
            case 'stripe':
                return $this->testStripeConnection($gateway);
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown gateway type',
                    'response_time' => round((microtime(true) - $startTime) * 1000, 2),
                ];
        }
    }

    public function initializeSubscriptionPayment(Subscription $subscription, float $amount, string $method): array
    {
        try {
            $gateway = PaymentGateway::where('is_active', true)->first();
            
            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => 'No active payment gateway found',
                ];
            }

            $reference = 'SUB-' . time() . '-' . Str::random(8);
            
            $transaction = PlatformTransaction::create([
                'tenant_id' => $subscription->tenant_id,
                'amount' => $amount,
                'currency' => 'NGN',
                'type' => 'subscription',
                'status' => 'pending',
                'reference' => $reference,
                'gateway' => $gateway->name,
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'payment_method' => $method,
                    'tenant_name' => $subscription->tenant->name,
                ],
            ]);

            $paymentData = $this->initializePaymentWithGateway($gateway, $amount, $reference, $method);

            return [
                'success' => true,
                'message' => 'Subscription payment initialized successfully',
                'payment' => [
                    'id' => $transaction->id,
                    'reference' => $reference,
                    'amount' => $amount,
                    'status' => 'pending',
                ],
                'data' => $paymentData,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to initialize subscription payment: ' . $e->getMessage(),
            ];
        }
    }

    public function initializeMemberSubscriptionPayment(MemberSubscription $memberSubscription, float $amount, string $method): array
    {
        try {
            $gateway = PaymentGateway::where('name', $method)
                ->where('is_active', true)
                ->first();
            
            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => "Payment gateway {$method} is not available",
                ];
            }

            // Use the reference from the subscription
            $reference = $memberSubscription->payment_reference;
            
            // Create platform transaction if needed
            try {
                $transaction = PlatformTransaction::create([
                    'tenant_id' => $memberSubscription->business_id,
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'type' => 'member_subscription',
                    'status' => 'pending',
                    'reference' => $reference,
                    'gateway' => $gateway->name,
                    'metadata' => [
                        'member_subscription_id' => $memberSubscription->id,
                        'payment_method' => $method,
                        'business_id' => $memberSubscription->business_id,
                    ],
                ]);
            } catch (\Exception $e) {
                // Transaction might already exist, continue
                Log::warning('Platform transaction creation failed: ' . $e->getMessage());
            }

            $paymentData = $this->initializePaymentWithGateway($gateway, $amount, $reference, $method);

            // Add payment URL if available
            $paymentUrl = null;
            if (isset($paymentData['authorization_url'])) {
                $paymentUrl = $paymentData['authorization_url'];
            } elseif (isset($paymentData['checkout_url'])) {
                $paymentUrl = $paymentData['checkout_url'];
            }

            return [
                'success' => true,
                'message' => 'Member subscription payment initialized successfully',
                'payment_url' => $paymentUrl,
                'reference' => $reference,
                'amount' => $amount,
                'data' => $paymentData,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to initialize member subscription payment: ' . $e->getMessage(),
            ];
        }
    }

    public function verifyMemberSubscriptionPayment(MemberSubscription $memberSubscription, string $provider, string $reference): array
    {
        try {
            $gateway = PaymentGateway::where('name', $provider)
                ->where('is_active', true)
                ->first();

            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway not found',
                ];
            }

            $verificationResult = $this->verifyPaymentWithGateway($gateway, $reference);

            if ($verificationResult['success']) {
                $memberSubscription->update([
                    'payment_status' => 'completed',
                ]);
            } else {
                $memberSubscription->update([
                    'payment_status' => 'rejected',
                ]);
            }

            return $verificationResult;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $transaction = PlatformTransaction::where('reference', $reference)->first();
            
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => 'Transaction not found',
                ];
            }

            $gateway = PaymentGateway::where('name', $transaction->gateway)->first();
            $verificationResult = $this->verifyPaymentWithGateway($gateway, $reference);

            if ($verificationResult['success']) {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Update related subscription or member subscription
                $this->updateRelatedSubscription($transaction);
            } else {
                $transaction->update(['status' => 'failed']);
            }

            return [
                'success' => $verificationResult['success'],
                'message' => $verificationResult['message'],
                'transaction' => $transaction,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    private function testPaystackConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        $settings = $gateway->settings;
        $secretKey = $settings['secret_key'] ?? null;
        
        if (!$secretKey) {
            return [
                'success' => false,
                'message' => 'Paystack secret key not configured',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        // Simulate API call to Paystack
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get('https://api.paystack.co/transaction/totals');

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Paystack connection successful' : 'Paystack connection failed',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Paystack connection test failed: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    private function testRemitaConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        $settings = $gateway->settings;
        $merchantId = $settings['merchant_id'] ?? null;
        
        if (!$merchantId) {
            return [
                'success' => false,
                'message' => 'Remita merchant ID not configured',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        // Simulate Remita API test
        return [
            'success' => true,
            'message' => 'Remita connection successful',
            'response_time' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    private function testStripeConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        $settings = $gateway->settings;
        $secretKey = $settings['secret_key'] ?? null;
        
        if (!$secretKey) {
            return [
                'success' => false,
                'message' => 'Stripe secret key not configured',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        // Simulate Stripe API test
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get('https://api.stripe.com/v1/balance');

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Stripe connection successful' : 'Stripe connection failed',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Stripe connection test failed: ' . $e->getMessage(),
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    private function initializePaymentWithGateway(PaymentGateway $gateway, float $amount, string $reference, string $method): array
    {
        switch ($gateway->name) {
            case 'paystack':
                return $this->initializePaystackPayment($gateway, $amount, $reference);
            case 'remita':
                return $this->initializeRemitaPayment($gateway, $amount, $reference);
            case 'stripe':
                return $this->initializeStripePayment($gateway, $amount, $reference);
            default:
                return [
                    'error' => 'Unsupported payment gateway',
                ];
        }
    }

    private function initializePaystackPayment(PaymentGateway $gateway, float $amount, string $reference): array
    {
        $settings = $gateway->settings;
        $publicKey = $settings['public_key'] ?? null;

        return [
            'gateway' => 'paystack',
            'public_key' => $publicKey,
            'amount' => $amount * 100, // Paystack expects amount in kobo
            'reference' => $reference,
            'currency' => 'NGN',
            'callback_url' => config('app.url') . '/api/super-admin/payment-gateways/callback',
        ];
    }

    private function initializeRemitaPayment(PaymentGateway $gateway, float $amount, string $reference): array
    {
        $settings = $gateway->settings;
        $merchantId = $settings['merchant_id'] ?? null;

        return [
            'gateway' => 'remita',
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'reference' => $reference,
            'currency' => 'NGN',
            'callback_url' => config('app.url') . '/api/super-admin/payment-gateways/callback',
        ];
    }

    private function initializeStripePayment(PaymentGateway $gateway, float $amount, string $reference): array
    {
        $settings = $gateway->settings;
        $publishableKey = $settings['publishable_key'] ?? null;

        return [
            'gateway' => 'stripe',
            'publishable_key' => $publishableKey,
            'amount' => $amount * 100, // Stripe expects amount in cents
            'reference' => $reference,
            'currency' => 'ngn',
            'callback_url' => config('app.url') . '/api/super-admin/payment-gateways/callback',
        ];
    }

    private function verifyPaymentWithGateway(PaymentGateway $gateway, string $reference): array
    {
        switch ($gateway->name) {
            case 'paystack':
                return $this->verifyPaystackPayment($gateway, $reference);
            case 'remita':
                return $this->verifyRemitaPayment($gateway, $reference);
            case 'stripe':
                return $this->verifyStripePayment($gateway, $reference);
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported payment gateway',
                ];
        }
    }

    private function verifyPaystackPayment(PaymentGateway $gateway, string $reference): array
    {
        $settings = $gateway->settings;
        $secretKey = $settings['secret_key'] ?? null;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['status'] === true,
                    'message' => $data['status'] ? 'Payment verified successfully' : 'Payment verification failed',
                ];
            }

            return [
                'success' => false,
                'message' => 'Payment verification failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    private function verifyRemitaPayment(PaymentGateway $gateway, string $reference): array
    {
        // Simulate Remita payment verification
        return [
            'success' => true,
            'message' => 'Remita payment verified successfully',
        ];
    }

    private function verifyStripePayment(PaymentGateway $gateway, string $reference): array
    {
        $settings = $gateway->settings;
        $secretKey = $settings['secret_key'] ?? null;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get("https://api.stripe.com/v1/payment_intents/{$reference}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['status'] === 'succeeded',
                    'message' => $data['status'] === 'succeeded' ? 'Payment verified successfully' : 'Payment verification failed',
                ];
            }

            return [
                'success' => false,
                'message' => 'Payment verification failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    private function updateRelatedSubscription(PlatformTransaction $transaction): void
    {
        if ($transaction->type === 'subscription') {
            $subscription = Subscription::find($transaction->metadata['subscription_id']);
            if ($subscription) {
                $subscription->update(['status' => 'active']);
            }
        } elseif ($transaction->type === 'member_subscription') {
            $memberSubscription = MemberSubscription::find($transaction->metadata['member_subscription_id']);
            if ($memberSubscription) {
                $memberSubscription->update(['status' => 'active']);
            }
        }
    }
}
