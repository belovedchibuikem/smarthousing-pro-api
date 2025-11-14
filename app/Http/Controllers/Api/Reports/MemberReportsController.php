<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\Investment;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanRepayment;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\MortgageRepayment;
use App\Models\Tenant\InternalMortgagePlan;
use App\Models\Tenant\InternalMortgageRepayment;
use App\Models\Tenant\PropertyInterest;
use App\Models\Tenant\PropertyPaymentPlan;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MemberReportsController extends Controller
{
    /**
     * Get contribution report for authenticated member
     */
    public function contributions(Request $request): JsonResponse
    {
        $member = Auth::user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
        $status = $request->get('status', 'all');
        $period = $request->get('period', 'all');

        $query = Contribution::with(['plan'])
            ->where('member_id', $member->id);

        // Apply date filters
        if ($dateFrom && $dateTo) {
            $query->whereBetween('contribution_date', [$dateFrom, $dateTo]);
        } elseif ($period !== 'all') {
            $dateRange = $this->getPeriodRange($period);
            $query->whereBetween('contribution_date', $dateRange);
        }

        // Apply status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $contributions = $query->orderByDesc('contribution_date')->get();

        // Calculate stats
        $totalContributions = $contributions->where('status', 'approved')->sum('amount');
        $thisMonth = $contributions->where('status', 'approved')
            ->filter(function ($c) {
                return Carbon::parse($c->contribution_date)->isCurrentMonth();
            })
            ->sum('amount');
        $lastMonth = Contribution::where('member_id', $member->id)
            ->where('status', 'approved')
            ->whereMonth('contribution_date', Carbon::now()->subMonth()->month)
            ->sum('amount');
        $avgMonthly = $lastMonth > 0 ? $lastMonth : ($thisMonth > 0 ? $thisMonth : 0);
        $totalPayments = $contributions->where('status', 'approved')->count();

        $monthlyChange = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'total_contributions' => $totalContributions,
                'this_month' => $thisMonth,
                'average_monthly' => $avgMonthly,
                'total_payments' => $totalPayments,
                'monthly_change' => round($monthlyChange, 1),
            ],
            'contributions' => $contributions->map(function ($c) {
                return [
                    'id' => $c->id,
                    'date' => $c->contribution_date?->toDateString(),
                    'amount' => (float) $c->amount,
                    'type' => $c->type ?? 'Monthly',
                    'status' => $c->status,
                    'reference' => $c->reference ?? 'CONT-' . $c->id,
                    'plan' => $c->plan ? $c->plan->name : null,
                ];
            }),
        ]);
    }

    /**
     * Get equity contribution report for authenticated member
     */
    public function equityContributions(Request $request): JsonResponse
    {
        $member = Auth::user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
        $status = $request->get('status', 'all');

        $query = EquityContribution::with(['plan'])
            ->where('member_id', $member->id);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $equityContributions = $query->orderByDesc('created_at')->get();

        $totalContributions = $equityContributions->where('status', 'approved')->sum('amount');
        $thisMonth = $equityContributions->where('status', 'approved')
            ->filter(function ($c) {
                return Carbon::parse($c->created_at)->isCurrentMonth();
            })
            ->sum('amount');

        return response()->json([
            'success' => true,
            'stats' => [
                'total_contributions' => $totalContributions,
                'this_month' => $thisMonth,
                'total_payments' => $equityContributions->where('status', 'approved')->count(),
            ],
            'equity_contributions' => $equityContributions->map(function ($c) {
                return [
                    'id' => $c->id,
                    'date' => $c->created_at->toDateString(),
                    'amount' => (float) $c->amount,
                    'status' => $c->status,
                    'reference' => $c->reference ?? 'EQ-' . $c->id,
                    'plan' => $c->plan ? $c->plan->name : null,
                ];
            }),
        ]);
    }

    /**
     * Get investment report for authenticated member
     */
    public function investments(Request $request): JsonResponse
    {
        $member = Auth::user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
        $type = $request->get('type', 'all');
        $status = $request->get('status', 'all');

        $query = Investment::with(['plan'])
            ->where('member_id', $member->id);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $investments = $query->orderByDesc('created_at')->get();

        $totalInvested = $investments->sum('amount');
        $currentValue = $investments->sum(function ($inv) {
            return $inv->current_value ?? $inv->amount;
        });
        $totalROI = $currentValue - $totalInvested;
        $roiPercentage = $totalInvested > 0 ? ($totalROI / $totalInvested) * 100 : 0;
        $activeInvestments = $investments->where('status', 'active')->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'total_invested' => $totalInvested,
                'current_value' => $currentValue,
                'total_roi' => $totalROI,
                'roi_percentage' => round($roiPercentage, 2),
                'active_investments' => $activeInvestments,
            ],
            'investments' => $investments->map(function ($inv) {
                $currentVal = $inv->current_value ?? $inv->amount;
                $roi = $currentVal - $inv->amount;
                $roiPct = $inv->amount > 0 ? ($roi / $inv->amount) * 100 : 0;

                return [
                    'id' => $inv->id,
                    'name' => $inv->plan ? $inv->plan->name : 'Investment #' . $inv->id,
                    'type' => $inv->type ?? 'Money',
                    'amount' => (float) $inv->amount,
                    'current_value' => (float) $currentVal,
                    'roi' => round($roiPct, 2),
                    'status' => $inv->status,
                    'date' => $inv->created_at->toDateString(),
                ];
            }),
        ]);
    }

    /**
     * Get loan report for authenticated member
     */
    public function loans(Request $request): JsonResponse
    {
        $member = Auth::user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
        $status = $request->get('status', 'all');
        $loanType = $request->get('loan_type', 'all');

        $query = Loan::with(['repayments'])
            ->where('member_id', $member->id);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($loanType !== 'all') {
            $query->where('type', $loanType);
        }

        $loans = $query->orderByDesc('created_at')->get();

        $totalBorrowed = $loans->sum('amount');
        $totalRepaid = $loans->sum(function ($loan) {
            return $loan->getTotalPrincipalRepaid();
        });
        $outstandingBalance = $totalBorrowed - $totalRepaid;
        $totalInterestPaid = $loans->sum(function ($loan) {
            return $loan->getTotalInterestPaid();
        });

        return response()->json([
            'success' => true,
            'stats' => [
                'total_borrowed' => $totalBorrowed,
                'total_repaid' => $totalRepaid,
                'outstanding_balance' => $outstandingBalance,
                'interest_paid' => $totalInterestPaid,
            ],
            'loans' => $loans->map(function ($loan) {
                $repaid = $loan->getTotalPrincipalRepaid();
                $balance = $loan->amount - $repaid;
                $progress = $loan->amount > 0 ? ($repaid / $loan->amount) * 100 : 0;

                return [
                    'id' => $loan->id,
                    'reference' => $loan->reference ?? 'LOAN-' . $loan->id,
                    'type' => $loan->type ?? 'Personal',
                    'amount' => (float) $loan->amount,
                    'repaid' => (float) $repaid,
                    'balance' => (float) $balance,
                    'interest_rate' => (float) ($loan->interest_rate ?? 0),
                    'status' => $loan->status,
                    'due_date' => $loan->due_date?->toDateString(),
                    'progress' => round($progress, 1),
                ];
            }),
        ]);
    }

    /**
     * Get property report for authenticated member
     */
    public function properties(Request $request): JsonResponse
    {
        $member = Auth::user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $propertyType = $request->get('property_type', 'all');
        $paymentStatus = $request->get('payment_status', 'all');
        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;

        $query = PropertyInterest::with(['property', 'paymentPlan'])
            ->where('member_id', $member->id)
            ->where('status', 'approved');

        if ($propertyType !== 'all') {
            $query->whereHas('property', function ($q) use ($propertyType) {
                $q->where('type', $propertyType);
            });
        }

        $interests = $query->get();

        $properties = $interests->map(function ($interest) use ($paymentStatus, $dateFrom, $dateTo) {
            $property = $interest->property;
            $plan = $interest->paymentPlan;

            if (!$property) return null;

            $totalCost = (float) ($property->price ?? 0);
            $amountPaid = 0;
            $paymentMethod = 'Cash';

            if ($plan) {
                $amountPaid = (float) PropertyPaymentTransaction::where('property_id', $property->id)
                    ->where('member_id', $interest->member_id)
                    ->where('direction', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount');

                // Get payment method from plan
                $methods = $plan->payment_methods ?? [];
                if (!empty($methods)) {
                    $paymentMethod = is_array($methods) ? implode(', ', $methods) : $methods;
                }
            }

            $paymentProgress = $totalCost > 0 ? ($amountPaid / $totalCost) * 100 : 0;
            $isCompleted = $paymentProgress >= 100;

            // Filter by payment status
            if ($paymentStatus !== 'all') {
                if ($paymentStatus === 'completed' && !$isCompleted) return null;
                if ($paymentStatus === 'ongoing' && $isCompleted) return null;
            }

            // Filter by date
            if ($dateFrom && $dateTo) {
                $subscriptionDate = $interest->created_at;
                if ($subscriptionDate < $dateFrom || $subscriptionDate > $dateTo) return null;
            }

            return [
                'id' => $property->id,
                'name' => $property->title ?? 'Property #' . $property->id,
                'type' => $property->type ?? 'house',
                'location' => $property->location ?? 'N/A',
                'size' => $property->size ?? 'N/A',
                'total_cost' => $totalCost,
                'amount_paid' => $amountPaid,
                'payment_status' => $isCompleted ? 'completed' : 'ongoing',
                'subscription_date' => $interest->created_at->toDateString(),
                'last_payment' => PropertyPaymentTransaction::where('property_id', $property->id)
                    ->where('member_id', $interest->member_id)
                    ->where('status', 'completed')
                    ->latest('paid_at')
                    ->value('paid_at')?->toDateString(),
                'payment_method' => $paymentMethod,
            ];
        })->filter()->values();

        $totalProperties = $properties->count();
        $completedProperties = $properties->where('payment_status', 'completed')->count();
        $ongoingProperties = $properties->where('payment_status', 'ongoing')->count();
        $totalInvested = $properties->sum('amount_paid');
        $totalValue = $properties->sum('total_cost');
        $paymentProgress = $totalValue > 0 ? ($totalInvested / $totalValue) * 100 : 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'total_properties' => $totalProperties,
                'completed_properties' => $completedProperties,
                'ongoing_properties' => $ongoingProperties,
                'total_invested' => $totalInvested,
                'total_value' => $totalValue,
                'payment_progress' => round($paymentProgress, 1),
            ],
            'properties' => $properties,
        ]);
    }

    /**
     * Get financial summary for authenticated member
     */
    public function financialSummary(Request $request): JsonResponse
    {
        $member = Auth::user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;

        // Contributions
        $contributionsQuery = Contribution::where('member_id', $member->id)->where('status', 'approved');
        if ($dateFrom && $dateTo) {
            $contributionsQuery->whereBetween('contribution_date', [$dateFrom, $dateTo]);
        }
        $totalContributions = (float) $contributionsQuery->sum('amount');

        // Investments
        $investmentsQuery = Investment::where('member_id', $member->id)->where('status', 'active');
        if ($dateFrom && $dateTo) {
            $investmentsQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
        }
        $totalInvestments = (float) $investmentsQuery->sum('amount');
        $investmentReturns = (float) $investmentsQuery->sum(function ($inv) {
            return ($inv->current_value ?? $inv->amount) - $inv->amount;
        });

        // Loans
        $loansQuery = Loan::where('member_id', $member->id)->where('status', 'approved');
        if ($dateFrom && $dateTo) {
            $loansQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
        }
        $totalLoans = (float) $loansQuery->sum('amount');
        $loanBalance = (float) $loansQuery->get()->sum(function ($loan) {
            return $loan->amount - $loan->getTotalPrincipalRepaid();
        });

        // Properties
        $propertyInterests = PropertyInterest::where('member_id', $member->id)
            ->where('status', 'approved')
            ->with('property', 'paymentPlan')
            ->get();

        $totalProperties = (float) $propertyInterests->sum(function ($interest) {
            return (float) ($interest->property->price ?? 0);
        });

        $propertyEquity = (float) $propertyInterests->sum(function ($interest) {
            $property = $interest->property;
            if (!$property) return 0;
            return (float) PropertyPaymentTransaction::where('property_id', $property->id)
                ->where('member_id', $interest->member_id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->sum('amount');
        });

        // Wallet
        $wallet = $member->wallet;
        $walletBalance = $wallet ? (float) $wallet->balance : 0;

        // Calculate totals
        $totalAssets = $totalContributions + $totalInvestments + $propertyEquity + $walletBalance;
        $totalLiabilities = $loanBalance;
        $netWorth = $totalAssets - $totalLiabilities;

        // Monthly trends (last 6 months)
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $income = (float) Contribution::where('member_id', $member->id)
                ->where('status', 'approved')
                ->whereBetween('contribution_date', [$monthStart, $monthEnd])
                ->sum('amount');

            $expenses = (float) LoanRepayment::whereHas('loan', function ($q) use ($member) {
                $q->where('member_id', $member->id);
            })
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $monthlyData[] = [
                'month' => $month->format('M'),
                'income' => $income,
                'expenses' => $expenses,
            ];
        }

        return response()->json([
            'success' => true,
            'financial_data' => [
                'total_contributions' => $totalContributions,
                'total_investments' => $totalInvestments,
                'total_loans' => $totalLoans,
                'total_properties' => $totalProperties,
                'wallet_balance' => $walletBalance,
                'loan_balance' => $loanBalance,
                'investment_returns' => $investmentReturns,
                'property_equity' => $propertyEquity,
            ],
            'net_worth' => $netWorth,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'monthly_data' => $monthlyData,
        ]);
    }

    /**
     * Get mortgages report (Mortgage and Internal Mortgage) for authenticated member
     */
    public function mortgages(Request $request): JsonResponse
    {
        $member = Auth::user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
        $mortgageType = $request->get('mortgage_type', 'all'); // 'mortgage', 'internal', 'all'

        // External Mortgages
        $mortgagesQuery = Mortgage::with(['provider', 'repayments'])
            ->where('member_id', $member->id);

        if ($dateFrom && $dateTo) {
            $mortgagesQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        if ($mortgageType === 'internal') {
            $mortgagesQuery->whereRaw('1 = 0'); // Exclude external mortgages
        }

        $mortgages = $mortgageType !== 'internal' ? $mortgagesQuery->get() : collect();

        // Internal Mortgages
        $internalMortgagesQuery = InternalMortgagePlan::with(['repayments'])
            ->where('member_id', $member->id);

        if ($dateFrom && $dateTo) {
            $internalMortgagesQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
        }

        if ($mortgageType === 'mortgage') {
            $internalMortgagesQuery->whereRaw('1 = 0'); // Exclude internal mortgages
        }

        $internalMortgages = $mortgageType !== 'mortgage' ? $internalMortgagesQuery->get() : collect();

        // Calculate stats
        $totalMortgages = $mortgages->count() + $internalMortgages->count();
        $totalMortgageAmount = $mortgages->sum('loan_amount') + $internalMortgages->sum('principal');
        $totalRepaid = $mortgages->sum(function ($m) {
            return $m->getTotalPrincipalRepaid();
        }) + $internalMortgages->sum(function ($m) {
            return $m->getTotalPrincipalRepaid();
        });
        $outstandingBalance = $totalMortgageAmount - $totalRepaid;
        $totalInterestPaid = $mortgages->sum(function ($m) {
            return $m->getTotalInterestPaid();
        }) + $internalMortgages->sum(function ($m) {
            return $m->getTotalInterestPaid();
        });

        $allMortgages = collect()
            ->merge($mortgages->map(function ($m) {
                $repaid = $m->getTotalPrincipalRepaid();
                $balance = $m->loan_amount - $repaid;
                $progress = $m->loan_amount > 0 ? ($repaid / $m->loan_amount) * 100 : 0;

                return [
                    'id' => $m->id,
                    'type' => 'mortgage',
                    'reference' => $m->reference ?? 'MORT-' . $m->id,
                    'provider' => $m->provider ? $m->provider->name : 'N/A',
                    'amount' => (float) $m->loan_amount,
                    'repaid' => (float) $repaid,
                    'balance' => (float) $balance,
                    'interest_rate' => (float) ($m->interest_rate ?? 0),
                    'monthly_payment' => (float) ($m->monthly_payment ?? 0),
                    'status' => $m->status,
                    'progress' => round($progress, 1),
                    'created_at' => $m->created_at->toDateString(),
                ];
            }))
            ->merge($internalMortgages->map(function ($m) {
                $repaid = $m->getTotalPrincipalRepaid();
                $balance = $m->principal - $repaid;
                $progress = $m->principal > 0 ? ($repaid / $m->principal) * 100 : 0;

                return [
                    'id' => $m->id,
                    'type' => 'internal',
                    'reference' => $m->title ?? 'INT-MORT-' . $m->id,
                    'provider' => 'Internal',
                    'amount' => (float) $m->principal,
                    'repaid' => (float) $repaid,
                    'balance' => (float) $balance,
                    'interest_rate' => (float) ($m->interest_rate ?? 0),
                    'monthly_payment' => (float) ($m->periodic_payment ?? 0),
                    'status' => $m->status,
                    'progress' => round($progress, 1),
                    'created_at' => $m->created_at->toDateString(),
                ];
            }));

        return response()->json([
            'success' => true,
            'stats' => [
                'total_mortgages' => $totalMortgages,
                'total_mortgage_amount' => $totalMortgageAmount,
                'total_repaid' => $totalRepaid,
                'outstanding_balance' => $outstandingBalance,
                'interest_paid' => $totalInterestPaid,
            ],
            'mortgages' => $allMortgages->values(),
        ]);
    }

    private function getPeriodRange(string $period): array
    {
        switch ($period) {
            case 'month':
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
            case 'quarter':
                return [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()];
            case 'year':
                return [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];
            default:
                return [Carbon::now()->subYears(10), Carbon::now()];
        }
    }
}

