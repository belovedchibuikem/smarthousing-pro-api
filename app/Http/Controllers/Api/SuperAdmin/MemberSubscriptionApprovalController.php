<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\MemberSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MemberSubscriptionApprovalController extends Controller
{
    /**
     * Get pending manual payment subscriptions
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $subscriptions = MemberSubscription::where('payment_method', 'manual')
                ->where('payment_status', 'pending')
                ->with(['package', 'business'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'subscriptions' => $subscriptions->items(),
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Pending member subscriptions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve manual payment subscription
     */
    public function approve(Request $request, MemberSubscription $subscription): JsonResponse
    {
        try {
            if ($subscription->payment_method !== 'manual') {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription is not a manual payment'
                ], 400);
            }

            if ($subscription->payment_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription is not pending approval'
                ], 400);
            }

            $admin = $request->user();
            
            $subscription->update([
                'payment_status' => 'approved',
                'status' => 'active',
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription approved successfully',
                'subscription' => $subscription->fresh(['package', 'business'])
            ]);
        } catch (\Exception $e) {
            Log::error('Approve member subscription error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject manual payment subscription
     */
    public function reject(Request $request, MemberSubscription $subscription): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        try {
            if ($subscription->payment_method !== 'manual') {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription is not a manual payment'
                ], 400);
            }

            if ($subscription->payment_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription is not pending approval'
                ], 400);
            }

            $admin = $request->user();
            
            $subscription->update([
                'payment_status' => 'rejected',
                'status' => 'cancelled',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
                'cancelled_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription rejected successfully',
                'subscription' => $subscription->fresh(['package', 'business'])
            ]);
        } catch (\Exception $e) {
            Log::error('Reject member subscription error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

