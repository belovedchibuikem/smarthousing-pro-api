<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Central\MemberSubscription;
use App\Models\Tenant\Member;
use Symfony\Component\HttpFoundation\Response;

class MemberSubscriptionGuard
{
    /**
     * Handle an incoming request.
     * 
     * Blocks access to user/member routes if member subscription is not active.
     * Allows access to subscription-related routes even when subscription is inactive.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow subscription routes to be accessible even without active subscription
        $subscriptionRoutes = [
            'api/subscriptions',
            'api/member-subscriptions',
            'subscriptions',
            'member-subscriptions',
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

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Get member from user
        $member = $user->member;
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member profile not found',
            ], 404);
        }

        // Get tenant ID (business_id)
        $tenantId = $request->header('X-Tenant-ID') 
            ?? session('tenant_id')
            ?? tenant('id');

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not identified',
                'subscription_required' => true,
            ], 403);
        }

        // Check member subscription status
        $subscription = MemberSubscription::where('business_id', $tenantId)
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->where(function($query) {
                $query->where('payment_status', 'approved')
                      ->orWhere('payment_status', 'completed');
            })
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired. Please renew your subscription to continue using the platform.',
                'subscription_required' => true,
                'redirect_to' => '/dashboard/subscriptions',
            ], 403);
        }

        return $next($request);
    }
}
