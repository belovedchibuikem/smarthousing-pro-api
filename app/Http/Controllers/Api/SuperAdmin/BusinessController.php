<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\BusinessRequest;
use App\Http\Resources\SuperAdmin\BusinessResource;
use App\Models\Central\Tenant;
use App\Services\Communication\SuperAdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function __construct(
        protected SuperAdminNotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Tenant::with(['subscription.package']);

        // Search in data field
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereJsonContains('data->name', $search)
                  ->orWhereJsonContains('data->contact_email', $search)
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Filter by status in data field
        if ($request->has('status')) {
            $query->whereJsonContains('data->status', $request->status);
        }

        // Filter by subscription status in data field
        if ($request->has('subscription_status')) {
            $query->whereJsonContains('data->subscription_status', $request->subscription_status);
        }

        $businesses = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'businesses' => BusinessResource::collection($businesses),
            'pagination' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
            ]
        ]);
    }

    public function store(BusinessRequest $request): JsonResponse
    {
        $tenant = Tenant::create([
            'data' => [
                'name' => $request->name,
                'slug' => $request->slug ?? Str::slug($request->name),
                'contact_email' => $request->contact_email,
                'contact_phone' => $request->contact_phone,
                'address' => $request->address,
                'primary_color' => $request->primary_color ?? '#000000',
                'secondary_color' => $request->secondary_color ?? '#000000',
                'logo_url' => $request->logo_url,
                'custom_domain' => $request->custom_domain,
                'status' => 'active',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(14)->toDateString(),
                'settings' => $request->settings ?? [],
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business created successfully',
            'business' => new BusinessResource($tenant)
        ], 201);
    }

    public function show(Tenant $business): JsonResponse
    {
        $business->load(['subscription.package', 'customDomainRequests', 'transactions']);
        
        return response()->json([
            'business' => new BusinessResource($business)
        ]);
    }

    public function update(BusinessRequest $request, Tenant $business): JsonResponse
    {
        $data = $business->data ?? [];
        
        $data = array_merge($data, [
            'name' => $request->name,
            'slug' => $request->slug ?? $business->id,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'address' => $request->address,
            'primary_color' => $request->primary_color,
            'secondary_color' => $request->secondary_color,
            'logo_url' => $request->logo_url,
            'custom_domain' => $request->custom_domain,
            'settings' => $request->settings ?? $data['settings'] ?? [],
        ]);

        $business->update(['data' => $data]);

        return response()->json([
            'success' => true,
            'message' => 'Business updated successfully',
            'business' => new BusinessResource($business)
        ]);
    }

    public function suspend(Tenant $business): JsonResponse
    {
        $data = $business->data ?? [];
        $data['status'] = 'suspended';
        $business->update(['data' => $data]);

        // Notify super admins about tenant suspension
        $tenantName = $data['name'] ?? $business->id;
        $this->notificationService->notifyTenantSuspended(
            $business->id,
            $tenantName
        );

        return response()->json([
            'success' => true,
            'message' => 'Business suspended successfully'
        ]);
    }

    public function activate(Tenant $business): JsonResponse
    {
        $data = $business->data ?? [];
        $data['status'] = 'active';
        $business->update(['data' => $data]);

        // Notify super admins about tenant activation
        $tenantName = $data['name'] ?? $business->id;
        $this->notificationService->notifyTenantActivated(
            $business->id,
            $tenantName
        );

        return response()->json([
            'success' => true,
            'message' => 'Business activated successfully'
        ]);
    }

    public function destroy(Tenant $business): JsonResponse
    {
        $data = $business->data ?? [];
        $data['status'] = 'cancelled';
        $business->update(['data' => $data]);

        // Notify super admins about tenant cancellation
        $tenantName = $data['name'] ?? $business->id;
        $this->notificationService->notifyTenantCancelled(
            $business->id,
            $tenantName
        );

        return response()->json([
            'success' => true,
            'message' => 'Business cancelled successfully'
        ]);
    }

    /**
     * Get business domains
     */
    public function domains(Tenant $business): JsonResponse
    {
        $domains = $business->customDomainRequests()->get();

        return response()->json([
            'domains' => $domains->map(function ($domain) {
                return [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'status' => $domain->status,
                    'is_primary' => $domain->is_primary,
                    'created_at' => $domain->created_at,
                    'verification_record' => $domain->verification_record,
                ];
            })
        ]);
    }

    /**
     * Add domain to business
     */
    public function addDomain(Request $request, Tenant $business): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255|unique:custom_domain_requests,domain'
        ]);

        $domain = $business->customDomainRequests()->create([
            'domain' => $request->domain,
            'status' => 'pending',
            'is_primary' => !$business->customDomainRequests()->exists(),
            'verification_record' => 'cname.yourplatform.com'
        ]);

        // Notify super admins about custom domain request
        $data = $business->data ?? [];
        $tenantName = $data['name'] ?? $business->id;
        $this->notificationService->notifyCustomDomainRequest(
            $business->id,
            $tenantName,
            $request->domain
        );

        return response()->json([
            'success' => true,
            'message' => 'Domain added successfully',
            'domain' => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'status' => $domain->status,
                'is_primary' => $domain->is_primary,
                'created_at' => $domain->created_at,
                'verification_record' => $domain->verification_record,
            ]
        ], 201);
    }

    /**
     * Verify domain
     */
    public function verifyDomain(Tenant $business, $domainId): JsonResponse
    {
        $domain = $business->customDomainRequests()->findOrFail($domainId);
        
        // Simulate domain verification
        $domain->update(['status' => 'verified']);

        return response()->json([
            'success' => true,
            'message' => 'Domain verified successfully'
        ]);
    }

    /**
     * Delete domain
     */
    public function deleteDomain(Tenant $business, $domainId): JsonResponse
    {
        $domain = $business->customDomainRequests()->findOrFail($domainId);
        
        if ($domain->is_primary) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete primary domain'
            ], 400);
        }

        $domain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Domain deleted successfully'
        ]);
    }
}
