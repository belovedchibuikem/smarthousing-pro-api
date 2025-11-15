<?php

namespace App\Http\Controllers\Api\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\ContributionRequest;
use App\Http\Resources\Financial\ContributionResource;
use App\Models\Tenant\Contribution;
use App\Services\Communication\NotificationService;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContributionController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TenantAuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Contribution::with(['member.user']);

        // Filter by user if not admin
        $user = $request->user();
        if ($user->role !== 'admin') {
            $query->whereHas('member', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by contribution type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by member_id
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        $contributions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'contributions' => ContributionResource::collection($contributions),
            'pagination' => [
                'current_page' => $contributions->currentPage(),
                'last_page' => $contributions->lastPage(),
                'per_page' => $contributions->perPage(),
                'total' => $contributions->total(),
            ]
        ]);
    }

    public function store(ContributionRequest $request): JsonResponse
    {
        $member = Auth::user()->member;
        
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $contribution = Contribution::create([
            'member_id' => $member->id,
            'amount' => $request->amount,
            'type' => $request->type,
            'frequency' => $request->frequency,
            'status' => 'pending',
            'contribution_date' => now(),
        ]);

        // Notify admins about new contribution
        $memberName = $member->first_name . ' ' . $member->last_name;
        $this->notificationService->notifyAdminsNewContribution(
            $contribution->id,
            $memberName,
            $request->amount
        );

        return response()->json([
            'success' => true,
            'message' => 'Contribution submitted successfully',
            'contribution' => new ContributionResource($contribution->load('member.user'))
        ], 201);
    }

    public function show(Request $request, Contribution $contribution): JsonResponse
    {
        // Check if user can view this contribution
        $user = $request->user();
        if ($user->role !== 'admin' && $contribution->member->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $contribution->load(['member.user', 'payments']);

        return response()->json([
            'contribution' => new ContributionResource($contribution)
        ]);
    }

    public function update(ContributionRequest $request, Contribution $contribution): JsonResponse
    {
        // Only allow updates if contribution is pending
        if ($contribution->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update contribution that is not pending'
            ], 400);
        }

        $contribution->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Contribution updated successfully',
            'contribution' => new ContributionResource($contribution->load('member.user'))
        ]);
    }

    public function approve(Contribution $contribution): JsonResponse
    {
        if ($contribution->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending contributions can be approved'
            ], 400);
        }

        $contribution->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        $contribution->load('member.user');
        
        // Log audit event
        $this->auditLogService->logApproval(
            $contribution,
            'Contribution',
            Auth::user(),
            [
                'amount' => $contribution->amount,
                'member_id' => $contribution->member_id,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logModelAction(
            'approve',
            $contribution,
            Auth::user(),
            [
                'amount' => $contribution->amount,
                'member_id' => $contribution->member_id,
                'contribution_id' => $contribution->id,
            ]
        );
        
        // Notify the member about contribution approval
        if ($contribution->member && $contribution->member->user) {
            $this->notificationService->notifyContributionReceived(
                $contribution->member->user,
                $contribution->id,
                ['amount' => $contribution->amount]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Contribution approved successfully'
        ]);
    }

    public function reject(Contribution $contribution): JsonResponse
    {
        $request = request();
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($contribution->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending contributions can be rejected'
            ], 400);
        }

        $contribution->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ]);

        $contribution->load('member.user');
        
        // Log audit event
        $this->auditLogService->logRejection(
            $contribution,
            'Contribution',
            $request->reason,
            Auth::user(),
            [
                'amount' => $contribution->amount,
                'member_id' => $contribution->member_id,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logModelAction(
            'reject',
            $contribution,
            Auth::user(),
            [
                'amount' => $contribution->amount,
                'member_id' => $contribution->member_id,
                'contribution_id' => $contribution->id,
                'reason' => $request->reason,
            ]
        );
        
        // Notify the member about contribution rejection
        if ($contribution->member && $contribution->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$contribution->member->user->id],
                'warning',
                'Contribution Rejected',
                'Your contribution of ₦' . number_format($contribution->amount, 2) . ' has been rejected. Reason: ' . $request->reason,
                [
                    'contribution_id' => $contribution->id,
                    'amount' => $contribution->amount,
                    'reason' => $request->reason,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Contribution rejected successfully'
        ]);
    }

    public function destroy(Contribution $contribution): JsonResponse
    {
        if ($contribution->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete contribution that is not pending'
            ], 400);
        }

        $contribution->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contribution deleted successfully'
        ]);
    }
}
