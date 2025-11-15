<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Central\Tenant;
use App\Models\Central\Subscription;
use Symfony\Component\HttpFoundation\Response;

class TenantSubscriptionGuard
{
    /**
     * Handle an incoming request.
     * 
     * Blocks access to admin routes if tenant subscription is not active.
     * Allows access to subscription-related routes even when subscription is inactive.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow subscription routes to be accessible even without active subscription
        $subscriptionRoutes = [
            'api/subscriptions',
            'api/admin/subscriptions',
            'subscriptions',
            'admin/subscriptions',
        ];

        $path = $request->path();
        // Remove 'api/' prefix if present for matching
        $cleanPath = str_replace('api/', '', $path);
        
        foreach ($subscriptionRoutes as $route) {
            $cleanRoute = str_replace('api/', '', $route);
            if (str_starts_with($cleanPath, $cleanRoute) || str_contains($cleanPath, $cleanRoute)) {
                return $next($request);
            }
        }

        // Get current tenant - tenant() helper should be available after TenantMiddleware
        $tenant = tenant();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not identified',
                'subscription_required' => true,
            ], 403);
        }

        // Check tenant subscription status
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired. Please renew your subscription to continue using the platform.',
                'subscription_required' => true,
                'redirect_to' => '/admin/subscriptions',
            ], 403);
        }

        return $next($request);
    }
}
