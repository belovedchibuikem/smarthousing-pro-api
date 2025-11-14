<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BusinessSubscriptionController extends Controller
{
    /**
     * Get all business subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Subscription::with(['tenant', 'package']);

            // Search in tenant data
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('tenant', function($q) use ($search) {
                    $q->whereJsonContains('data->name', $search)
                      ->orWhereJsonContains('data->contact_email', $search);
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $subscriptions = $query->paginate($request->get('per_page', 15));

            $subscriptions->getCollection()->transform(function ($subscription) {
                $tenantData = $subscription->tenant->data ?? [];
                return [
                    'id' => $subscription->id,
                    'business_name' => $tenantData['name'] ?? 'Unknown Business',
                    'business_id' => $subscription->tenant_id,
                    'package' => $subscription->package->name ?? 'No Package',
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'amount' => $subscription->amount,
                    'next_billing_date' => $subscription->next_billing_date,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at,
                ];
            });

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
            Log::error('Business subscriptions index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve business subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific business subscription
     */
    public function show(Subscription $subscription): JsonResponse
    {
        try {
            $subscription->load(['tenant', 'package']);
            $tenantData = $subscription->tenant->data ?? [];

            $data = [
                'id' => $subscription->id,
                'business_name' => $tenantData['name'] ?? 'Unknown Business',
                'business_id' => $subscription->tenant_id,
                'package' => $subscription->package->name ?? 'No Package',
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'amount' => $subscription->amount,
                'next_billing_date' => $subscription->next_billing_date,
                'created_at' => $subscription->created_at,
                'updated_at' => $subscription->updated_at,
            ];

            return response()->json([
                'success' => true,
                'subscription' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Business subscription show error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve business subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel business subscription
     */
    public function cancel(Subscription $subscription): JsonResponse
    {
        try {
            $subscription->update(['status' => 'cancelled']);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Business subscription cancel error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivate business subscription
     */
    public function reactivate(Subscription $subscription): JsonResponse
    {
        try {
            $subscription->update(['status' => 'active']);

            return response()->json([
                'success' => true,
                'message' => 'Subscription reactivated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Business subscription reactivate error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extend business subscription
     */
    public function extend(Request $request, Subscription $subscription): JsonResponse
    {
        try {
            $request->validate([
                'days' => 'required|integer|min:1'
            ]);

            $days = $request->days;
            $newEndDate = $subscription->current_period_end->addDays($days);
            
            $subscription->update([
                'current_period_end' => $newEndDate,
                'next_billing_date' => $newEndDate
            ]);

            return response()->json([
                'success' => true,
                'message' => "Subscription extended by {$days} days",
                'new_end_date' => $newEndDate->toDateString()
            ]);
        } catch (\Exception $e) {
            Log::error('Business subscription extend error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
