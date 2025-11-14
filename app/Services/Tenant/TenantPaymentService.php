<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Contribution;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\EquityWalletBalance;
use App\Models\Tenant\EquityTransaction;
use App\Models\Tenant\Member;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentGateway;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use App\Services\Payment\PaystackService;
use App\Services\Payment\RemitaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reusable payment service for tenant payments
 * Can be used for: loans, contributions, subscriptions, property payments, etc.
 */
class TenantPaymentService
{

    /**
     * Get available payment methods for the tenant
     * 
     * @param string|null $paymentType Type of payment (loan, contribution, subscription, property, etc.)
     * @return array
     */
    public function getAvailablePaymentMethods(?string $paymentType = null): array
    {
        try {
            Log::info('TenantPaymentService::getAvailablePaymentMethods() - Method called', [
                'payment_type' => $paymentType
            ]);

            $tenant = tenant();
            if (!$tenant) {
                Log::warning('TenantPaymentService::getAvailablePaymentMethods() - Tenant not found');
                return [];
            }

            Log::info('TenantPaymentService::getAvailablePaymentMethods() - Tenant found', [
                'tenant_id' => $tenant->id
            ]);

            $methods = [];
            
            // Get enabled payment gateways from tenant
            try {
                $tenantId = $tenant->id;
                Log::info('TenantPaymentService::getAvailablePaymentMethods() - Querying payment gateways', [
                    'tenant_id' => $tenantId
                ]);
                
                $gateways = PaymentGateway::where('tenant_id', $tenantId)
                    ->where('is_enabled', true)
                    ->get();
                    
                Log::info('TenantPaymentService::getAvailablePaymentMethods() - Payment gateways retrieved', [
                    'count' => $gateways->count(),
                    'gateway_types' => $gateways->pluck('gateway_type')->toArray()
                ]);
            } catch (\Exception $e) {
                Log::error('TenantPaymentService::getAvailablePaymentMethods() - Failed to query payment gateways', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'tenant_id' => $tenant->id ?? 'unknown'
                ]);
                throw $e;
            }
        
        // Map gateway types to display info
        $gatewayInfo = [
            'paystack' => [
                'id' => 'paystack',
                'name' => 'Paystack',
                'description' => 'Pay with card, bank transfer, or USSD',
                'icon' => 'credit-card',
            ],
            'remita' => [
                'id' => 'remita',
                'name' => 'Remita',
                'description' => 'Pay with Remita payment gateway',
                'icon' => 'bank',
            ],
            'stripe' => [
                'id' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Pay with Stripe payment gateway',
                'icon' => 'credit-card',
            ],
            'manual' => [
                'id' => 'manual',
                'name' => 'Manual Payment',
                'description' => 'Pay offline and wait for admin approval',
                'icon' => 'receipt',
            ],
        ];

            // Add enabled gateways
            foreach ($gateways as $gateway) {
                try {
                    Log::debug('TenantPaymentService::getAvailablePaymentMethods() - Processing gateway', [
                        'gateway_id' => $gateway->id,
                        'gateway_type' => $gateway->gateway_type
                    ]);

                    $info = $gatewayInfo[$gateway->gateway_type] ?? [
                        'id' => $gateway->gateway_type,
                        'name' => ucfirst($gateway->gateway_type),
                        'description' => 'Pay with ' . ucfirst($gateway->gateway_type),
                        'icon' => 'credit-card',
                    ];

                    $method = [
                        'id' => $info['id'],
                        'name' => $info['name'],
                        'description' => $info['description'],
                        'icon' => $info['icon'],
                        'is_enabled' => true,
                    ];

                    // For manual payment, include configuration requirements
                    if ($gateway->gateway_type === 'manual') {
                        try {
                            $method['configuration'] = $this->prepareManualConfig($gateway->configuration ?? []);
                        } catch (\Exception $e) {
                            Log::warning('TenantPaymentService::getAvailablePaymentMethods() - Failed to parse manual payment config', [
                                'gateway_id' => $gateway->id,
                                'error' => $e->getMessage()
                            ]);
                            $method['configuration'] = $this->getDefaultManualConfig();
                        }
                    }

                    $methods[] = $method;

                    if ($gateway->gateway_type === 'paystack' && $paymentType === 'wallet_funding') {
                        $config = is_array($gateway->configuration) ? $gateway->configuration : [];
                        $enableDedicated = $config['enable_virtual_account'] ?? true;
                        if ($enableDedicated) {
                            $methods[] = [
                                'id' => 'paystack_virtual_account',
                                'name' => $config['virtual_account_display_name'] ?? 'Paystack Titan Account Transfer',
                                'description' => $config['virtual_account_description']
                                    ?? 'Transfer to your personal Paystack Titan Trust account to fund your wallet instantly.',
                                'icon' => 'bank',
                                'is_enabled' => true,
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('TenantPaymentService::getAvailablePaymentMethods() - Error processing gateway', [
                        'gateway_id' => $gateway->id ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue processing other gateways
                }
            }

            // Add wallet as a payment method (except for wallet funding - can't fund wallet with wallet)
            if ($paymentType !== 'wallet_funding') {
                $methods[] = [
                    'id' => 'wallet',
                    'name' => 'Wallet',
                    'description' => 'Pay from your wallet balance',
                    'icon' => 'wallet',
                    'is_enabled' => true,
                ];
            }

            Log::info('TenantPaymentService::getAvailablePaymentMethods() - Methods prepared', [
                'total_methods' => count($methods)
            ]);

            return $methods;
        } catch (\Exception $e) {
            Log::error('TenantPaymentService::getAvailablePaymentMethods() - Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    private function getDefaultManualConfig(): array
    {
        return [
            'require_payer_name' => true,
            'require_payer_phone' => false,
            'require_transaction_reference' => true,
            'require_payment_evidence' => true,
            'bank_accounts' => [],
        ];
    }

    private function prepareManualConfig(array $config): array
    {
        $merged = array_merge($this->getDefaultManualConfig(), $config);

        if (!empty($merged['bank_accounts']) && is_array($merged['bank_accounts'])) {
            $merged['bank_accounts'] = array_map(function ($account) {
                if (is_array($account)) {
                    $account['id'] = $account['id'] ?? (string) Str::uuid();
                }

                return $account;
            }, $merged['bank_accounts']);
        } else {
            $merged['bank_accounts'] = [];
        }

        return $merged;
    }

    /**
     * Initialize a payment
     * 
     * @param array $data Payment data
     * @return array
     */
    public function initializePayment(array $data): array
    {
        $requiredFields = ['user_id', 'amount', 'payment_method', 'description', 'payment_type'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Missing required field: {$field}"
                ];
            }
        }

        $reference = $this->generateReference($data['payment_type']);

        try {
            DB::beginTransaction();

            // Create payment record
            $storagePaymentMethod = $data['payment_method'] === 'manual' ? 'bank_transfer' : $data['payment_method'];

            $payment = Payment::create([
                'user_id' => $data['user_id'],
                'reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'NGN',
                'payment_method' => $storagePaymentMethod,
                'status' => 'pending',
                'description' => $data['description'],
                'metadata' => $data['metadata'] ?? [],
                'approval_status' => 'pending',
                'payer_name' => $data['payer_name'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
                'account_details' => $data['account_details'] ?? null,
                'payment_evidence' => $data['payment_evidence'] ?? [],
            ]);

            // Process based on payment method
            // Note: Wallet payment method is not allowed for wallet funding
            if ($data['payment_method'] === 'wallet' && $data['payment_type'] === 'wallet_funding') {
                return [
                    'success' => false,
                    'message' => 'Cannot fund wallet using wallet balance'
                ];
            }

            $result = match($data['payment_method']) {
                'wallet' => $this->processWalletPayment($payment, $data),
                'manual' => $this->processManualPayment($payment, $data),
                'paystack' => $this->processPaystackPayment($payment, $data),
                'remita' => $this->processRemitaPayment($payment, $data),
                'stripe' => $this->processStripePayment($payment, $data),
                default => [
                    'success' => false,
                    'message' => 'Unsupported payment method'
                ]
            };

            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }

            DB::commit();

            return [
                'success' => true,
                'payment' => $payment->fresh(),
                'payment_url' => $result['payment_url'] ?? null,
                'reference' => $reference,
                'requires_approval' => $data['payment_method'] === 'manual',
                ...$result
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TenantPaymentService::initializePayment error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to initialize payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process wallet payment
     */
    protected function processWalletPayment(Payment $payment, array $data): array
    {
        $user = $payment->user;
        $wallet = $user->wallet;

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'currency' => 'NGN',
                'is_active' => true,
            ]);
        }

        if ($wallet->balance < $payment->amount) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance. Current balance: ₦' . number_format($wallet->balance, 2)
            ];
        }

        DB::beginTransaction();
        try {
            // Deduct from wallet
            $oldBalance = $wallet->balance;
            $wallet->decrement('balance', $payment->amount);
            $newBalance = $wallet->fresh()->balance;

            // Create wallet transaction
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $payment->amount,
                'description' => $payment->description,
                'payment_reference' => $payment->reference,
                'status' => 'completed',
                'metadata' => [
                    'balance_before' => $oldBalance,
                    'balance_after' => $newBalance,
                    'payment_id' => $payment->id,
                ],
            ]);

            // Update payment status
            $payment->update([
                'status' => 'completed',
                'approval_status' => 'approved',
                'completed_at' => now(),
            ]);

            $this->finalizeContributionPayment($payment);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Payment completed successfully from wallet'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet payment processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to process wallet payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process manual payment
     */
    protected function processManualPayment(Payment $payment, array $data): array
    {
        // Manual payments require approval
        $payment->update([
            'status' => 'pending',
            'approval_status' => 'pending',
        ]);

        return [
            'success' => true,
            'message' => 'Payment request submitted. Waiting for admin approval.'
        ];
    }

    /**
     * Process Paystack payment
     */
    protected function processPaystackPayment(Payment $payment, array $data): array
    {
        $tenant = tenant();
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'Tenant context not available'
            ];
        }

        $gateway = PaymentGateway::where('tenant_id', $tenant->id)
            ->where('gateway_type', 'paystack')
            ->where('is_enabled', true)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'message' => 'Paystack payment gateway is not available'
            ];
        }

