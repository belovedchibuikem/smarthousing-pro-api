<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\PlatformTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
                    $q->whereRaw("JSON_EXTRACT(data, '$.name') LIKE ?", ["%{$search}%"])
                      ->orWhereRaw("JSON_EXTRACT(data, '$.contact_email') LIKE ?", ["%{$search}%"]);
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $subscriptions = $query->paginate($request->get('per_page', 15));

            $subscriptions->getCollection()->transform(function ($subscription) {
                $tenantData = $subscription->tenant->data ?? [];
                // Try multiple ways to get business name
                $businessName = $tenantData['name'] ?? null;
                if (!$businessName && isset($tenantData['business_name'])) {
                    $businessName = $tenantData['business_name'];
                }
                if (!$businessName && $subscription->tenant) {
                    // Fallback to tenant ID if name not found
                    $businessName = 'Business ' . substr($subscription->tenant_id, 0, 8);
                }
                if (!$businessName) {
                    $businessName = 'Unknown Business';
                }
                
                return [
                    'id' => $subscription->id,
                    'business_name' => $businessName,
                    'business_id' => $subscription->tenant_id,
                    'package' => $subscription->package->name ?? 'No Package',
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->starts_at?->toIso8601String() ?? $subscription->current_period_start?->toIso8601String(),
                    'current_period_end' => $subscription->ends_at?->toIso8601String() ?? $subscription->current_period_end?->toIso8601String(),
                    'amount' => (float) $subscription->amount,
                    'payment_method' => $subscription->payment_method ?? null,
                    'next_billing_date' => $subscription->next_billing_date?->toIso8601String(),
                    'created_at' => $subscription->created_at?->toIso8601String(),
                    'updated_at' => $subscription->updated_at?->toIso8601String(),
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
            
            // Try multiple ways to get business name
            $businessName = $tenantData['name'] ?? null;
            if (!$businessName && isset($tenantData['business_name'])) {
                $businessName = $tenantData['business_name'];
            }
            if (!$businessName && $subscription->tenant) {
                // Fallback to tenant ID if name not found
                $businessName = 'Business ' . substr($subscription->tenant_id, 0, 8);
            }
            if (!$businessName) {
                $businessName = 'Unknown Business';
            }

            // Get payment information if available from PlatformTransaction
            $payment = null;
            $paymentData = null;
            if ($subscription->payment_reference) {
                // Try to find payment in platform_transactions
                $payment = PlatformTransaction::where('reference', $subscription->payment_reference)
                    ->orWhere('reference', 'like', '%' . $subscription->payment_reference . '%')
                    ->where('tenant_id', $subscription->tenant_id)
                    ->where('type', 'subscription')
                    ->first();
                
                if ($payment) {
                    $paymentData = [
                        'id' => $payment->id,
                        'reference' => $payment->reference,
                        'amount' => (float) $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'payment_gateway' => $payment->payment_gateway,
                        'approval_status' => $payment->approval_status ?? null,
                        'approved_by' => $payment->approved_by ?? null,
                        'approved_at' => $payment->approved_at?->toIso8601String(),
                        'rejection_reason' => $payment->rejection_reason ?? null,
                        'metadata' => $payment->metadata ?? [],
                        'created_at' => $payment->created_at?->toIso8601String(),
                    ];
                }
            }

            $data = [
                'id' => $subscription->id,
                'business_name' => $businessName,
                'business_id' => $subscription->tenant_id,
                'package' => $subscription->package->name ?? 'No Package',
                'package_id' => $subscription->package_id,
                'status' => $subscription->status,
                'current_period_start' => $subscription->starts_at?->toIso8601String() ?? $subscription->current_period_start?->toIso8601String(),
                'current_period_end' => $subscription->ends_at?->toIso8601String() ?? $subscription->current_period_end?->toIso8601String(),
                'amount' => (float) $subscription->amount,
                'next_billing_date' => $subscription->next_billing_date?->toIso8601String(),
                'payment_reference' => $subscription->payment_reference,
                'payment_method' => $subscription->payment_method ?? $subscription->metadata['payment_method'] ?? ($paymentData['payment_gateway'] ?? null),
                'payment_status' => $subscription->metadata['payment_status'] ?? ($paymentData['status'] ?? null),
                'payment' => $paymentData,
                'created_at' => $subscription->created_at?->toIso8601String(),
                'updated_at' => $subscription->updated_at?->toIso8601String(),
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

    /**
     * Approve manual payment for subscription
     */
    public function approvePayment(Request $request, string $subscriptionId): JsonResponse
    {
        try {
            
            $request->validate([
                'approval_notes' => 'nullable|string|max:1000',
            ]);

            $subscription = Subscription::findOrFail($subscriptionId);
            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found John Chibuikem'
                ], 404);
            }

            DB::beginTransaction();

            // Find the payment transaction
            $payment = PlatformTransaction::where('reference', $subscription->payment_reference)
                ->orWhere('reference', 'like', '%' . $subscription->payment_reference . '%')
                ->where('tenant_id', $subscription->tenant_id)
                ->where('type', 'subscription')
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment transaction not found'
                ], 404);
            }

            if ($payment->approval_status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment has already been approved'
                ], 400);
            }

            // Update payment status
            $payment->update([
                'approval_status' => 'approved',
                'approved_by' => $request->user()->id ?? null,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            // Activate subscription and update all relevant fields
            $package = $subscription->package;
            $billingCycle = $package->billing_cycle ?? 'monthly';
            $durationDays = match($billingCycle) {
                'weekly' => 7,
                'monthly' => 30,
                'quarterly' => 90,
                'yearly' => 365,
                default => 30
            };
            
            $startsAt = now();
            $endsAt = $startsAt->copy()->addDays($durationDays);
            $nextBillingDate = $endsAt->copy();
            
            $subscription->update([
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'current_period_start' => $startsAt,
                'current_period_end' => $endsAt,
                'next_billing_date' => $nextBillingDate,
            ]);

            // Update tenant subscription status
            $tenant = $subscription->tenant;
            if ($tenant) {
                $tenantData = $tenant->data ?? [];
                $tenantData['subscription_status'] = 'active';
                $tenantData['subscription_ends_at'] = $subscription->ends_at?->toDateString();
                $tenant->update(['data' => $tenantData]);
            }

            DB::commit();

            Log::info('Subscription payment approved', [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'approved_by' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment approved and subscription activated successfully',
                'subscription' => $subscription->fresh(['tenant', 'package'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription payment approval failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject manual payment for subscription
     */
    public function rejectPayment(Request $request, Subscription $subscription): JsonResponse
    {
        try {
            $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            DB::beginTransaction();

            // Find the payment transaction
            $payment = PlatformTransaction::where('reference', $subscription->payment_reference)
                ->orWhere('reference', 'like', '%' . $subscription->payment_reference . '%')
                ->where('tenant_id', $subscription->tenant_id)
                ->where('type', 'subscription')
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment transaction not found'
                ], 404);
            }

            if ($payment->approval_status === 'rejected') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment has already been rejected'
                ], 400);
            }

            // Update payment status
            $payment->update([
                'approval_status' => 'rejected',
                'approved_by' => $request->user()->id ?? null,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
                'status' => 'failed',
            ]);

            // Update subscription status to past_due if it was pending
            if ($subscription->status === 'trial') {
                $subscription->update([
                    'status' => 'past_due',
                ]);
            }

            DB::commit();

            Log::info('Subscription payment rejected', [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'rejected_by' => $request->user()->id ?? null,
                'reason' => $request->rejection_reason,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully',
                'subscription' => $subscription->fresh(['tenant', 'package'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription payment rejection failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
