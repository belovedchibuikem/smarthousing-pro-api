<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\ContributionAutoPaySetting;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\ContributionPlan;
use App\Services\Tenant\TenantPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContributionController extends Controller
{
    public function __construct(
        private TenantPaymentService $tenantPaymentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $member = $request->user()?->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);

        $query = Contribution::with(['plan:id,name'])
            ->where('member_id', $member->id)
            ->orderByDesc('contribution_date');

        $this->applyFilters($query, $request);

        $contributions = $query->paginate($perPage);

        $stats = $this->buildStats($member->id);

        $data = $contributions->getCollection()->map(function (Contribution $contribution) {
            return [
                'id' => $contribution->id,
                'plan' => $contribution->plan ? [
                    'id' => $contribution->plan->id,
                    'name' => $contribution->plan->name,
                ] : null,
                'amount' => (float) $contribution->amount,
                'status' => $contribution->status,
                'frequency' => $contribution->frequency,
                'type' => $contribution->type,
                'contribution_date' => optional($contribution->contribution_date)->toIso8601String(),
                'approved_at' => optional($contribution->approved_at)->toIso8601String(),
                'rejection_reason' => $contribution->rejection_reason,
            ];
        });

        return response()->json([
            'stats' => $stats,
            'contributions' => $data,
            'pagination' => [
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
                'per_page' => $contributions->perPage(),
                'total' => $contributions->total(),
            ],
        ]);
    }

    public function showAutoPay(Request $request): JsonResponse
    {
        $member = $request->user()?->member;
       
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }
        try {
            $setting = $member->contributionAutoPaySetting;
        } catch (\Throwable $exception) {
            Log::error('Failed to load contribution auto-pay setting', [
                'member_id' => $member->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'setting' => [],
            ]);
        }

        $settingData = [
            'is_enabled' => false,
            'payment_method' => 'wallet',
            'amount' => null,
            'day_of_month' => 1,
            'card_reference' => null,
            'metadata' => [],
            'last_run_at' => null,
            'next_run_at' => null,
        ];

        if ($setting) {
            $settingData = [
                'is_enabled' => (bool) $setting->is_enabled,
                'payment_method' => $setting->payment_method ?? 'wallet',
                'amount' => $setting->amount !== null ? (float) $setting->amount : null,
                'day_of_month' => $setting->day_of_month ?? 1,
                'card_reference' => $setting->card_reference,
                'metadata' => $setting->metadata ?? [],
                'last_run_at' => optional($setting->last_run_at)->toIso8601String(),
                'next_run_at' => optional($setting->next_run_at)->toIso8601String(),
            ];
        }

        return response()->json([
            'setting' => $settingData,
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = $this->tenantPaymentService->getAvailablePaymentMethods('contribution');

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
            'amount' => 'required|numeric|min:100',
            'payment_method' => ['required', Rule::in($allowedMethods)],
            'plan_id' => 'nullable|uuid|exists:contribution_plans,id',
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

        $planId = $validated['plan_id'] ?? $member->contribution_plan_id;
        $plan = null;
        if ($planId) {
            $plan = ContributionPlan::where('is_active', true)->find($planId);
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected contribution plan is not available.',
                ], 422);
            }
        }

        $selectedAccount = null;
        if ($normalizedMethod === 'manual') {
            $selectedAccount = $this->resolveManualAccount($manualAccounts, $validated['bank_account_id'] ?? null);
        }

        $storedEvidence = [];
        if ($normalizedMethod === 'manual') {
            $storedEvidence = $this->storeContributionPaymentEvidence($request);
        }

        DB::beginTransaction();

        try {
            if ($plan && $member->contribution_plan_id !== $plan->id) {
                $member->update(['contribution_plan_id' => $plan->id]);
            }

            [$frequency, $contributionType] = $this->determineContributionIdentifiers($plan);

            $contribution = Contribution::create([
                'member_id' => $member->id,
                'plan_id' => $plan?->id,
                'amount' => $amount,
                'type' => $contributionType,
                'frequency' => $frequency,
                'status' => 'pending',
                'contribution_date' => Carbon::now(),
            ]);

            $storageMethod = $normalizedMethod === 'manual' ? 'bank_transfer' : $normalizedMethod;

            $contributionPayment = ContributionPayment::create([
                'contribution_id' => $contribution->id,
                'amount' => $amount,
                'payment_date' => Carbon::now()->toDateString(),
                'payment_method' => $storageMethod,
                'status' => $normalizedMethod === 'wallet' ? 'completed' : 'pending',
                'reference' => null,
                'metadata' => array_filter([
                    'notes' => $validated['notes'] ?? null,
                    'manual_account' => $selectedAccount,
                    'payment_method_input' => $requestedMethod,
                    'payer_name' => $validated['payer_name'] ?? null,
                    'payer_phone' => $validated['payer_phone'] ?? null,
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'payment_evidence' => $storedEvidence,
                ]),
            ]);

            $metadata = array_filter([
                'type' => 'contribution',
                'contribution_id' => $contribution->id,
                'contribution_payment_id' => $contributionPayment->id,
                'plan_id' => $plan?->id,
                'notes' => $validated['notes'] ?? null,
            ], fn ($value) => $value !== null);

            $paymentPayload = [
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_method' => $normalizedMethod,
                'description' => $plan
                    ? sprintf('Contribution payment for %s plan', $plan->name)
                    : 'Contribution payment',
                'payment_type' => 'contribution',
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

            $contributionPayment->update([
                'reference' => $payment->reference,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status === 'completed' ? 'completed' : $contributionPayment->status,
            ]);

            if ($payment->status === 'completed') {
                $this->tenantPaymentService->finalizeContributionPayment($payment);
                $contribution->refresh();
                $contributionPayment->refresh();
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => $paymentResult['message'] ?? 'Contribution payment initialized successfully.',
                'reference' => $payment->reference,
                'payment_id' => $payment->id,
                'payment_method' => $payment->payment_method,
                'payment_url' => $paymentResult['payment_url'] ?? null,
                'requires_approval' => $paymentResult['requires_approval'] ?? false,
                'contribution' => [
                    'id' => $contribution->id,
                    'status' => $contribution->status,
                    'amount' => (float) $contribution->amount,
                    'plan_id' => $contribution->plan_id,
                ],
            ];

            if ($normalizedMethod === 'manual' && $selectedAccount) {
                $response['manual_instructions'] = [
                    'account' => $selectedAccount,
                    'requires_payment_evidence' => $manualConfig['require_payment_evidence'] ?? true,
                    'message' => 'Transfer the contribution to the account provided and upload evidence for confirmation.',
                ];
            }

            if ($paymentResult['bank_transfer_instructions'] ?? false) {
                $response['bank_transfer_instructions'] = $paymentResult['bank_transfer_instructions'];
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('ContributionController::pay() - Failed to process contribution payment', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to process contribution payment. Please try again later.'
                    : $e->getMessage(),
            ], 422);
        }
    }

    public function plans(Request $request): JsonResponse
    {
        $member = $request->user()?->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $plansQuery = ContributionPlan::query()->where('is_active', true);

        if ($search = $request->query('search')) {
            $plansQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $plans = $plansQuery
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'amount',
                'minimum_amount',
                'frequency',
                'is_mandatory',
                'created_at',
                'updated_at',
            ]);

        $plansData = $plans->map(fn (ContributionPlan $plan) => $this->formatPlan($plan));

        $memberPlan = null;
        $currentPlan = $member->contributionPlan;

        if ($currentPlan) {
            $memberPlan = $this->buildMemberPlanSummary($member, $currentPlan);
        } else {
            $latestContribution = $member->contributions()
                ->with([
                    'plan:id,name,description,amount,minimum_amount,frequency,is_mandatory',
                ])
                ->whereNotNull('plan_id')
                ->orderByDesc('contribution_date')
                ->orderByDesc('created_at')
                ->first();

            if ($latestContribution && $latestContribution->plan) {
                $memberPlan = $this->buildMemberPlanSummary($member, $latestContribution->plan, $latestContribution);
            }
        }

        return response()->json([
            'plans' => $plansData,
            'member_plan' => $memberPlan,
        ]);
    }

    public function switchPlan(Request $request, string $planId): JsonResponse
    {
        $member = $request->user()?->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        try {
            $plan = ContributionPlan::where('is_active', true)->findOrFail($planId);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'message' => 'Contribution plan not found or inactive.',
            ], 404);
        }

        if ($member->contribution_plan_id === $plan->id) {
            return response()->json([
                'success' => true,
                'message' => 'You are already enrolled in this contribution plan.',
                'member_plan' => $this->buildMemberPlanSummary($member, $plan),
            ]);
        }

        $member->forceFill([
            'contribution_plan_id' => $plan->id,
        ])->save();

        Log::info('Member switched contribution plan', [
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'user_id' => $request->user()?->id,
        ]);

        $member->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Contribution plan updated successfully.',
            'member_plan' => $this->buildMemberPlanSummary($member, $plan),
        ]);
    }

    public function updateAutoPay(Request $request): JsonResponse
    {
        $member = $request->user()?->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $validated = $request->validate([
            'is_enabled' => 'required|boolean',
            'payment_method' => ['nullable', Rule::in(['wallet', 'card'])],
            'amount' => 'nullable|numeric|min:0',
            'day_of_month' => 'required|integer|min:1|max:28',
            'card_reference' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validated['is_enabled']) {
            if (!isset($validated['payment_method'])) {
                $validated['payment_method'] = 'wallet';
            }

            if (($validated['payment_method'] ?? 'wallet') === 'card' && empty($validated['card_reference'])) {
                return response()->json([
                    'message' => 'Card reference is required when using card payment method.',
                ], 422);
            }
        } else {
            // Disable settings, keep method for reference
            $validated['payment_method'] = $validated['payment_method'] ?? 'wallet';
        }

        $now = Carbon::now();
        $nextRun = null;

        if ($validated['is_enabled']) {
            $day = (int) $validated['day_of_month'];
            $nextRun = Carbon::create($now->year, $now->month, 1, 8, 0, 0)->day($day);
            if ($nextRun->lessThanOrEqualTo($now)) {
                $nextRun->addMonth()->day($day);
            }
        }

        $setting = ContributionAutoPaySetting::updateOrCreate(
            ['member_id' => $member->id],
            [
                'is_enabled' => $validated['is_enabled'],
                'payment_method' => $validated['payment_method'] ?? 'wallet',
                'amount' => $validated['amount'] ?? null,
                'day_of_month' => $validated['day_of_month'],
                'card_reference' => $validated['card_reference'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
                'next_run_at' => $nextRun,
            ]
        );

        Log::info('Contribution auto-pay settings updated', [
            'member_id' => $member->id,
            'is_enabled' => $setting->is_enabled,
            'payment_method' => $setting->payment_method,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Auto-payment settings updated successfully.',
            'setting' => [
                'is_enabled' => $setting->is_enabled,
                'payment_method' => $setting->payment_method,
                'amount' => $setting->amount ? (float) $setting->amount : null,
                'day_of_month' => $setting->day_of_month,
                'card_reference' => $setting->card_reference,
                'metadata' => $setting->metadata ?? [],
                'last_run_at' => optional($setting->last_run_at)->toIso8601String(),
                'next_run_at' => optional($setting->next_run_at)->toIso8601String(),
            ],
        ]);
    }

    protected function applyFilters($query, Request $request): void
    {
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', '%' . $search . '%')
                    ->orWhereHas('plan', function ($planQuery) use ($search) {
                        $planQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if (($status = $request->query('status')) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (($type = $request->query('type')) && $type !== 'all') {
            $query->where('type', $type);
        }

        if ($dateFrom = $request->query('date_from')) {
            try {
                $query->where('contribution_date', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable $e) {
                // ignore invalid date
            }
        }

        if ($dateTo = $request->query('date_to')) {
            try {
                $query->where('contribution_date', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable $e) {
                // ignore invalid date
            }
        }
    }

    protected function buildStats(string $memberId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        $approvedQuery = Contribution::where('member_id', $memberId)->where('status', 'approved');

        $totalContributions = (clone $approvedQuery)->sum('amount');
        $thisMonth = (clone $approvedQuery)->where('contribution_date', '>=', $startOfMonth)->sum('amount');
        $thisYear = (clone $approvedQuery)->where('contribution_date', '>=', $startOfYear)->sum('amount');
        $completedPayments = (clone $approvedQuery)->count();

        $monthsConsidered = Contribution::where('member_id', $memberId)
            ->where('status', 'approved')
            ->where('contribution_date', '>=', $now->copy()->subMonths(6))
            ->get()
            ->groupBy(function ($contribution) {
                return optional($contribution->contribution_date)->format('Y-m');
            })
            ->count();

        $averageMonthly = $monthsConsidered > 0 ? $totalContributions / $monthsConsidered : $totalContributions;

        $nextPending = Contribution::where('member_id', $memberId)
            ->whereIn('status', ['pending', 'scheduled'])
            ->orderBy('contribution_date', 'asc')
            ->value('contribution_date');

        return [
            'total_contributions' => (float) $totalContributions,
            'this_month' => (float) $thisMonth,
            'this_year' => (float) $thisYear,
            'average_monthly' => (float) $averageMonthly,
            'completed_payments' => $completedPayments,
            'next_due_date' => $nextPending ? Carbon::parse($nextPending)->toIso8601String() : null,
        ];
    }

    protected function determineContributionIdentifiers(?ContributionPlan $plan): array
    {
        $frequency = $plan?->frequency ?? 'one_time';

        $type = match ($frequency) {
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'annually' => 'annual',
            default => 'special',
        };

        return [$frequency, $type];
    }

    protected function defaultManualConfig(): array
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

    protected function storeContributionPaymentEvidence(Request $request): array
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

                $storedPath = $file->store('contributions/payments/evidence', 'public');
                $paths[] = Storage::url($storedPath);
            }
        }

        return array_values(array_unique($paths));
    }

    protected function formatPlan(ContributionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'amount' => (float) $plan->amount,
            'minimum_amount' => (float) $plan->minimum_amount,
            'frequency' => $plan->frequency,
            'is_mandatory' => (bool) $plan->is_mandatory,
            'created_at' => optional($plan->created_at)->toIso8601String(),
            'updated_at' => optional($plan->updated_at)->toIso8601String(),
        ];
    }

    protected function buildMemberPlanSummary($member, ContributionPlan $plan, ?Contribution $latestContribution = null): array
    {
        $summary = $member->contributions()
            ->where('plan_id', $plan->id)
            ->selectRaw('COUNT(*) as contributions_count, SUM(amount) as total_amount, MIN(contribution_date) as first_contribution, MAX(contribution_date) as last_contribution')
            ->first();

        $firstContribution = optional($summary)->first_contribution;
        $lastContribution = optional($summary)->last_contribution;

        if (!$lastContribution && $latestContribution) {
            $lastContribution = optional($latestContribution->contribution_date)->toDateTimeString();
        }

        return [
            'plan' => $this->formatPlan($plan),
            'started_at' => $firstContribution ? Carbon::parse($firstContribution)->toIso8601String() : null,
            'last_contribution_at' => $lastContribution ? Carbon::parse($lastContribution)->toIso8601String() : null,
            'contributions_count' => (int) (optional($summary)->contributions_count ?? 0),
            'total_contributed' => (float) (optional($summary)->total_amount ?? 0),
        ];
    }
}

