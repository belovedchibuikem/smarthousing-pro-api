<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Investment;
use App\Models\Tenant\InvestmentReturn;
use App\Models\Tenant\InvestmentWithdrawalRequest;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvestmentWithdrawalRequestController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = InvestmentWithdrawalRequest::with([
            'investment',
            'member.user',
            'requestedBy',
            'approvedBy',
            'rejectedBy',
            'processedBy'
        ]);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by investment
        if ($request->has('investment_id')) {
            $query->where('investment_id', $request->investment_id);
        }

        // Filter by member
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('member', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('member_number', 'like', "%{$search}%");
            });
        }

        $withdrawals = $query->orderBy('requested_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'withdrawals' => $withdrawals->items(),
            'pagination' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ]
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = [
            'total_requests' => InvestmentWithdrawalRequest::count(),
            'pending' => InvestmentWithdrawalRequest::where('status', 'pending')->count(),
            'approved' => InvestmentWithdrawalRequest::where('status', 'approved')->count(),
            'rejected' => InvestmentWithdrawalRequest::where('status', 'rejected')->count(),
            'processing' => InvestmentWithdrawalRequest::where('status', 'processing')->count(),
            'completed' => InvestmentWithdrawalRequest::where('status', 'completed')->count(),
            'total_amount_pending' => InvestmentWithdrawalRequest::where('status', 'pending')->sum('amount'),
            'total_amount_completed' => InvestmentWithdrawalRequest::where('status', 'completed')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $withdrawal = InvestmentWithdrawalRequest::with([
            'investment',
            'member.user',
            'requestedBy',
            'approvedBy',
            'rejectedBy',
            'processedBy'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'withdrawal' => $withdrawal
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'admin_response' => 'nullable|string|max:2000',
            'process_immediately' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $withdrawalRequest = InvestmentWithdrawalRequest::with(['investment.member.user'])->findOrFail($id);

        if ($withdrawalRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be approved'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            
            $withdrawalRequest->update([
                'status' => $request->boolean('process_immediately') ? 'processing' : 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'admin_response' => $request->admin_response,
            ]);

            // If process immediately, process the withdrawal
            if ($request->boolean('process_immediately')) {
                $this->processWithdrawal($withdrawalRequest, $user);
            }

            DB::commit();

            // Notify member
            if ($withdrawalRequest->investment && $withdrawalRequest->investment->member && $withdrawalRequest->investment->member->user) {
                $memberName = trim($withdrawalRequest->investment->member->first_name . ' ' . $withdrawalRequest->investment->member->last_name);
                $this->notificationService->sendNotificationToUsers(
                    [$withdrawalRequest->investment->member->user->id],
                    'success',
                    'Investment Withdrawal Approved',
                    'Your withdrawal request of ₦' . number_format($withdrawalRequest->amount, 2) . ' has been approved.' . ($request->admin_response ? ' Response: ' . $request->admin_response : ''),
                    [
                        'withdrawal_request_id' => $withdrawalRequest->id,
                        'investment_id' => $withdrawalRequest->investment_id,
                        'amount' => $withdrawalRequest->amount,
                        'admin_response' => $request->admin_response,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request approved successfully',
                'withdrawal' => $withdrawalRequest->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve withdrawal request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $withdrawalRequest = InvestmentWithdrawalRequest::with(['investment.member.user'])->findOrFail($id);

        if ($withdrawalRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be rejected'
            ], 400);
        }

        $user = Auth::user();
        $withdrawalRequest->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Notify member
        if ($withdrawalRequest->investment && $withdrawalRequest->investment->member && $withdrawalRequest->investment->member->user) {
            $memberName = trim($withdrawalRequest->investment->member->first_name . ' ' . $withdrawalRequest->investment->member->last_name);
            $this->notificationService->sendNotificationToUsers(
                [$withdrawalRequest->investment->member->user->id],
                'warning',
                'Investment Withdrawal Rejected',
                'Your withdrawal request of ₦' . number_format($withdrawalRequest->amount, 2) . ' has been rejected. Reason: ' . $request->rejection_reason,
                [
                    'withdrawal_request_id' => $withdrawalRequest->id,
                    'investment_id' => $withdrawalRequest->investment_id,
                    'amount' => $withdrawalRequest->amount,
                    'rejection_reason' => $request->rejection_reason,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request rejected successfully',
            'withdrawal' => $withdrawalRequest->fresh()
        ]);
    }

    public function process(string $id): JsonResponse
    {
        $withdrawalRequest = InvestmentWithdrawalRequest::with(['investment.member.user'])->findOrFail($id);

        if (!in_array($withdrawalRequest->status, ['approved', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only approved or processing requests can be processed'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $this->processWithdrawal($withdrawalRequest, $user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal processed successfully',
                'withdrawal' => $withdrawalRequest->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process withdrawal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function processWithdrawal(InvestmentWithdrawalRequest $withdrawalRequest, $user): void
    {
        $investment = $withdrawalRequest->investment;
        $member = $investment->member;

        // Get or create wallet
        $wallet = $member->user->wallet ?? Wallet::create([
            'user_id' => $member->user->id,
            'balance' => 0
        ]);

        $amount = (float) $withdrawalRequest->amount;

        // Credit wallet
        $wallet->increment('balance', $amount);

        // Create wallet transaction
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $wallet->fresh()->balance,
            'reference' => 'INV-WITHDRAW-' . time() . '-' . rand(1000, 9999),
            'description' => "Investment withdrawal from investment #{$investment->id}",
            'status' => 'completed',
            'metadata' => [
                'investment_id' => $investment->id,
                'withdrawal_request_id' => $withdrawalRequest->id,
                'withdrawal_type' => $withdrawalRequest->withdrawal_type,
                'processed_by' => $user->id,
            ],
        ]);

        // Create investment return record
        InvestmentReturn::create([
            'investment_id' => $investment->id,
            'amount' => $amount,
            'return_date' => now(),
            'status' => 'paid',
            'metadata' => [
                'withdrawal_request_id' => $withdrawalRequest->id,
                'withdrawal_type' => $withdrawalRequest->withdrawal_type,
            ],
        ]);

        // Update withdrawal request
        $withdrawalRequest->update([
            'status' => 'completed',
            'processed_by' => $user->id,
            'processed_at' => now(),
            'completed_at' => now(),
        ]);

        // Update investment status if fully withdrawn
        if ($withdrawalRequest->withdrawal_type === 'full') {
            $investment->update(['status' => 'withdrawn']);
        }

        // Notify member
        if ($member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$member->user->id],
                'success',
                'Investment Withdrawal Completed',
                'Your withdrawal of ₦' . number_format($amount, 2) . ' has been processed and credited to your wallet.',
                [
                    'withdrawal_request_id' => $withdrawalRequest->id,
                    'investment_id' => $investment->id,
                    'amount' => $amount,
                    'wallet_balance' => $wallet->fresh()->balance,
                ]
            );
        }
    }
}
