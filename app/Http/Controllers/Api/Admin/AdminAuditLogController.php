<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdminAuditLogController extends Controller
{
    /**
     * Get audit logs with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AuditLog::with(['user:id,first_name,last_name,email']);

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by action
            if ($request->has('action') && $request->action !== 'all') {
                $query->where('action', $request->action);
            }

            // Filter by module
            if ($request->has('module') && $request->module !== 'all') {
                $query->where('module', $request->module);
            }

            // Filter by resource type
            if ($request->has('resource_type')) {
                $query->where('resource_type', $request->resource_type);
            }

            // Filter by resource ID
            if ($request->has('resource_id')) {
                $query->where('resource_id', $request->resource_id);
            }

            // Date range filter
            $dateRange = $request->get('date_range', 'this-month');
            $now = Carbon::now();
            [$startDate, $endDate] = match($dateRange) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'this-week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'this-month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last-month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                'last-7-days' => [$now->copy()->subDays(7), $now->copy()],
                'last-30-days' => [$now->copy()->subDays(30), $now->copy()],
                'custom' => [
                    $request->has('start_date') ? Carbon::parse($request->start_date)->startOfDay() : $now->copy()->subMonth(),
                    $request->has('end_date') ? Carbon::parse($request->end_date)->endOfDay() : $now->copy()
                ],
                default => [$now->copy()->subMonth(), $now->copy()]
            };
            $query->whereBetween('created_at', [$startDate, $endDate]);

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('module', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQ) use ($search) {
                          $userQ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $perPage = $request->get('per_page', 50);
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->map(function($log) {
                    return [
                        'id' => $log->id,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->first_name . ' ' . $log->user->last_name,
                            'email' => $log->user->email,
                        ] : null,
                        'action' => $log->action,
                        'module' => $log->module,
                        'resource_type' => $log->resource_type,
                        'resource_id' => $log->resource_id,
                        'description' => $log->description,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'metadata' => $log->metadata,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'created_at' => $log->created_at->toISOString(),
                        'created_at_formatted' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
                'filters' => [
                    'date_range' => $dateRange,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get audit log statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $dateRange = $request->get('date_range', 'this-month');
            $now = Carbon::now();
            [$startDate, $endDate] = match($dateRange) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'this-week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'this-month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last-month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                'last-7-days' => [$now->copy()->subDays(7), $now->copy()],
                'last-30-days' => [$now->copy()->subDays(30), $now->copy()],
                default => [$now->copy()->subMonth(), $now->copy()]
            };

            $baseQuery = AuditLog::whereBetween('created_at', [$startDate, $endDate]);

            $stats = [
                'total' => (clone $baseQuery)->count(),
                'by_action' => (clone $baseQuery)
                    ->selectRaw('action, COUNT(*) as count')
                    ->groupBy('action')
                    ->pluck('count', 'action')
                    ->toArray(),
                'by_module' => (clone $baseQuery)
                    ->selectRaw('module, COUNT(*) as count')
                    ->whereNotNull('module')
                    ->groupBy('module')
                    ->pluck('count', 'module')
                    ->toArray(),
                'by_user' => (clone $baseQuery)
                    ->selectRaw('user_id, COUNT(*) as count')
                    ->whereNotNull('user_id')
                    ->groupBy('user_id')
                    ->with('user:id,first_name,last_name,email')
                    ->get()
                    ->map(function($item) {
                        return [
                            'user_id' => $item->user_id,
                            'user' => $item->user ? [
                                'name' => $item->user->first_name . ' ' . $item->user->last_name,
                                'email' => $item->user->email,
                            ] : null,
                            'count' => $item->count,
                        ];
                    })
                    ->toArray(),
                'login_logout' => [
                    'logins' => (clone $baseQuery)->where('action', 'login')->count(),
                    'logouts' => (clone $baseQuery)->where('action', 'logout')->count(),
                ],
                'recent_activity' => (clone $baseQuery)
                    ->with('user:id,first_name,last_name,email')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($log) {
                        return [
                            'id' => $log->id,
                            'action' => $log->action,
                            'module' => $log->module,
                            'description' => $log->description,
                            'user' => $log->user ? $log->user->first_name . ' ' . $log->user->last_name : 'System',
                            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                        ];
                    })
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit log statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get a single audit log
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user:id,first_name,last_name,email');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $auditLog->id,
                'user' => $auditLog->user ? [
                    'id' => $auditLog->user->id,
                    'name' => $auditLog->user->first_name . ' ' . $auditLog->user->last_name,
                    'email' => $auditLog->user->email,
                ] : null,
                'action' => $auditLog->action,
                'module' => $auditLog->module,
                'resource_type' => $auditLog->resource_type,
                'resource_id' => $auditLog->resource_id,
                'description' => $auditLog->description,
                'old_values' => $auditLog->old_values,
                'new_values' => $auditLog->new_values,
                'metadata' => $auditLog->metadata,
                'ip_address' => $auditLog->ip_address,
                'user_agent' => $auditLog->user_agent,
                'created_at' => $auditLog->created_at->toISOString(),
                'created_at_formatted' => $auditLog->created_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Get audit logs for a specific resource
     */
    public function getResourceLogs(Request $request, string $resourceType, string $resourceId): JsonResponse
    {
        try {
            $logs = AuditLog::where('resource_type', $resourceType)
                ->where('resource_id', $resourceId)
                ->with('user:id,first_name,last_name,email')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $logs->map(function($log) {
                    return [
                        'id' => $log->id,
                        'user' => $log->user ? [
                            'name' => $log->user->first_name . ' ' . $log->user->last_name,
                            'email' => $log->user->email,
                        ] : null,
                        'action' => $log->action,
                        'description' => $log->description,
                        'old_values' => $log->old_values,
                        'new_values' => $log->new_values,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve resource audit logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific user
     */
    public function getUserLogs(Request $request, User $user): JsonResponse
    {
        try {
            $logs = AuditLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                ],
                'data' => $logs->map(function($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'module' => $log->module,
                        'description' => $log->description,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user audit logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Export audit logs to CSV
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $query = AuditLog::with(['user:id,first_name,last_name,email']);

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by action
            if ($request->has('action') && $request->action !== 'all') {
                $query->where('action', $request->action);
            }

            // Filter by module
            if ($request->has('module') && $request->module !== 'all') {
                $query->where('module', $request->module);
            }

            // Filter by resource type
            if ($request->has('resource_type')) {
                $query->where('resource_type', $request->resource_type);
            }

            // Filter by resource ID
            if ($request->has('resource_id')) {
                $query->where('resource_id', $request->resource_id);
            }

            // Date range filter
            $dateRange = $request->get('date_range', 'this-month');
            $now = Carbon::now();
            [$startDate, $endDate] = match($dateRange) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'this-week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'this-month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last-month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                'last-7-days' => [$now->copy()->subDays(7), $now->copy()],
                'last-30-days' => [$now->copy()->subDays(30), $now->copy()],
                'custom' => [
                    $request->has('start_date') ? Carbon::parse($request->start_date)->startOfDay() : $now->copy()->subMonth(),
                    $request->has('end_date') ? Carbon::parse($request->end_date)->endOfDay() : $now->copy()
                ],
                default => [$now->copy()->subMonth(), $now->copy()]
            };
            $query->whereBetween('created_at', [$startDate, $endDate]);

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('module', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQ) use ($search) {
                          $userQ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Get all logs for export (no pagination)
            $logs = $query->get();

            // Prepare CSV headers
            $headers = [
                'ID',
                'Timestamp',
                'User Name',
                'User Email',
                'Action',
                'Module',
                'Resource Type',
                'Resource ID',
                'Description',
                'IP Address',
                'User Agent',
            ];

            // Prepare filename
            $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';

            // Set CSV response headers
            $responseHeaders = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function() use ($logs, $headers) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Write headers
                fputcsv($file, $headers);
                
                // Write data rows
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user ? ($log->user->first_name . ' ' . $log->user->last_name) : 'System',
                        $log->user ? $log->user->email : 'N/A',
                        $log->action,
                        $log->module ?? 'N/A',
                        $log->resource_type ?? 'N/A',
                        $log->resource_id ?? 'N/A',
                        $log->description,
                        $log->ip_address ?? 'N/A',
                        $log->user_agent ?? 'N/A',
                    ]);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $responseHeaders);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export audit logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
}

