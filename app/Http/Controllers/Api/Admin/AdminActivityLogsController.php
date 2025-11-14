<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminActivityLogsController extends Controller
{
    /**
     * Get activity logs with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get tenant ID from tenancy context
            $tenant = tenant();
            $tenantId = null;
            
            if ($tenant) {
                $tenantId = $tenant->id;
            } else {
                // Fallback: try to extract from database connection name
                $dbName = config('database.connections.tenant.database', '');
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

            $search = $request->get('search', '');
            $moduleFilter = $request->get('module', 'all');
            $actionFilter = $request->get('action', 'all');
            $dateRange = $request->get('date_range', 'this-month');
            
            // Get date range
            $now = Carbon::now();
            [$startDate, $endDate] = match($dateRange) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'this-week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'this-month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last-month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                default => [Carbon::parse('2020-01-01'), $now->copy()]
            };

            $query = ActivityLog::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate, $endDate]);

            // Search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('module', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%");
                      // Note: Can't search causer directly due to cross-database relationship
                });
            }

            // Module filter
            if ($moduleFilter !== 'all') {
                $query->where('module', $moduleFilter);
            }

            // Action filter
            if ($actionFilter !== 'all') {
                $query->where('action', $actionFilter);
            }

            $logs = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            // Map module to icon/color for frontend
            $moduleIcons = [
                'loan' => ['icon' => 'dollar', 'color' => 'text-orange-600', 'bgColor' => 'bg-orange-100'],
                'payment' => ['icon' => 'dollar', 'color' => 'text-green-600', 'bgColor' => 'bg-green-100'],
                'kyc' => ['icon' => 'shield', 'color' => 'text-blue-600', 'bgColor' => 'bg-blue-100'],
                'property' => ['icon' => 'home', 'color' => 'text-purple-600', 'bgColor' => 'bg-purple-100'],
                'profile' => ['icon' => 'user', 'color' => 'text-teal-600', 'bgColor' => 'bg-teal-100'],
                'report' => ['icon' => 'file-text', 'color' => 'text-gray-600', 'bgColor' => 'bg-gray-100'],
                'document' => ['icon' => 'file', 'color' => 'text-indigo-600', 'bgColor' => 'bg-indigo-100'],
                'admin' => ['icon' => 'shield', 'color' => 'text-red-600', 'bgColor' => 'bg-red-100'],
            ];

            // Get user information from tenant database for logs
            $userIds = $logs->pluck('causer_id')->filter()->unique();
            $users = [];
            
            if ($userIds->isNotEmpty() && $logs->where('causer_type', 'App\Models\Tenant\User')->isNotEmpty()) {
                try {
                    // Load users from tenant database
                    $tenantUsers = \App\Models\Tenant\User::whereIn('id', $userIds)
                        ->select('id', 'first_name', 'last_name', 'email')
                        ->get()
                        ->keyBy('id');
                    
                    foreach ($tenantUsers as $user) {
                        $users[$user->id] = $user;
                    }
                } catch (\Exception $e) {
                    // If we can't load users, continue without user data
                    Log::warning('Failed to load users for activity logs', ['error' => $e->getMessage()]);
                }
            }

            $logsData = $logs->map(function($log) use ($moduleIcons, $users) {
                $moduleKey = strtolower($log->module ?? 'admin');
                $iconData = $moduleIcons[$moduleKey] ?? $moduleIcons['admin'];
                
                $userName = 'System';
                if ($log->causer_type === 'App\Models\Tenant\User' && $log->causer_id && isset($users[$log->causer_id])) {
                    $user = $users[$log->causer_id];
                    $userName = $user->first_name . ' ' . $user->last_name;
                    if ($user->email) {
                        $userName .= ' (' . $user->email . ')';
                    }
                } elseif ($log->causer_type === 'App\Models\Central\SuperAdmin' && $log->causer) {
                    // For super admin, we can load directly since it's in the same database
                    $userName = $log->causer->first_name . ' ' . $log->causer->last_name;
                    if ($log->causer->email) {
                        $userName .= ' (' . $log->causer->email . ')';
                    }
                }

                return [
                    'id' => $log->id,
                    'user' => $userName,
                    'action' => ucfirst($log->action) . ' - ' . ($log->module ? ucfirst($log->module) : 'General'),
                    'details' => $log->description ?? 'No details available',
                    'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                    'module' => $log->module ?? 'admin',
                    'type' => $log->module ?? 'admin', // For filtering
                    'icon' => $iconData['icon'],
                    'color' => $iconData['color'],
                    'bgColor' => $iconData['bgColor'],
                    'ip_address' => $log->ip_address,
                    'properties' => $log->properties,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $logsData,
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
                'message' => 'Failed to retrieve activity logs',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get activity log statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $tenant = tenant();
            $tenantId = null;
            
            if ($tenant) {
                $tenantId = $tenant->id;
            } else {
                $dbName = config('database.connections.tenant.database', '');
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

            $baseQuery = ActivityLog::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate, $endDate]);

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
                'recent_activity' => (clone $baseQuery)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($log) {
                        $userName = 'System';
                        // Try to load user if it's a tenant user
                        if ($log->causer_type === 'App\Models\Tenant\User' && $log->causer_id) {
                            try {
                                $user = \App\Models\Tenant\User::find($log->causer_id);
                                if ($user) {
                                    $userName = $user->first_name . ' ' . $user->last_name;
                                }
                            } catch (\Exception $e) {
                                // Ignore if can't load user
                            }
                        } elseif ($log->causer_type === 'App\Models\Central\SuperAdmin' && $log->causer) {
                            $userName = $log->causer->first_name . ' ' . $log->causer->last_name;
                        }
                        
                        return [
                            'id' => $log->id,
                            'action' => $log->action,
                            'module' => $log->module,
                            'description' => $log->description,
                            'user' => $userName,
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
                'message' => 'Failed to retrieve activity log statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get a single activity log
     */
    public function show(ActivityLog $activityLog): JsonResponse
    {
        // Verify this log belongs to the current tenant
        $tenant = tenant();
        $tenantId = $tenant ? $tenant->id : null;
        
        if ($tenantId && $activityLog->tenant_id !== $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Load user information based on causer type
        $userName = 'System';
        $userEmail = null;
        
        if ($activityLog->causer_type === 'App\Models\Tenant\User' && $activityLog->causer_id) {
            try {
                $user = \App\Models\Tenant\User::find($activityLog->causer_id);
                if ($user) {
                    $userName = $user->first_name . ' ' . $user->last_name;
                    $userEmail = $user->email;
                }
            } catch (\Exception $e) {
                // Ignore if can't load user
            }
        } elseif ($activityLog->causer_type === 'App\Models\Central\SuperAdmin') {
            $activityLog->load('causer');
            if ($activityLog->causer) {
                $userName = $activityLog->causer->first_name . ' ' . $activityLog->causer->last_name;
                $userEmail = $activityLog->causer->email;
            }
        }
        
        if ($userEmail) {
            $userName .= ' (' . $userEmail . ')';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $activityLog->id,
                'user' => $userName,
                'action' => $activityLog->action,
                'module' => $activityLog->module,
                'description' => $activityLog->description,
                'properties' => $activityLog->properties,
                'ip_address' => $activityLog->ip_address,
                'user_agent' => $activityLog->user_agent,
                'created_at' => $activityLog->created_at->toISOString(),
                'created_at_formatted' => $activityLog->created_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }
}

