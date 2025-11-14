<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get comprehensive dashboard overview
     */
    public function overview(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getDashboardOverview();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Dashboard overview retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard overview error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get key metrics for dashboard
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getKeyMetrics();
            
            return response()->json([
                'success' => true,
                'data' => $metrics,
                'message' => 'Key metrics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard metrics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve key metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent businesses
     */
    public function recentBusinesses(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $businesses = $this->dashboardService->getRecentBusinesses($limit);
            
            return response()->json([
                'success' => true,
                'data' => $businesses,
                'message' => 'Recent businesses retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Recent businesses error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue analytics
     */
    public function revenueAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'monthly'); // monthly, quarterly, yearly
            $analytics = $this->dashboardService->getRevenueAnalytics($period);
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Revenue analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Revenue analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve revenue analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription analytics
     */
    public function subscriptionAnalytics(): JsonResponse
    {
        try {
            $analytics = $this->dashboardService->getSubscriptionAnalytics();
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Subscription analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health metrics
     */
    public function systemHealth(): JsonResponse
    {
        try {
            $health = $this->dashboardService->getSystemHealth();
            
            return response()->json([
                'success' => true,
                'data' => $health,
                'message' => 'System health retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('System health error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get alerts and notifications
     */
    public function alerts(): JsonResponse
    {
        try {
            $alerts = $this->dashboardService->getAlerts();
            
            return response()->json([
                'success' => true,
                'data' => $alerts,
                'message' => 'Alerts retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Alerts error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get platform statistics
     */
    public function platformStats(): JsonResponse
    {
        try {
            $stats = $this->dashboardService->getPlatformStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Platform statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Platform stats error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve platform statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test member counting endpoint
     */
    public function testMemberCount(): JsonResponse
    {
        try {
            $totalMembers = $this->dashboardService->getTotalMembersAcrossTenants();
            
            return response()->json([
                'success' => true,
                'message' => 'Member count test completed',
                'data' => [
                    'total_members' => $totalMembers,
                    'timestamp' => now()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Member count test error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to test member count',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}