<?php

namespace App\Http\Controllers\Api\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\LoanRepaymentRequest;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanRepayment;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Wallet;
use App\Services\Payment\PaymentService;
use App\Services\Tenant\TenantPaymentService;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanRepaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected TenantPaymentService $tenantPaymentService,
        protected NotificationService $notificationService
    ) {}

    public function paymentMethods(Request $request): JsonResponse
    {
        
        
        $methods = collect($this->tenantPaymentService->getAvailablePaymentMethods('loan_repayment'))
            ->map(function (array $method) {
                $id = $method['id'] ?? '';

                if ($id === 'manual') {
                    $method['id'] = 'bank_transfer';
                    $method['name'] = $method['name'] ?? 'Bank Transfer';
                    $method['description'] = $method['description'] ?? 'Transfer to the cooperative bank account and upload proof of payment.';
                } elseif (in_array($id, ['paystack', 'remita', 'stripe'], true)) {
                    $method['id'] = 'card';
                    $method['name'] = 'Debit/Credit Card';
                    $method['description'] = 'Pay securely using your bank card.';
                } elseif ($id === 'wallet') {
                    $method['name'] = $method['name'] ?? 'Wallet';
                    $method['description'] = $method['description'] ?? 'Pay directly from your wallet balance.';
                }

                return [
                    'id' => $method['id'],
                    'name' => $method['name'],
                    'description' => $method['description'] ?? '',
                    'icon' => $method['icon'] ?? 'credit-card',
                    'is_enabled' => (bool) ($method['is_enabled'] ?? true),
                    'configuration' => $method['id'] === 'bank_transfer' ? ($method['configuration'] ?? null) : null,
                ];
            })
            ->filter(fn (array $method) => in_array($method['id'], ['wallet', 'card', 'bank_transfer'], true))
            ->unique('id')
            ->values();
            

        return response()->json([
            'payment_methods' => $methods,
        ]);
    }

    public function repay(LoanRepaymentRequest $request, string $loanId): JsonResponse
    {
        
        try {
            DB::beginTransaction();

            $loan = Loan::find($loanId);
            if (!$loan) {
                return response()->json([
                    'message' => 'Loan not found'
                ], 404);
            }

            $user = $request->user();
            $member = $user->member;

            if (!$member || $loan->member_id !== $member->id) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($loan->status !== 'approved') {
                return response()->json([
                    'message' => 'Loan is not approved'
                ], 400);
            }

            $totalRepaid = $loan->repayments()->sum('amount');
            $remainingAmount = $loan->total_amount - $totalRepaid;

            if ($remainingAmount <= 0) {
                return response()->json([
                    'message' => 'Loan is already fully repaid'
                ], 400);
            }

            if ($request->amount > $remainingAmount) {
                return response()->json([
                    'message' => 'Repayment amount exceeds remaining balance'
                ], 400);
            }

            $manualConfig = null;
            $manualAccounts = [];
            $selectedManualAccount = null;

            if ($request->payment_method === 'bank_transfer') {
                $manualMethod = collect($this->tenantPaymentService->getAvailablePaymentMethods('loan_repayment'))
                    ->first(function ($method) {
                        $id = $method['id'] ?? null;
                        return $id === 'manual' || $id === 'bank_transfer';
                    });

                if ($manualMethod && isset($manualMethod['configuration']) && is_array($manualMethod['configuration'])) {
                    $manualConfig = $manualMethod['configuration'];
                    $manualAccounts = $this->normalizeManualAccounts($manualConfig['bank_accounts'] ?? []);
                    $selectedManualAccount = $this->resolveManualAccount($manualAccounts, $request->input('bank_account_id'));
                }

                if (empty($manualAccounts)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Manual bank accounts are not configured. Please contact support or choose another payment method.',
                    ], 422);
                }

                if (count($manualAccounts) > 1 && !$request->filled('bank_account_id')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Select the cooperative bank account you paid into.',
                    ], 422);
                }

                if (count($manualAccounts) > 0 && !$selectedManualAccount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected bank account is not valid for manual repayments.',
                    ], 422);
                }

                if (($manualConfig['require_payment_evidence'] ?? true) && empty($request->input('payment_evidence'))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Upload at least one proof of payment to continue.',
                    ], 422);
                }
            }

            $metadata = array_filter([
                'loan_id' => $loan->id,
                'payment_method' => $request->payment_method,
                'notes' => $request->input('notes'),
                'bank_account_id' => $selectedManualAccount['id'] ?? null,
                'manual_account' => $selectedManualAccount,
                'transaction_reference' => $request->input('transaction_reference'),
            ], fn ($value) => $value !== null);

            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'currency' => 'NGN',
                'type' => 'loan_repayment',
                'status' => 'pending',
                'reference' => 'REPAY-' . time() . '-' . rand(1000, 9999),
                'description' => "Loan repayment for loan #{$loan->id}",
                'payment_method' => $request->payment_method,
                'approval_status' => $request->payment_method === 'bank_transfer' ? 'pending' : 'approved',
                'payment_evidence' => $request->input('payment_evidence', []),
                'payer_name' => $request->input('payer_name'),
                'payer_phone' => $request->input('payer_phone'),
                'bank_reference' => $request->input('transaction_reference'),
                'bank_name' => $selectedManualAccount['bank_name'] ?? null,
                'account_number' => $selectedManualAccount['account_number'] ?? null,
                'account_name' => $selectedManualAccount['account_name'] ?? null,
                'account_details' => $selectedManualAccount ? json_encode($selectedManualAccount) : null,
                'metadata' => $metadata,
            ]);

            $paymentResult = $this->processRepayment(
                $request,
                $payment,
                $request->payment_method,
                $request->amount,
                $selectedManualAccount
            );

            if (!($paymentResult['success'] ?? false)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'] ?? 'Unable to process repayment',
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Loan repayment initiated successfully',
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                ],
                'loan_details' => [
                    'loan_id' => $loan->id,
                    'total_amount' => $loan->total_amount,
                    'total_repaid' => $totalRepaid,
                    'remaining_amount' => $remainingAmount - $request->amount,
                ],
                'payment_data' => $paymentResult['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Loan repayment failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRepaymentSchedule(Request $request, String $loanId): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;
        $loan = Loan::find($loanId);
        
        if (!$member || $loan->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $loanAmount = $loan->amount;
        $interestRate = $loan->interest_rate / 100;
        $tenureMonths = $loan->duration_months;
        $monthlyRate = $interestRate / 12;
        $monthlyPayment = $loan->monthly_payment ?? ($loan->total_amount / $tenureMonths);
        
        // Calculate amortization schedule
        $schedule = [];
        $remainingBalance = $loanAmount;
        $totalPrincipalRepaid = $loan->getTotalPrincipalRepaid();
        $startDate = $loan->application_date ?? now();

        for ($month = 1; $month <= $tenureMonths; $month++) {
            $interestPortion = $remainingBalance * $monthlyRate;
            $principalPortion = $monthlyPayment - $interestPortion;
            
            if ($remainingBalance < $principalPortion) {
                $principalPortion = $remainingBalance;
                $monthlyPayment = $principalPortion + $interestPortion;
            }

            $dueDate = $startDate->copy()->addMonths($month);
            
            // Find matching repayment
            $repayment = $loan->repayments()
                ->where('due_date', '<=', $dueDate)
                ->where('status', 'paid')
                ->orderBy('due_date', 'desc')
                ->first();

            $isPaid = $repayment && $repayment->principal_paid >= $principalPortion * 0.99; // 99% tolerance

            $schedule[] = [
                'installment' => $month,
                'due_date' => $dueDate->format('Y-m-d'),
                'principal' => round($principalPortion, 2),
                'interest' => round($interestPortion, 2),
                'total' => round($monthlyPayment, 2),
                'remaining_balance' => round($remainingBalance - $principalPortion, 2),
                'status' => $isPaid ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'pending'),
                'paid_date' => $repayment ? $repayment->paid_at?->format('Y-m-d') : null,
                'repayment_id' => $repayment?->id,
            ];

            $remainingBalance -= $principalPortion;
            if ($remainingBalance <= 0) {
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'loan_id' => $loan->id,
                'loan_amount' => (float) $loanAmount,
                'interest_rate' => (float) $loan->interest_rate,
                'duration_months' => $tenureMonths,
                'monthly_payment' => (float) $monthlyPayment,
                'total_principal_repaid' => (float) $totalPrincipalRepaid,
                'total_interest_paid' => (float) $loan->getTotalInterestPaid(),
                'remaining_principal' => (float) $loan->getRemainingPrincipal(),
                'is_fully_repaid' => $loan->isFullyRepaid(),
                'schedule' => $schedule,
            ]
        ]);
    }

    public function getRepaymentHistory(Request $request, String $loanId): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;
        $loan = Loan::find($loanId);
        if (!$member || $loan->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $repayments = $loan->repayments()
            ->orderBy('payment_date', 'desc')
            ->paginate(15);

        return response()->json([
            'repayments' => $repayments,
            'pagination' => [
                'current_page' => $repayments->currentPage(),
                'last_page' => $repayments->lastPage(),
                'per_page' => $repayments->perPage(),
                'total' => $repayments->total(),
            ]
        ]);
    }

    private function processRepayment(Request $request, Payment $payment, string $method, float $amount, ?array $manualAccount = null): array
    {
        switch ($method) {
            case 'wallet':
                return $this->processWalletRepayment($request, $payment, $amount);
            case 'card':
                return $this->paymentService->initializeCardPayment($payment);
            case 'bank_transfer':
                $result = $this->paymentService->initializeBankTransfer($payment);

                if (($result['success'] ?? false) && $manualAccount) {
                    $result['data'] = array_merge(
                        [
                            'reference' => $payment->reference,
                            'requires_payment_evidence' => true,
                        ],
                        $result['data'] ?? [],
                        [
                            'account' => $manualAccount,
                            'payer_name' => $request->input('payer_name'),
                            'transaction_reference' => $request->input('transaction_reference'),
                            'notes' => $request->input('notes'),
                        ]
                    );
                }

                return $result;
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported payment method',
                ];
        }
    }

    private function processWalletRepayment(Request $request, Payment $payment, float $amount): array
    {
        $user = $request->user();
        $member = $user->member;


        $wallet = Wallet::where('user_id', $user->id)->first();
        
        if (!$wallet || $wallet->balance < $amount) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance',
            ];
        }

        // Deduct from wallet
        $wallet->decrement('balance', $amount);

        // Update payment status
        $payment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Create loan repayment record
        $repayment = LoanRepayment::create([
            'loan_id' => $payment->metadata['loan_id'],
            'amount' => $amount,
            'due_date' => now()->toDateString(),
            'paid_at' => now(),
            'payment_method' => 'wallet',
            'reference' => $payment->reference,
            'status' => 'paid',
        ]);

        // Check if loan is fully repaid
        $loan = Loan::find($payment->metadata['loan_id']);
        $loan->load('member.user');
        $totalRepaid = $loan->repayments()->sum('amount');
        
        if ($totalRepaid >= $loan->total_amount) {
            $loan->update(['status' => 'completed']);
            
            // Notify member that loan is fully repaid
            if ($loan->member && $loan->member->user) {
                $this->notificationService->sendNotificationToUsers(
                    [$loan->member->user->id],
                    'success',
                    'Loan Fully Repaid',
                    'Congratulations! Your loan of ₦' . number_format($loan->total_amount, 2) . ' has been fully repaid.',
                    [
                        'loan_id' => $loan->id,
                        'total_amount' => $loan->total_amount,
                    ]
                );
            }
        } else {
            // Notify member about successful repayment
            if ($loan->member && $loan->member->user) {
                $remainingBalance = $loan->total_amount - $totalRepaid;
                $this->notificationService->sendNotificationToUsers(
                    [$loan->member->user->id],
                    'success',
                    'Loan Repayment Successful',
                    'Your loan repayment of ₦' . number_format($amount, 2) . ' was successful. Remaining balance: ₦' . number_format($remainingBalance, 2),
                    [
                        'loan_id' => $loan->id,
                        'repayment_id' => $repayment->id,
                        'amount' => $amount,
                        'remaining_balance' => $remainingBalance,
                    ]
                );
            }
        }

        return [
            'success' => true,
            'message' => 'Repayment processed successfully',
            'data' => [
                'repayment_id' => $repayment->id,
                'wallet_balance' => $wallet->fresh()->balance,
            ],
        ];
    }

    private function normalizeManualAccounts(array $accounts): array
    {
        return collect($accounts)
            ->filter(fn ($account) => is_array($account))
            ->map(function (array $account) {
                return [
                    'id' => (string) ($account['id'] ?? Str::uuid()->toString()),
                    'bank_name' => $account['bank_name'] ?? null,
                    'account_name' => $account['account_name'] ?? null,
                    'account_number' => $account['account_number'] ?? null,
                    'instructions' => $account['instructions'] ?? null,
                    'is_primary' => (bool) ($account['is_primary'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveManualAccount(array $accounts, ?string $accountId): ?array
    {
        if (empty($accounts)) {
            return null;
        }

        if ($accountId) {
            foreach ($accounts as $account) {
                if (($account['id'] ?? null) === $accountId) {
                    return $account;
                }
            }
        }

        foreach ($accounts as $account) {
            if (!empty($account['is_primary'])) {
                return $account;
            }
        }

        return $accounts[0];
    }
}
