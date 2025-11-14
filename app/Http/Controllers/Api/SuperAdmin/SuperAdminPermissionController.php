<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SuperAdminPermissionRequest;
use App\Http\Resources\SuperAdmin\SuperAdminPermissionResource;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuperAdminPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Permission::query();

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            // Filter by guard
            if ($request->has('guard_name')) {
                $query->where('guard_name', $request->guard_name);
            }

            $permissions = $query->orderBy('name')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'permissions' => SuperAdminPermissionResource::collection($permissions),
                'pagination' => [
                    'current_page' => $permissions->currentPage(),
                    'last_page' => $permissions->lastPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin permissions index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(SuperAdminPermissionRequest $request): JsonResponse
    {
        try {
            $permission = Permission::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? 'web'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'permission' => new SuperAdminPermissionResource($permission)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Super admin permission creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'success' => true,
            'permission' => new SuperAdminPermissionResource($permission)
        ]);
    }

    public function update(SuperAdminPermissionRequest $request, Permission $permission): JsonResponse
    {
        try {
            $permission->update([
                'name' => $request->name,
                'guard_name' => $request->guard_name ?? $permission->guard_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'permission' => new SuperAdminPermissionResource($permission)
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin permission update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Permission $permission): JsonResponse
    {
        try {
            // Check if permission is in use
            if ($permission->roles()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete permission that is assigned to roles'
                ], 422);
            }

            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin permission deletion error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getGroupedPermissions(): JsonResponse
    {
        try {
            $permissions = Permission::where('guard_name', 'web')->get();
            
            $grouped = $permissions->groupBy(function ($permission) {
                $parts = explode('.', $permission->name);
                return $parts[0] ?? 'other';
            });

            // Transform to the format expected by frontend
            $groupedPermissions = [];
            foreach ($grouped as $category => $permissionGroup) {
                $groupedPermissions[$category] = $permissionGroup->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name
                    ];
                })->toArray();
            }

            return response()->json([
                'success' => true,
                'grouped_permissions' => $groupedPermissions
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin grouped permissions error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grouped permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