        $paystackService = $this->makePaystackService($gateway);

        try {
            $user = $payment->user;
            $email = $user?->email;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = sprintf('user-%s@local.dev', $user?->id ?? Str::random(8));
            }

            $paystackData = $paystackService->initialize([
                'amount' => $payment->amount * 100, // Convert to kobo
                'email' => $email,
                'reference' => $payment->reference,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'payment_type' => $data['payment_type'] ?? 'general',
                ]
            ]);

            if (!$paystackData['status'] ?? false) {
                return [
                    'success' => false,
                    'message' => $paystackData['message'] ?? 'Failed to initialize Paystack payment'
                ];
            }

            $payment->update([
                'gateway_reference' => $paystackData['data']['reference'] ?? null,
                'gateway_url' => $paystackData['data']['authorization_url'] ?? null,
            ]);

            return [
                'success' => true,
                'payment_url' => $paystackData['data']['authorization_url'] ?? null,
                'reference' => $paystackData['data']['reference'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('TenantPaymentService::processPaystackPayment() - Initialization failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initialize Paystack payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process Remita payment
     */
    protected function processRemitaPayment(Payment $payment, array $data): array
    {
        $tenant = tenant();
        if (!$tenant) {
            return [
                'success' => false,
                'message' => 'Tenant context not available'
            ];
        }

        $gateway = PaymentGateway::where('tenant_id', $tenant->id)
            ->where('gateway_type', 'remita')
            ->where('is_enabled', true)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'message' => 'Remita payment gateway is not available'
            ];
        }

        $remitaService = $this->makeRemitaService($gateway);

        try {
            $remitaData = $remitaService->initialize([
                'amount' => $payment->amount,
                'customer_email' => $payment->user->email,
                'customer_name' => $payment->user->name,
                'customer_phone' => $payment->user->phone ?? '',
                'description' => $payment->description,
                'reference' => $payment->reference,
                'callback_url' => config('app.url') . '/api/payments/callback',
            ]);

            if (!($remitaData['status'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $remitaData['message'] ?? 'Failed to initialize Remita payment'
                ];
            }

            $payment->update([
                'gateway_reference' => $remitaData['rrr'] ?? null,
                'gateway_url' => $remitaData['payment_url'] ?? null,
            ]);

            return [
                'success' => true,
                'payment_url' => $remitaData['payment_url'] ?? null,
                'rrr' => $remitaData['rrr'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('TenantPaymentService::processRemitaPayment() - Initialization failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initialize Remita payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process Stripe payment
     */
    protected function processStripePayment(Payment $payment, array $data): array
    {
        // TODO: Implement Stripe payment
        return [
            'success' => false,
            'message' => 'Stripe payment is not yet implemented'
        ];
    }

    /**
     * Verify payment after gateway callback
     */
    public function verifyPayment(string $reference, string $provider): array
    {
        try {
            $payment = Payment::where('reference', $reference)
                ->orWhere('gateway_reference', $reference)
                ->firstOrFail();

            $verification = match($provider) {
                'paystack' => $this->verifyPaystackPayment($payment, $reference),
                'remita' => $this->verifyRemitaPayment($payment, $reference),
                default => [
                    'success' => false,
                    'message' => 'Unsupported payment provider'
                ]
            };

            if ($verification['success']) {
                $payment->update([
                    'status' => 'completed',
                    'approval_status' => 'approved',
                    'completed_at' => now(),
                ]);

                // If this is a wallet funding payment, credit the wallet
                if (isset($payment->metadata['wallet_id'])) {
                    $this->creditWalletFromPayment($payment);
                }

                if (($payment->metadata['type'] ?? null) === 'contribution') {
                    $this->finalizeContributionPayment($payment);
                }
            }

            return $verification;
        } catch (\Exception $e) {
            Log::error('TenantPaymentService::verifyPayment error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to verify payment: ' . $e->getMessage()
            ];
        }
    }

    protected function verifyPaystackPayment(Payment $payment, string $reference): array
    {
        $paystackService = $this->makePaystackService();

        try {
            $verification = $paystackService->verify($reference);
            
            if (($verification['status'] ?? false) && isset($verification['data'])) {
                $payment->update([
                    'gateway_response' => $verification['data'] ?? [],
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => $verification['data']
                ];
            }

            return [
                'success' => false,
                'message' => $verification['message'] ?? 'Payment verification failed'
            ];
        } catch (\Exception $e) {
            Log::error('TenantPaymentService::verifyPaystackPayment() - Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function verifyRemitaPayment(Payment $payment, string $reference): array
    {
        $remitaService = $this->makeRemitaService();

        try {
            $verification = $remitaService->verify($reference);
            
            if (($verification['status'] ?? 'failed') === 'success') {
                $payment->update([
                    'gateway_response' => $verification,
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => $verification
                ];
            }

            return [
                'success' => false,
                'message' => $verification['message'] ?? 'Payment verification failed'
            ];
        } catch (\Exception $e) {
            Log::error('TenantPaymentService::verifyRemitaPayment() - Verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Credit wallet from payment (for wallet funding)
     */
    protected function creditWalletFromPayment(Payment $payment): void
    {
        if (!isset($payment->metadata['wallet_id'])) {
            return;
        }

        $wallet = Wallet::find($payment->metadata['wallet_id']);
        if (!$wallet) {
            return;
        }

        DB::beginTransaction();
        try {
            // Credit wallet
            $oldBalance = $wallet->balance;
            $wallet->increment('balance', $payment->amount);
            $newBalance = $wallet->fresh()->balance;

            // Create wallet transaction
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $payment->amount,
                'description' => $payment->description,
                'payment_reference' => $payment->reference,
                'status' => 'completed',
                'metadata' => [
                    'balance_before' => $oldBalance,
                    'balance_after' => $newBalance,
                    'payment_id' => $payment->id,
                ],
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet credit from payment error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function finalizeContributionPayment(Payment $payment): void
    {
        $metadata = $payment->metadata ?? [];

        if (($metadata['type'] ?? null) !== 'contribution') {
            return;
        }

        $contributionId = $metadata['contribution_id'] ?? null;

        if (!$contributionId) {
            return;
        }

        $contribution = Contribution::find($contributionId);

        if (!$contribution) {
            return;
        }

        $contributionPaymentId = $metadata['contribution_payment_id'] ?? null;
        $contributionPayment = null;

        if ($contributionPaymentId) {
            $contributionPayment = ContributionPayment::find($contributionPaymentId);
        }

        if (!$contributionPayment) {
            $contributionPayment = ContributionPayment::create([
                'contribution_id' => $contribution->id,
                'amount' => $payment->amount,
                'payment_date' => Carbon::now()->toDateString(),
                'payment_method' => $payment->payment_method,
                'status' => 'pending',
                'reference' => $payment->reference,
                'metadata' => [],
            ]);
        }

        if ($contributionPayment->status !== 'completed') {
            $existingMetadata = $contributionPayment->metadata ?? [];

            $metadataUpdate = array_filter(array_merge(
                $existingMetadata,
                array_filter([
                    'payment_id' => $payment->id,
                    'gateway_reference' => $payment->gateway_reference,
                    'gateway_response' => $payment->gateway_response ?? null,
                ], fn ($value) => $value !== null)
            ));

            $contributionPayment->update([
                'status' => 'completed',
                'payment_date' => Carbon::now()->toDateString(),
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'metadata' => $metadataUpdate,
            ]);
        }

        $contribution->update([
            'status' => 'approved',
            'approved_at' => $contribution->approved_at ?? Carbon::now(),
            'approved_by' => $contribution->approved_by ?? $payment->user_id,
        ]);
    }

    public function finalizeEquityContributionPayment(Payment $payment): void
    {
        Log::info('finalizeEquityContributionPayment called', [
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'payment_amount' => $payment->amount,
        ]);
    
        $metadata = $payment->metadata ?? [];
    
        // Validate payment type
        if (($metadata['type'] ?? null) !== 'equity_contribution') {
            Log::warning('Payment type mismatch in finalizeEquityContributionPayment', [
                'payment_id' => $payment->id,
                'expected_type' => 'equity_contribution',
                'actual_type' => $metadata['type'] ?? 'null',
            ]);
            return;
        }
    
        $contributionId = $metadata['equity_contribution_id'] ?? null;
    
        if (!$contributionId) {
            Log::warning('Missing equity_contribution_id in payment metadata', [
                'payment_id' => $payment->id,
                'metadata' => $metadata,
            ]);
            return;
        }
    
        $contribution = EquityContribution::find($contributionId);
    
        if (!$contribution) {
            Log::error('Equity contribution not found', [
                'payment_id' => $payment->id,
                'contribution_id' => $contributionId,
            ]);
            return;
        }
    
        // Check if already finalized (but allow if paid_at is missing)
        if ($contribution->status === 'approved' && $contribution->paid_at) {
            Log::info('Equity contribution already finalized', [
                'payment_id' => $payment->id,
                'contribution_id' => $contribution->id,
                'status' => $contribution->status,
                'paid_at' => $contribution->paid_at,
            ]);
            return;
        }
    
        $now = Carbon::now();
    
        try {
            DB::beginTransaction();
    
            // Update contribution status
            $existingMetadata = $contribution->payment_metadata ?? [];
            $metadataUpdate = array_filter(array_merge(
                $existingMetadata,
                array_filter([
                    'payment_id' => $payment->id,
                    'gateway_reference' => $payment->gateway_reference ?? null,
                    'gateway_response' => $payment->gateway_response ?? null,
                    'payment_method' => $payment->payment_method,
                    'finalized_at' => $now->toDateTimeString(),
                ], static fn ($value) => $value !== null)
            ));
    
            $contribution->update([
                'status' => 'approved',
                'approved_at' => $contribution->approved_at ?? $now,
                'approved_by' => $contribution->approved_by ?? $payment->user_id,
                'paid_at' => $contribution->paid_at ?? $now,
                'payment_reference' => $payment->reference,
                'transaction_id' => $payment->gateway_reference ?? $contribution->transaction_id,
                'payment_metadata' => $metadataUpdate,
            ]);
    
            Log::info('Updated equity contribution status', [
                'contribution_id' => $contribution->id,
                'status' => 'approved',
                'paid_at' => $contribution->paid_at,
            ]);
    
            // Resolve member ID
            $memberId = $contribution->member_id ?? ($metadata['member_id'] ?? null);
            
            if (!$memberId && $payment->user_id) {
                $memberId = Member::where('user_id', $payment->user_id)->value('id');
            }
    
            if (!$memberId) {
                Log::error('Unable to resolve member for wallet credit', [
                    'payment_id' => $payment->id,
                    'contribution_id' => $contribution->id,
                    'payment_user_id' => $payment->user_id,
                ]);
                
                DB::rollBack();
                throw new \RuntimeException('Unable to resolve member for wallet credit');
            }
    
            // Update wallet balance
            $this->updateEquityWalletBalance($memberId, $payment, $now);
    
            DB::commit();
    
            Log::info('Successfully finalized equity contribution payment', [
                'payment_id' => $payment->id,
                'contribution_id' => $contribution->id,
                'member_id' => $memberId,
                'amount' => $payment->amount,
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();
            
            Log::error('Failed to finalize equity contribution payment', [
                'payment_id' => $payment->id,
                'contribution_id' => $contribution->id ?? null,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            
            throw $exception;
        }
    }
    
    /**
     * Update equity wallet balance and create transaction record
     *
     * @param int $memberId
     * @param Payment $payment
     * @param Carbon $now
     * @return void
     * @throws \Throwable
     */
    private function updateEquityWalletBalance(String $memberId, Payment $payment, Carbon $now): void
    {
        $wallet = EquityWalletBalance::where('member_id', $memberId)
            ->lockForUpdate()
            ->first();
    
        if (!$wallet) {
            Log::info('Creating new equity wallet for member', [
                'member_id' => $memberId,
                'payment_id' => $payment->id,
            ]);
    
            $wallet = new EquityWalletBalance([
                'member_id' => $memberId,
                'balance' => 0,
                'total_contributed' => 0,
                'total_used' => 0,
                'currency' => 'NGN',
                'is_active' => true,
                'last_updated_at' => $now,
            ]);
        }
    
        $amount = (float) $payment->amount;
        $balanceBefore = (float) $wallet->balance;
        $balanceAfter = $balanceBefore + $amount;
    
        $wallet->balance = $balanceAfter;
        $wallet->total_contributed = (float) $wallet->total_contributed + $amount;
        $wallet->last_updated_at = $now;
        $wallet->save();
    
        Log::info('Updated equity wallet balance', [
            'member_id' => $memberId,
            'wallet_id' => $wallet->id,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'amount' => $amount,
        ]);
    
        // Create transaction record
        EquityTransaction::create([
            'member_id' => $wallet->member_id,
            'equity_wallet_balance_id' => $wallet->id,
            'type' => 'contribution',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference' => $payment->reference,
            'reference_type' => 'equity_contribution',
            'description' => "Equity contribution - {$payment->reference}",
        ]);
    
        Log::info('Created equity transaction record', [
            'member_id' => $memberId,
            'reference' => $payment->reference,
            'amount' => $amount,
        ]);
    }

    protected function makePaystackService(?PaymentGateway $gateway = null): PaystackService
    {
        $gateway ??= $this->getTenantGateway('paystack');
        $credentials = $gateway?->credentials ?? [];

        return PaystackService::fromCredentials($credentials);
    }

    protected function makeRemitaService(?PaymentGateway $gateway = null): RemitaService
    {
        $gateway ??= $this->getTenantGateway('remita');
        $credentials = $gateway?->credentials ?? [];

        return RemitaService::fromCredentials($credentials);
    }

    protected function getTenantGateway(string $gatewayType): ?PaymentGateway
    {
        $tenant = tenant();
        if (!$tenant) {
            return null;
        }

        return PaymentGateway::where('tenant_id', $tenant->id)
            ->where('gateway_type', $gatewayType)
            ->where('is_enabled', true)
            ->first();
    }

    /**
     * Generate unique payment reference
     */
    protected function generateReference(string $paymentType): string
    {
        $prefix = strtoupper(substr($paymentType, 0, 3));
        return $prefix . '_' . time() . '_' . Str::random(10);
    }
}

