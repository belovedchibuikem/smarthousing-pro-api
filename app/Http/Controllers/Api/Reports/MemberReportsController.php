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
use App\Exports\MemberReports\ContributionReportExport;
use App\Exports\MemberReports\LoanReportExport;
use App\Exports\MemberReports\InvestmentReportExport;
use App\Exports\MemberReports\PropertyReportExport;
use App\Exports\MemberReports\MortgageReportExport;
use App\Exports\MemberReports\FinancialSummaryExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class MemberReportsController extends Controller
{
    /**
     * Get contribution report for authenticated member
     */
    public function contributions(Request $request): JsonResponse
    {
       
        $member = $request->user()->member;
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
        $member = $request->user()->member;
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
        $member = $request->user()->member;
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

        // Pre-fetch investment returns to avoid N+1 queries
        $investmentIds = $investments->pluck('id')->toArray();
        $returnsByInvestment = DB::table('investment_returns')
            ->whereIn('investment_id', $investmentIds)
            ->select('investment_id', DB::raw('SUM(amount) as total_returns'))
            ->groupBy('investment_id')
            ->pluck('total_returns', 'investment_id');

        $totalInvested = $investments->sum('amount');
        $totalReturns = (float) DB::table('investment_returns')
            ->whereIn('investment_id', $investmentIds)
            ->sum('amount');
        $currentValue = $totalInvested + $totalReturns;
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
            'investments' => $investments->map(function ($inv) use ($returnsByInvestment) {
                $totalReturns = (float) ($returnsByInvestment->get($inv->id) ?? 0);
                $currentVal = $inv->amount + $totalReturns;
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
        $member = $request->user()->member;
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
        $member = $request->user()->member;
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
        // Increase memory limit for this endpoint
        ini_set('memory_limit', '256M');
        set_time_limit(60);

        try {
            $member = $request->user()->member;
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;

            // Contributions - use aggregate query
            $contributionsQuery = Contribution::where('member_id', $member->id)->where('status', 'approved');
            if ($dateFrom && $dateTo) {
                $contributionsQuery->whereBetween('contribution_date', [$dateFrom, $dateTo]);
            }
            $totalContributions = (float) $contributionsQuery->sum('amount');

            // Investments - calculate returns from InvestmentReturn table
            $investmentsQuery = Investment::where('member_id', $member->id)->where('status', 'active');
            if ($dateFrom && $dateTo) {
                $investmentsQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            $totalInvestments = (float) $investmentsQuery->sum('amount');
            
            // Get investment IDs and calculate total returns efficiently
            $investmentIds = $investmentsQuery->pluck('id')->toArray();
            $totalReturns = (float) DB::table('investment_returns')
                ->whereIn('investment_id', $investmentIds)
                ->sum('amount');
            $investmentReturns = $totalReturns;

            // Loans - optimize to avoid loading all loans
            $loansQuery = Loan::where('member_id', $member->id)->where('status', 'approved');
            if ($dateFrom && $dateTo) {
                $loansQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            $totalLoans = (float) $loansQuery->sum('amount');
            
            // Pre-calculate loan repayments efficiently
            $loanIds = $loansQuery->pluck('id')->toArray();
            $totalRepaid = (float) DB::table('loan_repayments')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'paid')
                ->sum('principal_paid');
            $loanBalance = $totalLoans - $totalRepaid;

            // Properties - optimize to avoid N+1 queries
            $propertyInterests = PropertyInterest::where('member_id', $member->id)
                ->where('status', 'approved')
                ->with(['property:id,price', 'paymentPlan:id'])
                ->select(['id', 'property_id', 'member_id'])
                ->get();

            // Get property IDs and calculate totals efficiently
            $propertyIds = $propertyInterests->pluck('property_id')->filter()->unique()->toArray();
            $totalProperties = (float) DB::table('properties')
                ->whereIn('id', $propertyIds)
                ->sum('price');

            // Pre-fetch all property payment transactions in one query
            $propertyEquity = (float) PropertyPaymentTransaction::whereIn('property_id', $propertyIds)
                ->where('member_id', $member->id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->sum('amount');

            // Wallet
            $user = $request->user();
            $wallet = $user->wallet;
            $walletBalance = $wallet ? (float) $wallet->balance : 0;

            // Calculate totals
            $totalAssets = $totalContributions + $totalInvestments + $propertyEquity + $walletBalance;
            $totalLiabilities = $loanBalance;
            $netWorth = $totalAssets - $totalLiabilities;

            // Monthly trends (last 6 months) - optimize queries
            $monthlyData = [];
            // Pre-fetch loan IDs for expense calculation
            $allLoanIds = Loan::where('member_id', $member->id)->pluck('id')->toArray();
            
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();

                $income = (float) Contribution::where('member_id', $member->id)
                    ->where('status', 'approved')
                    ->whereBetween('contribution_date', [$monthStart, $monthEnd])
                    ->sum('amount');

                // Use whereIn instead of whereHas for better performance
                $expenses = (float) LoanRepayment::whereIn('loan_id', $allLoanIds)
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
        } catch (\Exception $e) {
            Log::error('Financial summary error: ' . $e->getMessage(), [
                'member_id' => $request->user()->member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to load financial summary: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get mortgages report (Mortgage and Internal Mortgage) for authenticated member
     */
    public function mortgages(Request $request): JsonResponse
    {
        $member = $request->user()->member;
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
                    'monthly_payment' => (float) ($m->monthly_payment ?? 0),
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

    /**
     * Export contribution report
     */
    public function exportContributions(Request $request)
    {
        // Increase memory limit for exports
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        try {
            $member = $request->user()->member;
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $format = $request->get('format', 'excel'); // 'excel' or 'pdf'
            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
            $status = $request->get('status', 'all');
            $period = $request->get('period', 'all');

            $query = Contribution::with(['plan:id,name'])
                ->where('member_id', $member->id)
                ->select(['id', 'plan_id', 'amount', 'type', 'status', 'reference', 'contribution_date']);

            if ($dateFrom && $dateTo) {
                $query->whereBetween('contribution_date', [$dateFrom, $dateTo]);
            } elseif ($period !== 'all') {
                $dateRange = $this->getPeriodRange($period);
                $query->whereBetween('contribution_date', $dateRange);
            }

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Use chunking for large datasets
            $data = collect();
            $query->orderByDesc('contribution_date')->chunk(500, function ($chunk) use (&$data) {
                $data = $data->merge($chunk->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'date' => $c->contribution_date?->toDateString(),
                        'amount' => (float) $c->amount,
                        'type' => $c->type ?? 'Monthly',
                        'status' => $c->status,
                        'reference' => $c->reference ?? 'CONT-' . $c->id,
                        'plan' => $c->plan ? $c->plan->name : null,
                    ];
                }));
            });

            // Calculate stats efficiently
            $statsQuery = Contribution::where('member_id', $member->id)->where('status', 'approved');
            if ($dateFrom && $dateTo) {
                $statsQuery->whereBetween('contribution_date', [$dateFrom, $dateTo]);
            } elseif ($period !== 'all') {
                $dateRange = $this->getPeriodRange($period);
                $statsQuery->whereBetween('contribution_date', $dateRange);
            }

            $stats = [
                'total_contributions' => (float) $statsQuery->sum('amount'),
                'this_month' => (float) $statsQuery->whereMonth('contribution_date', Carbon::now()->month)
                    ->whereYear('contribution_date', Carbon::now()->year)
                    ->sum('amount'),
                'total_payments' => $statsQuery->count(),
            ];

            if ($format === 'pdf') {
                return $this->exportContributionsPDF($request, $data, $stats, $dateFrom, $dateTo);
            }

            $fileName = 'contribution-report-' . now()->format('Y-m-d-His') . '.xlsx';
            return Excel::download(new ContributionReportExport($data, $stats), $fileName);
        } catch (\Exception $e) {
            Log::error('Export contributions error: ' . $e->getMessage(), [
                'member_id' => $request->user()->member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export equity contribution report
     */
    public function exportEquityContributions(Request $request)
    {
        $member = $request->user()->member;
        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $format = $request->get('format', 'excel');
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

        $data = $equityContributions->map(function ($c) {
            return [
                'id' => $c->id,
                'date' => $c->created_at->toDateString(),
                'amount' => (float) $c->amount,
                'status' => $c->status,
                'reference' => $c->reference ?? 'EQ-' . $c->id,
                'plan' => $c->plan ? $c->plan->name : null,
            ];
        });

        $stats = [
            'total_contributions' => $equityContributions->where('status', 'approved')->sum('amount'),
            'total_payments' => $equityContributions->where('status', 'approved')->count(),
        ];

        if ($format === 'pdf') {
            return $this->exportEquityContributionsPDF($request, $data, $stats, $dateFrom, $dateTo);
        }

        $fileName = 'equity-contribution-report-' . now()->format('Y-m-d-His') . '.xlsx';
        return Excel::download(new ContributionReportExport($data, $stats), $fileName);
    }

    /**
     * Export investment report
     */
    public function exportInvestments(Request $request)
    {
        // Increase memory limit for exports
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        try {
            $member = $request->user()->member;
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $format = $request->get('format', 'excel');
            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
            $type = $request->get('type', 'all');
            $status = $request->get('status', 'all');

            $query = Investment::with(['plan:id,name'])
                ->where('member_id', $member->id)
                ->select(['id', 'plan_id', 'amount', 'type', 'status', 'created_at']);

            if ($dateFrom && $dateTo) {
                $query->whereBetween('created_at', [$dateFrom, $dateTo]);
            }

            if ($type !== 'all') {
                $query->where('type', $type);
            }

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Pre-fetch investment returns to avoid N+1 queries
            // Clone query to get IDs without affecting the main query
            $investmentIds = (clone $query)->pluck('id')->toArray();
            $returnsByInvestment = DB::table('investment_returns')
                ->whereIn('investment_id', $investmentIds)
                ->select('investment_id', DB::raw('SUM(amount) as total_returns'))
                ->groupBy('investment_id')
                ->pluck('total_returns', 'investment_id');

            // Use chunking for large datasets
            $data = collect();
            $query->orderByDesc('created_at')->chunk(500, function ($chunk) use (&$data, $returnsByInvestment) {
                $data = $data->merge($chunk->map(function ($inv) use ($returnsByInvestment) {
                    $totalReturns = (float) ($returnsByInvestment->get($inv->id) ?? 0);
                    $currentVal = $inv->amount + $totalReturns;
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
                }));
            });

            // Calculate stats efficiently
            $statsQuery = Investment::where('member_id', $member->id);
            if ($dateFrom && $dateTo) {
                $statsQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            if ($type !== 'all') {
                $statsQuery->where('type', $type);
            }
            if ($status !== 'all') {
                $statsQuery->where('status', $status);
            }

            // Pre-fetch returns for stats calculation
            $statsInvestmentIds = $statsQuery->pluck('id')->toArray();
            $totalReturnsForStats = (float) DB::table('investment_returns')
                ->whereIn('investment_id', $statsInvestmentIds)
                ->sum('amount');
            
            $investmentsForStats = $statsQuery->get(['id', 'amount', 'status']);
            $totalInvested = $investmentsForStats->sum('amount');
            $currentValue = $totalInvested + $totalReturnsForStats;

            $stats = [
                'total_invested' => $totalInvested,
                'current_value' => $currentValue,
                'total_roi' => $currentValue - $totalInvested,
                'roi_percentage' => $totalInvested > 0 ? (($currentValue - $totalInvested) / $totalInvested) * 100 : 0,
                'active_investments' => $investmentsForStats->where('status', 'active')->count(),
            ];

            if ($format === 'pdf') {
                return $this->exportInvestmentsPDF($request, $data, $stats, $dateFrom, $dateTo);
            }

            $fileName = 'investment-report-' . now()->format('Y-m-d-His') . '.xlsx';
            return Excel::download(new InvestmentReportExport($data, $stats), $fileName);
        } catch (\Exception $e) {
            Log::error('Export investments error: ' . $e->getMessage(), [
                'member_id' => $request->user()->member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export loan report
     */
    public function exportLoans(Request $request)
    {
        // Increase memory limit for exports
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        try {
            $member = $request->user()->member;
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $format = $request->get('format', 'excel');
            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
            $status = $request->get('status', 'all');
            $loanType = $request->get('loan_type', 'all');

            $query = Loan::with(['repayments:id,loan_id,principal_paid,interest_paid,status'])
                ->where('member_id', $member->id)
                ->select(['id', 'reference', 'type', 'amount', 'interest_rate', 'status', 'due_date']);

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

            // Pre-calculate repayment totals to avoid N+1 queries
            $loanIds = $loans->pluck('id')->toArray();
            $repaymentTotals = DB::table('loan_repayments')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'paid')
                ->select('loan_id', DB::raw('SUM(principal_paid) as total_principal'), DB::raw('SUM(interest_paid) as total_interest'))
                ->groupBy('loan_id')
                ->pluck('total_principal', 'loan_id')
                ->toArray();

            $interestTotals = DB::table('loan_repayments')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'paid')
                ->select('loan_id', DB::raw('SUM(interest_paid) as total_interest'))
                ->groupBy('loan_id')
                ->pluck('total_interest', 'loan_id')
                ->toArray();

            $data = $loans->map(function ($loan) use ($repaymentTotals, $interestTotals) {
                $repaid = (float) ($repaymentTotals[$loan->id] ?? 0);
                $balance = $loan->amount - $repaid;
                $progress = $loan->amount > 0 ? ($repaid / $loan->amount) * 100 : 0;

                return [
                    'id' => $loan->id,
                    'reference' => $loan->reference ?? 'LOAN-' . $loan->id,
                    'type' => $loan->type ?? 'Personal',
                    'amount' => (float) $loan->amount,
                    'repaid' => $repaid,
                    'balance' => (float) $balance,
                    'interest_rate' => (float) ($loan->interest_rate ?? 0),
                    'status' => $loan->status,
                    'due_date' => $loan->due_date?->toDateString(),
                    'progress' => round($progress, 1),
                ];
            });

            $totalBorrowed = $loans->sum('amount');
            $totalRepaid = array_sum($repaymentTotals);
            $totalInterestPaid = array_sum($interestTotals);

            $stats = [
                'total_borrowed' => $totalBorrowed,
                'total_repaid' => $totalRepaid,
                'outstanding_balance' => $totalBorrowed - $totalRepaid,
                'interest_paid' => $totalInterestPaid,
            ];

            if ($format === 'pdf') {
                return $this->exportLoansPDF($request, $data, $stats, $dateFrom, $dateTo);
            }

            $fileName = 'loan-report-' . now()->format('Y-m-d-His') . '.xlsx';
            return Excel::download(new LoanReportExport($data, $stats), $fileName);
        } catch (\Exception $e) {
            Log::error('Export loans error: ' . $e->getMessage(), [
                'member_id' => $request->user()->member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export property report
     */
    public function exportProperties(Request $request)
    {
        // Increase memory limit for exports
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        try {
            $member = $request->user()->member;
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $format = $request->get('format', 'excel');
            $propertyType = $request->get('property_type', 'all');
            $paymentStatus = $request->get('payment_status', 'all');
            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;

            $query = PropertyInterest::with([
                'property:id,title,type,location,size,price',
                'paymentPlan:id,payment_methods'
            ])
                ->where('member_id', $member->id)
                ->where('status', 'approved')
                ->select(['id', 'property_id', 'member_id', 'created_at']);

            if ($propertyType !== 'all') {
                $query->whereHas('property', function ($q) use ($propertyType) {
                    $q->where('type', $propertyType);
                });
            }

            $interests = $query->get();

            // Pre-fetch all payment transactions in one query to avoid N+1
            $propertyIds = $interests->pluck('property_id')->filter()->unique()->toArray();
            $paymentTransactions = PropertyPaymentTransaction::whereIn('property_id', $propertyIds)
                ->where('member_id', $member->id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->select('property_id', 'amount', 'paid_at')
                ->get()
                ->groupBy('property_id');

            // Pre-fetch last payment dates
            $lastPayments = PropertyPaymentTransaction::whereIn('property_id', $propertyIds)
                ->where('member_id', $member->id)
                ->where('status', 'completed')
                ->select('property_id', DB::raw('MAX(paid_at) as last_paid_at'))
                ->groupBy('property_id')
                ->pluck('last_paid_at', 'property_id');

            $data = $interests->map(function ($interest) use ($paymentStatus, $dateFrom, $dateTo, $paymentTransactions, $lastPayments) {
                $property = $interest->property;
                $plan = $interest->paymentPlan;

                if (!$property) return null;

                $totalCost = (float) ($property->price ?? 0);
                
                // Get amount paid from pre-fetched data
                $transactions = $paymentTransactions->get($property->id, collect());
                $amountPaid = (float) $transactions->sum('amount');
                
                $paymentMethod = 'Cash';
                if ($plan) {
                    $methods = $plan->payment_methods ?? [];
                    if (!empty($methods)) {
                        $paymentMethod = is_array($methods) ? implode(', ', $methods) : $methods;
                    }
                }

                $paymentProgress = $totalCost > 0 ? ($amountPaid / $totalCost) * 100 : 0;
                $isCompleted = $paymentProgress >= 100;

                if ($paymentStatus !== 'all') {
                    if ($paymentStatus === 'completed' && !$isCompleted) return null;
                    if ($paymentStatus === 'ongoing' && $isCompleted) return null;
                }

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
                    'last_payment' => $lastPayments->get($property->id)?->toDateString(),
                    'payment_method' => $paymentMethod,
                ];
            })->filter()->values();

            $stats = [
                'total_properties' => $data->count(),
                'completed_properties' => $data->where('payment_status', 'completed')->count(),
                'ongoing_properties' => $data->where('payment_status', 'ongoing')->count(),
                'total_invested' => $data->sum('amount_paid'),
                'total_value' => $data->sum('total_cost'),
            ];

            if ($format === 'pdf') {
                return $this->exportPropertiesPDF($request, $data, $stats, $dateFrom, $dateTo);
            }

            $fileName = 'property-report-' . now()->format('Y-m-d-His') . '.xlsx';
            return Excel::download(new PropertyReportExport($data, $stats), $fileName);
        } catch (\Exception $e) {
            Log::error('Export properties error: ' . $e->getMessage(), [
                'member_id' => $request->user()->member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export mortgage report
     */
    public function exportMortgages(Request $request)
    {
        // Increase memory limit for exports
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        try {
            $member = $request->user()->member;
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $format = $request->get('format', 'excel');
            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;
            $mortgageType = $request->get('mortgage_type', 'all');

            $mortgagesQuery = Mortgage::with(['provider:id,name'])
                ->where('member_id', $member->id)
                ->select(['id', 'provider_id', 'loan_amount', 'interest_rate', 'monthly_payment', 'status', 'reference', 'created_at']);

            if ($dateFrom && $dateTo) {
                $mortgagesQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }

            if ($mortgageType === 'internal') {
                $mortgagesQuery->whereRaw('1 = 0');
            }

            $mortgages = $mortgageType !== 'internal' ? $mortgagesQuery->get() : collect();

            $internalMortgagesQuery = InternalMortgagePlan::where('member_id', $member->id)
                ->select(['id', 'principal', 'interest_rate', 'monthly_payment', 'status', 'title', 'created_at']);

            if ($dateFrom && $dateTo) {
                $internalMortgagesQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }

            if ($mortgageType === 'mortgage') {
                $internalMortgagesQuery->whereRaw('1 = 0');
            }

            $internalMortgages = $mortgageType !== 'mortgage' ? $internalMortgagesQuery->get() : collect();

            // Pre-fetch repayment totals to avoid N+1 queries
            $mortgageIds = $mortgages->pluck('id')->toArray();
            $mortgageRepaymentTotals = DB::table('mortgage_repayments')
                ->whereIn('mortgage_id', $mortgageIds)
                ->where('status', 'paid')
                ->select('mortgage_id', DB::raw('SUM(principal_paid) as total_principal'), DB::raw('SUM(interest_paid) as total_interest'))
                ->groupBy('mortgage_id')
                ->get()
                ->keyBy('mortgage_id');

            $internalMortgageIds = $internalMortgages->pluck('id')->toArray();
            $internalMortgageRepaymentTotals = DB::table('internal_mortgage_repayments')
                ->whereIn('internal_mortgage_plan_id', $internalMortgageIds)
                ->where('status', 'paid')
                ->select('internal_mortgage_plan_id', DB::raw('SUM(principal_paid) as total_principal'), DB::raw('SUM(interest_paid) as total_interest'))
                ->groupBy('internal_mortgage_plan_id')
                ->get()
                ->keyBy('internal_mortgage_plan_id');

            $allMortgages = collect()
                ->merge($mortgages->map(function ($m) use ($mortgageRepaymentTotals) {
                    $repayment = $mortgageRepaymentTotals->get($m->id);
                    $repaid = (float) ($repayment->total_principal ?? 0);
                    $balance = $m->loan_amount - $repaid;
                    $progress = $m->loan_amount > 0 ? ($repaid / $m->loan_amount) * 100 : 0;

                    return [
                        'id' => $m->id,
                        'type' => 'mortgage',
                        'reference' => $m->reference ?? 'MORT-' . $m->id,
                        'provider' => $m->provider ? $m->provider->name : 'N/A',
                        'amount' => (float) $m->loan_amount,
                        'repaid' => $repaid,
                        'balance' => (float) $balance,
                        'interest_rate' => (float) ($m->interest_rate ?? 0),
                        'monthly_payment' => (float) ($m->monthly_payment ?? 0),
                        'status' => $m->status,
                        'progress' => round($progress, 1),
                        'created_at' => $m->created_at->toDateString(),
                    ];
                }))
                ->merge($internalMortgages->map(function ($m) use ($internalMortgageRepaymentTotals) {
                    $repayment = $internalMortgageRepaymentTotals->get($m->id);
                    $repaid = (float) ($repayment->total_principal ?? 0);
                    $balance = $m->principal - $repaid;
                    $progress = $m->principal > 0 ? ($repaid / $m->principal) * 100 : 0;

                    return [
                        'id' => $m->id,
                        'type' => 'internal',
                        'reference' => $m->title ?? 'INT-MORT-' . $m->id,
                        'provider' => 'Internal',
                        'amount' => (float) $m->principal,
                        'repaid' => $repaid,
                        'balance' => (float) $balance,
                        'interest_rate' => (float) ($m->interest_rate ?? 0),
                        'monthly_payment' => (float) ($m->monthly_payment ?? 0),
                        'status' => $m->status,
                        'progress' => round($progress, 1),
                        'created_at' => $m->created_at->toDateString(),
                    ];
                }));

            $totalMortgageAmount = $allMortgages->sum('amount');
            $totalRepaid = $allMortgages->sum('repaid');
            $totalInterestPaid = $mortgageRepaymentTotals->sum('total_interest') + $internalMortgageRepaymentTotals->sum('total_interest');

            $stats = [
                'total_mortgages' => $allMortgages->count(),
                'total_mortgage_amount' => $totalMortgageAmount,
                'total_repaid' => $totalRepaid,
                'outstanding_balance' => $totalMortgageAmount - $totalRepaid,
                'interest_paid' => (float) $totalInterestPaid,
            ];

            if ($format === 'pdf') {
                return $this->exportMortgagesPDF($request, $allMortgages->values(), $stats, $dateFrom, $dateTo);
            }

            $fileName = 'mortgage-report-' . now()->format('Y-m-d-His') . '.xlsx';
            return Excel::download(new MortgageReportExport($allMortgages->values(), $stats), $fileName);
        } catch (\Exception $e) {
            Log::error('Export mortgages error: ' . $e->getMessage(), [
                'member_id' => $request->user()->member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export financial summary
     */
    public function exportFinancialSummary(Request $request)
    {
        // Increase memory limit for exports
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        try {
            $member = $request->user()->member;
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }

            $format = $request->get('format', 'excel');
            $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : null;
            $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : null;

            // Get all data efficiently using aggregate queries
            $contributionsQuery = Contribution::where('member_id', $member->id)->where('status', 'approved');
            if ($dateFrom && $dateTo) {
                $contributionsQuery->whereBetween('contribution_date', [$dateFrom, $dateTo]);
            }
            $totalContributions = (float) $contributionsQuery->sum('amount');

            $investmentsQuery = Investment::where('member_id', $member->id)->where('status', 'active');
            if ($dateFrom && $dateTo) {
                $investmentsQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            $totalInvestments = (float) $investmentsQuery->sum('amount');
            
            // Calculate investment returns from InvestmentReturn table
            $investmentIds = $investmentsQuery->pluck('id')->toArray();
            $investmentReturns = (float) DB::table('investment_returns')
                ->whereIn('investment_id', $investmentIds)
                ->sum('amount');

            $loansQuery = Loan::where('member_id', $member->id)->where('status', 'approved');
            if ($dateFrom && $dateTo) {
                $loansQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }
            $totalLoans = (float) $loansQuery->sum('amount');
            
            // Calculate loan balance efficiently
            $loanIds = $loansQuery->pluck('id')->toArray();
            $totalRepaid = (float) DB::table('loan_repayments')
                ->whereIn('loan_id', $loanIds)
                ->where('status', 'paid')
                ->sum('principal_paid');
            $loanBalance = $totalLoans - $totalRepaid;

            // Get property data efficiently
            $propertyInterests = PropertyInterest::where('member_id', $member->id)
                ->where('status', 'approved')
                ->with(['property:id,price', 'paymentPlan:id'])
                ->select(['id', 'property_id', 'member_id'])
                ->get();

            $propertyIds = $propertyInterests->pluck('property_id')->filter()->unique()->toArray();
            $totalProperties = (float) DB::table('properties')
                ->whereIn('id', $propertyIds)
                ->sum('price');

            $propertyEquity = (float) PropertyPaymentTransaction::whereIn('property_id', $propertyIds)
                ->where('member_id', $member->id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->sum('amount');
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }
            $wallet = $user->wallet;
            $walletBalance = $wallet ? (float) $wallet->balance : 0;

            $totalAssets = $totalContributions + $totalInvestments + $propertyEquity + $walletBalance;
            $totalLiabilities = $loanBalance;
            $netWorth = $totalAssets - $totalLiabilities;

            // Calculate monthly data efficiently
            $monthlyData = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();

                $income = (float) Contribution::where('member_id', $member->id)
                    ->where('status', 'approved')
                    ->whereBetween('contribution_date', [$monthStart, $monthEnd])
                    ->sum('amount');

                $expenses = (float) LoanRepayment::whereIn('loan_id', $loanIds)
                    ->whereBetween('paid_at', [$monthStart, $monthEnd])
                    ->sum('amount');

                $monthlyData[] = [
                    'month' => $month->format('M'),
                    'income' => $income,
                    'expenses' => $expenses,
                ];
            }

            $financialData = [
                'total_contributions' => $totalContributions,
                'total_investments' => $totalInvestments,
                'total_loans' => $totalLoans,
                'total_properties' => $totalProperties,
                'wallet_balance' => $walletBalance,
                'loan_balance' => $loanBalance,
                'investment_returns' => $investmentReturns,
                'property_equity' => $propertyEquity,
            ];

            if ($format === 'pdf') {
                return $this->exportFinancialSummaryPDF($request, $financialData, $netWorth, $totalAssets, $totalLiabilities, $monthlyData, $dateFrom, $dateTo);
            }

            $fileName = 'financial-summary-' . now()->format('Y-m-d-His') . '.xlsx';
            return Excel::download(new FinancialSummaryExport($financialData, $netWorth, $totalAssets, $totalLiabilities, $monthlyData), $fileName);
        } catch (\Exception $e) {
            Log::error('Export financial summary error: ' . $e->getMessage(), [
                'member_id' => $request->user()->member->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    // PDF Export Methods
    private function exportContributionsPDF(Request $request, $data, $stats, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('reports.pdf.contribution', [
            'contributions' => $data,
            'stats' => $stats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'member' => $request->user()->member,
        ]);
        return $pdf->download('contribution-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    private function exportEquityContributionsPDF(Request $request, $data, $stats, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('reports.pdf.equity-contribution', [
            'contributions' => $data,
            'stats' => $stats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'member' => $request->user()->member,
        ]);
        return $pdf->download('equity-contribution-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    private function exportInvestmentsPDF(Request $request, $data, $stats, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('reports.pdf.investment', [
            'investments' => $data,
            'stats' => $stats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'member' => $request->user()->member,
        ]);
        return $pdf->download('investment-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    private function exportLoansPDF(Request $request, $data, $stats, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('reports.pdf.loan', [
            'loans' => $data,
            'stats' => $stats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'member' => $request->user()->member,
        ]);
        return $pdf->download('loan-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    private function exportPropertiesPDF(Request $request, $data, $stats, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('reports.pdf.property', [
            'properties' => $data,
            'stats' => $stats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'member' => $request->user()->member,
        ]);
        return $pdf->download('property-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    private function exportMortgagesPDF(Request $request, $data, $stats, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('reports.pdf.mortgage', [
            'mortgages' => $data,
            'stats' => $stats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'member' => $request->user()->member,
        ]);
        return $pdf->download('mortgage-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    private function exportFinancialSummaryPDF(Request $request, $financialData, $netWorth, $totalAssets, $totalLiabilities, $monthlyData, $dateFrom, $dateTo)
    {
        $pdf = Pdf::loadView('reports.pdf.financial-summary', [
            'financialData' => $financialData,
            'netWorth' => $netWorth,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'monthlyData' => $monthlyData,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'member' => $request->user()->member,
        ]);
        return $pdf->download('financial-summary-' . now()->format('Y-m-d-His') . '.pdf');
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

