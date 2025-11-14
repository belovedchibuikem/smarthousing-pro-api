<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Property;
use App\Models\Tenant\PropertyAllocation;
use App\Models\Tenant\PropertyMaintenanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyManagementReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

    try {
        // Get estate reports
        $estates = Property::select(
            'location', 
            DB::raw('COALESCE(city, "") as city'), 
            DB::raw('COALESCE(state, "") as state'),
            DB::raw('COUNT(*) as total_properties'),
            DB::raw('COUNT(CASE WHEN status = "available" THEN 1 END) as available_properties'),
            DB::raw('COUNT(CASE WHEN status = "allocated" OR status = "sold" THEN 1 END) as allocated_properties')
        )
        ->whereNotNull('location')
        ->groupBy('location', 'city', 'state')
        ->get()
        ->map(function($item) {
            $total = (int) $item->total_properties;
            $allocated = (int) $item->allocated_properties;
            $available = (int) $item->available_properties;
            
            // More efficient: query once instead of N+1 queries
            $maintenanceCount = PropertyMaintenanceRecord::whereHas('property', function($q) use ($item) {
                $q->where('location', $item->location);
                if (!empty($item->city)) {
                    $q->where(function($query) use ($item) {
                        $query->where('city', $item->city)->orWhereNull('city');
                    });
                }
                if (!empty($item->state)) {
                    $q->where(function($query) use ($item) {
                        $query->where('state', $item->state)->orWhereNull('state');
                    });
                }
            })->count();
            
            return [
                'estate_name' => $item->location,
                'location' => trim($item->city . ', ' . $item->state, ', '),
                'total_properties' => $total,
                'allocated_properties' => $allocated,
                'available_properties' => $available,
                'occupancy_rate' => $total > 0 ? round(($allocated / $total) * 100, 2) : 0,
                'maintenance_requests' => $maintenanceCount,
            ];
        });

        // Get allottee reports grouped by estate
        $allotteeReports = PropertyAllocation::select(
            'properties.location', 
            DB::raw('COALESCE(properties.city, "") as city'), 
            DB::raw('COALESCE(properties.state, "") as state'),
            DB::raw('COUNT(*) as total_allottees'),
            DB::raw('COUNT(CASE WHEN property_allocations.status = "approved" THEN 1 END) as active_allottees'),
            DB::raw('COUNT(CASE WHEN property_allocations.status = "rejected" THEN 1 END) as inactive_allottees')
        )
        ->join('properties', 'property_allocations.property_id', '=', 'properties.id')
        ->whereNotNull('properties.location')
        ->groupBy('properties.location', 'properties.city', 'properties.state')
        ->get()
        ->map(function($item) {
            return [
                'estate_name' => $item->location,
                'location' => trim($item->city . ', ' . $item->state, ', '),
                'total_allottees' => (int) $item->total_allottees,
                'active_allottees' => (int) $item->active_allottees,
                'inactive_allottees' => (int) $item->inactive_allottees,
            ];
        });

        // Get maintenance reports
        $maintenanceReports = PropertyMaintenanceRecord::with(['property' => function($query) {
            $query->select('id', 'location', 'city', 'state');
        }])
        ->select('id', 'property_id', 'issue_type', 'status', 'reported_date', 'completed_date', 'actual_cost', 'estimated_cost')
        ->orderBy('reported_date', 'desc')
        ->limit(50)
        ->get()
        ->map(function($record) {
            return [
                'id' => $record->id,
                'estate_name' => $record->property->location ?? '—',
                'location' => $record->property 
                    ? trim(($record->property->city ?? '') . ', ' . ($record->property->state ?? ''), ', ') 
                    : '—',
                'issue_type' => $record->issue_type ?? '—',
                'status' => $record->status,
                'reported_date' => $record->reported_date,
                'completed_date' => $record->completed_date,
                'cost' => $record->actual_cost ?? $record->estimated_cost ?? 0,
            ];
        });

        // Calculate overall stats
        $totalEstates = $estates->count();
        $totalAllottees = PropertyAllocation::count();
        $totalMaintenance = PropertyMaintenanceRecord::count();
        $totalProperties = Property::count();
        $allocatedProperties = Property::whereIn('status', ['allocated', 'sold'])->count();
        $occupancyRate = $totalProperties > 0 ? round(($allocatedProperties / $totalProperties) * 100, 2) : 0;
                return response()->json([
                    'success' => true,
                    'data' => [
                        'stats' => [
                            'total_estates' => $totalEstates,
                            'total_allottees' => $totalAllottees,
                            'maintenance_requests' => $totalMaintenance,
                            'occupancy_rate' => $occupancyRate,
                        ],
                        'estate_reports' => $estates,
                        'allottee_reports' => $allotteeReports,
                        'maintenance_reports' => $maintenanceReports,
                    ]
                ]);
    } catch (\Exception $e) {
        Log::error('Error fetching estate reports: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error fetching estate reports',
            'error' => $e->getMessage()
        ], 500);
    }
    }

    public function estateReport(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $estateId = $request->get('estate_id');
        
        $estates = Property::select('location', 'city', 'state',
                DB::raw('count(*) as total'),
                DB::raw('count(case when status = "available" then 1 end) as available'),
                DB::raw('count(case when status = "allocated" or status = "sold" then 1 end) as allocated'),
                DB::raw('sum(price) as total_value'))
            ->whereNotNull('location');

        if ($estateId) {
            $estates->where('location', $estateId);
        }

        $estates = $estates->groupBy('location', 'city', 'state')->get();

        return response()->json([
            'success' => true,
            'data' => $estates
        ]);
    }

    public function allotteeReport(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $allotteeId = $request->get('allottee_id');

        $query = PropertyAllocation::with(['property', 'member.user']);

        if ($allotteeId) {
            $query->where('id', $allotteeId);
        }

        $report = [
            'total_allocations' => PropertyAllocation::count(),
            'approved_allocations' => PropertyAllocation::where('status', 'approved')->count(),
            'pending_allocations' => PropertyAllocation::where('status', 'pending')->count(),
            'rejected_allocations' => PropertyAllocation::where('status', 'rejected')->count(),
            'allocations' => $query->limit(100)->get()->map(function($allocation) {
                return [
                    'id' => $allocation->id,
                    'member' => $allocation->member->user ? [
                        'name' => ($allocation->member->user->first_name ?? '') . ' ' . ($allocation->member->user->last_name ?? ''),
                        'member_id' => $allocation->member->member_id ?? $allocation->member->staff_id ?? '—',
                    ] : null,
                    'property' => [
                        'title' => $allocation->property->title,
                        'location' => $allocation->property->location,
                    ],
                    'allocation_date' => $allocation->allocation_date,
                    'status' => $allocation->status,
                ];
            }),
            'allocations_by_month' => PropertyAllocation::select(
                    DB::raw('DATE_FORMAT(allocation_date, "%Y-%m") as month'),
                    DB::raw('count(*) as count')
                )
                ->whereNotNull('allocation_date')
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    public function maintenanceReport(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $maintenanceId = $request->get('maintenance_id');

        $query = PropertyMaintenanceRecord::with(['property', 'reporter.user', 'assignee']);

        if ($maintenanceId) {
            $query->where('id', $maintenanceId);
        }

        $report = [
            'total_records' => PropertyMaintenanceRecord::count(),
            'pending_records' => PropertyMaintenanceRecord::where('status', 'pending')->count(),
            'in_progress_records' => PropertyMaintenanceRecord::where('status', 'in_progress')->count(),
            'completed_records' => PropertyMaintenanceRecord::where('status', 'completed')->count(),
            'cancelled_records' => PropertyMaintenanceRecord::where('status', 'cancelled')->count(),
            'total_estimated_cost' => PropertyMaintenanceRecord::whereIn('status', ['pending', 'in_progress'])->sum('estimated_cost'),
            'total_actual_cost' => PropertyMaintenanceRecord::where('status', 'completed')->sum('actual_cost'),
            'records' => $query->orderBy('reported_date', 'desc')->limit(100)->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }
}
