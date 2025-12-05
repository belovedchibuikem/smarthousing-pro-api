<?php

namespace App\Services;

use App\Models\Central\Tenant;
use App\Models\Central\Package;
use App\Models\Central\Subscription;
use App\Models\Central\PlatformTransaction;
use App\Models\Central\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Get comprehensive dashboard overview
     */
    public function getDashboardOverview(): array
    {
        // Cache for 5 minutes, but use a shorter key to allow faster updates
        $cacheKey = 'dashboard_overview_' . date('Y-m-d-H-i');
        
        return Cache::remember($cacheKey, 300, function () {
            return [
                'metrics' => $this->getKeyMetrics(),
                'recent_businesses' => $this->getRecentBusinesses(5),
                'revenue_analytics' => $this->getRevenueAnalytics('monthly'),
                'subscription_analytics' => $this->getSubscriptionAnalytics(),
                'alerts' => $this->getAlerts(),
                'system_health' => $this->getSystemHealth()
            ];
        });
    }

    /**
     * Get key metrics for dashboard
     */
    public function getKeyMetrics(): array
    {
        $totalBusinesses = Tenant::count();
        $activeBusinesses = Tenant::whereJsonContains('data->status', 'active')->count();
        $trialBusinesses = Tenant::whereJsonContains('data->status', 'trial')->count();
        
        // Get total revenue from platform transactions
        $totalRevenue = PlatformTransaction::where('type', 'subscription_payment')
            ->where('status', 'completed')
            ->sum('amount');
            
        $monthlyRevenue = PlatformTransaction::where('type', 'subscription_payment')
            ->where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('amount');

        // Get total members across all tenants
        $totalMembers = $this->getTotalMembersAcrossTenants();
        
        // Get active subscriptions
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        
        // Get past due subscriptions
        $pastDueSubscriptions = Subscription::where('status', 'past_due')->count();

        // Calculate growth percentages
        $previousMonth = Carbon::now()->subMonth();
        $previousMonthRevenue = PlatformTransaction::where('type', 'subscription_payment')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$previousMonth->startOfMonth(), $previousMonth->endOfMonth()])
            ->sum('amount');
            
        $revenueGrowth = $previousMonthRevenue > 0 
            ? (($monthlyRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100 
            : 0;

        $previousMonthMembers = $this->getTotalMembersAtDate($previousMonth);
        $memberGrowth = $previousMonthMembers > 0 
            ? (($totalMembers - $previousMonthMembers) / $previousMonthMembers) * 100 
            : 0;

        return [
            'total_businesses' => $totalBusinesses,
            'active_businesses' => $activeBusinesses,
            'trial_businesses' => $trialBusinesses,
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'total_members' => $totalMembers,
            'active_subscriptions' => $activeSubscriptions,
            'past_due_subscriptions' => $pastDueSubscriptions,
            'revenue_growth_percentage' => round($revenueGrowth, 2),
            'member_growth_percentage' => round($memberGrowth, 2)
        ];
    }

    /**
     * Get recent businesses
     */
    public function getRecentBusinesses(int $limit = 10): array
    {
        return Tenant::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($tenant) {
                // Get subscription for this tenant
                $subscription = Subscription::where('tenant_id', $tenant->id)->first();
                $package = $subscription?->package;
                
                // Get business name exactly like BusinessResource does
                // This matches the logic in BusinessResource::toArray() line 14
                // BusinessResource uses: $this->data['name'] ?? $this->name ?? 'Unknown Business'
                // Since Tenant model doesn't have a 'name' attribute, we only check data['name']
                // Use the exact same null coalescing pattern as BusinessResource
                $tenantData = $tenant->data ?? [];
                $businessName = $tenantData['name'] ?? 'Unknown Business';
                
                return [
                    'id' => $tenant->id,
                    'name' => $businessName,
                    'slug' => $tenant->id, // Match BusinessResource which uses tenant->id as slug
                    'package' => $package?->name ?? 'No Package',
                    'status' => $tenantData['status'] ?? 'unknown',
                    'members' => $this->getTenantMemberCount($tenant->id),
                    'revenue' => $subscription?->amount ?? 0,
                    'joined_date' => $tenant->created_at->format('Y-m-d'),
                    'logo_url' => $tenantData['logo_url'] ?? null,
                    'contact_email' => $tenantData['contact_email'] ?? null
                ];
            })
            ->toArray();
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(string $period = 'monthly'): array
    {
        $startDate = match($period) {
            'quarterly' => Carbon::now()->startOfQuarter(),
            'yearly' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth()
        };

        $transactions = PlatformTransaction::where('type', 'subscription_payment')
            ->where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $monthlyData = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte(Carbon::now())) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayRevenue = $transactions->where('date', $dateStr)->first();
            
            $monthlyData[] = [
                'date' => $dateStr,
                'revenue' => $dayRevenue?->revenue ?? 0
            ];
            
            $currentDate->addDay();
        }

        return [
            'period' => $period,
            'data' => $monthlyData,
            'total_revenue' => $transactions->sum('revenue'),
            'average_daily' => $transactions->avg('revenue') ?? 0
        ];
    }

    /**
     * Get subscription analytics
     */
    public function getSubscriptionAnalytics(): array
    {
        $subscriptions = Subscription::with('package')
            ->selectRaw('package_id, COUNT(*) as count, SUM(amount) as revenue')
            ->groupBy('package_id')
            ->get();

        $packageAnalytics = $subscriptions->map(function ($sub) {
            return [
                'package_name' => $sub->package?->name ?? 'Unknown',
                'count' => $sub->count,
                'revenue' => $sub->revenue
            ];
        });

        $statusCounts = Subscription::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'by_package' => $packageAnalytics,
            'by_status' => $statusCounts,
            'total_subscriptions' => array_sum($statusCounts)
        ];
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        $databaseStatus = $this->checkDatabaseHealth();
        $apiStatus = $this->checkApiHealth();
        $uptime = $this->getSystemUptime();
        
        return [
            'database_status' => $databaseStatus ? 'healthy' : 'unhealthy',
            'api_status' => $apiStatus ? 'operational' : 'degraded',
            'uptime' => $uptime,
            'active_connections' => $this->getActiveConnections(),
            'last_backup' => $this->getLastBackupTime(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage()
        ];
    }

    /**
     * Get alerts and notifications
     */
    public function getAlerts(): array
    {
        $alerts = [];
        
        // Past due subscriptions
        $pastDueCount = Subscription::where('status', 'past_due')->count();
        if ($pastDueCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Past Due Subscriptions',
                'message' => "{$pastDueCount} subscriptions are past due",
                'count' => $pastDueCount,
                'action_url' => '/super-admin/subscriptions?status=past_due'
            ];
        }

        // Trial businesses expiring soon
        $expiringTrials = Tenant::whereJsonContains('data->status', 'trial')
            ->whereJsonContains('data->trial_ends_at', Carbon::now()->addDays(3)->toDateString())
            ->count();
            
        if ($expiringTrials > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Trial Businesses Expiring',
                'message' => "{$expiringTrials} trial businesses expire in 3 days",
                'count' => $expiringTrials,
                'action_url' => '/super-admin/businesses?status=trial'
            ];
        }

        // High memory usage
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage > 80) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'High Memory Usage',
                'message' => "Memory usage is at {$memoryUsage}%",
                'count' => 1,
                'action_url' => '/super-admin/system'
            ];
        }

        return $alerts;
    }

    /**
     * Get platform statistics
     */
    public function getPlatformStats(): array
    {
        return [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::whereJsonContains('data->status', 'active')->count(),
            'total_users' => $this->getTotalMembersAcrossTenants(),
            'total_transactions' => PlatformTransaction::count(),
            'total_revenue' => PlatformTransaction::where('type', 'subscription_payment')
                ->where('status', 'completed')
                ->sum('amount'),
            'monthly_revenue' => PlatformTransaction::where('type', 'subscription_payment')
                ->where('status', 'completed')
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->sum('amount'),
            'revenue_this_month' => PlatformTransaction::where('type', 'subscription_payment')
                ->where('status', 'completed')
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->sum('amount'),
            'new_tenants_this_month' => Tenant::where('created_at', '>=', Carbon::now()->startOfMonth())->count()
        ];
    }

    /**
     * Get total members across all tenants
     */
    public function getTotalMembersAcrossTenants(): int
    {
        try {
            $totalMembers = 0;
            
            // Get all tenants dynamically
            $tenants = Tenant::all();
            Log::info("Found " . $tenants->count() . " tenants to check for members");
            
            foreach ($tenants as $tenant) {
                $memberCount = $this->getTenantMemberCount($tenant->id);
                $totalMembers += $memberCount;
                Log::info("Tenant {$tenant->id} has {$memberCount} members");
            }
            
            Log::info("Total members across all tenants: {$totalMembers}");
            return $totalMembers;
            
        } catch (\Exception $e) {
            Log::error("Failed to get total members across tenants: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Helper method to get total members at a specific date
     */
    private function getTotalMembersAtDate(Carbon $date): int
    {
        try {
            $totalMembers = 0;
            
            // Get all tenants that existed at that date
            $tenants = Tenant::where('created_at', '<=', $date)->get();
            
            foreach ($tenants as $tenant) {
                $memberCount = $this->getTenantMemberCountAtDate($tenant->id, $date);
                $totalMembers += $memberCount;
            }
            
            return $totalMembers;
            
        } catch (\Exception $e) {
            Log::error("Failed to get total members at date {$date}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Helper method to get member count for a specific tenant at a specific date
     */
    private function getTenantMemberCountAtDate(string $tenantId, Carbon $date): int
    {
        try {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return 0;
            }
            
            // Try different database naming patterns
            $possibleDbNames = [
                $tenant->id . '_smart_housing',
                $tenant->id . '_housing',
                $tenant->id,
                'tenant_' . $tenant->id,
                'smart_housing_' . $tenant->id
            ];
            
            foreach ($possibleDbNames as $dbName) {
                try {
                    // Check if database and users table exist
                    $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
                    if (empty($dbExists)) continue;
                    
                    $tableExists = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'", [$dbName]);
                    if (empty($tableExists)) continue;
                    
                    // Count users created before or on that date
                    $sql = "SELECT COUNT(*) as member_count FROM `{$dbName}`.`users` WHERE created_at <= ?";
                    $result = DB::select($sql, [$date]);
                    return $result[0]->member_count ?? 0;
                    
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            return 0;
            
        } catch (\Exception $e) {
            Log::warning("Failed to count members for tenant {$tenantId} at date {$date}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Helper method to get member count for a specific tenant
     * This method is dynamic and handles different database naming patterns
     */
    private function getTenantMemberCount(string $tenantId): int
    {
        try {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                Log::warning("Tenant not found: {$tenantId}");
                return 0;
            }
            
            // Try different database naming patterns
            $possibleDbNames = [
                $tenant->id . '_smart_housing',
                $tenant->id . '_housing',
                $tenant->id,
                'tenant_' . $tenant->id,
                'smart_housing_' . $tenant->id
            ];
            
            foreach ($possibleDbNames as $dbName) {
                try {
                    Log::info("Trying database: {$dbName}");
                    
                    // Check if database exists
                    $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
                    
                    if (empty($dbExists)) {
                        Log::info("Database {$dbName} does not exist, trying next...");
                        continue;
                    }
                    
                    // Check if users table exists in this database
                    $tableExists = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'", [$dbName]);
                    
                    if (empty($tableExists)) {
                        Log::info("Users table does not exist in database {$dbName}, trying next...");
                        continue;
                    }
                    
                    // Count users in this database
                    $sql = "SELECT COUNT(*) as member_count FROM `{$dbName}`.`users` where `role` = 'member'";
                    $result = DB::select($sql);
                    $memberCount = $result[0]->member_count ?? 0;
                    
                    Log::info("Found {$memberCount} members in database {$dbName}");
                    return $memberCount;
                    
                } catch (\Exception $e) {
                    Log::info("Failed to access database {$dbName}: " . $e->getMessage());
                    continue;
                }
            }
            
            Log::warning("No accessible database found for tenant {$tenantId}");
            return 0;
            
        } catch (\Exception $e) {
            Log::warning("Failed to count members for tenant {$tenantId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check API health
     */
    private function checkApiHealth(): bool
    {
        // Simple health check - in production, you might want more sophisticated checks
        return true;
    }

    /**
     * Get system uptime
     */
    private function getSystemUptime(): string
    {
        // This would need to be implemented based on your system
        return '99.9%';
    }

    /**
     * Get active connections
     */
    private function getActiveConnections(): int
    {
        // This would need to be implemented based on your system
        return 15;
    }

    /**
     * Get last backup time
     */
    private function getLastBackupTime(): string
    {
        // This would need to be implemented based on your backup system
        return Carbon::now()->subHours(6)->format('Y-m-d H:i:s');
    }

    /**
     * Get memory usage percentage
     */
    private function getMemoryUsage(): int
    {
        // This would need to be implemented based on your system
        return 45;
    }

    /**
     * Get disk usage percentage
     */
    private function getDiskUsage(): int
    {
        // This would need to be implemented based on your system
        return 60;
    }
}
