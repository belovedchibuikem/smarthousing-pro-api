<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Models\Tenant\Member;
use App\Models\Tenant\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = User::with(['member', 'roles']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    /**
     * Get a specific user
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $targetUser = User::with(['member'])->findOrFail($id);

        return response()->json([
            'user' => $targetUser
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Normalize member_data: convert empty strings to null for enum fields
        $requestData = $request->all();
        if (isset($requestData['member_data']) && is_array($requestData['member_data'])) {
            $enumFields = ['gender', 'marital_status', 'membership_type'];
            foreach ($enumFields as $field) {
                if (isset($requestData['member_data'][$field]) && $requestData['member_data'][$field] === '') {
                    $requestData['member_data'][$field] = null;
                }
            }
        }

        $validator = Validator::make($requestData, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'password' => 'required|string|min:8',
            
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
            // Create user
            $newUser = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => $request->status,
                'password' => Hash::make($request->password),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

            // Assign roles
            $roles = Role::whereIn('name', $request->roles)->get();
            $newUser->syncRoles($roles);

            // Create member profile if member_data is provided and user has member role
            if (isset($requestData['member_data']) && $newUser->hasRole('member')) {
                $memberData = $requestData['member_data'];
                $memberData['user_id'] = $newUser->id;
                $memberData['member_number'] = $this->generateMemberNumber();
                $memberData['kyc_status'] = 'pending';

                Member::create($memberData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $newUser->load(['member', 'roles'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $targetUser = User::with('member')->findOrFail($id);

        // Normalize member_data: convert empty strings to null for enum fields
        $requestData = $request->all();
        if (isset($requestData['member_data']) && is_array($requestData['member_data'])) {
            $enumFields = ['gender', 'marital_status', 'membership_type'];
            foreach ($enumFields as $field) {
                if (isset($requestData['member_data'][$field]) && $requestData['member_data'][$field] === '') {
                    $requestData['member_data'][$field] = null;
                }
            }
        }

        // Build validation rules for staff_id with proper ignore handling
        $staffIdRules = ['nullable', 'string', 'max:255'];
        $staffIdValue = $request->input('member_data.staff_id');
        if ($staffIdValue !== null && $staffIdValue !== '') {
            $uniqueRule = Rule::unique('members', 'staff_id');
            if ($targetUser->member && $targetUser->member->id) {
                $uniqueRule->ignore($targetUser->member->id);
            }
            $staffIdRules[] = $uniqueRule;
        }

        $validator = Validator::make($requestData, [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'phone' => 'sometimes|required|string|max:20',
            'roles' => 'sometimes|array|min:1',
            'roles.*' => 'string|exists:roles,name',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'suspended'])],
            'password' => 'sometimes|string|min:8',
            
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
            // Update user
            $updateData = $request->only(['first_name', 'last_name', 'email', 'phone', 'status','role']);
            
            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $updateData['role'] = 'admin';

            $targetUser->update($updateData);

            // Update roles if provided
            if ($request->has('roles')) {
                $roles = Role::whereIn('name', $request->roles)->get();
                $targetUser->syncRoles($roles);
            }

            // Update member profile if member_data is provided and user has member role
            if (isset($requestData['member_data']) && $targetUser->hasRole('member')) {
                $member = $targetUser->member;
                if ($member) {
                    $member->update($requestData['member_data']);
                } else {
                    // Create member profile if it doesn't exist
                    $memberData = $requestData['member_data'];
                    $memberData['user_id'] = $targetUser->id;
                    $memberData['member_number'] = $this->generateMemberNumber();
                    $memberData['kyc_status'] = 'pending';
                    Member::create($memberData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $targetUser->fresh()->load(['member', 'roles'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Prevent self-deletion
        if ($user->id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 400);
        }

        $targetUser = User::findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete associated member profile first
            if ($targetUser->member) {
                $targetUser->member->delete();
            }

            // Delete user
            $targetUser->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Prevent self-status change
        if ($user->id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own status'
            ], 400);
        }

        $targetUser = User::findOrFail($id);
        
        $newStatus = $targetUser->status === 'active' ? 'inactive' : 'active';
        $targetUser->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "User status updated to {$newStatus}",
            'user' => $targetUser->fresh()
        ]);
    }

    /**
     * Get user statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'inactive_users' => User::where('status', 'inactive')->count(),
            'suspended_users' => User::where('status', 'suspended')->count(),
            'admin_users' => User::whereHas('roles', function($q) {
                $q->whereNotIn('name', ['member']);
            })->count(),
            'member_users' => User::whereHas('roles', function($q) {
                $q->where('name', 'member');
            })->count(),
        ];

        return response()->json([
            'stats' => $stats
        ]);
    }

    /**
     * Generate unique member number
     */
    private function generateMemberNumber(): string
    {
        do {
            $memberNumber = 'FRSC/' . date('Y') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Member::where('member_number', $memberNumber)->exists());

        return $memberNumber;
    }
}
