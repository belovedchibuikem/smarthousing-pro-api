<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\MemberSubscription;
use App\Models\Central\MemberSubscriptionPackage;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class MemberSubscriptionController extends Controller
{
    /**
     * Get all member subscription packages or subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // If type=list, return actual subscriptions instead of packages
            if ($request->get('type') === 'list') {
                return $this->listSubscriptions($request);
            }

            // Otherwise, return packages (default behavior)
            $query = MemberSubscriptionPackage::query();

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            }

            // Filter by status
            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            $packages = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'packages' => $packages->items(),
                'pagination' => [
                    'current_page' => $packages->currentPage(),
                    'last_page' => $packages->lastPage(),
                    'per_page' => $packages->perPage(),
                    'total' => $packages->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription packages index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member subscription packages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all member subscriptions
     */
    private function listSubscriptions(Request $request): JsonResponse
    {
        try {
            $query = MemberSubscription::with(['package', 'business']);

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('package', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhere('payment_reference', 'like', "%{$search}%");
            }

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by payment status
            if ($request->has('payment_status') && $request->payment_status !== 'all') {
                $query->where('payment_status', $request->payment_status);
            }

            $subscriptions = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'subscriptions' => $subscriptions->map(function ($subscription) {
                    $memberName = 'Unknown Member';
                    $memberNumber = null;
                    
                    // Fetch member name from tenant database
                    try {
                        $tenant = Tenant::find($subscription->business_id);
                        if ($tenant) {
                            $databaseName = $tenant->id . '_smart_housing';
                            
                            // Check if database exists
                            $databaseExists = DB::connection('mysql')
                                ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
                            
                            if (!empty($databaseExists)) {
                                // Create temporary connection to tenant database
                                Config::set('database.connections.tenant_temp', [
                                    'driver' => 'mysql',
                                    'host' => env('DB_HOST', '127.0.0.1'),
                                    'port' => env('DB_PORT', '3306'),
                                    'database' => $databaseName,
                                    'username' => env('DB_USERNAME', 'root'),
                                    'password' => env('DB_PASSWORD', ''),
                                    'charset' => 'utf8mb4',
                                    'collation' => 'utf8mb4_unicode_ci',
                                    'prefix' => '',
                                    'strict' => true,
                                    'engine' => null,
                                ]);
                                
                                DB::purge('tenant_temp');
                                DB::connection('tenant_temp')->reconnect();
                                
                                // Fetch member with user relationship
                                $member = DB::connection('tenant_temp')
                                    ->table('members')
                                    ->join('users', 'members.user_id', '=', 'users.id')
                                    ->where('members.id', $subscription->member_id)
                                    ->select(
                                        'members.member_number',
                                        'users.first_name',
                                        'users.last_name',
                                        'users.email'
                                    )
                                    ->first();
                                
                                if ($member) {
                                    $memberName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
                                    if (empty($memberName)) {
                                        $memberName = $member->email ?? 'Unknown Member';
                                    }
                                    $memberNumber = $member->member_number;
                                }
                                
                                // Disconnect temporary connection
                                Config::forget('database.connections.tenant_temp');
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to fetch member name for subscription', [
                            'subscription_id' => $subscription->id,
                            'member_id' => $subscription->member_id,
                            'business_id' => $subscription->business_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    return [
                        'id' => $subscription->id,
                        'business_id' => $subscription->business_id,
                        'business_name' => $subscription->business->name ?? null,
                        'member_id' => $subscription->member_id,
                        'member_name' => $memberName,
                        'member_number' => $memberNumber,
                        'package_id' => $subscription->package_id,
                        'package_name' => $subscription->package->name ?? 'Unknown',
                        'status' => $subscription->status,
                        'payment_status' => $subscription->payment_status,
                        'payment_method' => $subscription->payment_method,
                        'start_date' => $subscription->start_date->format('Y-m-d'),
                        'end_date' => $subscription->end_date->format('Y-m-d'),
                        'amount_paid' => (float) $subscription->amount_paid,
                        'payment_reference' => $subscription->payment_reference,
                        'created_at' => $subscription->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $subscription->updated_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscriptions list error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new member subscription package
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:member_subscription_packages,slug',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'billing_cycle' => 'required|string|in:weekly,monthly,quarterly,yearly',
                'duration_days' => 'required|integer|min:1',
                'trial_days' => 'integer|min:0',
                'is_active' => 'boolean',
                'is_featured' => 'boolean',
                'features' => 'nullable|array',
                'benefits' => 'nullable|array',
                'sort_order' => 'integer',
            ]);

            // Calculate duration_days from billing_cycle if not provided
            $durationDays = $request->duration_days ?? match($request->billing_cycle) {
                'weekly' => 7,
                'monthly' => 30,
                'quarterly' => 90,
                'yearly' => 365,
                default => 30
            };

            $package = MemberSubscriptionPackage::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'price' => $request->price,
                'billing_cycle' => $request->billing_cycle,
                'duration_days' => $durationDays,
                'trial_days' => $request->get('trial_days', 0),
                'is_active' => $request->get('is_active', true),
                'is_featured' => $request->get('is_featured', false),
                'features' => $request->features ?? [],
                'benefits' => $request->benefits ?? [],
                'sort_order' => $request->get('sort_order', 0),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member subscription package created successfully',
                'package' => $package
            ], 201);
        } catch (\Exception $e) {
            Log::error('Member subscription package store error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create member subscription package',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific member subscription (not package)
     * This shows an actual subscription record, not a package
     */
    public function show(string $subscription): JsonResponse
    {
        try {
            // Try to find as MemberSubscription first (actual subscription)
            $memberSubscription = MemberSubscription::on('mysql')
                ->with(['package', 'business'])
                ->find($subscription);
            
            if ($memberSubscription) {
                return response()->json([
                    'success' => true,
                    'subscription' => [
                        'id' => $memberSubscription->id,
                        'package_id' => $memberSubscription->package_id,
                        'package_name' => $memberSubscription->package->name ?? 'Unknown',
                        'business_id' => $memberSubscription->business_id,
                        'business_name' => $memberSubscription->business->name ?? 'Unknown',
                        'member_id' => $memberSubscription->member_id,
                        'status' => $memberSubscription->status,
                        'payment_status' => $memberSubscription->payment_status ?? 'completed',
                        'payment_method' => $memberSubscription->payment_method,
                        'payment_reference' => $memberSubscription->payment_reference,
                        'amount_paid' => (float) $memberSubscription->amount_paid,
                        'start_date' => $memberSubscription->start_date->format('Y-m-d'),
                        'end_date' => $memberSubscription->end_date->format('Y-m-d'),
                        'notes' => $memberSubscription->notes,
                        'rejection_reason' => $memberSubscription->rejection_reason,
                        'payer_name' => $memberSubscription->payer_name,
                        'payer_phone' => $memberSubscription->payer_phone,
                        'account_details' => $memberSubscription->account_details,
                        'payment_evidence' => $memberSubscription->payment_evidence ?? [],
                        'created_at' => $memberSubscription->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $memberSubscription->updated_at->format('Y-m-d H:i:s'),
                    ]
                ]);
            }
            
            // If not found as subscription, try as package (for backward compatibility)
            $package = MemberSubscriptionPackage::on('mysql')->find($subscription);
            if ($package) {
                return response()->json([
                    'success' => true,
                    'package' => $package
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Member subscription not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Member subscription show error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve member subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update member subscription package
     */
    public function update(Request $request, MemberSubscriptionPackage $subscription): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|required|string|max:255|unique:member_subscription_packages,slug,' . $subscription->id,
                'description' => 'sometimes|nullable|string',
                'price' => 'sometimes|required|numeric|min:0',
                'billing_cycle' => 'sometimes|required|string|in:weekly,monthly,quarterly,yearly',
                'duration_days' => 'sometimes|required|integer|min:1',
                'trial_days' => 'sometimes|integer|min:0',
                'is_active' => 'sometimes|boolean',
                'is_featured' => 'sometimes|boolean',
                'features' => 'sometimes|nullable|array',
                'benefits' => 'sometimes|nullable|array',
                'sort_order' => 'sometimes|integer',
            ]);

            $updateData = $request->only([
                'name', 'slug', 'description', 'price', 'billing_cycle', 
                'duration_days', 'trial_days', 'is_active', 'is_featured', 
                'features', 'benefits', 'sort_order'
            ]);

            $subscription->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Member subscription package updated successfully',
                'package' => $subscription->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription package update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update member subscription package',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel member subscription package (deactivate)
     */
    public function cancel(MemberSubscriptionPackage $subscription): JsonResponse
    {
        try {
            $subscription->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Member subscription package deactivated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription package cancel error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate member subscription package',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extend member subscription package (reactivate)
     */
    public function extend(MemberSubscriptionPackage $subscription): JsonResponse
    {
        try {
            $subscription->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Member subscription package activated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription package extend error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate member subscription package',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete member subscription package
     */
    public function destroy(MemberSubscriptionPackage $subscription): JsonResponse
    {
        try {
            $subscription->delete();

            return response()->json([
                'success' => true,
                'message' => 'Member subscription package deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Member subscription package destroy error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete member subscription package',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}