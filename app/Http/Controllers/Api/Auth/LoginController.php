<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\Tenant\User;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(
        protected TenantAuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            $credentials = $request->only('email', 'password');
            $user = null;
            $isSuperAdmin = false;
            $tenant = null;
            $origin = $request->header('Origin');

            // Determine if this is a super-admin login attempt
            // Super-admin login should come from main platform domain (no tenant subdomain)
            $host = $request->header('X-Forwarded-Host') ?? $request->getHost();
            $hostWithoutPort = preg_replace('/:\d+$/', '', $host);
            
            // Check if this is a tenant subdomain
            $isTenantRequest = $this->isTenantRequest($hostWithoutPort);
            
            // If it's a tenant request, skip super-admin check and go straight to tenant auth
            if ($isTenantRequest) {
                // Tenant authentication flow
                $tenant = $this->resolveTenant($hostWithoutPort, $host);
                
                if (!$tenant) {
                    return response()->json([
                        'message' => 'Tenant not found for this domain',
                        'error' => 'Invalid domain',
                        'debug' => [
                            'host' => $host,
                            'host_without_port' => $hostWithoutPort,
                            'x_forwarded_host' => $request->header('X-Forwarded-Host'),
                            'origin' => $origin,
                        ]
                    ], 404)->withHeaders([
                        'Access-Control-Allow-Origin' => $origin ?? '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                        'Access-Control-Allow-Credentials' => 'true',
                    ]);
                }
                
                // Initialize tenant context
                $this->initializeTenantContext($tenant);
                
                // Authenticate tenant user
                $user = $this->authenticateTenantUser($credentials, $tenant);
                
                if (!$user) {
                    return response()->json([
                        'message' => 'The provided credentials are incorrect.',
                        'errors' => [
                            'email' => ['The provided credentials are incorrect.']
                        ]
                    ], 401)->withHeaders([
                        'Access-Control-Allow-Origin' => $origin ?? '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                        'Access-Control-Allow-Credentials' => 'true',
                    ]);
                }
            } else {
                // Try super-admin authentication first (for main platform domain)
                $superAdmin = $this->authenticateSuperAdmin($credentials);
                
                if ($superAdmin) {
                    $user = $superAdmin;
                    $isSuperAdmin = true;
                } else {
                    // If super-admin auth fails, check if it might be a tenant user
                    // trying to login from wrong domain - provide helpful error
                    $tenant = $this->findTenantByEmail($credentials['email']);
                    
                    if ($tenant) {
                        return response()->json([
                            'message' => 'Please login using your tenant subdomain',
                            'error' => 'Invalid login domain',
                            'hint' => 'Tenant users must login from their tenant subdomain'
                        ], 400)->withHeaders([
                            'Access-Control-Allow-Origin' => $origin ?? '*',
                            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                            'Access-Control-Allow-Credentials' => 'true',
                        ]);
                    }
                    
                    // Neither super-admin nor tenant user found
                    return response()->json([
                        'message' => 'The provided credentials are incorrect.',
                        'errors' => [
                            'email' => ['The provided credentials are incorrect.']
                        ]
                    ], 401)->withHeaders([
                        'Access-Control-Allow-Origin' => $origin ?? '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                        'Access-Control-Allow-Credentials' => 'true',
                    ]);
                }
            }

            // Update last login and generate token based on user type
            if ($isSuperAdmin) {
                // For super-admin, update last_login if the field exists
                if (isset($user->last_login)) {
                    $user->update(['last_login' => now()]);
                   
                }
                
                // Super-admin token (no tenant_id)
                $token = $user->createToken('auth_token');
                $tokenText = $token->plainTextToken;
                
            } else {
                
                // Tenant user - update last_login and generate token with tenant_id
                $user->update(['last_login' => now()]);
                
                
                
                // Log audit event for login
                try {
                    $this->auditLogService->logLogin($user, $request);
                    
                } catch (\Exception $e) {
                    // Don't fail login if audit logging fails
                    
                }
                
                // Log activity event for login
                try {
                    if ($tenant) {
                        $this->activityLogService->logUserAction(
                            'login',
                            'User logged in: ' . $user->email,
                            $user,
                            'auth',
                            ['email' => $user->email, 'tenant_id' => $tenant->id]
                        );
                        
                    }
                } catch (\Exception $e) {
                    // Don't fail login if activity logging fails
                    
                }
                
                // Tenant user token (with tenant_id)
                $token = $user->createToken('auth_token');
                
                // Update the token with tenant_id directly in the database if tenant is available
                if ($tenant) {
                    try {
                        DB::connection('tenant')->table('personal_access_tokens')
                            ->where('id', $token->accessToken->id)
                            ->update(['tenant_id' => $tenant->id]);
                        
                    } catch (\Exception $e) {
                        // Don't fail login if token update fails
                       
                    }
                }
                
                $tokenText = $token->plainTextToken;
            }

            // Create user resource based on user type
            if ($isSuperAdmin) {
                try {
                    $permissions = $user->getPermissionSlugs();
                    Log::debug('[LOGIN] Super-admin permissions retrieved', [
                        'super_admin_id' => $user->id,
                        'permission_count' => count($permissions),
                    ]);
                } catch (\Exception $e) {
                    Log::error('[LOGIN] Error getting SuperAdmin permissions', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $permissions = [];
                }
                
                $userResource = [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,
                    'avatar_url' => null,
                    'role' => 'super-admin',
                    'roles' => ['super-admin'],
                    'permissions' => $permissions,
                    'status' => $user->is_active ? 'active' : 'inactive',
                    'email_verified_at' => null,
                    'last_login' => $user->last_login ?? null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            } else {
                Log::info('[LOGIN] Creating tenant user resource', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant?->id,
                ]);
                
                $userResource = new UserResource($user);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'message' => 'Login successful',
                'user' => $userResource,
                'token' => $tokenText,
                'token_type' => 'Bearer',
            ], 200)->withHeaders([
                'Access-Control-Allow-Origin' => $origin ?? '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('[LOGIN] Login failed with exception', [
                'email' => $request->email ?? $credentials['email'] ?? 'unknown',
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $duration,
                'ip' => $request->ip(),
                'host' => $request->header('X-Forwarded-Host') ?? $request->getHost(),
            ]);
            
            $origin = $request->header('Origin');
            return response()->json([
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during login'
            ], 500)->withHeaders([
                'Access-Control-Allow-Origin' => $origin ?? '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
    }

    public function me(): JsonResponse
    {
        $origin = request()->header('Origin');
        
        try {
            // Get the authenticated user from Sanctum
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated',
                ], 401)->withHeaders([
                    'Access-Control-Allow-Origin' => $origin ?? '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                    'Access-Control-Allow-Credentials' => 'true',
                ]);
            }

            // Check if user is super-admin or tenant user
            $isSuperAdmin = $user instanceof \App\Models\Central\SuperAdmin;
            
            if ($isSuperAdmin) {
                // For super-admin users, return user data in the same format as login
                /** @var \App\Models\Central\SuperAdmin $user */
                $superAdmin = $user;
                
                try {
                    $permissions = $superAdmin->getPermissionSlugs();
                    Log::debug('[ME] Super-admin permissions retrieved', [
                        'super_admin_id' => $superAdmin->id,
                        'permission_count' => count($permissions),
                    ]);
                } catch (\Exception $e) {
                    Log::error('[ME] Error getting SuperAdmin permissions', [
                        'user_id' => $superAdmin->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $permissions = [];
                }
                
                $userResource = [
                    'id' => $superAdmin->id,
                    'first_name' => $superAdmin->first_name,
                    'last_name' => $superAdmin->last_name,
                    'email' => $superAdmin->email,
                    'phone' => $superAdmin->phone ?? null,
                    'avatar_url' => null,
                    'role' => 'super-admin',
                    'roles' => ['super-admin'],
                    'permissions' => $permissions,
                    'status' => $superAdmin->is_active ? 'active' : 'inactive',
                    'email_verified_at' => null,
                    'last_login' => $superAdmin->last_login ?? null,
                    'created_at' => $superAdmin->created_at,
                    'updated_at' => $superAdmin->updated_at,
                ];
                
                return response()->json([
                    'user' => $userResource,
                    'message' => 'User data retrieved successfully',
                ], 200)->withHeaders([
                    'Access-Control-Allow-Origin' => $origin ?? '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                    'Access-Control-Allow-Credentials' => 'true',
                ]);
            } else {
                // Tenant user - load member relationship if available
                /** @var \App\Models\Tenant\User $user */
                $tenantUser = $user;
                
                if (method_exists($tenantUser, 'member')) {
                    $tenantUser->load('member');
                }
                
                // Return user data using UserResource
                $userResource = new UserResource($tenantUser);
                
                Log::debug('[ME] Tenant user data retrieved', [
                    'user_id' => $tenantUser->id,
                    'has_member' => $tenantUser->member ? true : false,
                ]);
                
                return response()->json([
                    'user' => $userResource,
                    'message' => 'User data retrieved successfully',
                ], 200)->withHeaders([
                    'Access-Control-Allow-Origin' => $origin ?? '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                    'Access-Control-Allow-Credentials' => 'true',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[ME] Failed to retrieve user data', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => request()->ip(),
                'host' => request()->header('X-Forwarded-Host') ?? request()->getHost(),
            ]);
            
            return response()->json([
                'message' => 'Failed to retrieve user data',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while retrieving user data'
            ], 500)->withHeaders([
                'Access-Control-Allow-Origin' => $origin ?? '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $origin = $request->header('Origin');
        
        if ($user) {
            // Log audit event for logout (only for tenant users, not super admin)
            if ($user instanceof User) {
                try {
                    $this->auditLogService->logLogout($user, $request);
                } catch (\Exception $e) {
                    // Don't fail logout if audit logging fails
                    Log::warning('Failed to log logout audit event', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Log activity event for logout
                try {
                    $this->activityLogService->logUserAction(
                        'logout',
                        'User logged out: ' . $user->email,
                        $user,
                        'auth',
                        ['email' => $user->email]
                    );
                } catch (\Exception $e) {
                    // Don't fail logout if activity logging fails
                    Log::warning('Failed to log logout activity event', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Delete all user tokens (both super-admin and tenant users have tokens())
            if (method_exists($user, 'tokens') && $user->tokens()->count() > 0) {
                $user->tokens()->delete();
            }
            
            // Also delete current access token
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ])->withHeaders([
            'Access-Control-Allow-Origin' => $origin ?? '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }

    /**
     * Check if the request is for a tenant subdomain
     */
    private function isTenantRequest(string $host): bool
    {
        Log::debug('[LOGIN] Checking if tenant request', [
            'host' => $host,
        ]);
        
        // Check if host contains localhost subdomain pattern (e.g., frsc.localhost)
        if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
            $parts = explode('.', $host);
            Log::debug('[LOGIN] Analyzing localhost subdomain', [
                'host' => $host,
                'parts' => $parts,
                'parts_count' => count($parts),
            ]);
            
            // If it's a subdomain (more than 2 parts for localhost), it's a tenant
            // e.g., frsc.localhost -> ['frsc', 'localhost'] -> count = 2, but parts[0] = 'frsc' != 'localhost'
            if (count($parts) >= 2) {
                $firstPart = $parts[0];
                // If first part is not 'localhost' or '127', it's likely a tenant subdomain
                if ($firstPart !== 'localhost' && $firstPart !== '127' && $firstPart !== '127.0.0.1') {
                    Log::debug('[LOGIN] Detected tenant subdomain pattern', [
                        'host' => $host,
                        'subdomain' => $firstPart,
                    ]);
                    return true;
                }
            }
        }

        // Check if domain exists in domains table (excluding main platform domains)
        $domain = DB::connection('mysql')->table('domains')
            ->where('domain', $host)
            ->first();

        if ($domain) {
            Log::debug('[LOGIN] Found domain in domains table', [
                'host' => $host,
                'domain_id' => $domain->id,
                'tenant_id' => $domain->tenant_id,
            ]);
            return true;
        }

        // Also check if it's a subdomain pattern in tenant_details
        if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
                $tenantDetail = DB::connection('mysql')->table('tenant_details')
                    ->where('slug', $subdomain)
                    ->where('status', 'active')
                    ->first();
                
                if ($tenantDetail) {
                    Log::debug('[LOGIN] Found tenant by slug in tenant_details', [
                        'host' => $host,
                        'subdomain' => $subdomain,
                        'tenant_id' => $tenantDetail->tenant_id,
                    ]);
                    return true;
                }
            }
        }

        Log::debug('[LOGIN] Not a tenant request', [
            'host' => $host,
        ]);
        return false;
    }

    /**
     * Resolve tenant from host/domain
     */
    private function resolveTenant(string $hostWithoutPort, string $host): ?\App\Models\Central\Tenant
    {
        Log::debug('[LOGIN] Resolving tenant', [
            'host_without_port' => $hostWithoutPort,
            'host' => $host,
        ]);
        
        // Try to find domain without port first
        $domain = DB::connection('mysql')->table('domains')
            ->where('domain', $hostWithoutPort)
            ->first();

        if ($domain) {
            Log::debug('[LOGIN] Found domain (without port)', [
                'domain_id' => $domain->id,
                'domain' => $domain->domain,
                'tenant_id' => $domain->tenant_id,
            ]);
        }

        // If not found, try with port
        if (!$domain && $hostWithoutPort !== $host) {
            $domain = DB::connection('mysql')->table('domains')
                ->where('domain', $host)
                ->first();
                
            if ($domain) {
                Log::debug('[LOGIN] Found domain (with port)', [
                    'domain_id' => $domain->id,
                    'domain' => $domain->domain,
                    'tenant_id' => $domain->tenant_id,
                ]);
            }
        }

        // If still not found, try subdomain matching for localhost
        if (!$domain && (str_contains($hostWithoutPort, 'localhost') || str_contains($hostWithoutPort, '127.0.0.1'))) {
            Log::debug('[LOGIN] Trying subdomain matching for localhost', [
                'host_without_port' => $hostWithoutPort,
            ]);
            
            $parts = explode('.', $hostWithoutPort);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
                
                Log::debug('[LOGIN] Extracted subdomain', [
                    'subdomain' => $subdomain,
                    'host' => $hostWithoutPort,
                ]);
                
                // Try to find tenant by slug in tenant_details
                $tenantDetail = DB::connection('mysql')->table('tenant_details')
                    ->where('slug', $subdomain)
                    ->where('status', 'active')
                    ->first();
                
                if ($tenantDetail) {
                    Log::debug('[LOGIN] Found tenant by slug', [
                        'subdomain' => $subdomain,
                        'tenant_id' => $tenantDetail->tenant_id,
                    ]);
                    
                    // CRITICAL: Use DB facade directly to avoid Eloquent connection issues
                    $tenantData = DB::connection('mysql')
                        ->table('tenants')
                        ->where('id', $tenantDetail->tenant_id)
                        ->first();
                    
                    if ($tenantData) {
                        // Create tenant model instance with explicit central connection
                        $tenant = new \App\Models\Central\Tenant();
                        $tenant->setConnection('mysql');
                        $tenant->setRawAttributes((array) $tenantData, true);
                        $tenant->exists = true;
                        return $tenant;
                    }
                }
                
                // Try domain pattern matching
                $domain = DB::connection('mysql')->table('domains')
                    ->where('domain', 'like', $subdomain . '.%')
                    ->orWhere('domain', $subdomain)
                    ->first();
                    
                if ($domain) {
                    Log::debug('[LOGIN] Found domain by pattern matching', [
                        'subdomain' => $subdomain,
                        'domain_id' => $domain->id,
                        'domain' => $domain->domain,
                        'tenant_id' => $domain->tenant_id,
                    ]);
                }
            }
        }

        if (!$domain) {
            Log::warning('[LOGIN] Tenant resolution failed - no domain found', [
                'host_without_port' => $hostWithoutPort,
                'host' => $host,
            ]);
            return null;
        }

        // CRITICAL: Use DB facade directly to avoid Eloquent connection issues
        // The default connection might have been switched to 'tenant' by middleware
        // Using DB facade ensures we always query the central database
        $tenantData = DB::connection('mysql')
            ->table('tenants')
            ->where('id', $domain->tenant_id)
            ->first();
        
        if (!$tenantData) {
            Log::warning('[LOGIN] Tenant resolution failed - tenant not found', [
                'domain_id' => $domain->id,
                'tenant_id' => $domain->tenant_id,
            ]);
            return null;
        }
        
        // Create tenant model instance with explicit central connection
        $tenant = new \App\Models\Central\Tenant();
        $tenant->setConnection('mysql');
        $tenant->setRawAttributes((array) $tenantData, true);
        $tenant->exists = true;
        
        Log::debug('[LOGIN] Tenant resolved successfully', [
            'tenant_id' => $tenant->id,
            'domain' => $domain->domain,
        ]);

        return $tenant;
    }

    /**
     * Initialize tenant database context
     */
    private function initializeTenantContext(\App\Models\Central\Tenant $tenant): void
    {
        Log::debug('[LOGIN] Initializing tenant context', [
            'tenant_id' => $tenant->id,
        ]);
        
        try {
            // CRITICAL: Ensure tenant model uses central connection BEFORE anything else
            // This prevents any lazy loading or relationship queries from using wrong database
            $tenant->setConnection('mysql');
            
            // Store tenant ID to avoid any model reloading issues
            $tenantId = $tenant->id;
            
            // Configure tenant database connection
            // Get database prefix and suffix from environment/config
            $dbPrefix = env('TENANCY_DB_PREFIX', config('tenancy.database.prefix', ''));
            $dbSuffix = env('TENANCY_DB_SUFFIX', config('tenancy.database.suffix', '_smart_housing'));
            $tenantDbName = $dbPrefix . $tenantId . $dbSuffix;
            
            // Store original default connection
            $originalDefault = Config::get('database.default');
            
            // Ensure default connection is 'mysql' before configuring tenant connection
            Config::set('database.default', 'mysql');
            
            // Configure tenant database connection
            DB::purge('tenant');
            Config::set('database.connections.tenant.database', $tenantDbName);
            DB::connection('tenant')->reconnect();
            
            Log::debug('[LOGIN] Tenant database connection configured', [
                'tenant_id' => $tenantId,
                'database' => $tenantDbName,
                'prefix' => $dbPrefix,
                'suffix' => $dbSuffix,
            ]);
            
            // Check if tenancy is already initialized by middleware
            // If middleware already initialized it, we don't need to do it again
            try {
                $currentTenant = \Stancl\Tenancy\Facades\Tenancy::tenant();
                if ($currentTenant && $currentTenant->id === $tenantId) {
                    Log::debug('[LOGIN] Tenancy already initialized by middleware', [
                        'tenant_id' => $tenantId,
                    ]);
                    // Just ensure tenant model connection is set correctly
                    $tenant->setConnection('mysql');
                    return;
                }
            } catch (\Exception $e) {
                // Tenancy might not be initialized yet, continue
                Log::debug('[LOGIN] Tenancy not initialized yet, will initialize now', [
                    'tenant_id' => $tenantId,
                ]);
            }
            
            // Only initialize tenancy if not already done by middleware
            // Load tenant data directly from DB to avoid Eloquent connection issues
            $tenantData = DB::connection('mysql')
                ->table('tenants')
                ->where('id', $tenantId)
                ->first();
            
            if (!$tenantData) {
                throw new \Exception("Tenant not found: {$tenantId}");
            }
            
            // Create fresh tenant model instance with explicit central connection
            $freshTenant = new \App\Models\Central\Tenant();
            $freshTenant->setConnection('mysql');
            $freshTenant->setRawAttributes((array) $tenantData, true);
            $freshTenant->exists = true;
            
            // Initialize tenant context for Stancl\Tenancy
            // Use fresh tenant instance to avoid any connection issues
            \Stancl\Tenancy\Facades\Tenancy::initialize($freshTenant);
            
            Log::debug('[LOGIN] Tenancy initialized successfully', [
                'tenant_id' => $tenantId,
            ]);
            
            // Ensure default connection remains 'mysql' after initialization
            // Tenant operations should use 'tenant' connection explicitly
            Config::set('database.default', 'mysql');
            
        } catch (\Exception $e) {
            Log::error('[LOGIN] Failed to initialize tenant context', [
                'tenant_id' => $tenant->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Authenticate tenant user
     */
    private function authenticateTenantUser(array $credentials, \App\Models\Central\Tenant $tenant): ?User
    {
        try {
            Log::debug('[LOGIN] Authenticating tenant user', [
                'email' => $credentials['email'],
                'tenant_id' => $tenant->id,
            ]);
            
            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                Log::warning('[LOGIN] Tenant user not found', [
                    'email' => $credentials['email'],
                    'tenant_id' => $tenant->id,
                ]);
                return null;
            }

            Log::debug('[LOGIN] Tenant user found', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'user_status' => $user->status,
                'user_role' => $user->role ?? 'N/A',
            ]);

            if (!Hash::check($credentials['password'], $user->password)) {
                Log::warning('[LOGIN] Tenant user password mismatch', [
                    'email' => $credentials['email'],
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                ]);
                return null;
            }

            // Check if user account is active
            if ($user->status !== 'active') {
                Log::warning('[LOGIN] Tenant user account inactive', [
                    'email' => $credentials['email'],
                    'user_id' => $user->id,
                    'tenant_id' => $tenant->id,
                    'status' => $user->status,
                ]);
                throw new \Exception('Your account is inactive. Please contact support.');
            }

            Log::info('[LOGIN] Tenant user authenticated', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);

            return $user;
        } catch (\Exception $e) {
            Log::error('[LOGIN] Tenant user authentication error', [
                'email' => $credentials['email'],
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Authenticate super-admin
     */
    private function authenticateSuperAdmin(array $credentials): ?\App\Models\Central\SuperAdmin
    {
        try {
            Log::debug('[LOGIN] Authenticating super-admin', [
                'email' => $credentials['email'],
            ]);
            
            $superAdmin = \App\Models\Central\SuperAdmin::where('email', $credentials['email'])->first();

            if (!$superAdmin) {
                Log::debug('[LOGIN] Super-admin not found', [
                    'email' => $credentials['email'],
                ]);
                return null;
            }

            Log::debug('[LOGIN] Super-admin found', [
                'email' => $credentials['email'],
                'super_admin_id' => $superAdmin->id,
                'is_active' => $superAdmin->is_active,
            ]);

            if (!Hash::check($credentials['password'], $superAdmin->password)) {
                Log::warning('[LOGIN] Super-admin password mismatch', [
                    'email' => $credentials['email'],
                    'super_admin_id' => $superAdmin->id,
                ]);
                return null;
            }

            // Check if super-admin account is active
            if (!$superAdmin->is_active) {
                Log::warning('[LOGIN] Super-admin account inactive', [
                    'email' => $credentials['email'],
                    'super_admin_id' => $superAdmin->id,
                    'is_active' => $superAdmin->is_active,
                ]);
                throw new \Exception('Your account is inactive. Please contact support.');
            }

            Log::info('[LOGIN] Super-admin authenticated', [
                'email' => $credentials['email'],
                'super_admin_id' => $superAdmin->id,
            ]);

            return $superAdmin;
        } catch (\Exception $e) {
            Log::error('[LOGIN] Super-admin authentication error', [
                'email' => $credentials['email'],
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Find tenant by user email (for helpful error messages)
     */
    private function findTenantByEmail(string $email): ?\App\Models\Central\Tenant
    {
        try {
            Log::debug('[LOGIN] Searching for tenant by email', [
                'email' => $email,
            ]);
            
            // Try to find user in any tenant database
            // This is expensive but only used for error messages
            $tenants = \App\Models\Central\Tenant::all();
            
            Log::debug('[LOGIN] Searching across tenants', [
                'email' => $email,
                'tenant_count' => $tenants->count(),
            ]);
            
            // Get database prefix and suffix from environment/config
            $dbPrefix = env('TENANCY_DB_PREFIX', config('tenancy.database.prefix', ''));
            $dbSuffix = env('TENANCY_DB_SUFFIX', config('tenancy.database.suffix', '_smart_housing'));
            
            foreach ($tenants as $tenant) {
                try {
                    $tenantDbName = $dbPrefix . $tenant->id . $dbSuffix;
                    Config::set('database.connections.tenant.database', $tenantDbName);
                    DB::purge('tenant');
                    DB::connection('tenant')->reconnect();
                    
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        Log::info('[LOGIN] Found tenant by email search', [
                            'email' => $email,
                            'tenant_id' => $tenant->id,
                            'user_id' => $user->id,
                        ]);
                        return $tenant;
                    }
                } catch (\Exception $e) {
                    // Continue to next tenant if this one fails
                    Log::debug('[LOGIN] Error checking tenant database', [
                        'email' => $email,
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
            
            Log::debug('[LOGIN] No tenant found for email', [
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            // If error, just return null
            Log::warning('[LOGIN] Error finding tenant by email', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return null;
    }
}
