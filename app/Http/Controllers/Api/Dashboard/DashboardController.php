<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Investment;
use App\Models\Tenant\Property;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        // Get user's financial stats
        $totalContributions = Contribution::where('member_id', $member->id)
            ->whereIn('status', ['approved', 'completed'])
            ->sum('amount');

        $activeLoans = Loan::where('member_id', $member->id)
            ->where('status', 'approved')
            ->get();

        $totalLoanBalance = $activeLoans->sum('amount');
        $activeLoanCount = $activeLoans->count();

        $totalInvestments = Investment::where('member_id', $member->id)
            ->where('status', 'active')
            ->sum('amount');

        $wallet = $member->wallet;
        $walletBalance = $wallet ? $wallet->balance : 0;

        // Get property value (if user has properties)
        $userProperties = Property::whereHas('allocations', function($query) use ($member) {
            $query->where('member_id', $member->id);
        })->sum('price');

        // Get recent transactions
        $recentTransactions = Payment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get upcoming payments (loan repayments due in next 30 days)
        $upcomingPayments = $this->getUpcomingPayments($member->id);

        return response()->json([
            'stats' => [
                'total_contributions' => $totalContributions,
                'active_loans' => $totalLoanBalance,
                'active_loan_count' => $activeLoanCount,
                'total_investments' => $totalInvestments,
                'property_value' => $userProperties,
                'wallet_balance' => $walletBalance,
            ],
            'recent_transactions' => $recentTransactions,
            'upcoming_payments' => $upcomingPayments,
            'member_info' => [
                'member_number' => $member->member_number,
                'membership_type' => $member->membership_type,
                'kyc_status' => $member->kyc_status,
            ]
        ]);
    }

    public function adminStats(Request $request): JsonResponse
    {
        try {
            // Get tenant-wide stats for admin
            // Members are identified by role='member' in the users table
            // Using the configured tenant connection which should already be initialized by middleware
            $totalMembers = DB::table('users')->where('role', 'member')->count();
            $totalContributions = Contribution::whereIn('status', ['approved', 'completed'])->sum('amount');
            $totalLoans = Loan::where('status', 'approved')->count();
            $totalInvestments = Investment::where('status', 'active')->sum('amount');
            $totalProperties = Property::count();
            $activeProperties = Property::where('status', 'available')->count();

        // Get monthly revenue
        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // Get growth metrics
        $lastMonth = now()->subMonth();
        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->sum('amount');

        $revenueGrowth = $lastMonthRevenue > 0 
            ? (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

            // Get member growth
            $lastMonthMembers = DB::table('users')
                ->where('role', 'member')
                ->whereMonth('created_at', $lastMonth->month)
                ->whereYear('created_at', $lastMonth->year)
                ->count();
            
            $thisMonthMembers = DB::table('users')
                ->where('role', 'member')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
        
        $memberGrowth = $lastMonthMembers > 0 
            ? (($thisMonthMembers - $lastMonthMembers) / $lastMonthMembers) * 100 
            : 0;

            // Pending approvals
            // Note: KYC status should be in members table linked to users
            $pendingKyc = DB::table('members')
                ->whereIn('kyc_status', ['pending', 'submitted'])
                ->count();
            
            $pendingLoans = Loan::where('status', 'pending')->count();
            
            // Check if wallet_transactions table exists in tenant database
            try {
                $pendingWithdrawals = DB::table('wallet_transactions')
                    ->where('type', 'withdrawal')
                    ->where('status', 'pending')
                    ->count();
            } catch (\Exception $e) {
                $pendingWithdrawals = 0;
            }

            // Recent activities
            $recentActivities = $this->getRecentActivities();
            
            // Get tenant info - use helper function with fallback
            $tenantName = 'Tenant';
            try {
                $tenant = tenant();
                if ($tenant) {
                    $tenantName = $tenant->data['name'] ?? $tenant->id ?? 'Tenant';
                }
            } catch (\Exception $e) {
                Log::warning('Could not get tenant info: ' . $e->getMessage());
            }

            return response()->json([
                'tenant' => [
                    'name' => $tenantName,
                ],
                'stats' => [
                    'total_members' => $totalMembers,
                    'total_contributions' => $totalContributions,
                    'total_loans' => $totalLoans,
                    'total_investments' => $totalInvestments,
                    'total_properties' => $totalProperties,
                    'active_properties' => $activeProperties,
                    'monthly_revenue' => $monthlyRevenue,
                    'revenue_growth' => round($revenueGrowth, 2),
                    'member_growth' => round($memberGrowth, 2),
                ],
                'pending_approvals' => [
                    'kyc' => $pendingKyc,
                    'loans' => $pendingLoans,
                    'withdrawals' => $pendingWithdrawals,
                ],
                'recent_activities' => $recentActivities,
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard adminStats Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'error' => 'Failed to load dashboard statistics',
                'message' => $e->getMessage(),
                'debug_info' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    private function getUpcomingPayments(string $memberId): array
    {
        // This would typically query loan repayments due in the next 30 days
        // For now, return empty array
        return [];
    }

    private function getRecentActivities(): array
    {
        $activities = [];

        // Get recent loan applications
        $recentLoans = Loan::with(['member.user'])
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'type' => 'loan_application',
                    'user' => $loan->member->user->first_name . ' ' . $loan->member->user->last_name,
                    'action' => 'Applied for ' . $loan->type . ' loan',
                    'amount' => number_format($loan->amount, 2),
                    'time' => $loan->created_at->diffForHumans(),
                    'status' => $loan->status,
                ];
            });

        // Get recent KYC submissions
        $recentKyc = DB::table('members')
            ->join('users', 'members.user_id', '=', 'users.id')
            ->whereIn('members.kyc_status', ['submitted', 'pending'])
            ->select('members.*', 'users.first_name', 'users.last_name', 'users.email')
            ->orderBy('members.updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'type' => 'kyc_submission',
                    'user' => $member->first_name . ' ' . $member->last_name,
                    'action' => 'Submitted KYC verification',
                    'amount' => null,
                    'time' => \Carbon\Carbon::parse($member->updated_at)->diffForHumans(),
                    'status' => 'pending',
                ];
            });

        // Get recent contributions
        $recentContributions = Contribution::with(['member.user'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($contribution) {
                return [
                    'id' => $contribution->id,
                    'type' => 'contribution',
                    'user' => $contribution->member->user->first_name . ' ' . $contribution->member->user->last_name,
                    'action' => 'Made monthly contribution',
                    'amount' => number_format($contribution->amount, 2),
                    'time' => $contribution->created_at->diffForHumans(),
                    'status' => 'completed',
                ];
            });

        // Get recent investments
        $recentInvestments = Investment::with(['member.user'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($investment) {
                return [
                    'id' => $investment->id,
                    'type' => 'investment',
                    'user' => $investment->member->user->first_name . ' ' . $investment->member->user->last_name,
                    'action' => 'Invested in property',
                    'amount' => number_format($investment->amount, 2),
                    'time' => $investment->created_at->diffForHumans(),
                    'status' => 'completed',
                ];
            });

        // Combine and sort all activities by time
        $allActivities = collect()
            ->concat($recentLoans)
            ->concat($recentKyc)
            ->concat($recentContributions)
            ->concat($recentInvestments)
            ->sortByDesc(function ($activity) {
                return $activity['time'];
            })
            ->take(10)
            ->values();

        return $allActivities->toArray();
    }
}
