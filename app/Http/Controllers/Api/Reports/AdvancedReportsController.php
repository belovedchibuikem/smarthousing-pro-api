<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Investment;
use App\Models\Tenant\Property;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvancedReportsController extends Controller
{
    public function financialSummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        // Total contributions
        $totalContributions = Contribution::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        // Total loans disbursed
        $totalLoans = Loan::where('status', 'approved')
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->sum('amount');

        // Total investments
        $totalInvestments = Investment::where('status', 'active')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        // Total payments received
        $totalPayments = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        // Property value
        $totalPropertyValue = Property::sum('price');
        $allocatedPropertyValue = Property::where('status', 'allocated')->sum('price');

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_contributions' => $totalContributions,
                'total_loans' => $totalLoans,
                'total_investments' => $totalInvestments,
                'total_payments' => $totalPayments,
                'total_property_value' => $totalPropertyValue,
                'allocated_property_value' => $allocatedPropertyValue,
                'available_property_value' => $totalPropertyValue - $allocatedPropertyValue,
            ]
        ]);
    }

    public function memberAnalytics(Request $request): JsonResponse
    {
        // Total members
        $totalMembers = Member::count();
        $activeMembers = Member::where('status', 'active')->count();
        $inactiveMembers = Member::where('status', 'inactive')->count();
        $suspendedMembers = Member::where('status', 'suspended')->count();

        // KYC status breakdown
        $kycPending = Member::where('kyc_status', 'pending')->count();
        $kycVerified = Member::where('kyc_status', 'verified')->count();
        $kycRejected = Member::where('kyc_status', 'rejected')->count();

        // Membership type breakdown
        $regularMembers = Member::where('membership_type', 'regular')->count();
        $premiumMembers = Member::where('membership_type', 'premium')->count();
        $vipMembers = Member::where('membership_type', 'vip')->count();

        // New members this month
        $newMembersThisMonth = Member::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'member_stats' => [
                'total_members' => $totalMembers,
                'active_members' => $activeMembers,
                'inactive_members' => $inactiveMembers,
                'suspended_members' => $suspendedMembers,
            ],
            'kyc_breakdown' => [
                'pending' => $kycPending,
                'verified' => $kycVerified,
                'rejected' => $kycRejected,
            ],
            'membership_types' => [
                'regular' => $regularMembers,
                'premium' => $premiumMembers,
                'vip' => $vipMembers,
            ],
            'growth' => [
                'new_members_this_month' => $newMembersThisMonth,
            ]
        ]);
    }

    public function loanAnalytics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        // Loan applications
        $totalApplications = Loan::whereBetween('created_at', [$startDate, $endDate])->count();
        $approvedLoans = Loan::where('status', 'approved')
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->count();
        $rejectedLoans = Loan::where('status', 'rejected')
            ->whereBetween('rejected_at', [$startDate, $endDate])
            ->count();
        $pendingLoans = Loan::where('status', 'pending')->count();

        // Loan amounts
        $totalLoanAmount = Loan::where('status', 'approved')
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->sum('amount');
        $averageLoanAmount = $approvedLoans > 0 ? $totalLoanAmount / $approvedLoans : 0;

        // Loan performance
        $activeLoans = Loan::where('status', 'approved')->count();
        $overdueLoans = Loan::where('status', 'overdue')->count();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'applications' => [
                'total_applications' => $totalApplications,
                'approved' => $approvedLoans,
                'rejected' => $rejectedLoans,
                'pending' => $pendingLoans,
                'approval_rate' => $totalApplications > 0 ? ($approvedLoans / $totalApplications) * 100 : 0,
            ],
            'amounts' => [
                'total_loan_amount' => $totalLoanAmount,
                'average_loan_amount' => $averageLoanAmount,
            ],
            'performance' => [
                'active_loans' => $activeLoans,
                'overdue_loans' => $overdueLoans,
            ]
        ]);
    }

    public function contributionAnalytics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        // Contribution statistics
        $totalContributions = Contribution::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
        
        $totalContributors = Contribution::where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('member_id')
            ->count('member_id');

        $averageContribution = $totalContributors > 0 ? $totalContributions / $totalContributors : 0;

        // Contribution types
        $monthlyContributions = Contribution::where('type', 'monthly')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $specialContributions = Contribution::where('type', 'special')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_contributions' => $totalContributions,
                'total_contributors' => $totalContributors,
                'average_contribution' => $averageContribution,
            ],
            'by_type' => [
                'monthly_contributions' => $monthlyContributions,
                'special_contributions' => $specialContributions,
            ]
        ]);
    }

    public function propertyAnalytics(Request $request): JsonResponse
    {
        // Property statistics
        $totalProperties = Property::count();
        $availableProperties = Property::where('status', 'available')->count();
        $allocatedProperties = Property::where('status', 'allocated')->count();
        $maintenanceProperties = Property::where('status', 'maintenance')->count();
        $soldProperties = Property::where('status', 'sold')->count();

        // Property values
        $totalPropertyValue = Property::sum('price');
        $availablePropertyValue = Property::where('status', 'available')->sum('price');
        $allocatedPropertyValue = Property::where('status', 'allocated')->sum('price');

        // Property types
        $houses = Property::where('type', 'house')->count();
        $apartments = Property::where('type', 'apartment')->count();
        $lands = Property::where('type', 'land')->count();
        $commercial = Property::where('type', 'commercial')->count();

        return response()->json([
            'property_stats' => [
                'total_properties' => $totalProperties,
                'available' => $availableProperties,
                'allocated' => $allocatedProperties,
                'maintenance' => $maintenanceProperties,
                'sold' => $soldProperties,
            ],
            'property_values' => [
                'total_value' => $totalPropertyValue,
                'available_value' => $availablePropertyValue,
                'allocated_value' => $allocatedPropertyValue,
            ],
            'by_type' => [
                'houses' => $houses,
                'apartments' => $apartments,
                'lands' => $lands,
                'commercial' => $commercial,
            ]
        ]);
    }

    public function monthlyTrends(Request $request): JsonResponse
    {
        $months = $request->get('months', 12);
        $startDate = now()->subMonths($months)->startOfMonth();

        $trends = [];

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $contributions = Contribution::where('status', 'approved')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $loans = Loan::where('status', 'approved')
                ->whereBetween('approved_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $payments = Payment::where('status', 'completed')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $trends[] = [
                'month' => $monthStart->format('Y-m'),
                'month_name' => $monthStart->format('F Y'),
                'contributions' => $contributions,
                'loans' => $loans,
                'payments' => $payments,
            ];
        }

        return response()->json([
            'trends' => $trends,
            'period' => [
                'months' => $months,
                'start_date' => $startDate,
                'end_date' => now()->endOfMonth(),
            ]
        ]);
    }

    public function exportReport(Request $request): JsonResponse
    {
        $reportType = $request->get('type', 'financial');
        $format = $request->get('format', 'json');
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        // This would typically generate and return a file
        // For now, we'll return the data that would be exported
        $data = [];

        switch ($reportType) {
            case 'financial':
                $data = $this->getFinancialReportData($startDate, $endDate);
                break;
            case 'members':
                $data = $this->getMemberReportData();
                break;
            case 'loans':
                $data = $this->getLoanReportData($startDate, $endDate);
                break;
            case 'contributions':
                $data = $this->getContributionReportData($startDate, $endDate);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => $data,
            'metadata' => [
                'type' => $reportType,
                'format' => $format,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'generated_at' => now(),
            ]
        ]);
    }

    private function getFinancialReportData($startDate, $endDate): array
    {
        return [
            'contributions' => Contribution::where('status', 'approved')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get()->toArray(),
            'loans' => Loan::where('status', 'approved')
                ->whereBetween('approved_at', [$startDate, $endDate])
                ->get()->toArray(),
            'payments' => Payment::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get()->toArray(),
        ];
    }

    private function getMemberReportData(): array
    {
        return Member::with(['user'])->get()->toArray();
    }

    private function getLoanReportData($startDate, $endDate): array
    {
        return Loan::with(['member.user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()->toArray();
    }

    private function getContributionReportData($startDate, $endDate): array
    {
        return Contribution::with(['member.user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()->toArray();
    }
}
