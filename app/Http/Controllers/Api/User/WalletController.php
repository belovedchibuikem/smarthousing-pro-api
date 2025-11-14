<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\WalletResource;
use App\Http\Requests\Payments\InitializePaymentRequest;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentGateway;
use App\Models\Tenant\PaystackDedicatedAccount;
use App\Services\Payment\PaystackService;
use App\Services\Payment\RemitaService;
use App\Services\Payment\StripeService;
use App\Services\Tenant\TenantPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class WalletController extends Controller
{
    

    public function show(Request $request): JsonResponse
    {
        
        $wallet = $request->user()->wallet;
        
        if (!$wallet) {
            // Create wallet if it doesn't exist
            $wallet = Wallet::create([
                'user_id' => $request->user()->id,
                'balance' => 0,
                'currency' => 'NGN',
            ]);
        }

        return response()->json([
            'wallet' => new WalletResource($wallet)
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;
        
        if (!$wallet) {
            return response()->json([
                'transactions' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int) $request->get('per_page', 15),
                    'total' => 0,
                ],
                'summary' => [
                    'total_transactions' => 0,
                    'total_credit' => 0,
                    'total_debit' => 0,
                ],
            ]);
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);

        $query = $wallet->transactions()->orderByDesc('created_at');
        $this->applyTransactionFilters($query, $request);

        $summaryQuery = clone $query;
        $totalTransactions = (clone $summaryQuery)->count();

        $completedSummaryQuery = (clone $summaryQuery)->where('status', 'completed');
        $totalCompleted = (clone $completedSummaryQuery)->count();
        $totalCredit = (clone $completedSummaryQuery)->where('type', 'credit')->sum('amount');
        $totalDebit = (clone $completedSummaryQuery)->where('type', 'debit')->sum('amount');

        $transactions = $query->paginate($perPage);
        $collection = $transactions->getCollection()->map(function (WalletTransaction $transaction) {
            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => (float) $transaction->amount,
                'payment_method' => $transaction->payment_method,
                'payment_reference' => $transaction->payment_reference,
                'description' => $transaction->description,
                'metadata' => $transaction->metadata,
                'created_at' => optional($transaction->created_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'transactions' => $collection,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
            'summary' => [
                'total_transactions' => $totalTransactions,
                'total_completed_transactions' => $totalCompleted,
                'total_credit' => (float) $totalCredit,
                'total_debit' => (float) $totalDebit,
            ],
            'balance' => (float) $wallet->balance,
        ]);
    }

    public function exportTransactions(Request $request)
    {
        $wallet = $request->user()->wallet;

        if (!$wallet) {
            return response()->json([
                'message' => 'No wallet transactions found for export.',
            ], 404);
        }

        $query = $wallet->transactions()->orderByDesc('created_at');
        $this->applyTransactionFilters($query, $request);

        $transactions = $query->get();

        $fileName = 'wallet-transactions-' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Type',
                'Status',
                'Amount',
                'Description',
                'Reference',
                'Payment Method',
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    optional($transaction->created_at)->format('Y-m-d H:i:s'),
                    $transaction->type,
                    $transaction->status,
                    (float) $transaction->amount,
                    $transaction->description,
                    $transaction->payment_reference,
                    $transaction->payment_method,
                ]);
            }

            fclose($handle);
        }, $fileName, $headers);
    }

    protected function applyTransactionFilters($query, Request $request): void
    {
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                    ->orWhere('payment_reference', 'like', '%' . $search . '%');
            });
        }

        if (($type = $request->query('type')) && in_array($type, ['credit', 'debit'], true)) {
            $query->where('type', $type);
        }

        if (($status = $request->query('status')) && in_array($status, ['pending', 'completed', 'failed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if (($paymentMethod = $request->query('payment_method')) && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($dateFrom = $request->query('date_from')) {
            try {
                $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable $e) {
                // Ignore invalid date
            }
        }

        if ($dateTo = $request->query('date_to')) {
            try {
                $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable $e) {
                // Ignore invalid date
            }
        }
    }

    public function paymentMethods(): JsonResponse
    {
        Log::info('WalletController::paymentMethods() - Request received');

        $tenant = tenant();
        if (!$tenant) {
            Log::warning('WalletController::paymentMethods() - Tenant not resolved');
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        try {
            /** @var TenantPaymentService $paymentService */
            $paymentService = app(TenantPaymentService::class);
            $methods = $paymentService->getAvailablePaymentMethods('wallet_funding');

            Log::info('WalletController::paymentMethods() - Methods retrieved', [
                'tenant_id' => $tenant->id,
                'methods_count' => count($methods),
            ]);

            return response()->json([
                'payment_methods' => $methods,
            ]);
        } catch (\Throwable $e) {
            Log::error('WalletController::paymentMethods() - Failed to retrieve methods', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'trace' => app()->environment('production') ? null : $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load payment methods',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    public function virtualAccount(Request $request): JsonResponse
    {
        return $this->respondWithVirtualAccount($request, false);
    }

    public function refreshVirtualAccount(Request $request): JsonResponse
    {
        return $this->respondWithVirtualAccount($request, true);
    }

    protected function respondWithVirtualAccount(Request $request, bool $forceRefresh): JsonResponse
    {
        Log::info('WalletController::virtualAccount() - Request received', [
            'force_refresh' => $forceRefresh,
            'user_id' => $request->user()->id,
        ]);

        $tenant = tenant();
        if (!$tenant) {
            Log::warning('WalletController::virtualAccount() - Tenant not resolved');
            return response()->json([
                'message' => 'Tenant not found',
            ], 404);
        }

        try {
            $account = $this->ensurePaystackDedicatedAccount($request->user(), $forceRefresh);

            return response()->json([
                'success' => true,
                'message' => 'Dedicated account retrieved successfully',
                'account' => [
                    'account_number' => $account->account_number,
                    'account_name' => $account->account_name,
                    'bank_name' => $account->bank_name,
                    'bank_slug' => $account->bank_slug,
                    'status' => $account->status,
                    'currency' => $account->currency,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('WalletController::virtualAccount() - Failed to retrieve account', [
                'error' => $e->getMessage(),
                'force_refresh' => $forceRefresh,
                'trace' => app()->environment('production') ? null : $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve dedicated account',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    public function fund(Request $request): JsonResponse
    {
        $requestedMethod = $request->input('payment_method');
        $normalizedMethod = $requestedMethod === 'bank_transfer' ? 'manual' : $requestedMethod;

        if ($request->hasFile('payment_evidence') && !is_array($request->file('payment_evidence'))) {
            $request->files->set('payment_evidence', [$request->file('payment_evidence')]);
        }

        $manualConfig = $normalizedMethod === 'manual' ? $this->getManualGatewayConfig() : null;
        $manualAccounts = $manualConfig['bank_accounts'] ?? [];
        $storageMethod = $normalizedMethod === 'manual' ? 'bank_transfer' : $normalizedMethod;

        $rules = [
            'amount' => 'required|numeric|min:100',
            'payment_method' => ['required', Rule::in(['paystack', 'remita', 'stripe', 'wallet', 'manual', 'bank_transfer', 'paystack_virtual_account'])],
            'description' => 'nullable|string|max:500',
        ];

        if ($normalizedMethod === 'manual') {
            $rules['payer_name'] = ($manualConfig['require_payer_name'] ?? true)
                ? 'required|string|max:255'
                : 'nullable|string|max:255';
            $rules['payer_phone'] = ($manualConfig['require_payer_phone'] ?? false)
                ? 'required|string|max:50'
                : 'nullable|string|max:50';
            $rules['transaction_reference'] = ($manualConfig['require_transaction_reference'] ?? true)
                ? 'required|string|max:255'
                : 'nullable|string|max:255';

            if ($manualConfig['require_payment_evidence'] ?? true) {
                $rules['payment_evidence'] = 'required|array|min:1';
                $rules['payment_evidence.*'] = 'nullable';
            } else {
                $rules['payment_evidence'] = 'nullable|array';
                $rules['payment_evidence.*'] = 'nullable';
            }

            $rules['bank_account_id'] = [
                Rule::requiredIf(count($manualAccounts) > 1),
                'nullable',
                'string',
            ];
        }

        $validated = $request->validate($rules);

        $user = $request->user();
        $wallet = $user->wallet;

        Log::info('WalletController::fund() - Start wallet funding', [
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'payment_method' => $normalizedMethod,
        ]);
        
        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'currency' => 'NGN',
            ]);

            Log::info('WalletController::fund() - Wallet created for user', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
            ]);
        }

        if ($normalizedMethod === 'paystack_virtual_account') {
            $account = $this->ensurePaystackDedicatedAccount($user);

            return response()->json([
                'success' => true,
                'message' => 'Transfer the selected amount to your dedicated Paystack Titan account to fund your wallet.',
                'data' => [
                    'account' => [
                        'account_number' => $account->account_number,
                        'account_name' => $account->account_name,
                        'bank_name' => $account->bank_name,
                        'bank_slug' => $account->bank_slug,
                        'currency' => $account->currency,
                    ],
                ],
            ]);
        }

        $reference = 'WALLET_FUND_' . time() . '_' . Str::random(10);

        $selectedAccount = null;
        if ($normalizedMethod === 'manual' && !empty($manualAccounts)) {
            $accountId = $validated['bank_account_id'] ?? null;
            $accountsCollection = collect($manualAccounts);
            if ($accountId) {
                $selectedAccount = $accountsCollection->firstWhere('id', $accountId);
            }
            if (!$selectedAccount) {
                $selectedAccount = $accountsCollection->firstWhere('is_primary', true) ?? $accountsCollection->first();
            }
        }

        $storedEvidence = [];
        if ($normalizedMethod === 'manual') {
            try {
                $storedEvidence = $this->storePaymentEvidence($request);
            } catch (\Throwable $e) {
                Log::error('WalletController::fund() - Failed to store payment evidence', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload payment evidence. Please try again.',
                ], 422);
            }
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'amount' => $validated['amount'],
            'currency' => 'NGN',
            'payment_method' => $storageMethod,
            'status' => 'pending',
            'description' => $validated['description'] ?? 'Wallet funding',
            'metadata' => array_filter([
                'type' => 'wallet_funding',
                'wallet_id' => $wallet->id,
                'manual_bank_account_id' => $selectedAccount['id'] ?? null,
            ]),
            'payer_name' => $normalizedMethod === 'manual' ? ($validated['payer_name'] ?? null) : null,
            'payer_phone' => $normalizedMethod === 'manual' ? ($validated['payer_phone'] ?? null) : null,
            'bank_reference' => $normalizedMethod === 'manual' ? ($validated['transaction_reference'] ?? null) : null,
            'bank_name' => $selectedAccount['bank_name'] ?? null,
            'account_number' => $selectedAccount['account_number'] ?? null,
            'account_name' => $selectedAccount['account_name'] ?? null,
            'account_details' => $selectedAccount ? json_encode($selectedAccount) : null,
            'payment_evidence' => $normalizedMethod === 'manual' ? $storedEvidence : [],
        ]);

        Log::info('WalletController::fund() - Payment record created', [
            'payment_id' => $payment->id,
            'reference' => $reference,
        ]);

        $walletTransaction = $wallet->transactions()->create([
            'type' => 'credit',
            'amount' => $validated['amount'],
            'status' => 'pending',
            'payment_method' => $storageMethod,
            'payment_reference' => $reference,
            'description' => $validated['description'] ?? 'Wallet funding',
            'metadata' => array_filter([
                'payment_id' => $payment->id,
                'payer_name' => $payment->payer_name,
                'bank_reference' => $payment->bank_reference,
            ]),
        ]);

        Log::info('WalletController::fund() - Wallet transaction created', [
            'wallet_transaction_id' => $walletTransaction->id,
        ]);

        try {
            $response = match($normalizedMethod) {
                'paystack' => $this->initializePaystack($payment, $user),
                'remita' => $this->initializeRemita($payment, $user),
                'stripe' => $this->initializeStripe($payment, $user),
                'manual', 'bank_transfer' => $this->initializeManualPayment($payment),
                default => throw new \InvalidArgumentException('Unsupported payment method')
            };

            Log::info('WalletController::fund() - Payment initialization succeeded', [
                'payment_method' => $normalizedMethod,
                'payment_id' => $payment->id,
            ]);

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('WalletController::fund() - Payment initialization failed', [
                'error' => $e->getMessage(),
                'payment_method' => $normalizedMethod,
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'trace' => app()->environment('production') ? null : $e->getTraceAsString(),
        ]);

        return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment: ' . ($e->getMessage()),
            ], 500);
        }
    }

    private function initializePaystack(Payment $payment, $user): array
    {
        $paystackService = $this->makePaystackService();

        if (!$paystackService) {
            Log::warning('WalletController::initializePaystack() - Paystack gateway not configured');
            return [
                'success' => false,
                'message' => 'Paystack payment gateway is not available',
            ];
        }

        $response = $paystackService->initialize([
            'amount' => $payment->amount * 100, // Convert to kobo
            'email' => $user->email,
            'reference' => $payment->reference,
            'callback_url' => config('app.url') . '/api/payments/callback',
            'metadata' => [
                'payment_id' => $payment->id,
                'type' => 'wallet_funding',
            ],
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

    private function initializeRemita(Payment $payment, $user): array
    {
        $remitaService = $this->makeRemitaService();

        if (!$remitaService) {
            Log::warning('WalletController::initializeRemita() - Remita gateway not configured');
            return [
                'success' => false,
                'message' => 'Remita payment gateway is not available',
            ];
        }

        $response = $remitaService->initialize([
            'amount' => $payment->amount,
            'customer_email' => $user->email,
            'customer_name' => $user->full_name ?? $user->name,
            'description' => $payment->description,
        ]);

        $payment->update([
            'gateway_reference' => $response['rrr'],
            'gateway_url' => $response['payment_url'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Payment initialized successfully',
            'data' => [
                'rrr' => $response['rrr'],
                'status' => 'pending',
                'payment_url' => $response['payment_url'] ?? null,
            ]
        ];
    }

    private function initializeStripe(Payment $payment, $user): array
    {
        $stripeService = $this->makeStripeService();

        $response = $stripeService->createPaymentIntent([
            'amount' => $payment->amount * 100, // Convert to cents
            'currency' => strtolower($payment->currency),
            'metadata' => [
                'reference' => $payment->reference,
                'user_id' => $payment->user_id,
                'type' => 'wallet_funding',
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

    private function initializeManualPayment(Payment $payment): array
    {
        $bankDetails = array_filter([
            'bank_name' => $payment->bank_name,
            'account_number' => $payment->account_number,
            'account_name' => $payment->account_name,
        ]);

        return [
            'success' => true,
            'message' => 'Manual payment submitted successfully. Awaiting verification by the accounts team.',
            'data' => array_filter([
                'reference' => $payment->reference,
                'status' => 'pending',
                'bank_details' => !empty($bankDetails) ? $bankDetails : null,
                'requires_approval' => true,
            ]),
        ];
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
            'provider' => 'required|in:paystack,remita,stripe',
        ]);

        try {
            DB::beginTransaction();

            $payment = Payment::where('reference', $request->reference)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            if ($payment->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already verified',
                    'data' => [
                        'reference' => $payment->reference,
                        'status' => 'completed'
                    ]
                ]);
            }

            $verificationResult = match($request->provider) {
                'paystack' => $this->verifyPaystack($payment),
                'remita' => $this->verifyRemita($payment),
                'stripe' => $this->verifyStripe($payment),
                default => throw new \InvalidArgumentException('Unsupported payment provider')
            };

            if ($verificationResult['success']) {
                // Credit wallet
                $wallet = Wallet::find($payment->metadata['wallet_id']);
                if ($wallet) {
                    $wallet->deposit($payment->amount);

                    // Update wallet transaction
                    $walletTransaction = WalletTransaction::where('payment_reference', $payment->reference)
                        ->where('wallet_id', $wallet->id)
                        ->first();

                    if ($walletTransaction) {
                        $walletTransaction->update([
                            'status' => 'completed',
                        ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified and wallet credited successfully',
                    'data' => [
                        'reference' => $payment->reference,
                        'status' => 'completed',
                        'wallet_balance' => $wallet->fresh()->balance ?? 0,
                    ]
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'error' => $verificationResult['message'] ?? 'Verification failed'
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function verifyPaystack(Payment $payment): array
    {
        $paystackService = $this->makePaystackService();

        if (!$paystackService) {
            return [
                'success' => false,
                'message' => 'Paystack payment gateway is not available',
            ];
        }

        $response = $paystackService->verify($payment->gateway_reference);
        
        if ($response['status']) {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gateway_response' => $response,
            ]);
        }

        return [
            'success' => $response['status'],
            'message' => $response['status'] ? 'Payment verified successfully' : 'Payment verification failed',
        ];
    }

    private function verifyRemita(Payment $payment): array
    {
        $remitaService = $this->makeRemitaService();

        if (!$remitaService) {
            return [
                'success' => false,
                'message' => 'Remita payment gateway is not available',
            ];
        }

        $response = $remitaService->verify($payment->gateway_reference);
        
        if ($response['status'] === 'success') {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gateway_response' => $response,
            ]);
        }

        return [
            'success' => $response['status'] === 'success',
            'message' => $response['status'] === 'success' ? 'Payment verified successfully' : 'Payment verification failed',
        ];
    }

    private function verifyStripe(Payment $payment): array
    {
        $stripeService = $this->makeStripeService();

        $response = $stripeService->verify($payment->gateway_reference);
        
        if ($response['status'] === 'succeeded') {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'gateway_response' => $response,
            ]);
        }

        return [
            'success' => $response['status'] === 'succeeded',
            'message' => $response['status'] === 'succeeded' ? 'Payment verified successfully' : 'Payment verification failed',
        ];
    }

    private function makeStripeService(): StripeService
    {
        return app(StripeService::class);
    }

    private function storePaymentEvidence(Request $request): array
    {
        $paths = [];

        $inputEvidence = $request->input('payment_evidence');
        if (is_array($inputEvidence)) {
            foreach ($inputEvidence as $value) {
                if (is_string($value) && $value !== '') {
                    $paths[] = $value;
                }
            }
        }

        $files = $request->file('payment_evidence', []);

        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (is_array($files)) {
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $storedPath = $file->store('wallet-payments/evidence', 'public');
                $paths[] = Storage::url($storedPath);
            }
        }

        return array_values(array_unique($paths));
    }

    private function getManualGatewayConfig(): array
    {
        $default = [
            'require_payer_name' => true,
            'require_payer_phone' => false,
            'require_transaction_reference' => true,
            'require_payment_evidence' => true,
            'bank_accounts' => [],
        ];

        $gateway = $this->getTenantGateway('manual');

        if (!$gateway) {
            return $default;
        }

        $config = $gateway->configuration ?? [];

        if (!empty($config['bank_accounts']) && is_array($config['bank_accounts'])) {
            $config['bank_accounts'] = array_map(function ($account) {
                if (is_array($account)) {
                    $account['id'] = $account['id'] ?? (string) Str::uuid();
                }

                return $account;
            }, $config['bank_accounts']);
        }

        return array_merge($default, is_array($config) ? $config : []);
    }

    private function makePaystackService(): ?PaystackService
    {
        $gateway = $this->getTenantGateway('paystack');

        if (!$gateway) {
            return null;
        }

        return PaystackService::fromCredentials($gateway->credentials ?? []);
    }

    private function ensurePaystackDedicatedAccount($user, bool $forceRefresh = false): PaystackDedicatedAccount
    {
        $existing = PaystackDedicatedAccount::where('user_id', $user->id)->first();
        if ($existing && !$forceRefresh && $existing->status === 'active') {
            return $existing;
        }

        $gateway = $this->getTenantGateway('paystack');
        if (!$gateway) {
            throw new \RuntimeException('Paystack gateway is not configured');
        }

        $paystackService = $this->makePaystackService();
        if (!$paystackService) {
            throw new \RuntimeException('Unable to initialise Paystack service');
        }

        $customerData = $this->resolvePaystackCustomer($paystackService, $user, $existing);
        $customerCode = $customerData['customer_code'] ?? $customerData['code'] ?? null;
        if (!$customerCode) {
            throw new \RuntimeException('Unable to resolve Paystack customer code');
        }

        $preferredBank = 'titan-paystack';
        $config = is_array($gateway->configuration) ? $gateway->configuration : [];
        if (!empty($config['preferred_bank']) && is_string($config['preferred_bank'])) {
            $preferredBank = $config['preferred_bank'];
        }

        $remoteAccount = null;

        try {
            $remoteAccounts = $paystackService->listDedicatedAccounts(['customer' => $customerCode]);
            if (!empty($remoteAccounts)) {
                $remoteAccount = collect($remoteAccounts)->first(function ($account) use ($preferredBank) {
                    $slug = $account['bank']['slug'] ?? null;
                    return $slug === $preferredBank;
                }) ?? $remoteAccounts[0];
            }
        } catch (\Throwable $e) {
            Log::warning('WalletController::ensurePaystackDedicatedAccount() - Unable to list dedicated accounts', [
                'error' => $e->getMessage(),
            ]);
        }

        if (!$remoteAccount || $forceRefresh) {
            $payload = [
                'customer' => $customerCode,
                'preferred_bank' => $preferredBank,
                'currency' => 'NGN',
                'account_name' => $this->generatePaystackAccountName($user),
            ];

            $remoteAccount = $paystackService->createDedicatedAccount($payload);
        }

        if (!$remoteAccount) {
            throw new \RuntimeException('Unable to create Paystack dedicated account');
        }

        $bank = $remoteAccount['bank'] ?? [];
        $accountData = [
            'user_id' => $user->id,
            'customer_code' => $customerCode,
            'customer_id' => $customerData['id'] ?? $existing?->customer_id,
            'dedicated_account_id' => $remoteAccount['id'] ?? $existing?->dedicated_account_id,
            'account_number' => $remoteAccount['account_number'],
            'account_name' => $remoteAccount['account_name'] ?? $this->generatePaystackAccountName($user),
            'bank_name' => $bank['name'] ?? ($remoteAccount['bank_name'] ?? null),
            'bank_slug' => $bank['slug'] ?? ($remoteAccount['bank_slug'] ?? null),
            'currency' => strtoupper($remoteAccount['currency'] ?? 'NGN'),
            'status' => $remoteAccount['status'] ?? 'active',
            'data' => $remoteAccount,
        ];

        return PaystackDedicatedAccount::updateOrCreate(
            ['user_id' => $user->id],
            $accountData
        );
    }

    private function resolvePaystackCustomer(PaystackService $paystackService, $user, ?PaystackDedicatedAccount $existing): array
    {
        if ($existing && $existing->customer_code) {
            return [
                'customer_code' => $existing->customer_code,
                'id' => $existing->customer_id,
            ];
        }

        $customer = null;
        if (!empty($user->email)) {
            $customer = $paystackService->findCustomerByEmail($user->email);
        }

        if ($customer) {
            return $customer;
        }

        $nameParts = $this->splitName($user->name ?? '');
        $payload = array_filter([
            'email' => $user->email ?? null,
            'first_name' => $user->first_name ?? $nameParts['first_name'] ?? null,
            'last_name' => $user->last_name ?? $nameParts['last_name'] ?? null,
            'phone' => $user->phone ?? $user->phone_number ?? null,
        ]);

        return $paystackService->createCustomer($payload);
    }

    private function generatePaystackAccountName($user): string
    {
        $base = $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $base = $base ?: 'Smart Housing Member';
        return strtoupper(substr($base, 0, 24));
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));
        if (!$parts || count($parts) === 0) {
            return [
                'first_name' => null,
                'last_name' => null,
            ];
        }

        $firstName = array_shift($parts);
        $lastName = count($parts) > 0 ? implode(' ', $parts) : null;

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    private function makeRemitaService(): ?RemitaService
    {
        $gateway = $this->getTenantGateway('remita');

        if (!$gateway) {
            return null;
        }

        return RemitaService::fromCredentials($gateway->credentials ?? []);
    }

    private function getTenantGateway(string $type): ?PaymentGateway
    {
        $tenant = tenant();
        if (!$tenant) {
            Log::warning('WalletController::getTenantGateway() - Tenant not resolved');
            return null;
        }

        return PaymentGateway::where('tenant_id', $tenant->id)
            ->where('gateway_type', $type)
            ->where('is_enabled', true)
            ->first();
    }
}
