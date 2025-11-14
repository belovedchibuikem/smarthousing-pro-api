<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Central\Tenant;

class TenantValidationController extends Controller
{
    /**
     * Validate tenant by slug or domain
     * This is a public endpoint that doesn't require tenant middleware
     */
    public function validate(Request $request)
    {
        $origin = $request->header('Origin');
        $corsHeaders = [
            'Access-Control-Allow-Origin' => $origin ?? '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
        ];
        
        $host = $request->get('host') ?? $request->header('x-forwarded-host') ?? $request->getHost();
        $slug = $request->get('slug');
        
        // If slug is provided, validate by slug
        if ($slug) {
            $tenantDetail = DB::connection('mysql')
                ->table('tenant_details')
                ->where('slug', $slug)
                ->where('status', 'active')
                ->first();
            
            if (!$tenantDetail) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Tenant not found or inactive'
                ], 404)->withHeaders($corsHeaders);
            }
            
            $tenant = Tenant::find($tenantDetail->tenant_id);
            
            return response()->json([
                'valid' => true,
                'tenant' => [
                    'id' => $tenant->id,
                    'slug' => $tenantDetail->slug,
                    'name' => $tenantDetail->name,
                    'custom_domain' => $tenantDetail->custom_domain,
                    'status' => $tenantDetail->status,
                ]
            ])->withHeaders($corsHeaders);
        }
        
        // Validate by hostname (domain or subdomain)
        $platformDomain = env('PLATFORM_DOMAIN', config('app.domain', 'frschousing.com'));
        
        // Check if it's a custom domain
        $domain = DB::connection('mysql')
            ->table('domains')
            ->where('domain', $host)
            ->first();
        
        if ($domain) {
            $tenant = Tenant::find($domain->tenant_id);
            $tenantDetail = DB::connection('mysql')
                ->table('tenant_details')
                ->where('tenant_id', $tenant->id)
                ->first();
            
            if (!$tenantDetail || $tenantDetail->status !== 'active') {
                return response()->json([
                    'valid' => false,
                    'message' => 'Tenant is inactive'
                ], 404)->withHeaders($corsHeaders);
            }
            
            return response()->json([
                'valid' => true,
                'tenant' => [
                    'id' => $tenant->id,
                    'slug' => $tenantDetail->slug,
                    'name' => $tenantDetail->name,
                    'custom_domain' => $tenantDetail->custom_domain,
                    'status' => $tenantDetail->status,
                ]
            ])->withHeaders($corsHeaders);
        }
        
        // Check if it's a subdomain
        if (substr($host, -strlen('.' . $platformDomain)) === '.' . $platformDomain) {
            $subdomain = str_replace('.' . $platformDomain, '', $host);
            
            $tenantDetail = DB::connection('mysql')
                ->table('tenant_details')
                ->where('slug', $subdomain)
                ->where('status', 'active')
                ->first();
            
            if (!$tenantDetail) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Tenant not found or inactive'
                ], 404)->withHeaders($corsHeaders);
            }
            
            $tenant = Tenant::find($tenantDetail->tenant_id);
            
            return response()->json([
                'valid' => true,
                'tenant' => [
                    'id' => $tenant->id,
                    'slug' => $tenantDetail->slug,
                    'name' => $tenantDetail->name,
                    'custom_domain' => $tenantDetail->custom_domain,
                    'status' => $tenantDetail->status,
                ]
            ])->withHeaders($corsHeaders);
        }
        
        return response()->json([
            'valid' => false,
            'message' => 'Invalid hostname'
        ], 404)->withHeaders($corsHeaders);
    }
}

