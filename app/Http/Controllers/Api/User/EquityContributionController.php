<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\EquityPlan;
use App\Services\Communication\NotificationService;
use App\Services\Tenant\TenantPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EquityContributionController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TenantPaymentService $tenantPaymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $member = $request->user()->member;
        
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $query = EquityContribution::where('member_id', $member->id)
            ->with(['plan'])
            ->latest('created_at');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $contributions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $contributions->items(),
            'pagination' => [
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
                'per_page' => $contributions->perPage(),
                'total' => $contributions->total(),
            ]
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        try {
            $methods = $this->tenantPaymentService->getAvailablePaymentMethods('equity_contribution');

            return response()->json([
                'success' => true,
                'payment_methods' => $methods,
            ]);
        } catch (\Throwable $exception) {
            Log::error('EquityContributionController::paymentMethods() failed', [
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to load equity payment methods at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function show(string $id, Request $request): JsonResponse
    {
        $member = $request->user()->member;
        
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found'
            ], 404);
        }

        $contribution = EquityContribution::where('member_id', $member->id)
            ->with(['plan'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contribution
        ]);
    }

    public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'plan_id' => 'nullable|exists:equity_plans,id',
        'amount' => 'required|numeric|min:100',
        'payment_method' => 'required|string',
        'payment_reference' => 'nullable|string|max:255',
        'notes' => 'nullable|string|max:1000',
        'payer_name' => 'nullable|string|max:255',
        'payer_phone' => 'nullable|string|max:255',
        'transaction_reference' => 'nullable|string|max:255',
        'bank_account_id' => 'nullable|string|max:255',
        'payment_evidence' => 'sometimes|array',
        'payment_evidence.*' => 'nullable|string',
    ]);

    $member = $request->user()->member;
    
    if (!$member) {
        return response()->json([
            'success' => false,
            'message' => 'Member profile not found'
        ], 404);
    }

    // Validate plan if provided
    if ($validated['plan_id']) {
        $plan = EquityPlan::findOrFail($validated['plan_id']);
        if (!$plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Selected equity plan is not active'
            ], 400);
        }
        if ($validated['amount'] < $plan->min_amount) {
            return response()->json([
                'success' => false,
                'message' => "Minimum amount for this plan is ₦" . number_format($plan->min_amount, 2)
            ], 400);
        }
        if ($plan->max_amount && $validated['amount'] > $plan->max_amount) {
            return response()->json([
                'success' => false,
                'message' => "Maximum amount for this plan is ₦" . number_format($plan->max_amount, 2)
            ], 400);
        }
    }

    $availableMethods = collect($this->tenantPaymentService->getAvailablePaymentMethods('equity_contribution'));

    $requestedMethod = $validated['payment_method'];
    $methodExists = $availableMethods->firstWhere('id', $requestedMethod);
    if (!$methodExists) {
        return response()->json([
            'success' => false,
            'message' => 'Selected payment method is not available.',
        ], 422);
    }

    $normalizedMethod = $requestedMethod === 'bank_transfer' ? 'manual' : $requestedMethod;

    if (!in_array($normalizedMethod, ['paystack', 'remita', 'stripe', 'manual', 'wallet'], true)) {
        return response()->json([
            'success' => false,
            'message' => 'Unsupported payment method selected.',
        ], 422);
    }

    $manualConfig = null;
    $manualAccounts = [];

    if ($normalizedMethod === 'manual') {
        $configMethod = $methodExists;
        if ($requestedMethod === 'bank_transfer') {
            $configMethod = $availableMethods->firstWhere('id', 'manual') ?? $methodExists;
        }

        $manualConfig = $this->prepareManualConfigFromMethod($configMethod);
        $manualAccounts = $this->normalizeManualAccounts($manualConfig['bank_accounts'] ?? []);

        $manualRules = [
            'payer_name' => Rule::requiredIf($manualConfig['require_payer_name'] ?? true),
            'payer_phone' => Rule::requiredIf($manualConfig['require_payer_phone'] ?? false),
            'transaction_reference' => Rule::requiredIf($manualConfig['require_transaction_reference'] ?? true),
            'payment_evidence' => Rule::requiredIf($manualConfig['require_payment_evidence'] ?? true),
        ];

        $request->validate([
            'payer_name' => [$manualRules['payer_name'], 'nullable', 'string', 'max:255'],
            'payer_phone' => [$manualRules['payer_phone'], 'nullable', 'string', 'max:255'],
            'transaction_reference' => [$manualRules['transaction_reference'], 'nullable', 'string', 'max:255'],
            'payment_evidence' => [$manualRules['payment_evidence'], 'sometimes', 'array'],
            'payment_evidence.*' => 'nullable|string',
        ]);

        if (count($manualAccounts) > 1) {
            $request->validate([
                'bank_account_id' => ['required', 'string'],
            ]);
        }
    }

    try {
        DB::beginTransaction();

        $contribution = EquityContribution::create([
            'member_id' => $member->id,
            'plan_id' => $validated['plan_id'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $requestedMethod,
            'payment_reference' => $validated['payment_reference'] ?? 'EQ-' . time() . '-' . rand(1000, 9999),
            'status' => $this->getInitialStatus($requestedMethod),
            'notes' => $validated['notes'] ?? null,
            'payment_metadata' => array_filter([
                'payer_name' => $validated['payer_name'] ?? null,
                'payer_phone' => $validated['payer_phone'] ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_method_input' => $requestedMethod,
            ]),
        ]);

        $selectedAccount = null;
        $storedEvidence = [];

        if ($normalizedMethod === 'manual') {
            $selectedAccount = $this->resolveManualAccount($manualAccounts, $request->input('bank_account_id'));
            if (!$selectedAccount && !empty($manualAccounts)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a valid destination account for your manual payment.',
                ], 422);
            }

            $storedEvidence = $this->storeEquityPaymentEvidence($request);

            $contribution->update([
                'payment_metadata' => array_filter(array_merge($contribution->payment_metadata ?? [], [
                    'manual_account' => $selectedAccount,
                    'payment_evidence' => $storedEvidence,
                ])),
            ]);
        }

        $paymentMetadata = array_filter([
            'type' => 'equity_contribution',
            'equity_contribution_id' => $contribution->id,
            'plan_id' => $contribution->plan_id,
            'notes' => $validated['notes'] ?? null,
            'manual_account' => $selectedAccount,
            'payer_name' => $validated['payer_name'] ?? null,
            'payer_phone' => $validated['payer_phone'] ?? null,
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'payment_evidence' => $storedEvidence,
            'member_id' => $member->id,
        ], fn ($value) => $value !== null);

        $paymentPayload = [
            'user_id' => $member->user_id,
            'amount' => (float) $contribution->amount,
            'payment_method' => $normalizedMethod,
            'description' => $contribution->plan_id
                ? sprintf('Equity contribution for plan %s', $contribution->plan?->name ?? '')
                : 'Equity contribution',
            'payment_type' => 'equity_contribution',
            'metadata' => $paymentMetadata,
        ];

        if ($normalizedMethod === 'manual') {
            $paymentPayload['payer_name'] = $validated['payer_name'] ?? null;
            $paymentPayload['payer_phone'] = $validated['payer_phone'] ?? null;
            $paymentPayload['account_details'] = $selectedAccount ? json_encode($selectedAccount) : null;
            $paymentPayload['payment_evidence'] = $storedEvidence;
        } elseif ($normalizedMethod === 'wallet') {
            $paymentPayload['metadata']['wallet_impact'] = 'equity_wallet';
        }

        $paymentResult = $this->tenantPaymentService->initializePayment($paymentPayload);

        if (!$paymentResult['success']) {
            throw new \RuntimeException($paymentResult['message'] ?? 'Failed to initialize payment');
        }

        /** @var \App\Models\Tenant\Payment $payment */
        $payment = $paymentResult['payment'];

        // Update contribution with payment reference but don't set status to approved yet
        $metadataUpdate = array_filter(array_merge($contribution->payment_metadata ?? [], [
            'payment_id' => $payment->id,
            'gateway_reference' => $payment->gateway_reference ?? null,
            'gateway_response' => $payment->gateway_response ?? null,
        ]));

        $contribution->update([
            'payment_reference' => $payment->reference,
            'payment_method' => $payment->payment_method,
            'payment_metadata' => $metadataUpdate,
        ]);

        // Handle completed payments - finalize the contribution
        if ($payment->status === 'completed') {
            Log::info('Processing completed payment for equity contribution', [
                'payment_id' => $payment->id,
                'contribution_id' => $contribution->id,
                'amount' => $payment->amount,
                'payment_status' => $payment->status,
            ]);

            try {
                // Call the service method to finalize payment and update wallet
                $this->tenantPaymentService->finalizeEquityContributionPayment($payment);
                
                // Refresh the contribution to get updated data from service
                $contribution->refresh();

                Log::info('Successfully finalized equity contribution payment', [
                    'payment_id' => $payment->id,
                    'contribution_id' => $contribution->id,
                    'new_status' => $contribution->status,
                    'approved_at' => $contribution->approved_at,
                    'paid_at' => $contribution->paid_at,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to finalize equity contribution payment', [
                    'payment_id' => $payment->id,
                    'contribution_id' => $contribution->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Rollback the entire transaction and re-throw
                DB::rollBack();
                throw $e;
            }
        }

        DB::commit();

        // Send notification for manual payments
        if ($normalizedMethod === 'manual') {
            $memberName = trim($member->first_name . ' ' . $member->last_name);
            $this->notificationService->notifyAdminsNewEquityContribution(
                $contribution->id,
                $memberName,
                $contribution->amount
            );
        }

        $response = [
            'success' => true,
            'message' => $paymentResult['message'] ?? 'Equity contribution initialized successfully.',
            'data' => $contribution->fresh(),
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'payment_method' => $payment->payment_method,
            'payment_url' => $paymentResult['payment_url'] ?? null,
            'requires_approval' => $paymentResult['requires_approval'] ?? ($normalizedMethod === 'manual'),
            'manual_instructions' => null,
        ];

        if ($normalizedMethod === 'manual' && $selectedAccount) {
            $response['manual_instructions'] = [
                'account' => $selectedAccount,
                'requires_payment_evidence' => $manualConfig['require_payment_evidence'] ?? true,
                'message' => $paymentResult['message'] ?? 'Equity contribution submitted. Awaiting admin approval.',
            ];
        }

        return response()->json($response, 201);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error creating equity contribution', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'member_id' => $member->id,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to create equity contribution',
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
        ], 500);
    }
}

    private function getInitialStatus(string $paymentMethod): string
    {
        // Auto-approve for payment gateways, pending for manual
        return in_array($paymentMethod, ['paystack', 'remita', 'stripe', 'wallet'], true) ? 'approved' : 'pending';
    }

    private function prepareManualConfigFromMethod(array $method): array
    {
        $configuration = $method['configuration'] ?? [];
        $defaults = $this->getDefaultManualConfig();

        $config = array_merge($defaults, is_array($configuration) ? $configuration : []);
        $config['bank_accounts'] = $this->normalizeManualAccounts($config['bank_accounts'] ?? []);

        return $config;
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

    protected function normalizeManualAccounts(array $accounts): array
    {
        return collect($accounts)
            ->filter(fn ($account) => is_array($account))
            ->map(function (array $account) {
                $account['id'] = $account['id'] ?? (string) Str::uuid();
                return $account;
            })
            ->values()
            ->all();
    }

    protected function resolveManualAccount(array $accounts, ?string $accountId): ?array
    {
        $collection = collect($accounts);

        if ($accountId) {
            $match = $collection->firstWhere('id', $accountId);
            if ($match) {
                return $match;
            }
        }

        $primary = $collection->firstWhere('is_primary', true);

        return $primary ?? $collection->first();
    }

    protected function storeEquityPaymentEvidence(Request $request): array
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

                $storedPath = $file->store('equity-contributions/payments/evidence', 'public');
                $paths[] = Storage::url($storedPath);
            }
        }

        return array_values(array_unique($paths));
    }
}

