<?php

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\InitializeSubscriptionRequest;
use App\Http\Resources\Subscriptions\SubscriptionResource;
use App\Models\Central\Package;
use App\Models\Central\Subscription;
use App\Models\Tenant\Payment;
use App\Services\Payment\PaystackService;
use App\Services\Payment\RemitaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected RemitaService $remitaService
    ) {}

    public function initialize(InitializeSubscriptionRequest $request): JsonResponse
    {
        $user = Auth::user();
        $package = Package::findOrFail($request->package_id);
        
        $reference = 'SUB_' . time() . '_' . Str::random(10);
        
        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'amount' => $package->price,
            'currency' => 'NGN',
            'payment_method' => $request->payment_method,
            'status' => 'pending',
            'description' => "Subscription to {$package->name}",
            'metadata' => [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'duration' => $package->billing_cycle ?? 'monthly',
            ],
        ]);

        $response = match($request->payment_method) {
            'paystack' => $this->initializePaystack($payment, $package),
            'remita' => $this->initializeRemita($payment, $package),
            'wallet' => $this->processWalletPayment($payment, $package),
            default => throw new \InvalidArgumentException('Unsupported payment method')
        };

        return response()->json($response);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:paystack,remita',
            'reference' => 'required|string'
        ]);

        $payment = Payment::where('reference', $request->reference)->firstOrFail();

        $response = match($request->provider) {
            'paystack' => $this->verifyPaystack($payment),
            'remita' => $this->verifyRemita($payment),
            default => throw new \InvalidArgumentException('Unsupported payment provider')
        };

        return response()->json($response);
    }

    public function callback(Request $request): JsonResponse
    {
        $reference = $request->get('reference');
        $status = $request->get('status', 'failed');

        $payment = Payment::where('reference', $reference)->firstOrFail();
        
        if ($status === 'success') {
            $subscription = $this->createSubscription($payment);
            
            // Subscription created successfully
            // You can add notification logic here if needed
        } else {
            // Notify super admins about subscription payment failure
            $metadata = $payment->metadata ?? [];
            if (isset($metadata['package_id'])) {
                $package = Package::find($metadata['package_id']);
                if ($package) {
                    // Get tenant from user
                    $user = $payment->user;
                    if ($user && $user->member) {
                        // This is a tenant subscription, but we need to find the tenant
                        // For now, we'll just log the failure
                        // In a real implementation, you'd need to track tenant_id in payment metadata
                    }
                }
            }
        }
        
        $payment->update([
            'status' => $status === 'success' ? 'completed' : 'failed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => $status === 'success',
            'message' => $status === 'success' ? 'Subscription successful' : 'Subscription failed',
            'reference' => $reference
        ]);
    }

    public function packages(): JsonResponse
    {
        $packages = Package::where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();

        return response()->json([
            'packages' => $packages->map(function ($package) {
                // Calculate duration in days from billing_cycle
                $duration = match($package->billing_cycle ?? 'monthly') {
                    'weekly' => 7,
                    'monthly' => 30,
                    'quarterly' => 90,
                    'yearly' => 365,
                    default => 30
                };
                
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'slug' => $package->slug,
                    'description' => $package->description,
                    'price' => (float) $package->price,
                    'billing_cycle' => $package->billing_cycle ?? 'monthly',
                    'duration' => $duration,
                    'duration_days' => $duration,
                    'trial_days' => $package->trial_days ?? 0,
                    'features' => $package->limits ?? [],
                    'is_popular' => $package->is_featured ?? false,
                    'is_active' => $package->is_active,
                ];
            })
        ]);
    }

    public function current(): JsonResponse
    {
        try {
            $tenant = tenant();
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            $subscription = Subscription::where('tenant_id', $tenant->id)
                ->with('package')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'subscription' => null,
                    'message' => 'No active subscription'
                ]);
            }

            return response()->json([
                'subscription' => [
                    'id' => $subscription->id,
                    'package_id' => $subscription->package_id,
                    'package_name' => $subscription->package->name ?? 'Unknown',
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at?->toISOString(),
                    'ends_at' => $subscription->ends_at?->toISOString(),
                    'amount' => (float) $subscription->amount,
                    'payment_reference' => $subscription->payment_reference,
                    'days_remaining' => $subscription->ends_at ? max(0, now()->diffInDays($subscription->ends_at, false)) : 0,
                    'is_active' => $subscription->isActive(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function history(): JsonResponse
    {
        try {
            $tenant = tenant();
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            $subscriptions = Subscription::where('tenant_id', $tenant->id)
                ->with('package')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($subscription) {
                    return [
                        'id' => $subscription->id,
                        'package_name' => $subscription->package->name ?? 'Unknown',
                        'amount' => (float) $subscription->amount,
                        'status' => $subscription->status,
                        'starts_at' => $subscription->starts_at?->format('Y-m-d'),
                        'ends_at' => $subscription->ends_at?->format('Y-m-d'),
                        'payment_reference' => $subscription->payment_reference,
                        'created_at' => $subscription->created_at?->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'subscriptions' => $subscriptions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch subscription history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function paymentMethods(): JsonResponse
    {
        // Get available payment methods from central database
        // These are configured payment gateways
        $methods = [
            [
                'id' => 'paystack',
                'name' => 'Paystack',
                'description' => 'Pay with card, bank transfer, or USSD',
                'icon' => 'credit-card',
                'is_enabled' => true,
            ],
            [
                'id' => 'remita',
                'name' => 'Remita',
                'description' => 'Pay with Remita payment gateway',
                'icon' => 'bank',
                'is_enabled' => true,
            ],
            [
                'id' => 'wallet',
                'name' => 'Wallet',
                'description' => 'Pay from your wallet balance',
                'icon' => 'wallet',
                'is_enabled' => true,
            ],
        ];

        return response()->json([
            'payment_methods' => $methods
        ]);
    }

    private function initializePaystack(Payment $payment, Package $package): array
    {
        $response = $this->paystackService->initialize([
            'amount' => $payment->amount * 100, // Convert to kobo
            'email' => Auth::user()->email,
            'reference' => $payment->reference,
            'callback_url' => config('app.url') . '/api/subscriptions/callback',
        ]);

        $payment->update([
            'gateway_reference' => $response['data']['reference'],
            'gateway_url' => $response['data']['authorization_url'],
        ]);

        return [
            'success' => true,
            'paymentUrl' => $response['data']['authorization_url'],
            'reference' => $payment->reference
        ];
    }

    private function initializeRemita(Payment $payment, Package $package): array
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
            'rrr' => $response['rrr'],
            'paymentUrl' => $response['payment_url']
        ];
    }

    private function processWalletPayment(Payment $payment, Package $package): array
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

        $this->createSubscription($payment);

        return [
            'success' => true,
            'message' => 'Subscription successful from wallet'
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
            
            $this->createSubscription($payment);
        }

        return [
            'success' => $response['status'],
            'message' => $response['status'] ? 'Subscription successful' : 'Subscription failed'
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
            
            $this->createSubscription($payment);
        }

        return [
            'success' => $response['status'] === 'success',
            'message' => $response['status'] === 'success' ? 'Subscription successful' : 'Subscription failed'
        ];
    }

    private function createSubscription(Payment $payment): ?Subscription
    {
        $package = Package::findOrFail($payment->metadata['package_id']);
        
        // Calculate duration in days from billing_cycle
        $durationDays = match($package->billing_cycle ?? 'monthly') {
            'weekly' => 7,
            'monthly' => 30,
            'quarterly' => 90,
            'yearly' => 365,
            default => 30
        };
        
        return Subscription::create([
            'tenant_id' => tenant('id'),
            'package_id' => $package->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays($durationDays),
            'amount' => $payment->amount,
            'payment_reference' => $payment->reference,
        ]);
    }
}
