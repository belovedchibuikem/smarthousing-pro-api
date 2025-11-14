<?php

namespace App\Http\Controllers\Api\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\LoanRequest;
use App\Http\Resources\Financial\LoanResource;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanApplication;
use App\Services\Communication\NotificationService;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected TenantAuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        
        $query = Loan::with(['member.user']);
        
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

        // Filter by loan type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by member_id
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }
        
        $loans = $query->paginate($request->get('per_page', 15));

        

        return response()->json([
            'loans' => LoanResource::collection($loans),
            'pagination' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ]
        ]);
    }

    public function store(LoanRequest $request): JsonResponse
    {
        $member = Auth::user()->member;
        
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $loan = Loan::create([
            'member_id' => $member->id,
            'amount' => $request->amount,
            'interest_rate' => $request->interest_rate,
            'duration_months' => $request->duration_months,
            'type' => $request->type,
            'purpose' => $request->purpose,
            'status' => 'pending',
            'application_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan application submitted successfully',
            'loan' => new LoanResource($loan->load('member.user'))
        ], 201);
    }

    public function show(Request $request, Loan $loan): JsonResponse
    {
        
        // Check if user can view this loan
        $user = $request->user();
        if ($user->role !== 'admin' && $loan->member->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $loan->load(['member.user', 'repayments']);

        return response()->json([
            'loan' => new LoanResource($loan)
        ]);
    }

    public function update(LoanRequest $request, Loan $loan): JsonResponse
    {
        // Only allow updates if loan is pending
        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update loan that is not pending'
            ], 400);
        }

        $loan->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Loan updated successfully',
            'loan' => new LoanResource($loan->load('member.user'))
        ]);
    }

    public function approve(Loan $loan): JsonResponse
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending loans can be approved'
            ], 400);
        }

        $oldValues = $loan->toArray();
        
        $loan->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        $loan->load('member.user');
        
        // Log audit event
        $this->auditLogService->logApproval(
            $loan,
            'Loan',
            Auth::user(),
            [
                'amount' => $loan->amount,
                'member_id' => $loan->member_id,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logModelAction(
            'approve',
            $loan,
            Auth::user(),
            [
                'amount' => $loan->amount,
                'member_id' => $loan->member_id,
                'loan_id' => $loan->id,
            ]
        );
        
        // Notify the member about loan approval
        if ($loan->member && $loan->member->user) {
            $this->notificationService->notifyLoanApproved(
                $loan->member->user,
                $loan->id,
                [
                    'amount' => $loan->amount,
                    'product' => $loan->type,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Loan approved successfully'
        ]);
    }

    public function reject(Loan $loan): JsonResponse
    {
        $request = request();
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending loans can be rejected'
            ], 400);
        }

        $loan->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ]);

        $loan->load('member.user');
        
        // Log audit event
        $this->auditLogService->logRejection(
            $loan,
            'Loan',
            $request->reason,
            Auth::user(),
            [
                'amount' => $loan->amount,
                'member_id' => $loan->member_id,
            ]
        );
        
        // Log activity event
        $this->activityLogService->logModelAction(
            'reject',
            $loan,
            Auth::user(),
            [
                'amount' => $loan->amount,
                'member_id' => $loan->member_id,
                'loan_id' => $loan->id,
                'reason' => $request->reason,
            ]
        );
        
        // Notify the member about loan rejection
        if ($loan->member && $loan->member->user) {
            $this->notificationService->notifyLoanRejected(
                $loan->member->user,
                $loan->id,
                $request->reason
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Loan rejected successfully'
        ]);
    }

    public function destroy(Loan $loan): JsonResponse
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete loan that is not pending'
            ], 400);
        }

        $loan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Loan deleted successfully'
        ]);
    }
}
