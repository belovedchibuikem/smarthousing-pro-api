<?php

namespace App\Http\Controllers\Api\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberRequest;
use App\Http\Resources\Members\MemberResource;
use App\Models\Tenant\Member;
use App\Models\Tenant\User;
use App\Services\Communication\NotificationService;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TenantAuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}
    
    public function index(Request $request): JsonResponse
    {
        try {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Member::with(['user.wallet', 'equityWalletBalance']);

            // Filter by status (only apply if column exists to avoid SQL errors)
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by kyc_status
        if ($request->has('kyc_status') && $request->kyc_status !== 'all') {
            $query->where('kyc_status', $request->kyc_status);
        }

        // Search by name, member_number, staff_id, or email
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%")
                  ->orWhere('staff_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('first_name', 'like', "%{$search}%")
                               ->orWhere('last_name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $members = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'members' => MemberResource::collection($members),
            'pagination' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ]
        ]);
        } catch (\Throwable $e) {
            Log::error('Members index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Failed to load members',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Check if user already exists or needs to be created
            $user = null;
            if ($request->has('user_id')) {
                $user = User::find($request->user_id);
                if (!$user) {
                    return response()->json(['error' => 'User not found'], 404);
                }
            } else {
                // Create user first
                $request->validate([
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users,email',
                    'phone' => 'nullable|string|max:20',
                    'password' => 'required|string|min:8',
                ]);

                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'role' => 'member',
                    'status' => 'active',
                ]);
            }

            // Create member
            $member = Member::create([
                'user_id' => $user->id,
                'staff_id' => $request->staff_id,
                'ippis_number' => $request->ippis_number,
                'member_number'=> $request->member_number,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'nationality' => $request->nationality ?? 'Nigerian',
                'state_of_origin' => $request->state_of_origin,
                'lga' => $request->lga,
                'residential_address' => $request->residential_address,
                'city' => $request->city,
                'state' => $request->state,
                'rank' => $request->rank,
                'department' => $request->department,
                'command_state' => $request->command_state,
                'employment_date' => $request->employment_date,
                'years_of_service' => $request->years_of_service,
                'membership_type' => $request->membership_type ?? 'regular',
                'kyc_status' => 'pending',
                'status' => 'active',
            ]);

            DB::commit();

            // Log audit event
            $this->auditLogService->logCreate(
                $member,
                Auth::user(),
                [
                    'member_number' => $member->member_number,
                    'email' => $user->email,
                ]
            );
            
            // Log activity event
            $this->activityLogService->logModelAction(
                'create',
                $member,
                Auth::user(),
                [
                    'member_number' => $member->member_number,
                    'email' => $user->email,
                    'member_name' => $member->first_name . ' ' . $member->last_name,
                ]
            );

            // Notify admins about new member registration
            $memberName = $member->first_name . ' ' . $member->last_name;
            $this->notificationService->notifyAdminsNewMemberRegistration(
                $member->id,
                $memberName,
                $member->member_number ?? 'N/A'
            );

            return response()->json([
                'success' => true,
                'message' => 'Member created successfully',
                'member' => new MemberResource($member->load('user'))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Member creation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'error' => 'Failed to create member',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $user = $member->user;
        
        // Load user relationships
        $user->load(['wallet']);
        
        // Load member relationships that use member_id
        if (class_exists('App\Models\Tenant\Loan')) {
            $member->load('loans');
        }
        if (class_exists('App\Models\Tenant\Investment')) {
            $member->load('investments');
        }
        if (class_exists('App\Models\Tenant\Contribution')) {
            $member->load('contributions');
        }

        return response()->json([
            'member' => new MemberResource($member)
        ]);
    }

    public function update(MemberRequest $request, $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $oldValues = $member->toArray();
        
        $member->update($request->validated());
        
        // Log audit event
        $this->auditLogService->logUpdate(
            $member,
            $oldValues,
            $member->toArray(),
            Auth::user(),
            ['member_id' => $member->id]
        );
        
        // Log activity event
        $this->activityLogService->logModelAction(
            'update',
            $member,
            Auth::user(),
            [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Member updated successfully',
            'member' => new MemberResource($member->load('user'))
        ]);
    }

    public function activate($id): JsonResponse
    {
        $member = Member::findOrFail($id);
        if ($member->status === 'active') {
            return response()->json([
                'message' => 'Member is already active'
            ], 400);
        }

        $member->update([
            'status' => 'active',
            'activated_at' => now(),
            'activated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member activated successfully'
        ]);
    }

    public function deactivate($id): JsonResponse
    {
        $member = Member::findOrFail($id);
        if ($member->status === 'inactive') {
            return response()->json([
                'message' => 'Member is already inactive'
            ], 400);
        }

        $oldValues = $member->toArray();
        $member->update([
            'status' => 'inactive',
            'deactivated_at' => now(),
            'deactivated_by' => Auth::id(),
        ]);

        // Log audit event
        $this->auditLogService->logUpdate(
            $member,
            $oldValues,
            $member->toArray(),
            Auth::user(),
            ['action' => 'deactivation']
        );

        return response()->json([
            'success' => true,
            'message' => 'Member deactivated successfully'
        ]);
    }

    public function suspend($id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $request = request();
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($member->status === 'suspended') {
            return response()->json([
                'message' => 'Member is already suspended'
            ], 400);
        }

        $member->update([
            'status' => 'suspended',
            'suspension_reason' => $request->reason,
            'suspended_at' => now(),
            'suspended_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member suspended successfully'
        ]);
    }

    public function unsuspend($id): JsonResponse
    {
        $member = Member::findOrFail($id);
        if ($member->status !== 'suspended') {
            return response()->json([
                'message' => 'Member is not suspended'
            ], 400);
        }

        $member->update([
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_at' => null,
            'suspended_by' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member unsuspended successfully'
        ]);
    }

    public function kycStatus($id): JsonResponse
    {
        $member = Member::findOrFail($id);
        return response()->json([
            'kyc_status' => $member->kyc_status,
            'kyc_submitted_at' => $member->kyc_submitted_at,
            'kyc_verified_at' => $member->kyc_verified_at,
            'kyc_rejection_reason' => $member->kyc_rejection_reason,
        ]);
    }

    public function approveKyc(Request $request, $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        if ($member->kyc_status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted KYC can be approved'
            ], 400);
        }
        Log::info('Approving KYC for member: ' . $member->id);
        $member->update([
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
            'kyc_verified_by' => $request->user()->id,
        ]);

        // Log audit event
        $this->auditLogService->logApproval(
            $member,
            'KYC',
            $request->user(),
            [
                'member_id' => $member->id,
                'member_name' => $member->first_name . ' ' . $member->last_name,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logUserAction(
            'approve',
            'KYC approved for member: ' . $member->first_name . ' ' . $member->last_name,
            $request->user(),
            'kyc',
            [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
            ]
        );

        // Notify the member about KYC approval
        if ($member->user) {
            $this->notificationService->notifyKycApproved($member->user);
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC approved successfully'
        ]);
    }

    public function rejectKyc(Request $request, $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $request = request();
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($member->kyc_status !== 'submitted') {
            return response()->json([
                'message' => 'Only submitted KYC can be rejected'
            ], 400);
        }

        $member->update([
            'kyc_status' => 'rejected',
            'kyc_rejection_reason' => $request->reason,
            'kyc_rejected_at' => now(),
            'kyc_rejected_by' => $request->user()->id,
        ]);

        // Log audit event
        $this->auditLogService->logRejection(
            $member,
            'KYC',
            $request->reason,
            Auth::user(),
            [
                'member_id' => $member->id,
                'member_name' => $member->first_name . ' ' . $member->last_name,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logUserAction(
            'reject',
            'KYC rejected for member: ' . $member->first_name . ' ' . $member->last_name . '. Reason: ' . $request->reason,
            $request->user(),
            'kyc',
            [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
                'reason' => $request->reason,
            ]
        );

        // Notify the member about KYC rejection
        if ($member->user) {
            $this->notificationService->notifyKycRejected($member->user, $request->reason);
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC rejected successfully'
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $member = Member::findOrFail($id);
        if ($member->status === 'active') {
            return response()->json([
                'message' => 'Cannot delete active member'
            ], 400);
        }

        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Member deleted successfully'
        ]);
    }

    /**
     * Get member statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $stats = [
            'total_members' => Member::count(),
            'active_members' => Member::where('status', 'active')->count(),
            'inactive_members' => Member::where('status', 'inactive')->count(),
            'suspended_members' => Member::where('status', 'suspended')->count(),
            'kyc_verified' => Member::where('kyc_status', 'verified')->count(),
            'kyc_pending' => Member::whereIn('kyc_status', ['pending', 'submitted'])->count(),
            'kyc_rejected' => Member::where('kyc_status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
}
