<?php

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\InitializeSubscriptionRequest;
use App\Http\Resources\Subscriptions\SubscriptionResource;
use App\Models\Central\Package;
use App\Models\Central\Subscription;
use App\Models\Central\PlatformTransaction;
use App\Models\Tenant\Payment;
use App\Services\Payment\PaystackService;
use App\Services\Payment\RemitaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected RemitaService $remitaService
    ) {}

    public function initialize(InitializeSubscriptionRequest $request): JsonResponse
    {
        
        try {
            $user = $request->user();
            $package = Package::findOrFail($request->package_id);
            
            // For manual and gateway payments, use TenantPaymentService
            if ($request->payment_method === 'manual' || in_array($request->payment_method, ['paystack', 'remita', 'stripe'])) {
                $paymentService = app(\App\Services\Tenant\TenantPaymentService::class);
                
                // Initialize payment using the service
                Log::info('Initializing payment for subscription', [
                    'user_id' => $user->id,
                    'amount' => $package->price,
                    'payment_method' => $request->payment_method,
                    'description' => "Tenant subscription payment for {$package->name}",
                    'payment_type' => 'subscription',
                    'currency' => 'NGN',
                    'notes' => $request->notes ?? null,
                ]);
                
                $paymentData = $paymentService->initializePayment([
                    'user_id' => $user->id,
                    'amount' => $package->price,
                    'payment_method' => $request->payment_method,
                    'description' => "Tenant subscription payment for {$package->name}",
                    'payment_type' => 'subscription',
                    'currency' => 'NGN',
                    'notes' => $request->notes ?? null,
                    'payer_name' => $request->payer_name ?? null,
                    'payer_phone' => $request->payer_phone ?? null,
                    'account_details' => $request->account_details ?? null,
                    'payment_evidence' => $request->payment_evidence ?? [],
                    'metadata' => [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'tenant_id' => tenant('id'),
                    ],
                ]);

                if (!$paymentData['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $paymentData['message'] ?? 'Failed to initialize payment'
                    ], 400);
                }

                // Create pending subscription - will be activated when payment is approved/completed
                $reference = $paymentData['reference'];
                $billingCycle = $package->billing_cycle ?? 'monthly';
                
                // Calculate duration in days based on billing cycle
                $durationDays = match($billingCycle) {
                    'weekly' => 7,
                    'monthly' => 30,
                    'quarterly' => 90,
                    'yearly' => 365,
                    default => 30
                };
                
                // Calculate dates
                $startsAt = now();
                $endsAt = $startsAt->copy()->addDays($durationDays);
                $nextBillingDate = $endsAt->copy(); // Next billing is when current period ends
                
                // For active subscriptions, set current period dates
                $currentPeriodStart = $startsAt;
                $currentPeriodEnd = $endsAt;

                $subscription = Subscription::create([
                    'tenant_id' => tenant('id'),
                    'package_id' => $package->id,
                    'status' => 'trial', // Will be activated to 'active' when payment is approved/completed
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'current_period_start' => $currentPeriodStart,
                    'current_period_end' => $currentPeriodEnd,
                    'next_billing_date' => $nextBillingDate,
                    'amount' => $package->price,
                    'payment_reference' => $reference,
                    'payment_method' => $request->payment_method,
                    'metadata' => [
                        'package_name' => $package->name,
                        'billing_cycle' => $billingCycle,
                        'duration_days' => $durationDays,
                    ],
                ]);

                // Update payment metadata with subscription ID
                $payment = Payment::where('reference', $reference)->first();
                if ($payment) {
                    $metadata = $payment->metadata ?? [];
                    $metadata['subscription_id'] = $subscription->id;
                    $payment->update(['metadata' => $metadata]);
                }

                // Create PlatformTransaction record
                try {
                    PlatformTransaction::create([
                        'tenant_id' => tenant('id'),
                        'reference' => $reference,
                        'type' => 'subscription',
                        'amount' => $package->price,
                        'currency' => 'NGN',
                        'status' => $request->payment_method === 'manual' ? 'pending' : 'processing',
                        'payment_gateway' => $request->payment_method === 'manual' ? 'manual' : $request->payment_method,
                        'gateway_reference' => $paymentData['gateway_reference'] ?? null,
                        'approval_status' => $request->payment_method === 'manual' ? 'pending' : null,
                        'metadata' => [
                            'subscription_id' => $subscription->id,
                            'package_id' => $package->id,
                            'package_name' => $package->name,
                            'payment_method' => $request->payment_method,
                            'billing_cycle' => $billingCycle,
                            'duration_days' => $durationDays,
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to create PlatformTransaction for subscription', [
                        'subscription_id' => $subscription->id,
                        'reference' => $reference,
                        'error' => $e->getMessage()
                    ]);
                    // Continue even if PlatformTransaction creation fails
                }

                return response()->json([
                    'success' => true,
                    'message' => $request->payment_method === 'manual' 
                        ? 'Subscription request submitted. Payment pending approval.'
                        : 'Payment initialized successfully',
                    'reference' => $reference,
                    'paymentUrl' => $paymentData['paymentUrl'] ?? null,
                    'rrr' => $paymentData['rrr'] ?? null,
                    'requires_approval' => $request->payment_method === 'manual',
                ]);
            }

            // Handle wallet payment (legacy support)
            if ($request->payment_method === 'wallet') {
                $reference = 'SUB_' . time() . '_' . Str::random(10);
                
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'amount' => $package->price,
                    'currency' => 'NGN',
                    'payment_method' => 'wallet',
                    'status' => 'pending',
                    'description' => "Subscription to {$package->name}",
                    'metadata' => [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'duration' => $package->billing_cycle ?? 'monthly',
                    ],
                ]);

                $result = $this->processWalletPayment($request, $payment->id, $package);
                return response()->json($result);
            }

            throw new \InvalidArgumentException('Unsupported payment method');
        } catch (\Exception $e) {
            Log::error('SubscriptionController::initialize failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize subscription payment: ' . $e->getMessage()
            ], 500);
        }
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
        // Get available payment methods from super admin configured payment gateways
        // These are platform-wide payment gateways configured by super admin
        $gateways = \App\Models\Central\PaymentGateway::where('is_active', true)->get();
        
        $methods = [];
        
        foreach ($gateways as $gateway) {
            $settings = $gateway->settings ?? [];
            $isTestMode = $settings['test_mode'] ?? false;
            
            // Map gateway names to payment method IDs
            $methodId = match($gateway->name) {
                'paystack' => 'paystack',
                'remita' => 'remita',
                'stripe' => 'stripe',
                'manual' => 'manual',
                default => null,
            };
            
            if (!$methodId) {
                continue; // Skip unknown gateways
            }
            
            // Get icon based on gateway type
            $icon = match($gateway->name) {
                'paystack', 'stripe' => 'credit-card',
                'remita' => 'bank',
                'manual' => 'bank',
                default => 'credit-card',
            };
            
            // Build method configuration
            $configuration = [];
            
            // For manual payment, include bank accounts and configuration
            if ($gateway->name === 'manual') {
                $configuration = [
                    'bank_accounts' => $settings['bank_accounts'] ?? [],
                    'require_payer_name' => $settings['require_payer_name'] ?? true,
                    'require_payer_phone' => $settings['require_payer_phone'] ?? false,
                    'require_account_details' => $settings['require_account_details'] ?? false,
                    'require_payment_evidence' => $settings['require_payment_evidence'] ?? true,
                    'account_details' => $settings['account_details'] ?? null,
                ];
            }
            
            $methods[] = [
                'id' => $methodId,
                'name' => $gateway->display_name,
                'description' => $gateway->description ?? $this->getDefaultDescription($gateway->name),
                'icon' => $icon,
                'is_enabled' => $gateway->is_active,
                'configuration' => $configuration,
                'test_mode' => $isTestMode,
            ];
        }
        
        return response()->json([
            'payment_methods' => $methods
        ]);
    }
    
    private function getDefaultDescription(string $gatewayName): string
    {
        return match($gatewayName) {
            'paystack' => 'Pay with card, bank transfer, or USSD',
            'remita' => 'Pay with Remita payment gateway',
            'stripe' => 'Pay with card via Stripe',
            'manual' => 'Manual bank transfer payment',
            default => 'Payment gateway',
        };
    }

    private function initializePaystack(Request $request, string $paymentId, Package $package): array
    {
        $payment = Payment::findOrFail($paymentId);
        $response = $this->paystackService->initialize([
            'amount' => $payment->amount * 100, // Convert to kobo
            'email' => $request->user()->email,
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

    private function initializeRemita(Request $request, string $paymentId, Package $package): array
    {
        $payment = Payment::findOrFail($paymentId);
        $response = $this->remitaService->initialize([
            'amount' => $payment->amount,
            'customer_email' => $request->user()->email,
            'customer_name' => $request->user()->full_name,
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

    private function processWalletPayment(Request $request, string $paymentId, Package $package): array
    {
        $user = $request->user();
        $wallet = $user->wallet;

        $payment = Payment::findOrFail($paymentId);

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
            
            // Check if subscription already exists in metadata
            $metadata = $payment->metadata ?? [];
            if (isset($metadata['subscription_id'])) {
                // Activate existing subscription
                $subscription = Subscription::find($metadata['subscription_id']);
                if ($subscription) {
                    $package = $subscription->package;
                    $billingCycle = $package->billing_cycle ?? 'monthly';
                    $durationDays = match($billingCycle) {
                        'weekly' => 7,
                        'monthly' => 30,
                        'quarterly' => 90,
                        'yearly' => 365,
                        default => 30
                    };
                    
                    $startsAt = now();
                    $endsAt = $startsAt->copy()->addDays($durationDays);
                    $nextBillingDate = $endsAt->copy();
                    
                    $subscription->update([
                        'status' => 'active',
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'current_period_start' => $startsAt,
                        'current_period_end' => $endsAt,
                        'next_billing_date' => $nextBillingDate,
                        'payment_method' => $payment->payment_method ?? $subscription->payment_method ?? 'paystack',
                    ]);
                    $this->updateTenantSubscriptionStatus($subscription);
                }
            } else {
                // Create new subscription (legacy support)
                $this->createSubscription($payment);
            }
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
            
            // Check if subscription already exists in metadata
            $metadata = $payment->metadata ?? [];
            if (isset($metadata['subscription_id'])) {
                // Activate existing subscription
                $subscription = Subscription::find($metadata['subscription_id']);
                if ($subscription) {
                    $package = $subscription->package;
                    $billingCycle = $package->billing_cycle ?? 'monthly';
                    $durationDays = match($billingCycle) {
                        'weekly' => 7,
                        'monthly' => 30,
                        'quarterly' => 90,
                        'yearly' => 365,
                        default => 30
                    };
                    
                    $startsAt = now();
                    $endsAt = $startsAt->copy()->addDays($durationDays);
                    $nextBillingDate = $endsAt->copy();
                    
                    $subscription->update([
                        'status' => 'active',
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'current_period_start' => $startsAt,
                        'current_period_end' => $endsAt,
                        'next_billing_date' => $nextBillingDate,
                        'payment_method' => $payment->payment_method ?? $subscription->payment_method ?? 'remita',
                    ]);
                    $this->updateTenantSubscriptionStatus($subscription);
                }
            } else {
                // Create new subscription (legacy support)
                $this->createSubscription($payment);
            }
        }

        return [
            'success' => $response['status'] === 'success',
            'message' => $response['status'] === 'success' ? 'Subscription successful' : 'Subscription failed'
        ];
    }
    
    private function updateTenantSubscriptionStatus(Subscription $subscription): void
    {
        // Update tenant_details table
        DB::connection('mysql')->table('tenant_details')
            ->where('tenant_id', $subscription->tenant_id)
            ->update([
                'subscription_status' => 'active',
                'subscription_ends_at' => $subscription->ends_at,
                'updated_at' => now(),
            ]);
    }

    private function createSubscription(Payment $payment): ?Subscription
    {
        $package = Package::findOrFail($payment->metadata['package_id']);
        $tenantId = tenant('id');
        $billingCycle = $package->billing_cycle ?? 'monthly';
        
        // Calculate duration in days from billing_cycle
        $durationDays = match($billingCycle) {
            'weekly' => 7,
            'monthly' => 30,
            'quarterly' => 90,
            'yearly' => 365,
            default => 30
        };
        
        // Calculate dates
        $startsAt = now();
        $endsAt = $startsAt->copy()->addDays($durationDays);
        $nextBillingDate = $endsAt->copy(); // Next billing is when current period ends
        
        // For active subscriptions, set current period dates
        $currentPeriodStart = $startsAt;
        $currentPeriodEnd = $endsAt;
        
        // Create subscription in central database
        $subscription = Subscription::create([
            'tenant_id' => $tenantId,
            'package_id' => $package->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'next_billing_date' => $nextBillingDate,
            'amount' => $payment->amount,
            'payment_reference' => $payment->reference,
            'payment_method' => $payment->payment_method ?? 'manual',
            'metadata' => array_merge($payment->metadata ?? [], [
                'package_name' => $package->name,
                'billing_cycle' => $billingCycle,
                'duration_days' => $durationDays,
            ]),
        ]);
        
        // Update tenant_details table
        DB::connection('mysql')->table('tenant_details')
            ->where('tenant_id', $tenantId)
            ->update([
                'subscription_status' => 'active',
                'subscription_ends_at' => $endsAt,
                'updated_at' => now(),
            ]);
        
        return $subscription;
    }
}
