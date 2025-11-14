<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    /**
     * Get all permissions with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Permission::withCount(['roles', 'users']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by group
        if ($request->has('group') && !empty($request->group)) {
            $query->where('group', $request->group);
        }

        // Filter by status
        if ($request->has('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'true');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'group');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $permissions = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'permissions' => $permissions->items(),
            'pagination' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ]
        ]);
    }

    /**
     * Get permissions grouped by category
     */
    public function grouped(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $permissions = Permission::active()
            ->ordered()
            ->get()
            ->groupBy('group');

        $grouped = [];
        foreach ($permissions as $group => $perms) {
            $grouped[] = [
                'group' => $group,
                'group_display_name' => $perms->first()->group_display_name,
                'permissions' => $perms->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'display_name' => $permission->display_name,
                        'description' => $permission->description,
                        'is_in_use' => $permission->isInUse(),
                    ];
                })
            ];
        }

        return response()->json([
            'success' => true,
            'grouped_permissions' => $grouped
        ]);
    }

    /**
     * Get a specific permission
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $permission = Permission::withCount(['roles', 'users'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'permission' => $permission
        ]);
    }

    /**
     * Create a new permission
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'group' => 'required|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $permission = Permission::create([
                'name' => $request->name,
                'display_name' => $request->display_name ?? str_replace('_', ' ', ucwords($request->name, '_')),
                'guard_name' => 'web',
                'description' => $request->description,
                'group' => $request->group,
                'is_active' => $request->is_active ?? true,
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'permission' => $permission->loadCount(['roles', 'users'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a permission
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $permission = Permission::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:permissions,name,' . $id,
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'group' => 'sometimes|required|string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $permission->update([
                'name' => $request->name ?? $permission->name,
                'display_name' => $request->display_name ?? $permission->display_name,
                'description' => $request->description ?? $permission->description,
                'group' => $request->group ?? $permission->group,
                'is_active' => $request->has('is_active') ? $request->is_active : $permission->is_active,
                'sort_order' => $request->sort_order ?? $permission->sort_order,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'permission' => $permission->fresh()->loadCount(['roles', 'users'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a permission
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $permission = Permission::findOrFail($id);

        // Check if permission is in use
        if ($permission->isInUse()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete permission that is in use'
            ], 422);
        }

        try {
            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Permission deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete permission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get permission groups
     */
    public function groups(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $groups = Permission::select('group')
            ->distinct()
            ->whereNotNull('group')
            ->orderBy('group')
            ->get()
            ->map(function ($permission) {
                return [
                    'group' => $permission->group,
                    'display_name' => (new Permission(['group' => $permission->group]))->group_display_name,
                    'count' => Permission::where('group', $permission->group)->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'groups' => $groups
        ]);
    }

    /**
     * Get permission statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $stats = [
            'total_permissions' => Permission::count(),
            'active_permissions' => Permission::where('is_active', true)->count(),
            'inactive_permissions' => Permission::where('is_active', false)->count(),
            'permissions_in_use' => Permission::whereHas('roles')->orWhereHas('users')->count(),
            'total_groups' => Permission::distinct('group')->count('group'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
}




