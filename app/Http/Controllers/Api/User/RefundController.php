<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Member;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RefundController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get user's refund requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $query = Refund::where('member_id', $member->id)
            ->with(['requestedBy', 'approvedBy', 'rejectedBy', 'processedBy'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by request type
        if ($request->has('request_type') && $request->request_type !== 'all') {
            $query->where('request_type', $request->request_type);
        }

        $refunds = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'refunds' => $refunds->items(),
            'pagination' => [
                'current_page' => $refunds->currentPage(),
                'last_page' => $refunds->lastPage(),
                'per_page' => $refunds->perPage(),
                'total' => $refunds->total(),
            ]
        ]);
    }

    /**
     * Get refund statistics for user
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $stats = [
            'total_requests' => Refund::where('member_id', $member->id)->count(),
            'pending' => Refund::where('member_id', $member->id)->where('status', 'pending')->count(),
            'approved' => Refund::where('member_id', $member->id)->where('status', 'approved')->count(),
            'rejected' => Refund::where('member_id', $member->id)->where('status', 'rejected')->count(),
            'processing' => Refund::where('member_id', $member->id)->where('status', 'processing')->count(),
            'completed' => Refund::where('member_id', $member->id)->where('status', 'completed')->count(),
            'total_refunded' => Refund::where('member_id', $member->id)
                ->whereIn('status', ['completed', 'processing'])
                ->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Create a new refund request (ticket)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'request_type' => 'required|in:refund,stoppage_of_deduction,building_plan,tdp,change_of_ownership,other',
            'source' => 'required_if:request_type,refund|nullable|in:wallet,contribution,investment_return,equity_wallet',
            'amount' => 'required_if:request_type,refund|nullable|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $refundData = [
                'member_id' => $member->id,
                'request_type' => $request->request_type,
                'status' => 'pending',
                'reason' => $request->reason,
                'message' => $request->message,
                'requested_by' => $user->id,
                'requested_at' => now(),
            ];

            // Only add source and amount for refund requests
            if ($request->request_type === 'refund') {
                $refundData['source'] = $request->source;
                $refundData['amount'] = $request->amount ?? 0;
            }

            $refund = Refund::create($refundData);

            DB::commit();

            // Notify admins about new refund request
            $memberName = trim($member->first_name . ' ' . $member->last_name);
            $requestTypeLabels = [
                'refund' => 'Refund Request',
                'stoppage_of_deduction' => 'Stoppage of Deduction Request',
                'building_plan' => 'Building Plan Request',
                'tdp' => 'TDP Request',
                'change_of_ownership' => 'Change of Ownership Request',
                'other' => 'Other Request',
            ];

            $this->notificationService->notifyAdmins(
                'info',
                'New ' . ($requestTypeLabels[$request->request_type] ?? 'Request'),
                "A new {$requestTypeLabels[$request->request_type]} has been submitted by {$memberName}",
                [
                    'refund_id' => $refund->id,
                    'ticket_number' => $refund->ticket_number,
                    'member_id' => $member->id,
                    'member_name' => $memberName,
                    'request_type' => $request->request_type,
                    'amount' => $request->amount ?? 0,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund request submitted successfully',
                'refund' => $refund->load(['requestedBy', 'member.user'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit refund request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific refund request
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $refund = Refund::where('id', $id)
            ->where('member_id', $member->id)
            ->with(['requestedBy', 'approvedBy', 'rejectedBy', 'processedBy', 'member.user'])
            ->first();

        if (!$refund) {
            return response()->json([
                'message' => 'Refund request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'refund' => $refund
        ]);
    }
}
