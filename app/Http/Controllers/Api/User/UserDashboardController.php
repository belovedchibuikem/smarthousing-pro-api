<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\Investment;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanRepayment;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Property;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserDashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }
        
            $member = $user->member;
        
            if (!$member) {
                return response()->json(['message' => 'Member profile not found'], 404);
            }
        
            // Safely get wallet
            $wallet = $member->wallet;
            $walletBalance = $wallet ? (float) $wallet->balance : 0;
        
            // Financial Summary - with error handling
            $totalContributions = (float) Contribution::where('member_id', $member->id)
                ->where('status', 'approved')
                ->sum('amount') ?? 0;
        
            $totalLoans = (float) Loan::where('member_id', $member->id)
                ->where('status', 'approved')
                ->sum('amount') ?? 0;
        
            $totalInvestments = (float) Investment::where('member_id', $member->id)
                ->where('status', 'active')
                ->sum('amount') ?? 0;
        
            // Fixed: Get total repayments properly
            $totalRepaid = (float) DB::table('loan_repayments')
                ->join('loans', 'loan_repayments.loan_id', '=', 'loans.id')
                ->where('loans.member_id', $member->id)
                ->where('loans.status', 'approved')
                ->sum('loan_repayments.amount') ?? 0;
        
            $outstandingLoans = max(0, $totalLoans - $totalRepaid);
        
            // Recent Activity - with safe loading
            $recentContributions = Contribution::where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($contribution) {
                    return [
                        'id' => $contribution->id,
                        'amount' => $contribution->amount,
                        'status' => $contribution->status,
                        'created_at' => $contribution->created_at,
                        'type' => $contribution->type ?? null,
                    ];
                });
        
            $recentLoans = Loan::where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'amount' => $loan->amount,
                        'status' => $loan->status,
                        'created_at' => $loan->created_at,
                        'loan_plan_id' => $loan->loan_plan_id ?? null,
                    ];
                });
        
            $recentInvestments = Investment::where('member_id', $member->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($investment) {
                    return [
                        'id' => $investment->id,
                        'amount' => $investment->amount,
                        'status' => $investment->status,
                        'created_at' => $investment->created_at,
                        'investment_plan_id' => $investment->investment_plan_id ?? null,
                    ];
                });
        
            // Upcoming Payments - get from loan repayments that are due
            $upcomingPayments = collect([]);
            try {
                $upcomingPayments = LoanRepayment::whereHas('loan', function ($query) use ($member) {
                    $query->where('member_id', $member->id)
                        ->where('status', 'approved');
                })
                    ->where('status', 'pending')
                    ->where('due_date', '>=', now()->toDateString())
                    ->orderBy('due_date', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(function ($repayment) {
                        return [
                            'id' => $repayment->id,
                            'amount' => (float) $repayment->amount,
                            'due_date' => $repayment->due_date->format('Y-m-d'),
                            'status' => $repayment->status,
                            'description' => 'Loan Repayment',
                            'type' => 'loan_repayment',
                        ];
                    });
            } catch (\Exception $e) {
                Log::warning('Error fetching upcoming payments: ' . $e->getMessage());
                $upcomingPayments = collect([]);
            }
        
            // Property Interests - check if relationship exists
            $propertyInterests = collect([]);
            if (method_exists($member, 'propertyInterests')) {
                $propertyInterests = $member->propertyInterests()
                    ->with('property')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($interest) {
                        return [
                            'id' => $interest->id,
                            'property' => $interest->property ? [
                                'id' => $interest->property->id,
                                'name' => $interest->property->name ?? 'N/A',
                                'address' => $interest->property->address ?? null,
                            ] : null,
                            'created_at' => $interest->created_at,
                            'status' => $interest->status ?? null,
                        ];
                    });
            }
        
            // Monthly Trends - with error handling
            $monthlyTrends = [];
            if (method_exists($this, 'getMonthlyTrends')) {
                try {
                    $monthlyTrends = $this->getMonthlyTrends($member);
                } catch (\Exception $e) {
                    Log::error('Error getting monthly trends: ' . $e->getMessage());
                    $monthlyTrends = [];
                }
            }
        
            return response()->json([
                'wallet_balance' => $walletBalance,
                'financial_summary' => [
                    'total_contributions' => $totalContributions,
                    'total_loans' => $totalLoans,
                    'outstanding_loans' => $outstandingLoans,
                    'total_investments' => $totalInvestments,
                    'total_repayments' => $totalRepaid,
                ],
                'recent_activity' => [
                    'contributions' => $recentContributions,
                    'loans' => $recentLoans,
                    'investments' => $recentInvestments,
                ],
                'upcoming_payments' => $upcomingPayments,
                'property_interests' => $propertyInterests,
                'monthly_trends' => $monthlyTrends,
                'member_status' => $member->status ?? 'unknown',
                'kyc_status' => $member->kyc_status ?? 'pending',
                'membership_type' => $member->membership_type ?? 'regular',
            ], 200);
        
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Member Dashboard Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        
            return response()->json([
                'message' => 'An error occurred while fetching dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
        
    }

    public function quickActions(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $actions = [
            'make_contribution' => [
                'title' => 'Make Contribution',
                'description' => 'Add to your savings',
                'url' => '/dashboard/contributions/new',
                'icon' => 'plus-circle',
                'available' => true,
            ],
            'apply_loan' => [
                'title' => 'Apply for Loan',
                'description' => 'Get financial assistance',
                'url' => '/dashboard/loans/apply',
                'icon' => 'credit-card',
                'available' => $member->kyc_status === 'verified',
            ],
            'invest_money' => [
                'title' => 'Make Investment',
                'description' => 'Grow your money',
                'url' => '/dashboard/investments/new',
                'icon' => 'trending-up',
                'available' => $member->kyc_status === 'verified',
            ],
            'express_property_interest' => [
                'title' => 'Express Property Interest',
                'description' => 'Show interest in properties',
                'url' => '/dashboard/properties',
                'icon' => 'home',
                'available' => true,
            ],
            'top_up_wallet' => [
                'title' => 'Top Up Wallet',
                'description' => 'Add funds to your wallet',
                'url' => '/dashboard/wallet/top-up',
                'icon' => 'wallet',
                'available' => true,
            ],
            'view_reports' => [
                'title' => 'View Reports',
                'description' => 'Check your financial reports',
                'url' => '/dashboard/reports',
                'icon' => 'bar-chart',
                'available' => true,
            ],
        ];

        return response()->json([
            'actions' => $actions,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json(['message' => 'Member profile not found'], 404);
        }

        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $unreadCount = $user->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markNotificationAsRead(string $notificationId, Request $request): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    private function getMonthlyTrends(Member $member): array
    {
        $trends = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $monthlyContributions = Contribution::where('member_id', $member->id)
                ->where('status', 'approved')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $monthlyLoans = Loan::where('member_id', $member->id)
                ->where('status', 'approved')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $monthlyInvestments = Investment::where('member_id', $member->id)
                ->where('status', 'active')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            $trends[] = [
                'month' => $date->format('M Y'),
                'contributions' => $monthlyContributions,
                'loans' => $monthlyLoans,
                'investments' => $monthlyInvestments,
            ];
        }

        return $trends;
    }
}
