<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SuperAdminRoleRequest;
use App\Http\Resources\SuperAdmin\SuperAdminRoleResource;
use App\Models\Central\Role;
use App\Models\Central\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;
use App\Models\Central\Permission;

class SuperAdminRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Role::query();

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Always load roles without permissions to avoid memory issues
            $roles = $query->withCount('superAdmins')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'roles' => SuperAdminRoleResource::collection($roles),
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin roles index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(SuperAdminRoleRequest $request): JsonResponse
    {
        try {
            $role = Role::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
                'guard_name' => 'web'
            ]);

            // Assign permissions using direct SQL to avoid memory issues
            if ($request->has('permissions') && is_array($request->permissions)) {
                // Get permission IDs in batches to avoid memory issues
                $permissionIds = DB::table('permissions')
                    ->whereIn('name', $request->permissions)
                    ->pluck('id')
                    ->toArray();
                
                // Insert new permissions in batches
                $batchSize = 20;
                for ($i = 0; $i < count($permissionIds); $i += $batchSize) {
                    $batch = [];
                    $permissionBatch = array_slice($permissionIds, $i, $batchSize);
                    foreach ($permissionBatch as $permissionId) {
                        $batch[] = [
                            'id' => Str::uuid()->toString(),
                            'role_id' => $role->id,
                            'permission_id' => $permissionId,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                    if (!empty($batch)) {
                        DB::table('role_has_permissions')->insert($batch);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'guard_name' => $role->guard_name,
                    'permissions_count' => DB::table('role_has_permissions')->where('role_id', $role->id)->count(),
                    'user_count' => 0, // New role has no users
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Super admin role creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Role $role): JsonResponse
    {
        try {
            // Don't load permissions to avoid memory issues
            // Permissions will be loaded separately via the getRolePermissions endpoint
            
            return response()->json([
                'success' => true,
                'role' => new SuperAdminRoleResource($role)
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin role show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(SuperAdminRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $role->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'is_active' => $request->is_active ?? $role->is_active
            ]);

            // Update permissions using direct SQL to avoid memory issues
            if ($request->has('permissions') && is_array($request->permissions)) {
                // Clear existing permissions
                DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
                
                // Get permission IDs in batches to avoid memory issues
                $permissionIds = DB::table('permissions')
                    ->whereIn('name', $request->permissions)
                    ->pluck('id')
                    ->toArray();
                
                // Insert new permissions in batches
                $batchSize = 20;
                for ($i = 0; $i < count($permissionIds); $i += $batchSize) {
                    $batch = [];
                    $permissionBatch = array_slice($permissionIds, $i, $batchSize);
                    foreach ($permissionBatch as $permissionId) {
                        $batch[] = [
                            'id' => Str::uuid()->toString(),
                            'role_id' => $role->id,
                            'permission_id' => $permissionId,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                    if (!empty($batch)) {
                        DB::table('role_has_permissions')->insert($batch);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'is_active' => $role->is_active,
                    'guard_name' => $role->guard_name,
                    'permissions_count' => DB::table('role_has_permissions')->where('role_id', $role->id)->count(),
                    'user_count' => DB::table('model_has_roles')->where('role_id', $role->id)->count(),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin role update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Role $role): JsonResponse
    {
        try {
            // Check if role is in use
            if ($role->superAdmins()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role that is in use'
                ], 422);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin role deletion error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all permissions for a specific role
     */
    public function getRolePermissions(Request $request, Role $role): JsonResponse
    {
        try {
            $permissions = $role->permissions()->paginate($request->get('per_page', 50));
            
            return response()->json([
                'success' => true,
                'permissions' => $permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name
                    ];
                }),
                'pagination' => [
                    'current_page' => $permissions->currentPage(),
                    'last_page' => $permissions->lastPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get role permissions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
