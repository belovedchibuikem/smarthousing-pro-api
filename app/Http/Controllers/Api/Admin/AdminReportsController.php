<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Member;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Investment;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\EquityWalletBalance;
use App\Models\Tenant\EquityTransaction;
use App\Models\Tenant\Property;
use App\Models\Tenant\Mail;
use App\Models\Central\ActivityLog;
use App\Models\Tenant\LoanRepayment;
use App\Models\Tenant\ContributionPayment;
use App\Models\Tenant\InvestmentReturn;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminReportsController extends Controller
{
    /**
     * Get date range from request parameter
     */
    private function getDateRange(string $range): array
    {
        $now = Carbon::now();
        
        return match($range) {
            'this-month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last-month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'this-quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last-quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'this-year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last-year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [Carbon::parse('2020-01-01'), $now->copy()]
        };
    }

    /**
     * Format currency
     */
    private function formatCurrency($amount): string
    {
        return '₦' . number_format((float)$amount, 2);
    }

    /**
     * 1. Member Reports
     */
    public function members(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;
            
            $search = $request->get('search', '');
            $statusFilter = $request->get('status', 'all');

            // Stats
            $totalMembers = Member::count();
            $activeMembers = Member::whereHas('user', function($q) {
                $q->where('status', 'active');
            })->count();
            $pendingKyc = Member::where('kyc_status', 'pending')->count();
            $inactiveMembers = Member::whereHas('user', function($q) {
                $q->where('status', 'inactive');
            })->count();

            // Member list query
            $query = Member::with(['user:id,first_name,last_name,email,phone,status'])
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereHas('user', function($userQ) use ($search) {
                        $userQ->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%")
                              ->orWhere('phone', 'like', "%{$search}%");
                    })->orWhere('member_number', 'like', "%{$search}%");
                });
            }

            if ($statusFilter !== 'all') {
                $query->whereHas('user', function($q) use ($statusFilter) {
                    $q->where('status', $statusFilter);
                });
            }

            $members = $query->withSum('contributions', 'amount')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            $membersData = $members->map(function($member) {
                return [
                    'id' => $member->id,
                    'member_number' => $member->member_number,
                    'name' => $member->user ? ($member->user->first_name . ' ' . $member->user->last_name) : 'N/A',
                    'email' => $member->user->email ?? '',
                    'phone' => $member->user->phone ?? '',
                    'status' => $member->user->status ?? 'inactive',
                    'join_date' => $member->created_at->format('Y-m-d'),
                    'contributions' => $this->formatCurrency($member->contributions_sum_amount ?? 0),
                    'kyc_status' => $member->kyc_status ?? 'pending',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_members' => $totalMembers,
                        'active_members' => $activeMembers,
                        'pending_kyc' => $pendingKyc,
                        'inactive_members' => $inactiveMembers,
                    ],
                    'members' => $membersData,
                    'pagination' => [
                        'current_page' => $members->currentPage(),
                        'last_page' => $members->lastPage(),
                        'per_page' => $members->perPage(),
                        'total' => $members->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate member report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 2. Financial Reports
     */
    public function financial(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;

            // Revenue from contributions and loan repayments
            $totalRevenue = ContributionPayment::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount') + 
                LoanRepayment::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount');

            // Expenses from loan disbursements
            $totalExpenses = Loan::where('status', 'approved')
                ->whereBetween('approved_at', [$startDate, $endDate])
                ->sum('amount');

            $netProfit = $totalRevenue - $totalExpenses;
            $cashBalance = ContributionPayment::sum('amount') - 
                          Loan::where('status', 'approved')->sum('amount') +
                          LoanRepayment::sum('amount');

            // Monthly breakdown
            $monthlyData = [];
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();
                
                $monthIncome = ContributionPayment::whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount') + 
                    LoanRepayment::whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount');
                
                $monthExpenses = Loan::where('status', 'approved')
                    ->whereBetween('approved_at', [$monthStart, $monthEnd])
                    ->sum('amount');
                
                $monthlyData[] = [
                    'month' => $current->format('F Y'),
                    'income' => $this->formatCurrency($monthIncome),
                    'expenses' => $this->formatCurrency($monthExpenses),
                    'profit' => $this->formatCurrency($monthIncome - $monthExpenses),
                ];
                
                $current->addMonth();
            }

            // Recent transactions
            $transactions = DB::table('contribution_payments')
                ->select('id', 'created_at as date', DB::raw("'Contribution' as type"), 'amount', DB::raw("'Income' as category"), DB::raw("'completed' as status"))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->union(
                    DB::table('loan_repayments')
                        ->select('id', 'created_at as date', DB::raw("'Loan Repayment' as type"), 'amount', DB::raw("'Income' as category"), DB::raw("'completed' as status"))
                        ->whereBetween('created_at', [$startDate, $endDate])
                )
                ->union(
                    DB::table('loans')
                        ->select('id', 'approved_at as date', DB::raw("'Loan Disbursement' as type"), 'amount', DB::raw("'Expense' as category"), DB::raw("status"))
                        ->where('status', 'approved')
                        ->whereBetween('approved_at', [$startDate, $endDate])
                )
                ->orderBy('date', 'desc')
                ->limit(20)
                ->get();

            $transactionsData = $transactions->map(function($txn) {
                return [
                    'id' => 'TXN' . str_pad($txn->id, 3, '0', STR_PAD_LEFT),
                    'date' => Carbon::parse($txn->date)->format('Y-m-d'),
                    'type' => $txn->type,
                    'category' => $txn->category,
                    'amount' => $this->formatCurrency($txn->amount),
                    'status' => $txn->status,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_revenue' => $this->formatCurrency($totalRevenue),
                        'total_expenses' => $this->formatCurrency($totalExpenses),
                        'net_profit' => $this->formatCurrency($netProfit),
                        'cash_balance' => $this->formatCurrency($cashBalance),
                    ],
                    'monthly_data' => $monthlyData,
                    'transactions' => $transactionsData,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate financial report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 3. Contribution Reports
     */
    public function contributions(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;
            
            $search = $request->get('search', '');

            // Stats
            $totalContributions = Contribution::whereBetween('contribution_date', [$startDate, $endDate])
                ->sum('amount');
            
            $paidContributions = Contribution::whereBetween('contribution_date', [$startDate, $endDate])
                ->where('status', 'approved')
                ->sum('amount');
            
            $pendingContributions = Contribution::whereBetween('contribution_date', [$startDate, $endDate])
                ->where('status', 'pending')
                ->sum('amount');
            
            $overdueContributions = Contribution::whereBetween('contribution_date', [$startDate, $endDate])
                ->where('status', 'pending')
                ->where('contribution_date', '<', now()->subDays(30))
                ->sum('amount');

            // Contributions list
            $query = Contribution::with(['member.user:id,first_name,last_name'])
                ->whereBetween('contribution_date', [$startDate, $endDate]);

            if ($search) {
                $query->whereHas('member.user', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            }

            $contributions = $query->orderBy('contribution_date', 'desc')
                ->paginate($request->get('per_page', 50));

            $contributionsData = $contributions->map(function($contribution) {
                $latestPayment = $contribution->payments()->latest()->first();
                return [
                    'id' => 'C' . str_pad(substr($contribution->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'member' => $contribution->member->user ? 
                        ($contribution->member->user->first_name . ' ' . $contribution->member->user->last_name) : 'N/A',
                    'member_id' => $contribution->member->member_number ?? 'N/A',
                    'amount' => $this->formatCurrency($contribution->amount),
                    'due_date' => $contribution->contribution_date->format('Y-m-d'),
                    'paid_date' => $latestPayment ? $latestPayment->created_at->format('Y-m-d') : null,
                    'status' => ucfirst($contribution->status),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_contributions' => $this->formatCurrency($totalContributions),
                        'paid' => $this->formatCurrency($paidContributions),
                        'pending' => $this->formatCurrency($pendingContributions),
                        'overdue' => $this->formatCurrency($overdueContributions),
                    ],
                    'contributions' => $contributionsData,
                    'pagination' => [
                        'current_page' => $contributions->currentPage(),
                        'last_page' => $contributions->lastPage(),
                        'per_page' => $contributions->perPage(),
                        'total' => $contributions->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate contribution report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 4. Equity Contribution Reports
     */
    public function equityContributions(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;
            
            $search = $request->get('search', '');

            // Stats
            $totalContributions = EquityContribution::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount');
            
            $approvedContributions = EquityContribution::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'approved')
                ->sum('amount');
            
            $pendingContributions = EquityContribution::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'pending')
                ->sum('amount');
            
            $totalWalletBalance = EquityWalletBalance::sum('balance');
            
            $totalUsed = EquityWalletBalance::sum('total_used');

            // Payment method breakdown
            $paymentMethods = EquityContribution::whereBetween('created_at', [$startDate, $endDate])
                ->select('payment_method',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total_amount')
                )
                ->groupBy('payment_method')
                ->get();

            $paymentMethodsData = $paymentMethods->map(function($method) {
                return [
                    'method' => ucfirst(str_replace('_', ' ', $method->payment_method)),
                    'count' => $method->count,
                    'total_amount' => $this->formatCurrency($method->total_amount),
                ];
            });

            // Contributions list
            $query = EquityContribution::with(['member.user:id,first_name,last_name', 'plan'])
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereHas('member.user', function($userQ) use ($search) {
                        $userQ->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                    })->orWhereHas('member', function($memberQ) use ($search) {
                        $memberQ->where('member_number', 'like', "%{$search}%");
                    })->orWhere('payment_reference', 'like', "%{$search}%");
                });
            }

            $contributions = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            $contributionsData = $contributions->map(function($contribution) {
                return [
                    'id' => 'EQ' . str_pad(substr($contribution->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'member' => $contribution->member->user ? 
                        ($contribution->member->user->first_name . ' ' . $contribution->member->user->last_name) : 'N/A',
                    'member_id' => $contribution->member->member_number ?? 'N/A',
                    'plan' => $contribution->plan ? $contribution->plan->name : 'N/A',
                    'amount' => $this->formatCurrency($contribution->amount),
                    'payment_method' => ucfirst(str_replace('_', ' ', $contribution->payment_method)),
                    'payment_reference' => $contribution->payment_reference ?? 'N/A',
                    'status' => ucfirst($contribution->status),
                    'approved_at' => $contribution->approved_at ? $contribution->approved_at->format('Y-m-d') : null,
                    'created_at' => $contribution->created_at->format('Y-m-d'),
                ];
            });

            // Wallet usage statistics
            $walletUsage = EquityTransaction::whereBetween('created_at', [$startDate, $endDate])
                ->where('type', 'deposit_payment')
                ->select(
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total_used')
                )
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_contributions' => $this->formatCurrency($totalContributions),
                        'approved' => $this->formatCurrency($approvedContributions),
                        'pending' => $this->formatCurrency($pendingContributions),
                        'total_wallet_balance' => $this->formatCurrency($totalWalletBalance),
                        'total_used' => $this->formatCurrency($totalUsed),
                        'wallet_transactions' => $walletUsage->count ?? 0,
                    ],
                    'payment_methods' => $paymentMethodsData,
                    'contributions' => $contributionsData,
                    'pagination' => [
                        'current_page' => $contributions->currentPage(),
                        'last_page' => $contributions->lastPage(),
                        'per_page' => $contributions->perPage(),
                        'total' => $contributions->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate equity contribution report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 5. Investment Reports
     */
    public function investments(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;

            // Stats
            $totalInvestments = Investment::whereBetween('investment_date', [$startDate, $endDate])
                ->sum('amount');
            
            $activeInvestments = Investment::where('status', 'active')->count();
            
            $avgROI = Investment::where('status', 'active')
                ->avg('expected_return_rate');
            
            $totalReturns = InvestmentReturn::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount');

            // Plan performance
            $planPerformance = Investment::whereBetween('investment_date', [$startDate, $endDate])
                ->select('type', 
                    DB::raw('SUM(amount) as total_invested'),
                    DB::raw('COUNT(*) as investors'),
                    DB::raw('AVG(expected_return_rate) as avg_roi')
                )
                ->groupBy('type')
                ->get();

            $planData = $planPerformance->map(function($plan) {
                $returns = InvestmentReturn::whereHas('investment', function($q) use ($plan) {
                    $q->where('type', $plan->type);
                })->sum('amount');
                
                return [
                    'plan' => $plan->type ?? 'General',
                    'total_invested' => $this->formatCurrency($plan->total_invested),
                    'investors' => $plan->investors,
                    'avg_roi' => number_format($plan->avg_roi, 2) . '%',
                    'returns' => $this->formatCurrency($returns),
                ];
            });

            // Investment details
            $investments = Investment::with(['member.user:id,first_name,last_name'])
                ->whereBetween('investment_date', [$startDate, $endDate])
                ->orderBy('investment_date', 'desc')
                ->paginate($request->get('per_page', 50));

            $investmentsData = $investments->map(function($investment) {
                $maturityDate = $investment->investment_date->copy()->addMonths($investment->duration_months);
                return [
                    'id' => 'INV' . str_pad(substr($investment->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'member' => $investment->member->user ? 
                        ($investment->member->user->first_name . ' ' . $investment->member->user->last_name) : 'N/A',
                    'plan' => $investment->type ?? 'General',
                    'amount' => $this->formatCurrency($investment->amount),
                    'roi' => number_format($investment->expected_return_rate, 2) . '%',
                    'start_date' => $investment->investment_date->format('Y-m-d'),
                    'maturity_date' => $maturityDate->format('Y-m-d'),
                    'status' => ucfirst($investment->status),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_investments' => $this->formatCurrency($totalInvestments),
                        'active_investments' => $activeInvestments,
                        'avg_roi' => number_format($avgROI, 2) . '%',
                        'total_returns' => $this->formatCurrency($totalReturns),
                    ],
                    'plan_performance' => $planData,
                    'investments' => $investmentsData,
                    'pagination' => [
                        'current_page' => $investments->currentPage(),
                        'last_page' => $investments->lastPage(),
                        'per_page' => $investments->perPage(),
                        'total' => $investments->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate investment report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 6. Loan Reports
     */
    public function loans(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;

            // Stats
            $totalLoans = Loan::whereBetween('application_date', [$startDate, $endDate])
                ->sum('amount');
            
            $activeLoans = Loan::where('status', 'approved')->count();
            
            $pendingApplications = Loan::where('status', 'pending')
                ->whereBetween('application_date', [$startDate, $endDate])
                ->count();
            
            $defaultedLoans = Loan::where('status', 'defaulted')->count();

            // Loan type analysis
            $loanTypes = Loan::whereBetween('application_date', [$startDate, $endDate])
                ->select('type',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as total_amount'),
                    DB::raw('AVG(amount) as avg_amount')
                )
                ->groupBy('type')
                ->get();

            $loanTypesData = $loanTypes->map(function($type) {
                $repaid = LoanRepayment::whereHas('loan', function($q) use ($type) {
                    $q->where('type', $type->type);
                })->sum('amount');
                
                $total = $type->total_amount;
                $repaymentRate = $total > 0 ? ($repaid / $total * 100) : 0;
                
                return [
                    'type' => $type->type ?? 'General',
                    'count' => $type->count,
                    'total_amount' => $this->formatCurrency($type->total_amount),
                    'avg_amount' => $this->formatCurrency($type->avg_amount),
                    'repayment_rate' => number_format($repaymentRate, 2) . '%',
                ];
            });

            // Loan details
            $loans = Loan::with(['member.user:id,first_name,last_name'])
                ->whereBetween('application_date', [$startDate, $endDate])
                ->orderBy('application_date', 'desc')
                ->paginate($request->get('per_page', 50));

            $loansData = $loans->map(function($loan) {
                $disbursed = $loan->status === 'approved' ? $loan->amount : 0;
                $repaid = $loan->repayments()->sum('amount');
                $balance = $disbursed - $repaid;
                
                return [
                    'id' => 'L' . str_pad(substr($loan->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'member' => $loan->member->user ? 
                        ($loan->member->user->first_name . ' ' . $loan->member->user->last_name) : 'N/A',
                    'type' => $loan->type ?? 'General',
                    'amount' => $this->formatCurrency($loan->amount),
                    'disbursed' => $this->formatCurrency($disbursed),
                    'repaid' => $this->formatCurrency($repaid),
                    'balance' => $this->formatCurrency($balance),
                    'status' => ucfirst($loan->status),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_loans' => $this->formatCurrency($totalLoans),
                        'active_loans' => $activeLoans,
                        'pending_applications' => $pendingApplications,
                        'defaulted' => $defaultedLoans,
                    ],
                    'loan_types' => $loanTypesData,
                    'loans' => $loansData,
                    'pagination' => [
                        'current_page' => $loans->currentPage(),
                        'last_page' => $loans->lastPage(),
                        'per_page' => $loans->perPage(),
                        'total' => $loans->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate loan report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 6. Property Reports
     */
    public function properties(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;

            // Stats
            $totalProperties = Property::count();
            // Count houses (house, duplex, bungalow, apartment)
            $houses = Property::whereIn('type', ['house', 'duplex', 'bungalow', 'apartment'])->count();
            $land = Property::where('type', 'land')->count();
            $totalValue = Property::sum('price');

            // Properties list
            $properties = Property::whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            $propertiesData = $properties->map(function($property) {
                $allocatedCount = $property->allocations()->count();
                return [
                    'id' => 'P' . str_pad(substr($property->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'name' => $property->title,
                    'type' => $property->type ?? 'N/A',
                    'location' => $property->location ?? (($property->city ?? '') . ($property->state ? ', ' . $property->state : '')),
                    'price' => $this->formatCurrency($property->price),
                    'allocated' => $allocatedCount,
                    'status' => ucfirst($property->status ?? 'available'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_properties' => $totalProperties,
                        'houses' => $houses,
                        'land' => $land,
                        'total_value' => $this->formatCurrency($totalValue),
                    ],
                    'properties' => $propertiesData,
                    'pagination' => [
                        'current_page' => $properties->currentPage(),
                        'last_page' => $properties->lastPage(),
                        'per_page' => $properties->perPage(),
                        'total' => $properties->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate property report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 7. Mail Service Reports
     */
    public function mailService(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;

            // Stats
            $totalMessages = Mail::whereBetween('created_at', [$startDate, $endDate])->count();
            $sentMessages = Mail::where('status', 'sent')
                ->whereBetween('sent_at', [$startDate, $endDate])
                ->count();
            $draftMessages = Mail::where('status', 'draft')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            $deliveredMessages = Mail::where('status', 'delivered')
                ->whereBetween('delivered_at', [$startDate, $endDate])
                ->count();

            // Message breakdown by category
            $messagesByCategory = Mail::whereBetween('created_at', [$startDate, $endDate])
                ->select('category', DB::raw('COUNT(*) as count'))
                ->groupBy('category')
                ->get();

            // Recent messages
            $messages = Mail::with(['sender:id,first_name,last_name'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            $messagesData = $messages->map(function($mail) {
                return [
                    'id' => 'MSG' . str_pad(substr($mail->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'sender' => $mail->sender ? 
                        ($mail->sender->first_name . ' ' . $mail->sender->last_name) : 'System',
                    'subject' => $mail->subject,
                    'category' => ucfirst($mail->category ?? 'general'),
                    'recipient_type' => ucfirst($mail->recipient_type ?? 'all'),
                    'status' => ucfirst($mail->status),
                    'sent_at' => $mail->sent_at ? $mail->sent_at->format('Y-m-d H:i') : null,
                    'created_at' => $mail->created_at->format('Y-m-d H:i'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_messages' => $totalMessages,
                        'sent_messages' => $sentMessages,
                        'draft_messages' => $draftMessages,
                        'delivered_messages' => $deliveredMessages,
                    ],
                    'messages_by_category' => $messagesByCategory->map(function($item) {
                        return [
                            'category' => ucfirst($item->category ?? 'general'),
                            'count' => $item->count,
                        ];
                    }),
                    'messages' => $messagesData,
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate mail service report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * 8. Audit Reports
     */
    public function audit(Request $request): JsonResponse
    {
        try {
            // Get tenant ID from tenancy context (set by TenantMiddleware)
            $tenant = tenant();
            if (!$tenant) {
                // Fallback: try to get from request attribute set by middleware
                $tenantId = $request->attributes->get('tenant_id') ?? null;
                
                // If still not found, try to extract from database connection name
                if (!$tenantId) {
                    $dbName = config('database.connections.tenant.database', '');
                    // Extract tenant ID from database name (format: {tenant_id}_smart_housing)
                    if (preg_match('/^(.+)_smart_housing$/', $dbName, $matches)) {
                        $tenantId = $matches[1];
                    }
                }
                
                if (!$tenantId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tenant not identified'
                    ], 400);
                }
            } else {
                $tenantId = $tenant->id;
            }

            $dateRange = $this->getDateRange($request->get('date_range', 'this-month'));
            [$startDate, $endDate] = $dateRange;
            
            $search = $request->get('search', '');
            $actionFilter = $request->get('action', 'all');

            // Stats
            $totalActivities = ActivityLog::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            
            $adminActions = ActivityLog::where('tenant_id', $tenantId)
                ->where('module', 'admin')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            
            $warnings = ActivityLog::where('tenant_id', $tenantId)
                ->where('action', 'warning')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            
            $successful = ActivityLog::where('tenant_id', $tenantId)
                ->where('action', '!=', 'warning')
                ->where('action', '!=', 'failed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Audit logs query
            $query = ActivityLog::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with(['causer' => function($q) {
                    $q->select('id', 'first_name', 'last_name', 'email');
                }]);

            if ($search) {
                $query->where('description', 'like', "%{$search}%")
                      ->orWhere('module', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%");
            }

            if ($actionFilter !== 'all') {
                $query->where('action', $actionFilter);
            }

            $logs = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            $logsData = $logs->map(function($log) {
                return [
                    'id' => 'AUD' . str_pad(substr($log->id, 0, 3), 3, '0', STR_PAD_LEFT),
                    'user' => $log->causer ? 
                        ($log->causer->first_name . ' ' . $log->causer->last_name) : 'System',
                    'action' => ucfirst($log->action),
                    'module' => ucfirst($log->module ?? 'general'),
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_activities' => $totalActivities,
                        'admin_actions' => $adminActions,
                        'warnings' => $warnings,
                        'successful' => $successful,
                    ],
                    'logs' => $logsData,
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate audit report',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Export report
     */
    public function export(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $type = $request->get('type');
        $dateRange = $request->get('date_range', 'this-month');
        $search = $request->get('search', '');
        $format = $request->get('format', 'csv');

        [$startDate, $endDate] = $this->getDateRange($dateRange);

        try {
            $data = [];
            $headers = [];
            $filename = '';

            switch ($type) {
                case 'contributions':
                    $data = $this->exportContributions($startDate, $endDate, $search);
                    $headers = ['Member Number', 'Member Name', 'Plan', 'Amount', 'Payment Method', 'Payment Reference', 'Status', 'Date'];
                    $filename = 'contributions_' . date('Y-m-d') . '.csv';
                    break;

                case 'loans':
                    $data = $this->exportLoans($startDate, $endDate, $search);
                    $headers = ['Loan ID', 'Member Number', 'Member Name', 'Product', 'Amount', 'Status', 'Disbursed At', 'Created At'];
                    $filename = 'loans_' . date('Y-m-d') . '.csv';
                    break;

                case 'investments':
                    $data = $this->exportInvestments($startDate, $endDate, $search);
                    $headers = ['Investment ID', 'Member Number', 'Member Name', 'Plan', 'Amount', 'Expected Return', 'Status', 'Created At'];
                    $filename = 'investments_' . date('Y-m-d') . '.csv';
                    break;

                case 'properties':
                    $data = $this->exportProperties($startDate, $endDate, $search);
                    $headers = ['Property ID', 'Title', 'Location', 'Type', 'Price', 'Status', 'Created At'];
                    $filename = 'properties_' . date('Y-m-d') . '.csv';
                    break;

                case 'members':
                    $data = $this->exportMembers($startDate, $endDate, $search);
                    $headers = ['Member Number', 'First Name', 'Last Name', 'Email', 'Phone', 'Status', 'Registration Date'];
                    $filename = 'members_' . date('Y-m-d') . '.csv';
                    break;

                case 'financial':
                    $data = $this->exportFinancial($startDate, $endDate);
                    $headers = ['Period', 'Contributions', 'Loans', 'Investments', 'Properties', 'Total Revenue'];
                    $filename = 'financial_' . date('Y-m-d') . '.csv';
                    break;

                case 'mail-service':
                    $data = $this->exportMailService($startDate, $endDate, $search);
                    $headers = ['ID', 'Subject', 'Category', 'Recipient', 'Status', 'Sent At'];
                    $filename = 'mail_service_' . date('Y-m-d') . '.csv';
                    break;

                case 'audit':
                    $data = $this->exportAudit($startDate, $endDate, $search);
                    $headers = ['ID', 'User', 'Action', 'Module', 'Description', 'IP Address', 'Created At'];
                    $filename = 'audit_' . date('Y-m-d') . '.csv';
                    break;

                case 'equity-contributions':
                    $data = $this->exportEquityContributions($startDate, $endDate, $search);
                    $headers = ['Member Number', 'Member Name', 'Plan', 'Amount', 'Payment Method', 'Payment Reference', 'Status', 'Approved At', 'Created At'];
                    $filename = 'equity_contributions_' . date('Y-m-d') . '.csv';
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid report type'
                    ], 400);
            }

            if ($format === 'csv') {
                return $this->downloadCsv($data, $headers, $filename);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'filename' => $filename
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report: ' . $e->getMessage()
            ], 500);
        }
    }

    private function downloadCsv(array $data, array $headers, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers2 = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write headers
            fputcsv($file, $headers);
            
            // Write data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers2);
    }

    private function exportContributions($startDate, $endDate, $search): array
    {
        $query = Contribution::with(['member.user', 'plan'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('member', function($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function($contribution) {
            return [
                $contribution->member->member_number ?? 'N/A',
                ($contribution->member->user->first_name ?? '') . ' ' . ($contribution->member->user->last_name ?? ''),
                $contribution->plan->name ?? 'N/A',
                number_format($contribution->amount, 2),
                $contribution->payment_method ?? 'N/A',
                $contribution->payment_reference ?? 'N/A',
                ucfirst($contribution->status),
                $contribution->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportLoans($startDate, $endDate, $search): array
    {
        $query = Loan::with(['member.user', 'product'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function($loan) {
            return [
                $loan->loan_number ?? 'N/A',
                $loan->member->member_number ?? 'N/A',
                ($loan->member->user->first_name ?? '') . ' ' . ($loan->member->user->last_name ?? ''),
                $loan->product->name ?? 'N/A',
                number_format($loan->amount, 2),
                ucfirst($loan->status),
                $loan->disbursed_at ? $loan->disbursed_at->format('Y-m-d H:i:s') : 'N/A',
                $loan->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportInvestments($startDate, $endDate, $search): array
    {
        $query = Investment::with(['member.user', 'plan'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function($investment) {
            return [
                $investment->investment_number ?? 'N/A',
                $investment->member->member_number ?? 'N/A',
                ($investment->member->user->first_name ?? '') . ' ' . ($investment->member->user->last_name ?? ''),
                $investment->plan->name ?? 'N/A',
                number_format($investment->amount, 2),
                number_format($investment->expected_return, 2),
                ucfirst($investment->status),
                $investment->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportProperties($startDate, $endDate, $search): array
    {
        $query = Property::whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
        }

        return $query->get()->map(function($property) {
            return [
                $property->id,
                $property->title ?? 'N/A',
                $property->location ?? 'N/A',
                ucfirst($property->type ?? 'N/A'),
                number_format($property->price ?? 0, 2),
                ucfirst($property->status ?? 'N/A'),
                $property->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportMembers($startDate, $endDate, $search): array
    {
        $query = Member::with('user')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->whereHas('user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('member_number', 'like', "%{$search}%");
        }

        return $query->get()->map(function($member) {
            return [
                $member->member_number ?? 'N/A',
                $member->user->first_name ?? 'N/A',
                $member->user->last_name ?? 'N/A',
                $member->user->email ?? 'N/A',
                $member->user->phone ?? 'N/A',
                ucfirst($member->status ?? 'N/A'),
                $member->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportFinancial($startDate, $endDate): array
    {
        $contributions = Contribution::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $loans = Loan::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $investments = Investment::where('status', 'active')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $properties = Property::whereBetween('created_at', [$startDate, $endDate])
            ->sum('price');

        return [[
            $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            number_format($contributions, 2),
            number_format($loans, 2),
            number_format($investments, 2),
            number_format($properties, 2),
            number_format($contributions + $loans + $investments + $properties, 2),
        ]];
    }

    private function exportMailService($startDate, $endDate, $search): array
    {
        $query = Mail::whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->where('subject', 'like', "%{$search}%");
        }

        return $query->get()->map(function($mail) {
            return [
                $mail->id,
                $mail->subject ?? 'N/A',
                ucfirst($mail->category ?? 'N/A'),
                $mail->recipient_email ?? 'N/A',
                ucfirst($mail->status ?? 'N/A'),
                $mail->sent_at ? $mail->sent_at->format('Y-m-d H:i:s') : 'N/A',
            ];
        })->toArray();
    }

    private function exportAudit($startDate, $endDate, $search): array
    {
        $query = ActivityLog::whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->where('description', 'like', "%{$search}%");
        }

        return $query->with('causer')->get()->map(function($log) {
            return [
                $log->id,
                $log->causer ? ($log->causer->first_name . ' ' . $log->causer->last_name) : 'System',
                ucfirst($log->action ?? 'N/A'),
                ucfirst($log->module ?? 'general'),
                $log->description ?? 'N/A',
                $log->ip_address ?? 'N/A',
                $log->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    private function exportEquityContributions($startDate, $endDate, $search): array
    {
        $query = EquityContribution::with(['member.user', 'plan'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($search) {
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('member', function($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function($contribution) {
            return [
                $contribution->member->member_number ?? 'N/A',
                ($contribution->member->user->first_name ?? '') . ' ' . ($contribution->member->user->last_name ?? ''),
                $contribution->plan->name ?? 'N/A',
                number_format($contribution->amount, 2),
                ucfirst(str_replace('_', ' ', $contribution->payment_method ?? 'N/A')),
                $contribution->payment_reference ?? 'N/A',
                ucfirst($contribution->status),
                $contribution->approved_at ? $contribution->approved_at->format('Y-m-d H:i:s') : 'N/A',
                $contribution->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
}

