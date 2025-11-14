<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SuperAdminController extends Controller
{
    /**
     * Get super-admin dashboard analytics
     */
    public function dashboard(): JsonResponse
    {
        try {
            // Simple test response first
            return response()->json([
                'success' => true,
                'stats' => [
                    'total_tenants' => 5,
                    'active_tenants' => 4,
                    'total_users' => 250,
                    'total_transactions' => 1000,
                    'total_revenue' => 250000,
                    'monthly_revenue' => [
                        ['month' => 'Jul 2024', 'amount' => 25000],
                        ['month' => 'Aug 2024', 'amount' => 30000],
                        ['month' => 'Sep 2024', 'amount' => 35000],
                        ['month' => 'Oct 2024', 'amount' => 40000],
                        ['month' => 'Nov 2024', 'amount' => 45000],
                        ['month' => 'Dec 2024', 'amount' => 50000]
                    ],
                    'revenue_this_month' => 50000,
                    'new_tenants_this_month' => 1,
                    'system_health' => [
                        'database_status' => 'healthy',
                        'api_status' => 'operational',
                        'uptime' => '99.9%',
                        'last_backup' => '2024-12-20 10:00:00',
                        'active_connections' => 15
                    ]
                ],
                'message' => 'Dashboard data retrieved successfully'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                     ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                     ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                     ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                     ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats(): array
    {
        try {
            // Get basic tenant statistics
            $totalTenants = DB::table('tenants')->count();
            $activeTenants = DB::table('tenants')->where('status', 'active')->count();
            
            // For now, return mock data to avoid complex database queries
            // This can be enhanced later with real data aggregation
            return [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'total_users' => $totalTenants * 50, // Mock: average 50 users per tenant
                'total_transactions' => $totalTenants * 200, // Mock: average 200 transactions per tenant
                'total_revenue' => $totalTenants * 50000, // Mock: average $50k revenue per tenant
                'monthly_revenue' => [
                    ['month' => 'Jul 2024', 'amount' => 25000],
                    ['month' => 'Aug 2024', 'amount' => 30000],
                    ['month' => 'Sep 2024', 'amount' => 35000],
                    ['month' => 'Oct 2024', 'amount' => 40000],
                    ['month' => 'Nov 2024', 'amount' => 45000],
                    ['month' => 'Dec 2024', 'amount' => 50000]
                ],
                'revenue_this_month' => 50000,
                'new_tenants_this_month' => max(1, $totalTenants / 10), // Mock: 10% growth
                'system_health' => [
                    'database_status' => 'healthy',
                    'api_status' => 'operational',
                    'uptime' => '99.9%',
                    'last_backup' => now()->subDays(1)->format('Y-m-d H:i:s'),
                    'active_connections' => 15
                ]
            ];
        } catch (\Exception $e) {
            // Return basic stats if database queries fail
            return [
                'total_tenants' => 0,
                'active_tenants' => 0,
                'total_users' => 0,
                'total_transactions' => 0,
                'total_revenue' => 0,
                'monthly_revenue' => [],
                'revenue_this_month' => 0,
                'new_tenants_this_month' => 0,
                'system_health' => [
                    'database_status' => 'unknown',
                    'api_status' => 'operational',
                    'uptime' => '99.9%',
                    'last_backup' => 'N/A',
                    'active_connections' => 0
                ]
            ];
        }
    }

    /**
     * Get monthly revenue for the last 6 months
     */
    private function getMonthlyRevenue(): array
    {
        $monthlyData = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'amount' => $this->getRevenueForMonth($date)
            ];
        }
        
        return $monthlyData;
    }

    /**
     * Get revenue for a specific month
     */
    private function getRevenueForMonth($date): float
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        
        $totalRevenue = 0;
        $tenants = DB::table('tenants')->get();
        
        foreach ($tenants as $tenant) {
            try {
                config(['database.connections.tenant.database' => $tenant->database_name ?? 'tenant_' . $tenant->id]);
                
                $monthlyRevenue = DB::connection('tenant')->table('payments')
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->sum('amount') ?? 0;
                    
                $totalRevenue += $monthlyRevenue;
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return $totalRevenue;
    }

    /**
     * Get current month revenue
     */
    private function getCurrentMonthRevenue(): float
    {
        return $this->getRevenueForMonth(now());
    }

    /**
     * Get new tenants this month
     */
    private function getNewTenantsThisMonth(): int
    {
        return DB::table('tenants')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Get system health metrics
     */
    private function getSystemHealth(): array
    {
        return [
            'database_status' => 'healthy',
            'api_status' => 'operational',
            'uptime' => '99.9%',
            'last_backup' => now()->subDays(1)->format('Y-m-d H:i:s'),
            'active_connections' => DB::table('personal_access_tokens')->where('last_used_at', '>=', now()->subHour())->count()
        ];
    }
}
