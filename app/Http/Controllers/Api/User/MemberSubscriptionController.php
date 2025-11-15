<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Central\MemberSubscription;
use App\Models\Central\MemberSubscriptionPackage;
use App\Models\Central\PaymentGateway;
use App\Models\Central\PlatformTransaction;
use App\Models\Tenant\Wallet;
use App\Services\Payment\PaystackService;
use App\Services\Payment\RemitaService;
use App\Services\SuperAdmin\SuperAdminPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MemberSubscriptionController extends Controller
{
    // Services are resolved lazily when needed (only for payment operations)
    // This prevents instantiation errors for methods that don't need payment services

    /**
     * Get all available member subscription packages
     * Note: This accesses the central database (member_subscription_packages table)
     * even though the route is in tenant context, as packages are platform-wide
     */
    public function packages(): JsonResponse
    {
        Log::info('MemberSubscriptionController::packages() - METHOD CALLED');
        try {
            Log::info('MemberSubscriptionController::packages() - INSIDE TRY BLOCK');
            
            Log::info('MemberSubscriptionController::packages() called');
            
            // Verify tenant context is available (like user dashboard stats)
            $tenant = tenant();
            if (!$tenant) {
                Log::warning('MemberSubscriptionController::packages() - Tenant not found');
                return response()->json(['message' => 'Tenant not found'], 404);
            }
            
            Log::info('MemberSubscriptionController::packages() - Tenant found', ['tenant_id' => $tenant->id]);

            // CRITICAL: Use DB facade directly to query central database
            // When tenant context is active, Eloquent models might use tenant connection
            // even with ->on('mysql'), so we use DB facade which explicitly uses the connection
            Log::info('MemberSubscriptionController::packages() - Querying packages from central database');
            
            $packagesData = DB::connection('mysql')
                ->table('member_subscription_packages')
                ->where('is_active', true)
                ->orderByRaw('ISNULL(sort_order), sort_order ASC')
                ->orderBy('price')
                ->get();
            
            Log::info('MemberSubscriptionController::packages() - Packages found', ['count' => $packagesData->count()]);

            return response()->json([
                'packages' => $packagesData->map(function ($package) {
                    return [
                        'id' => $package->id,
                        'name' => $package->name,
                        'slug' => $package->slug,
                        'description' => $package->description,
                        'price' => (float) $package->price,
                        'billing_cycle' => $package->billing_cycle,
                        'duration_days' => $package->duration_days,
                        'trial_days' => $package->trial_days,
                        'features' => is_string($package->features) ? json_decode($package->features, true) : ($package->features ?? []),
                        'benefits' => is_string($package->benefits) ? json_decode($package->benefits, true) : ($package->benefits ?? []),
                        'is_popular' => (bool) $package->is_featured,
                        'is_active' => (bool) $package->is_active,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription packages error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Failed to load packages',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get current member's subscription
     */
    public function current( Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $member = $user->member;

            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $tenant = tenant();
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            // CRITICAL: Use DB facade directly to query central database
            // Query for active subscription with valid payment status and not expired
            $subscriptionData = DB::connection('mysql')
                ->table('member_subscriptions')
                ->where('business_id', $tenant->id)
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->whereIn('payment_status', ['completed', 'approved'])
                ->where('end_date', '>=', now()->toDateString())
                ->orderBy('created_at', 'desc')
                ->first();
            
            if (!$subscriptionData) {
                return response()->json([
                    'subscription' => null,
                    'message' => 'No active subscription'
                ]);
            }
            
            // Load package details
            $packageData = DB::connection('mysql')
                ->table('member_subscription_packages')
                ->where('id', $subscriptionData->package_id)
                ->first();

            $startDate = \Carbon\Carbon::parse($subscriptionData->start_date);
            $endDate = \Carbon\Carbon::parse($subscriptionData->end_date);
            $daysRemaining = max(0, now()->diffInDays($endDate, false));
            
            return response()->json([
                'subscription' => [
                    'id' => $subscriptionData->id,
                    'package_id' => $subscriptionData->package_id,
                    'package_name' => $packageData->name ?? 'Unknown',
                    'status' => $subscriptionData->status,
                    'payment_status' => $subscriptionData->payment_status,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'amount_paid' => (float) $subscriptionData->amount_paid,
                    'payment_method' => $subscriptionData->payment_method,
                    'payment_reference' => $subscriptionData->payment_reference,
                    'days_remaining' => $daysRemaining,
                    'is_active' => $subscriptionData->status === 'active' && $endDate->isFuture() && in_array($subscriptionData->payment_status, ['completed', 'approved']),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription current error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get member's subscription history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $member = $user->member;

            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $tenant = tenant();
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            // CRITICAL: Use DB facade directly to query central database
            $subscriptionsData = DB::connection('mysql')
                ->table('member_subscriptions')
                ->where('business_id', $tenant->id)
                ->where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get package IDs to fetch package names
            $packageIds = $subscriptionsData->pluck('package_id')->unique()->toArray();
            $packages = DB::connection('mysql')
                ->table('member_subscription_packages')
                ->whereIn('id', $packageIds)
                ->get()
                ->keyBy('id');
            
            $subscriptions = $subscriptionsData->map(function ($subscription) use ($packages) {
                $package = $packages->get($subscription->package_id);
                return [
                    'id' => $subscription->id,
                    'package_name' => $package->name ?? 'Unknown',
                    'amount_paid' => (float) $subscription->amount_paid,
                    'status' => $subscription->status,
                    'payment_status' => $subscription->payment_status ?? 'completed',
                    'payment_method' => $subscription->payment_method,
                    'start_date' => \Carbon\Carbon::parse($subscription->start_date)->format('Y-m-d'),
                    'end_date' => \Carbon\Carbon::parse($subscription->end_date)->format('Y-m-d'),
                    'payment_reference' => $subscription->payment_reference,
                    'created_at' => \Carbon\Carbon::parse($subscription->created_at)->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'subscriptions' => $subscriptions
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription history error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch subscription history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available payment methods from super admin configured payment gateways
     * Member subscriptions use platform-wide payment gateways configured by super admin
     */
    public function paymentMethods(): JsonResponse
    {
        try {
            Log::info('MemberSubscriptionController::paymentMethods() - METHOD CALLED');
        
            $tenant = tenant();
            if (!$tenant) {
                Log::error('MemberSubscriptionController::paymentMethods() - Tenant not found');
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            Log::info('MemberSubscriptionController::paymentMethods() - Tenant found', [
                'tenant_id' => $tenant->id,
                'tenant_domain' => $tenant->domain ?? 'N/A'
            ]);

            // Get available payment methods from super admin configured payment gateways
            // These are platform-wide payment gateways configured by super admin
            $gateways = PaymentGateway::where('is_active', true)->get();
            
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
            
            Log::info('MemberSubscriptionController::paymentMethods() - Payment methods retrieved', [
                'methods_count' => count($methods)
            ]);

            return response()->json([
                'payment_methods' => $methods
            ]);
        } catch (\Exception $e) {
            Log::error('MemberSubscriptionController::paymentMethods() - Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
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

    /**
     * Initialize member subscription payment
     */
    public function initialize(Request $request): JsonResponse
    {
        Log::info('MemberSubscriptionController::initialize() - METHOD CALLED', [
            'request_data' => $request->all(),
        ]);
        
        // Custom validation for package_id - must exist in central database
        $validationRules = [
            'package_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $exists = DB::connection('mysql')
                        ->table('member_subscription_packages')
                        ->where('id', $value)
                        ->where('is_active', true)
                        ->exists();
                    
                    if (!$exists) {
                        $fail('The selected package is invalid or not available.');
                    }
                },
            ],
            'payment_method' => 'required|in:paystack,remita,stripe,wallet,manual',
            'notes' => 'nullable|string|max:1000',
        ];

        // Add validation for manual payment fields based on super admin gateway configuration
        if ($request->payment_method === 'manual') {
            // Get manual gateway config from super admin payment gateways
            $manualGateway = PaymentGateway::where('name', 'manual')
                ->where('is_active', true)
                ->first();
            
            $settings = $manualGateway ? ($manualGateway->settings ?? []) : [];
            $requireEvidence = $settings['require_payment_evidence'] ?? true;
            $requirePayerName = $settings['require_payer_name'] ?? true;
            $requirePayerPhone = $settings['require_payer_phone'] ?? false;
            $requireAccountDetails = $settings['require_account_details'] ?? false;

            if ($requirePayerName) {
                $validationRules['payer_name'] = 'required|string|max:255';
            }
            if ($requirePayerPhone) {
                $validationRules['payer_phone'] = 'required|string|max:20';
            }
            if ($requireAccountDetails) {
                $validationRules['account_details'] = 'required|string|max:1000';
            } else {
                $validationRules['account_details'] = 'nullable|string|max:1000';
            }
            if ($requireEvidence) {
                $validationRules['payment_evidence'] = 'required|array|min:1';
                $validationRules['payment_evidence.*'] = 'required|string|url'; // URLs of uploaded files
            } else {
                $validationRules['payment_evidence'] = 'nullable|array';
                $validationRules['payment_evidence.*'] = 'nullable|string|url';
            }
        }

        $request->validate($validationRules);

        try {
            $user = $request->user();
            $member = $user->member;

            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $tenant = tenant();
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            // Explicitly use central database connection for package lookup
            $package = MemberSubscriptionPackage::on('mysql')->findOrFail($request->package_id);

            if (!$package->is_active) {
                return response()->json(['message' => 'Package is not available'], 400);
            }

            // Use the reusable payment service
            $paymentService = app(\App\Services\Tenant\TenantPaymentService::class);
            
            // Initialize payment using the service
            $paymentData = $paymentService->initializePayment([
                'user_id' => $user->id,
                'amount' => $package->price,
                'payment_method' => $request->payment_method,
                'description' => "Member subscription payment for {$package->name}",
                'payment_type' => 'member_subscription',
                'currency' => 'NGN',
                'notes' => $request->notes,
                'payer_name' => $request->payer_name ?? null,
                'payer_phone' => $request->payer_phone ?? null,
                'account_details' => $request->account_details ?? null,
                'payment_evidence' => $request->payment_evidence ?? [],
                'metadata' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'member_id' => $member->id,
                    'business_id' => $tenant->id,
                ],
            ]);

            if (!$paymentData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $paymentData['message'] ?? 'Failed to initialize payment'
                ], 400);
            }

            // If wallet payment, create subscription immediately
            if ($request->payment_method === 'wallet') {
                $reference = $paymentData['reference'];
                return $this->createMemberSubscription($user, $member, $tenant, $package, $reference, $request->notes, 'wallet', 'completed');
            }

            // For manual and gateway payments, create pending subscription
            // The subscription will be activated when payment is approved/completed
            $reference = $paymentData['reference'];
            
            // Calculate dates
            $startDate = now();
            $endDate = $startDate->copy()->addDays($package->duration_days ?? 30);
            $nextBillingDate = $endDate->copy(); // Next billing is when current period ends
            
            $subscription = \App\Models\Central\MemberSubscription::on('mysql')->create([
                'business_id' => $tenant->id,
                'member_id' => $member->id,
                'package_id' => $package->id,
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_billing_date' => $nextBillingDate,
                'amount_paid' => $package->price,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_method === 'manual' ? 'pending' : 'pending',
                'payment_reference' => $reference,
                'notes' => $request->notes,
                'payer_name' => $request->payer_name ?? null,
                'payer_phone' => $request->payer_phone ?? null,
                'account_details' => $request->account_details ?? null,
                'payment_evidence' => $request->payment_evidence ?? [],
            ]);

            // Update payment metadata with subscription ID
            $payment = \App\Models\Tenant\Payment::where('reference', $reference)->first();
            if ($payment) {
                $metadata = $payment->metadata ?? [];
                $metadata['subscription_id'] = $subscription->id;
                $metadata['member_subscription_id'] = $subscription->id;
                $payment->update(['metadata' => $metadata]);
            }

            // Create PlatformTransaction record
            try {
                PlatformTransaction::create([
                    'tenant_id' => $tenant->id,
                    'reference' => $reference,
                    'type' => 'subscription', // Using 'subscription' type for member subscriptions too
                    'amount' => $package->price,
                    'currency' => 'NGN',
                    'status' => $request->payment_method === 'manual' ? 'pending' : 'processing',
                    'payment_gateway' => $request->payment_method === 'manual' ? 'manual' : $request->payment_method,
                    'gateway_reference' => $paymentData['gateway_reference'] ?? null,
                    'approval_status' => $request->payment_method === 'manual' ? 'pending' : null,
                    'metadata' => [
                        'member_subscription_id' => $subscription->id,
                        'member_id' => $member->id,
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'payment_method' => $request->payment_method,
                        'business_id' => $tenant->id,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create PlatformTransaction for member subscription', [
                    'member_subscription_id' => $subscription->id,
                    'reference' => $reference,
                    'error' => $e->getMessage()
                ]);
                // Continue even if PlatformTransaction creation fails
            }

            return response()->json([
                'success' => true,
                'message' => $request->payment_method === 'manual' 
                    ? 'Subscription request submitted. Waiting for admin approval.'
                    : 'Payment initialized successfully',
                'subscription_id' => $subscription->id,
                'reference' => $reference,
                'payment_url' => $paymentData['payment_url'] ?? null,
                'requires_approval' => $request->payment_method === 'manual',
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription initialize error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to initialize subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create member subscription (helper method)
     */
    private function createMemberSubscription($user, $member, $tenant, $package, $reference, $notes, $paymentMethod, $paymentStatus): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Calculate dates
            $startDate = now();
            $endDate = $startDate->copy()->addDays($package->duration_days ?? 30);
            $nextBillingDate = $endDate->copy(); // Next billing is when current period ends
            
            // Create subscription in central database
            $subscription = MemberSubscription::on('mysql')->create([
                'business_id' => $tenant->id,
                'member_id' => $member->id,
                'package_id' => $package->id,
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_billing_date' => $nextBillingDate,
                'amount_paid' => $package->price,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_reference' => $reference,
                'notes' => $notes,
            ]);

            // Create PlatformTransaction record for wallet payment
            try {
                PlatformTransaction::create([
                    'tenant_id' => $tenant->id,
                    'reference' => $reference,
                    'type' => 'subscription',
                    'amount' => $package->price,
                    'currency' => 'NGN',
                    'status' => $paymentStatus === 'completed' ? 'completed' : 'pending',
                    'payment_gateway' => $paymentMethod,
                    'approval_status' => $paymentStatus === 'completed' ? 'approved' : null,
                    'paid_at' => $paymentStatus === 'completed' ? now() : null,
                    'metadata' => [
                        'member_subscription_id' => $subscription->id,
                        'member_id' => $member->id,
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'payment_method' => $paymentMethod,
                        'business_id' => $tenant->id,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create PlatformTransaction for member subscription (wallet)', [
                    'member_subscription_id' => $subscription->id,
                    'reference' => $reference,
                    'error' => $e->getMessage()
                ]);
                // Continue even if PlatformTransaction creation fails
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'subscription_id' => $subscription->id,
                'reference' => $reference,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    /**
     * Verify payment after gateway callback
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:paystack,remita,stripe',
            'reference' => 'required|string'
        ]);

        try {
            // Use the reusable payment service
            $paymentService = app(\App\Services\Tenant\TenantPaymentService::class);
            $verification = $paymentService->verifyPayment($request->reference, $request->provider);

            if ($verification['success']) {
                // Update subscription status in central database
                $payment = \App\Models\Tenant\Payment::where('reference', $request->reference)
                    ->orWhere('gateway_reference', $request->reference)
                    ->first();

                if ($payment && isset($payment->metadata['subscription_id'])) {
                    MemberSubscription::on('mysql')
                        ->where('id', $payment->metadata['subscription_id'])
                        ->update([
                            'payment_status' => 'completed',
                        ]);
                }
            }

            return response()->json($verification);
        } catch (\Exception $e) {
            Log::error('Member subscription verify error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}

