<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\CustomDomainRequest;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DomainRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CustomDomainRequest::with(['tenant']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                  ->orWhereHas('tenant', function ($tenantQuery) use ($search) {
                      $tenantQuery->whereJsonContains('data->name', $search);
                  });
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Calculate stats
        $stats = [
            'total' => CustomDomainRequest::count(),
            'pending' => CustomDomainRequest::where('status', 'pending')->count(),
            'verified' => CustomDomainRequest::where('status', 'verified')->count(),
            'active' => CustomDomainRequest::where('status', 'active')->count(),
        ];

        return response()->json([
            'requests' => $requests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'tenant_id' => $request->tenant_id,
                    'business_name' => $request->tenant->data['name'] ?? $request->tenant->id,
                    'full_domain' => $request->domain,
                    'status' => $request->status,
                    'verification_token' => $request->verification_token,
                    'dns_records' => $request->dns_records ?? [],
                    'requested_at' => $request->created_at->toISOString(),
                    'admin_notes' => $request->admin_notes,
                ];
            }),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ]
        ]);
    }

    public function show(CustomDomainRequest $domainRequest): JsonResponse
    {
        $domainRequest->load(['tenant']);

        return response()->json([
            'request' => [
                'id' => $domainRequest->id,
                'tenant_id' => $domainRequest->tenant_id,
                'business_name' => $domainRequest->tenant->data['name'] ?? $domainRequest->tenant->id,
                'business_email' => $domainRequest->tenant->data['contact_email'] ?? '',
                'business_address' => $domainRequest->tenant->data['address'] ?? '',
                'full_domain' => $domainRequest->domain,
                'status' => $domainRequest->status,
                'verification_token' => $domainRequest->verification_token,
                'dns_records' => $domainRequest->dns_records ?? [],
                'requested_at' => $domainRequest->created_at->toISOString(),
                'admin_notes' => $domainRequest->admin_notes,
                'verified_at' => $domainRequest->verified_at?->toISOString(),
                'activated_at' => $domainRequest->activated_at?->toISOString(),
            ]
        ]);
    }

    public function review(Request $request, CustomDomainRequest $domainRequest): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $status = $request->action === 'approve' ? 'approved' : 'rejected';
            
            $domainRequest->update([
                'status' => $status,
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);

            // If approved, set up DNS verification
            if ($request->action === 'approve') {
                $this->setupDNSVerification($domainRequest);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Domain request {$request->action}d successfully",
                'request' => $domainRequest->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to review domain request: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verify(CustomDomainRequest $domainRequest): JsonResponse
    {
        try {
            // Simulate DNS verification
            $isVerified = $this->checkDNSRecords($domainRequest);
            
            if ($isVerified) {
                $domainRequest->update([
                    'status' => 'verified',
                    'verified_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Domain verification successful',
                    'verified' => true
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'DNS records not found or incorrect',
                    'verified' => false
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Domain verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function activate(CustomDomainRequest $domainRequest): JsonResponse
    {
        if ($domainRequest->status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Domain must be verified before activation'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Update tenant's custom domain
            $domainRequest->tenant->update([
                'data->custom_domain' => $domainRequest->domain,
            ]);

            $domainRequest->update([
                'status' => 'active',
                'activated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Domain activated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Domain activation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function setupDNSVerification(CustomDomainRequest $domainRequest): void
    {
        $dnsRecords = [
            [
                'type' => 'CNAME',
                'name' => '@',
                'value' => config('app.domain_verification_cname', 'cname.yourplatform.com')
            ],
            [
                'type' => 'TXT',
                'name' => '_platform-verify',
                'value' => $domainRequest->verification_token
            ]
        ];

        $domainRequest->update([
            'dns_records' => $dnsRecords,
            'status' => 'pending_verification'
        ]);
    }

    private function checkDNSRecords(CustomDomainRequest $domainRequest): bool
    {
        // This would typically use a DNS lookup service
        // For now, we'll simulate the verification
        return true; // Simulate successful verification
    }
}
