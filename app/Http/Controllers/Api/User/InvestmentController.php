<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Investment;
use App\Models\Tenant\InvestmentPlan;
use App\Services\Tenant\TenantPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    public function __construct(
        private TenantPaymentService $tenantPaymentService
    ) {}

    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = $this->tenantPaymentService->getAvailablePaymentMethods('contribution'); // Reuse contribution payment methods

        return response()->json([
            'payment_methods' => $methods,
        ]);
    }

    public function pay(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user?->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        if ($request->hasFile('payment_evidence') && !is_array($request->file('payment_evidence'))) {
            $request->files->set('payment_evidence', [$request->file('payment_evidence')]);
        }

        $availableMethods = collect($this->tenantPaymentService->getAvailablePaymentMethods('contribution'));
        $allowedMethods = array_unique(array_merge($availableMethods->pluck('id')->map(fn ($id) => (string) $id)->all(), ['bank_transfer']));

        $requestedMethod = (string) $request->input('payment_method', '');
        $normalizedMethod = $requestedMethod === 'bank_transfer' ? 'manual' : $requestedMethod;
        $selectedMethod = $availableMethods->firstWhere('id', $normalizedMethod);

        if (!$selectedMethod && $normalizedMethod !== 'wallet') {
            return response()->json([
                'success' => false,
                'message' => 'Selected payment method is not available.',
            ], 422);
        }

        $manualConfig = $normalizedMethod === 'manual'
            ? ($selectedMethod['configuration'] ?? $this->defaultManualConfig())
            : null;

        $manualAccounts = [];
        if ($manualConfig) {
            $manualAccounts = $this->normalizeManualAccounts($manualConfig['bank_accounts'] ?? []);
            $manualConfig['bank_accounts'] = $manualAccounts;
        }

        $rules = [
            'amount' => 'required|numeric|min:1000',
            'payment_method' => ['required', Rule::in($allowedMethods)],
            'investment_plan_id' => 'required|uuid|exists:investment_plans,id',
            'type' => 'required|in:savings,fixed_deposit,treasury_bills,bonds,stocks',
            'duration_months' => 'required|integer|min:1|max:120',
            'expected_return_rate' => 'required|numeric|min:0|max:50',
            'notes' => 'nullable|string|max:500',
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

        $amount = (float) $validated['amount'];
        $planId = $validated['investment_plan_id'];
        
        $plan = InvestmentPlan::where('is_active', true)->find($planId);
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Selected investment plan is not available.',
            ], 422);
        }

        // Validate amount against plan limits
        if ($amount < $plan->min_amount || $amount > $plan->max_amount) {
            return response()->json([
                'success' => false,
                'message' => "Investment amount must be between " . number_format($plan->min_amount, 2) . " and " . number_format($plan->max_amount, 2),
            ], 422);
        }

        // Validate duration against plan limits
        $durationMonths = (int) $validated['duration_months'];
        if ($durationMonths < $plan->min_duration_months || $durationMonths > $plan->max_duration_months) {
            return response()->json([
                'success' => false,
                'message' => "Duration must be between {$plan->min_duration_months} and {$plan->max_duration_months} months",
            ], 422);
        }

        $selectedAccount = null;
        if ($normalizedMethod === 'manual') {
            $selectedAccount = $this->resolveManualAccount($manualAccounts, $validated['bank_account_id'] ?? null);
        }

        $storedEvidence = [];
        if ($normalizedMethod === 'manual') {
            $storedEvidence = $this->storeInvestmentPaymentEvidence($request);
        }

        DB::beginTransaction();

        try {
            $investment = Investment::create([
                'member_id' => $member->id,
                'amount' => $amount,
                'type' => $validated['type'],
                'duration_months' => $durationMonths,
                'expected_return_rate' => $validated['expected_return_rate'],
                'status' => 'pending',
                'investment_date' => now(),
            ]);

            $storageMethod = $normalizedMethod === 'manual' ? 'bank_transfer' : $normalizedMethod;

            $metadata = array_filter([
                'type' => 'investment',
                'investment_id' => $investment->id,
                'investment_plan_id' => $planId,
                'notes' => $validated['notes'] ?? null,
            ], fn ($value) => $value !== null);

            $paymentPayload = [
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_method' => $normalizedMethod,
                'description' => sprintf('Investment payment for %s plan', $plan->name),
                'payment_type' => 'investment',
                'metadata' => $metadata,
            ];

            if ($normalizedMethod === 'manual') {
                $paymentPayload['payer_name'] = $validated['payer_name'] ?? null;
                $paymentPayload['payer_phone'] = $validated['payer_phone'] ?? null;
                $paymentPayload['account_details'] = $selectedAccount ? json_encode($selectedAccount) : null;
                $paymentPayload['payment_evidence'] = $storedEvidence;
            }

            $paymentResult = $this->tenantPaymentService->initializePayment($paymentPayload);

            if (!$paymentResult['success']) {
                throw new \RuntimeException($paymentResult['message'] ?? 'Failed to initialize payment.');
            }

            /** @var \App\Models\Tenant\Payment $payment */
            $payment = $paymentResult['payment'];

            if ($payment->status === 'completed') {
                // If wallet payment, auto-approve investment
                $investment->update([
                    'status' => 'active',
                    'approved_at' => now(),
                ]);
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => $paymentResult['message'] ?? 'Investment payment initialized successfully.',
                'reference' => $payment->reference,
                'payment_id' => $payment->id,
                'payment_method' => $payment->payment_method,
                'payment_url' => $paymentResult['payment_url'] ?? null,
                'requires_approval' => $paymentResult['requires_approval'] ?? false,
                'investment' => [
                    'id' => $investment->id,
                    'status' => $investment->status,
                    'amount' => (float) $investment->amount,
                ],
            ];

            if ($normalizedMethod === 'manual' && $selectedAccount) {
                $response['manual_instructions'] = [
                    'account' => $selectedAccount,
                    'requires_payment_evidence' => $manualConfig['require_payment_evidence'] ?? true,
                    'message' => 'Transfer the investment amount to the account provided and upload evidence for confirmation.',
                ];
            }

            if ($paymentResult['bank_transfer_instructions'] ?? false) {
                $response['bank_transfer_instructions'] = $paymentResult['bank_transfer_instructions'];
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('InvestmentController::pay() - Failed to process investment payment', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to process investment payment. Please try again later.'
                    : $e->getMessage(),
            ], 422);
        }
    }

    private function defaultManualConfig(): array
    {
        return [
            'require_payer_name' => true,
            'require_payer_phone' => false,
            'require_transaction_reference' => true,
            'require_payment_evidence' => true,
            'bank_accounts' => [],
        ];
    }

    private function normalizeManualAccounts(array $accounts): array
    {
        return array_map(function ($account) {
            return [
                'id' => $account['id'] ?? uniqid(),
                'bank_name' => $account['bank_name'] ?? null,
                'account_name' => $account['account_name'] ?? null,
                'account_number' => $account['account_number'] ?? null,
                'instructions' => $account['instructions'] ?? null,
                'is_primary' => $account['is_primary'] ?? false,
            ];
        }, $accounts);
    }

    private function resolveManualAccount(array $accounts, ?string $accountId): ?array
    {
        if (empty($accounts)) {
            return null;
        }

        if ($accountId) {
            $found = collect($accounts)->firstWhere('id', $accountId);
            if ($found) {
                return $found;
            }
        }

        $primary = collect($accounts)->firstWhere('is_primary', true);
        if ($primary) {
            return $primary;
        }

        return $accounts[0];
    }

    private function storeInvestmentPaymentEvidence(Request $request): array
    {
        if (!$request->hasFile('payment_evidence')) {
            return [];
        }

        $files = $request->file('payment_evidence');
        if (!is_array($files)) {
            $files = [$files];
        }

        $stored = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $path = $file->store('investment-payment-evidence', 'public');
                $stored[] = Storage::url($path);
            }
        }

        return $stored;
    }
}
