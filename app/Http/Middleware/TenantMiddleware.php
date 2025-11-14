<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            Log::debug('TenantMiddleware: handling request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'initial_host' => $request->header('X-Forwarded-Host') ?? $request->getHost(),
            ]);
            // Check X-Forwarded-Host header first (sent by frontend for tenant context)
            // Then check Origin header (for public routes like white-label)
            // Finally fall back to Host header
            $host = $request->header('X-Forwarded-Host');
            
            // If X-Forwarded-Host is not set, try to extract from Origin header
            if (!$host) {
                $origin = $request->header('Origin');
                if ($origin) {
                    // Extract host from Origin: http://frsc.localhost:3000 -> frsc.localhost:3000
                    $parsedOrigin = parse_url($origin);
                    if ($parsedOrigin && isset($parsedOrigin['host'])) {
                        $host = $parsedOrigin['host'];
                        if (isset($parsedOrigin['port'])) {
                            $host .= ':' . $parsedOrigin['port'];
                        }
                    }
                }
            }
            
            // If still no host, use request host
            if (!$host) {
                $host = $request->getHost();
                if ($request->getPort()) {
                    $host .= ':' . $request->getPort();
                }
            }
            
            Log::debug('TenantMiddleware: resolved host', [
                'host' => $host,
                'origin' => $request->header('Origin'),
            ]);
            
            // Remove port if present for domain lookup
            $hostWithoutPort = preg_replace('/:\d+$/', '', $host);
            
            // Try to find domain - first without port, then with port
            $domain = DB::connection('mysql')->table('domains')->where('domain', $hostWithoutPort)->first();
            if ($domain) {
                Log::debug('TenantMiddleware: domain matched without port', [
                    'host_without_port' => $hostWithoutPort,
                    'tenant_id' => $domain->tenant_id,
                ]);
            }
            
            if (!$domain) {
                // Try with port if host without port didn't match
                if ($hostWithoutPort !== $host) {
                    $domain = DB::connection('mysql')->table('domains')->where('domain', $host)->first();
                    if ($domain) {
                        Log::debug('TenantMiddleware: domain matched with port', [
                            'host' => $host,
                            'tenant_id' => $domain->tenant_id,
                        ]);
                    }
                }
            }
            
            // If still not found, try to match by subdomain pattern (for localhost subdomains like frsc.localhost)
            if (!$domain && (str_contains($hostWithoutPort, 'localhost') || str_contains($hostWithoutPort, '127.0.0.1'))) {
                // For frsc.localhost, try to find domain that matches frsc.localhost or starts with frsc.
                $parts = explode('.', $hostWithoutPort);
                if (count($parts) >= 2) {
                    $subdomain = $parts[0]; // e.g., "frsc" from "frsc.localhost"
                    
                    // First, try to find tenant by slug in tenant_details
                    $tenantDetail = DB::connection('mysql')->table('tenant_details')
                        ->where('slug', $subdomain)
                        ->where('status', 'active')
                        ->first();
                    
                    if ($tenantDetail) {
                        // Found tenant by slug, now create a virtual domain lookup
                        $tenant = \App\Models\Central\Tenant::find($tenantDetail->tenant_id);
                        if ($tenant) {
                            Log::debug('TenantMiddleware: tenant found via slug lookup', [
                                'slug' => $tenantDetail->slug,
                                'tenant_id' => $tenant->id,
                            ]);
                            // Set domain manually for this request
                            $domain = (object) [
                                'id' => 0,
                                'domain' => $hostWithoutPort,
                                'tenant_id' => $tenant->id,
                            ];
                        }
                    }
                    
                    // If still not found, try exact match in domains table
                    if (!$domain) {
                        $domain = DB::connection('mysql')->table('domains')
                            ->where('domain', $hostWithoutPort)
                            ->first();
                    }
                    
                    // If still not found, try to match by subdomain pattern
                    if (!$domain) {
                        $domain = DB::connection('mysql')->table('domains')
                            ->where('domain', 'like', $subdomain . '.%')
                            ->orWhere('domain', $subdomain)
                            ->first();
                    }
                }
            }
            
            // Development mode: Allow tenant to be specified via header or query parameter
            // Check both environment and if accessing via localhost/127.0.0.1
            $isDevelopment = app()->environment(['local', 'development', 'testing']) 
                || str_contains($hostWithoutPort, 'localhost') 
                || str_contains($hostWithoutPort, '127.0.0.1');
            
           
                
            if (!$domain && $isDevelopment) {
                // Check for tenant in X-Tenant-Slug header
                $tenantSlug = $request->header('X-Tenant-Slug');
                
                // If not in header, check query parameter
                if (!$tenantSlug) {
                    $tenantSlug = $request->query('tenant');
                }

                // Fallback to cookie (set by frontend) when available
                if (!$tenantSlug) {
                    $tenantSlug = $request->cookie('tenant_slug');
                }
                
              
                
                // If tenant slug is provided, try to find tenant
                if ($tenantSlug) {
                    Log::debug('TenantMiddleware: tenant slug provided', [
                        'tenant_slug' => $tenantSlug,
                        'source' => $request->header('X-Tenant-Slug') ? 'header' : ($request->query('tenant') ? 'query' : 'cookie'),
                    ]);
                    $tenantDetail = DB::connection('mysql')->table('tenant_details')
                        ->where('slug', $tenantSlug)
                        ->where('status', 'active')
                        ->first();
                    
                    if (!$tenantDetail) {
                        Log::warning('TenantMiddleware: tenant slug not found or inactive', [
                            'tenant_slug' => $tenantSlug,
                        ]);
                    }
                    
                    if ($tenantDetail) {
                        $tenant = \App\Models\Central\Tenant::find($tenantDetail->tenant_id);
                        if ($tenant) {
                            Log::debug('TenantMiddleware: tenant resolved from slug', [
                                'tenant_slug' => $tenantSlug,
                                'tenant_id' => $tenant->id,
                            ]);
                            $domain = (object) [
                                'id' => 0,
                                'domain' => $hostWithoutPort,
                                'tenant_id' => $tenant->id,
                            ];
                        }
                    }
                }
                
                // If still not found and no tenant specified, try to get first active tenant (for quick dev testing)
                if (!$domain && !$tenantSlug) {
                    $firstTenantDetail = DB::connection('mysql')->table('tenant_details')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->first();
                    
                   
                    
                    if ($firstTenantDetail) {
                        $domain = (object) [
                            'id' => 0,
                            'domain' => $hostWithoutPort,
                            'tenant_id' => $firstTenantDetail->tenant_id,
                        ];
                       
                    }
                }
            }

            
            
            if (!$domain) {
                $origin = $request->header('Origin');
                $tenantSlug = $request->header('X-Tenant-Slug') ?: $request->query('tenant') ?: $request->cookie('tenant_slug');
                
                // Check if there are any tenants available (for debugging)
                $activeTenantCount = DB::connection('mysql')->table('tenant_details')
                    ->where('status', 'active')
                    ->count();
                
                $totalTenantCount = DB::connection('mysql')->table('tenant_details')
                    ->count();
                
                // Get list of available tenant slugs for better error message
                $availableTenants = DB::connection('mysql')->table('tenant_details')
                    ->select('slug', 'status', 'name')
                    ->orderBy('created_at', 'asc')
                    ->limit(10)
                    ->get();
                
               
                
                $hint = 'Tenant must be identified via subdomain or custom domain';
                if ($isDevelopment) {
                    if ($totalTenantCount === 0) {
                        $hint = 'No tenants found in database. Please create a tenant first.';
                    } elseif ($activeTenantCount === 0) {
                        $hint = 'No active tenants found. Available tenants: ' . $availableTenants->pluck('slug')->join(', ') . '. Try adding ?tenant=YOUR_TENANT_SLUG to the URL or use X-Tenant-Slug header';
                    } else {
                        $hint = 'Try adding ?tenant=YOUR_TENANT_SLUG to the URL or use X-Tenant-Slug header. Available tenants: ' . $availableTenants->pluck('slug')->join(', ');
                    }
                }
                
                Log::warning('TenantMiddleware: tenant not found', [
                    'host' => $host,
                    'tenant_slug' => $tenantSlug,
                    'available_tenants' => $availableTenants->pluck('slug')->join(', '),
                ]);
                
                return response()->json([
                    'message' => 'Tenant not found',
                    'debug' => [
                        'host' => $host,
                        'host_without_port' => $hostWithoutPort,
                        'x_forwarded_host' => $request->header('X-Forwarded-Host'),
                        'origin' => $request->header('Origin'),
                        'request_host' => $request->getHost(),
                        'x_tenant_slug_header' => $request->header('X-Tenant-Slug'),
                        'tenant_query_param' => $request->query('tenant'),
                        'tenant_slug_provided' => $tenantSlug,
                        'is_development' => $isDevelopment ?? false,
                        'active_tenants_count' => $activeTenantCount,
                        'total_tenants_count' => $totalTenantCount,
                        'available_tenants' => $availableTenants->map(fn($t) => ['slug' => $t->slug, 'name' => $t->name, 'status' => $t->status])->toArray(),
                        'hint' => $hint,
                    ]
                ], 404)
                    ->withHeaders([
                        'Access-Control-Allow-Origin' => $origin ?? '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                        'Access-Control-Allow-Credentials' => 'true',
                    ]);
            }

            Log::debug('TenantMiddleware: tenant resolved', [
                'tenant_id' => tenant()?->id,
                'tenant_domain' => $domain->domain ?? null,
            ]);
            
            $tenant = \App\Models\Central\Tenant::find($domain->tenant_id);
            
            if (!$tenant) {
                Log::error('TenantMiddleware: tenant record missing in central database', [
                    'tenant_id' => $domain->tenant_id,
                    'domain' => $domain->domain ?? $hostWithoutPort,
                ]);
                $origin = $request->header('Origin');
                return response()->json(['message' => 'Tenant not found'], 404)
                    ->withHeaders([
                        'Access-Control-Allow-Origin' => $origin ?? '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                        'Access-Control-Allow-Credentials' => 'true',
                    ]);
            }

            // Configure tenant database
            $tenantDatabaseName = $tenant->id . '_smart_housing';
            
            
            try {
            DB::purge('tenant');
            Config::set('database.connections.tenant.database', $tenantDatabaseName);
            DB::connection('tenant')->reconnect();
                Log::debug('TenantMiddleware: tenant database connection established', [
                    'database' => $tenantDatabaseName,
                ]);
            } catch (\Exception $dbException) {
               Log::error('TenantMiddleware: failed to connect tenant database', [
                   'database' => $tenantDatabaseName,
                   'error' => $dbException->getMessage(),
               ]);
                $origin = $request->header('Origin');
                return response()->json([
                    'message' => 'Failed to connect to tenant database',
                    'error' => config('app.debug') ? $dbException->getMessage() : 'Internal server error',
                    'database' => config('app.debug') ? $tenantDatabaseName : null,
                ], 500)
                    ->withHeaders([
                        'Access-Control-Allow-Origin' => $origin ?? '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                        'Access-Control-Allow-Credentials' => 'true',
                    ]);
            }
            
            // Set as default - store original before changing
            $originalDefault = Config::get('database.default');
            Config::set('database.default', 'tenant');
            
           
            
            // Initialize tenant context for Stancl\Tenancy
            try {
            \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
                Log::debug('TenantMiddleware: tenancy context initialized', [
                    'tenant_id' => $tenant->id,
                ]);
            } catch (\Exception $tenancyException) {
                Log::error('TenantMiddleware: failed to initialize tenancy context', [
                    'tenant_id' => $tenant->id,
                    'error' => $tenancyException->getMessage(),
                ]);
                
                $origin = $request->header('Origin');
                return response()->json([
                    'message' => 'Failed to initialize tenant context',
                    'error' => config('app.debug') ? $tenancyException->getMessage() : 'Internal server error',
                    'trace' => config('app.debug') ? $tenancyException->getTraceAsString() : null,
                ], 500)
                    ->withHeaders([
                        'Access-Control-Allow-Origin' => $origin ?? '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                        'Access-Control-Allow-Credentials' => 'true',
                    ]);
            }

            try {
                return $next($request);
            } finally {
                // Restore original database default if it was set
                if (isset($originalDefault)) {
                Config::set('database.default', $originalDefault);
                }
                Log::debug('TenantMiddleware: request completed, database default restored');
            }
        } catch (\Exception $e) {
            Log::error('TenantMiddleware: unexpected error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            $origin = $request->header('Origin');
            return response()->json([
                'message' => 'An error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'file' => config('app.debug') ? $e->getFile() . ':' . $e->getLine() : null,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500)
                ->withHeaders([
                    'Access-Control-Allow-Origin' => $origin ?? '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                    'Access-Control-Allow-Credentials' => 'true',
                ]);
        }
    }
}
