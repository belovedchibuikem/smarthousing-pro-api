<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLoanRepaymentRequest;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanRepayment;
use App\Models\Tenant\Member;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\PropertyPaymentPlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoanRepaymentController extends Controller
{
    public function searchMembers(Request $request): JsonResponse
    {
        try {
            $query = trim((string) $request->query('query', ''));

            if (mb_strlen($query) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Enter at least two characters to search members.',
                ]);
            }

            $members = Member::query()
                ->with([
                    'user:id,first_name,last_name,email,phone',
                    'loans' => function ($loanQuery) {
                        $loanQuery->select('id', 'member_id', 'amount', 'interest_rate', 'duration_months', 'status')
                            ->with([
                                'repayments' => function ($repaymentQuery) {
                                    $repaymentQuery->select('id', 'loan_id', 'amount', 'status');
                                },
                            ]);
                    },
                ])
                ->where(function ($memberQuery) use ($query) {
                    $memberQuery->where('member_number', 'like', "%{$query}%")
                        ->orWhereHas('user', function ($userQuery) use ($query) {
                            $userQuery->where('first_name', 'like', "%{$query}%")
                                ->orWhere('last_name', 'like', "%{$query}%")
                                ->orWhere('email', 'like', "%{$query}%")
                                ->orWhere('phone', 'like', "%{$query}%");
                        });
                })
                ->limit(15)
                ->get();

            $results = $members->map(function (Member $member) {
                $activeLoans = $member->loans->whereIn('status', ['approved', 'active']);

                $outstanding = $activeLoans->sum(function (Loan $loan) {
                    $loanTotal = $loan->total_amount ?? ($loan->amount + ($loan->amount * ($loan->interest_rate ?? 0) / 100));
                    $repaid = $loan->repayments->where('status', 'paid')->sum('amount');

                    return max($loanTotal - $repaid, 0);
                });

                $user = $member->user;
                $phone = $user->phone ?? $user->phone_number ?? null;

                return [
                    'id' => $member->id,
                    'member_number' => $member->member_number,
                    'name' => trim(sprintf('%s %s', $user->first_name ?? '', $user->last_name ?? '')) ?: 'Unknown Member',
                    'email' => $user->email ?? null,
                    'phone_number' => $phone,
                    'active_loans' => $activeLoans->count(),
                    'outstanding_balance' => (float) $outstanding,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Admin LoanRepaymentController::searchMembers failed', [
                'query' => $request->query(),
                'admin_id' => optional($request->user())->id,
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to search members at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function memberLoans(Request $request, String $member): JsonResponse
    {
        $member = Member::find($member);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }
        try {
            $member->load([
                'user:id,first_name,last_name,email,phone',
                'loans' => function ($query) {
                    $query->select('id', 'member_id', 'amount', 'interest_rate', 'duration_months', 'status', 'approved_at')
                        ->with(['product:id,name'])
                        ->with(['repayments' => function ($repaymentQuery) {
                            $repaymentQuery->orderByDesc('paid_at');
                        }])
                        ->orderByDesc('created_at');
                },
            ]);

            $loans = $member->loans->map(function (Loan $loan) {
                $totalRepaid = $loan->repayments->where('status', 'paid')->sum('amount');
                $loanTotal = $loan->total_amount ?? ($loan->amount + ($loan->amount * ($loan->interest_rate ?? 0) / 100));
                $balance = max($loanTotal - $totalRepaid, 0);
                $lastRepayment = $loan->repayments->where('status', 'paid')->sortByDesc('paid_at')->first();

                return [
                    'id' => $loan->id,
                    'status' => $loan->status,
                    'product' => $loan->product?->name,
                    'amount' => (float) $loan->amount,
                    'total_amount' => (float) $loanTotal,
                    'interest_rate' => (float) ($loan->interest_rate ?? 0),
                    'duration_months' => (int) ($loan->duration_months ?? 0),
                    'total_repaid' => (float) $totalRepaid,
                    'balance' => (float) $balance,
                    'monthly_payment' => (float) ($loan->monthly_payment ?? 0),
                    'approved_at' => optional($loan->approved_at)->toDateTimeString(),
                    'last_repayment_at' => optional($lastRepayment?->paid_at)->toDateTimeString(),
                ];
            });

            return response()->json([
                'success' => true,
                'member' => [
                    'id' => $member->id,
                    'member_number' => $member->member_number,
                    'name' => trim(sprintf('%s %s', $member->user?->first_name ?? '', $member->user?->last_name ?? '')) ?: 'Unknown Member',
                    'email' => $member->user?->email,
                    'phone_number' => $member->user?->phone ?? $member->user?->phone_number,
                ],
                'loans' => $loans,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Admin LoanRepaymentController::memberLoans failed', [
                'member_id' => $member->id,
                'admin_id' => optional($request->user())->id,
                'error' => $exception->getMessage(),
                'trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Unable to load member loans at the moment.'
                    : $exception->getMessage(),
            ], 500);
        }
    }

    public function store(StoreLoanRepaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var Loan $loan */
        $loan = Loan::with(['member', 'repayments' => function ($query) {
            $query->where('status', 'paid');
        }])->findOrFail($validated['loan_id']);

        if ($loan->member_id !== $validated['member_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Selected loan does not belong to the specified member.',
            ], 422);
        }

        if (!in_array($loan->status, ['approved', 'active', 'completed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Loan is not in a payable state.',
            ], 422);
        }

        $loanTotal = $loan->total_amount ?? ($loan->amount + ($loan->amount * ($loan->interest_rate ?? 0) / 100));
        $totalRepaid = $loan->repayments->where('status', 'paid')->sum('amount');
        $remainingAmount = max($loanTotal - $totalRepaid, 0);
        
        // Calculate principal and interest portions
        $principalPaid = $validated['principal_paid'] ?? null;
        $interestPaid = $validated['interest_paid'] ?? null;
        
        // If not provided, calculate based on remaining balance
        if ($principalPaid === null || $interestPaid === null) {
            $remainingPrincipal = $loan->getRemainingPrincipal();
            $remainingInterest = max(0, $remainingAmount - $remainingPrincipal);
            
            // Allocate payment proportionally
            if ($remainingAmount > 0) {
                $principalPaid = ($remainingPrincipal / $remainingAmount) * $validated['amount'];
                $interestPaid = $validated['amount'] - $principalPaid;
            } else {
                $principalPaid = 0;
                $interestPaid = $validated['amount'];
            }
        }
        
        // Ensure principal + interest = amount
        if (abs($principalPaid + $interestPaid - $validated['amount']) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Principal paid + Interest paid must equal total amount.',
            ], 422);
        }

        if ($remainingAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Loan is already fully repaid.',
            ], 422);
        }

        if ($validated['amount'] > $remainingAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Repayment amount exceeds remaining balance.',
            ], 422);
        }
        
        // Check principal doesn't exceed remaining principal
        $remainingPrincipal = $loan->getRemainingPrincipal();
        if ($principalPaid > $remainingPrincipal) {
            return response()->json([
                'success' => false,
                'message' => 'Principal paid exceeds remaining principal balance.',
            ], 422);
        }

        $paymentDate = !empty($validated['payment_date'])
            ? Carbon::parse($validated['payment_date'])->startOfDay()
            : now();

        try {
            DB::beginTransaction();

            $reference = $validated['reference'] ?? 'LOAN-REP-' . strtoupper(Str::random(10));
            
            $repayment = LoanRepayment::create([
                'loan_id' => $loan->id,
                'property_id' => $loan->property_id,
                'amount' => $validated['amount'],
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
                'due_date' => $paymentDate,
                'status' => 'paid',
                'paid_at' => $paymentDate,
                'payment_method' => $validated['payment_method'],
                'reference' => $reference,
                'recorded_by' => $request->user()->id,
            ]);

            // Update loan status based on principal repayment
            if ($loan->isFullyRepaid()) {
                $loan->update(['status' => 'completed']);
            } elseif ($loan->status === 'approved') {
                $loan->update(['status' => 'active']);
            }

            // Create PropertyPaymentTransaction if loan is tied to property
            if ($loan->property_id) {
                $this->createPropertyTransaction(
                    $loan->property_id,
                    $loan->member_id,
                    $principalPaid, // Only principal counts toward property progress
                    $reference,
                    'loan',
                    $loan->id
                );
            }

            DB::commit();

            // Calculate new total repaid after this payment
            $newTotalRepaid = $totalRepaid + $validated['amount'];

            return response()->json([
                'success' => true,
                'message' => 'Loan repayment recorded successfully.',
                'data' => [
                    'repayment' => [
                        'id' => $repayment->id,
                        'amount' => (float) $repayment->amount,
                        'payment_method' => $repayment->payment_method,
                        'paid_at' => optional($repayment->paid_at)->toDateTimeString(),
                        'reference' => $repayment->reference,
                    ],
                    'loan' => [
                        'id' => $loan->id,
                        'status' => $loan->status,
                        'total_amount' => (float) $loanTotal,
                        'total_repaid' => (float) $newTotalRepaid,
                        'balance' => (float) max($loanTotal - $newTotalRepaid, 0),
                    ],
                ],
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Admin LoanRepaymentController::store failed', [
                'loan_id' => $loan->id,
                'member_id' => $loan->member_id,
                'admin_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to record loan repayment. Please try again.',
                'error' => app()->environment('production') ? null : $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Create PropertyPaymentTransaction record
     */
    private function createPropertyTransaction(
        string $propertyId,
        string $memberId,
        float $amount,
        string $reference,
        string $source,
        ?string $loanId = null
    ): void {
        // Find the property payment plan for this property and member
        $plan = PropertyPaymentPlan::where('property_id', $propertyId)
            ->whereHas('interest', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->first();

        PropertyPaymentTransaction::create([
            'property_id' => $propertyId,
            'member_id' => $memberId,
            'plan_id' => $plan?->id,
            'source' => $source,
            'amount' => $amount, // Only principal amount for property progress
            'direction' => 'credit',
            'reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => [
                'loan_id' => $loanId,
                'recorded_by_admin' => true,
            ],
        ]);
    }
}

