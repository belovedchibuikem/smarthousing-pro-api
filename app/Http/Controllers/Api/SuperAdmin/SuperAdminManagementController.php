<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SuperAdminManagementRequest;
use App\Http\Resources\SuperAdmin\SuperAdminManagementResource;
use App\Models\Central\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SuperAdminManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SuperAdmin::query();

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('role', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->has('status')) {
                $isActive = $request->status === 'active';
                $query->where('is_active', $isActive);
            }

            $admins = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'admins' => SuperAdminManagementResource::collection($admins),
                'pagination' => [
                    'current_page' => $admins->currentPage(),
                    'last_page' => $admins->lastPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin management index error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve super admins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(SuperAdminManagementRequest $request): JsonResponse
    {
        try {
            // Split name into first_name and last_name
            $nameParts = explode(' ', $request->name, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

            // Get the role name from the role ID
            $role = \App\Models\Central\Role::find($request->role);
            $roleName = $role ? $role->slug : 'super_admin';

            $admin = SuperAdmin::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $roleName,
                'is_active' => $request->status === 'active' || $request->status === null,
                'permissions' => $request->permissions ?? []
            ]);

            // Assign the role using Spatie permissions
            if ($role) {
                $admin->assignRole($role);
            }

            return response()->json([
                'success' => true,
                'message' => 'Super admin created successfully',
                'admin' => new SuperAdminManagementResource($admin)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Super admin creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create super admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(SuperAdmin $admin): JsonResponse
    {
        return response()->json([
            'success' => true,
            'admin' => new SuperAdminManagementResource($admin)
        ]);
    }

    public function update(SuperAdminManagementRequest $request, SuperAdmin $admin): JsonResponse
    {
        try {
            // Split name into first_name and last_name
            $nameParts = explode(' ', $request->name, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

            // Get the role name from the role ID
            $role = \App\Models\Central\Role::find($request->role);
            $roleName = $role ? $role->slug : $admin->role;

            $updateData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $request->email,
                'role' => $roleName,
                'is_active' => $request->status === 'active',
                'permissions' => $request->permissions ?? $admin->permissions
            ];

            if ($request->has('password') && $request->password) {
                $updateData['password'] = Hash::make($request->password);
            }

            $admin->update($updateData);

            // Update the role using Spatie permissions
            if ($role) {
                // Remove all current roles
                $admin->syncRoles([]);
                // Assign the new role
                $admin->assignRole($role);
            }

            return response()->json([
                'success' => true,
                'message' => 'Super admin updated successfully',
                'admin' => new SuperAdminManagementResource($admin)
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update super admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(SuperAdmin $admin): JsonResponse
    {
        try {
            // Prevent deleting the last super admin
            if (SuperAdmin::count() <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the last super admin'
                ], 422);
            }

            $admin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Super admin deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin deletion error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete super admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(SuperAdmin $admin): JsonResponse
    {
        try {
            $admin->update([
                'is_active' => !$admin->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Super admin status updated successfully',
                'admin' => new SuperAdminManagementResource($admin)
            ]);
        } catch (\Exception $e) {
            Log::error('Super admin status toggle error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update super admin status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
