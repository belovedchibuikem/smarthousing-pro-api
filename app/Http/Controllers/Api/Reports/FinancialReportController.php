<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Investment;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\Payment;
use App\Models\Tenant\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        
        $stats = [
            'total_loans' => Loan::whereBetween('created_at', $dateRange)->count(),
            'total_loans_amount' => Loan::whereBetween('created_at', $dateRange)->sum('amount'),
            'active_loans' => Loan::where('status', 'approved')->whereBetween('created_at', $dateRange)->count(),
            'total_investments' => Investment::whereBetween('created_at', $dateRange)->count(),
            'total_investments_amount' => Investment::whereBetween('created_at', $dateRange)->sum('amount'),
            'active_investments' => Investment::where('status', 'active')->whereBetween('created_at', $dateRange)->count(),
            'total_contributions' => Contribution::whereBetween('created_at', $dateRange)->count(),
            'total_contributions_amount' => Contribution::whereBetween('created_at', $dateRange)->sum('amount'),
            'total_payments' => Payment::whereBetween('created_at', $dateRange)->count(),
            'total_payments_amount' => Payment::whereBetween('created_at', $dateRange)->sum('amount'),
            'successful_payments' => Payment::where('status', 'completed')->whereBetween('created_at', $dateRange)->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'date_range' => $dateRange
        ]);
    }

    public function loans(Request $request): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        
        $loans = Loan::with(['member.user'])
            ->whereBetween('created_at', $dateRange)
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->type, function($query, $type) {
                return $query->where('type', $type);
            })
            ->paginate($request->get('per_page', 15));

        $summary = [
            'total_amount' => $loans->sum('amount'),
            'average_amount' => $loans->avg('amount'),
            'status_breakdown' => $loans->groupBy('status')->map->count(),
            'type_breakdown' => $loans->groupBy('type')->map->count(),
        ];

        return response()->json([
            'loans' => $loans,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ]
        ]);
    }

    public function investments(Request $request): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        
        $investments = Investment::with(['member.user'])
            ->whereBetween('created_at', $dateRange)
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->type, function($query, $type) {
                return $query->where('type', $type);
            })
            ->paginate($request->get('per_page', 15));

        $summary = [
            'total_amount' => $investments->sum('amount'),
            'average_amount' => $investments->avg('amount'),
            'expected_returns' => $investments->sum('expected_return'),
            'status_breakdown' => $investments->groupBy('status')->map->count(),
            'type_breakdown' => $investments->groupBy('type')->map->count(),
        ];

        return response()->json([
            'investments' => $investments,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $investments->currentPage(),
                'last_page' => $investments->lastPage(),
                'per_page' => $investments->perPage(),
                'total' => $investments->total(),
            ]
        ]);
    }

    public function contributions(Request $request): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        
        $contributions = Contribution::with(['member.user'])
            ->whereBetween('created_at', $dateRange)
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->type, function($query, $type) {
                return $query->where('type', $type);
            })
            ->paginate($request->get('per_page', 15));

        $summary = [
            'total_amount' => $contributions->sum('amount'),
            'average_amount' => $contributions->avg('amount'),
            'status_breakdown' => $contributions->groupBy('status')->map->count(),
            'type_breakdown' => $contributions->groupBy('type')->map->count(),
        ];

        return response()->json([
            'contributions' => $contributions,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
                'per_page' => $contributions->perPage(),
                'total' => $contributions->total(),
            ]
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $dateRange = $this->getDateRange($request);
        
        $payments = Payment::with(['user'])
            ->whereBetween('created_at', $dateRange)
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->payment_method, function($query, $method) {
                return $query->where('payment_method', $method);
            })
            ->paginate($request->get('per_page', 15));

        $summary = [
            'total_amount' => $payments->sum('amount'),
            'average_amount' => $payments->avg('amount'),
            'status_breakdown' => $payments->groupBy('status')->map->count(),
            'method_breakdown' => $payments->groupBy('payment_method')->map->count(),
        ];

        return response()->json([
            'payments' => $payments,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ]
        ]);
    }

    public function monthlyTrends(Request $request): JsonResponse
    {
        $months = $request->get('months', 12);
        $startDate = now()->subMonths($months)->startOfMonth();
        $endDate = now()->endOfMonth();

        $trends = [
            'loans' => $this->getMonthlyTrends(Loan::class, 'amount', $startDate, $endDate),
            'investments' => $this->getMonthlyTrends(Investment::class, 'amount', $startDate, $endDate),
            'contributions' => $this->getMonthlyTrends(Contribution::class, 'amount', $startDate, $endDate),
            'payments' => $this->getMonthlyTrends(Payment::class, 'amount', $startDate, $endDate),
        ];

        return response()->json([
            'trends' => $trends,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'months' => $months
            ]
        ]);
    }

    private function getDateRange(Request $request): array
    {
        $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());
        
        return [$startDate, $endDate];
    }

    private function getMonthlyTrends(string $model, string $amountField, $startDate, $endDate): array
    {
        return $model::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw("SUM({$amountField}) as total"),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->month => [
                    'total' => $item->total,
                    'count' => $item->count
                ]];
            });
    }
}
