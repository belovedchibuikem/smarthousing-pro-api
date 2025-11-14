<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * Get all roles with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Role::withCount(['users', 'permissions']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'true');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $roles = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'roles' => $roles->items(),
            'pagination' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ]
        ]);
    }

    /**
     * Get a specific role
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $role = Role::withCount(['users', 'permissions'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'role' => $role
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create role
            $role = Role::create([
                'name' => $request->name,
                'display_name' => $request->display_name ?? $request->name,
                'guard_name' => 'web',
                'description' => $request->description,
                'color' => $request->color,
                'is_active' => $request->is_active ?? true,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            // Assign permissions
            if ($request->has('permissions') && is_array($request->permissions)) {
                $permissions = Permission::whereIn('name', $request->permissions)->get();
                $role->syncPermissions($permissions);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'role' => $role->loadCount(['users', 'permissions'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a role
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $id,
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Update role
            $role->update([
                'name' => $request->name ?? $role->name,
                'display_name' => $request->display_name ?? $role->display_name,
                'description' => $request->description ?? $role->description,
                'color' => $request->color ?? $role->color,
                'is_active' => $request->has('is_active') ? $request->is_active : $role->is_active,
                'sort_order' => $request->sort_order ?? $role->sort_order,
            ]);

            // Update permissions
            if ($request->has('permissions')) {
                if (is_array($request->permissions)) {
                    $permissions = Permission::whereIn('name', $request->permissions)->get();
                    $role->syncPermissions($permissions);
                } else {
                    $role->syncPermissions([]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'role' => $role->fresh()->loadCount(['users', 'permissions'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a role
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $role = Role::findOrFail($id);

        // Check if role is in use
        if ($role->isInUse()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role that is in use'
            ], 422);
        }

        try {
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle role status
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $role = Role::findOrFail($id);
        $role->update(['is_active' => !$role->is_active]);

        return response()->json([
            'success' => true,
            'message' => "Role status updated to " . ($role->is_active ? 'active' : 'inactive'),
            'role' => $role->fresh()
        ]);
    }

    /**
     * Get role permissions
     */
    public function permissions(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $role = Role::findOrFail($id);
        $permissions = $role->permissions()->ordered()->get();

        return response()->json([
            'success' => true,
            'permissions' => $permissions
        ]);
    }

    /**
     * Get role statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $stats = [
            'total_roles' => Role::count(),
            'active_roles' => Role::where('is_active', true)->count(),
            'inactive_roles' => Role::where('is_active', false)->count(),
            'roles_in_use' => Role::whereHas('users')->count(),
            'total_permissions' => Permission::count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
}




