<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\EquityContribution;
use App\Models\Tenant\EquityTransaction;
use App\Models\Tenant\InvestmentReturn;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Member;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
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
    public function summary(Request $request, String $memberId): JsonResponse
    {
        $user = $request->user();
        $member = Member::findOrFail($memberId);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $member->loadMissing(['user', 'equityWalletBalance']);

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'member_number' => $member->member_number ?? $member->staff_id ?? $member->user?->email,
                'name' => trim(($member->user->first_name ?? '') . ' ' . ($member->user->last_name ?? '')) ?: ($member->user->name ?? 'Member'),
                'staff_id' => $member->staff_id,
            ],
            'summary' => $this->buildSummary($member),
        ]);
    }

    public function refundMember(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|uuid|exists:members,id',
            'source' => 'required|in:wallet,contribution,investment_return,equity_wallet',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string',
            'auto_approve' => 'boolean',
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
            $member = Member::with(['user.wallet', 'equityWalletBalance'])->findOrFail($request->member_id);

            $summary = $this->buildSummary($member);
            $source = $request->source;
            $amount = (float) $request->amount;
            $reference = $request->input('reference') ?: 'REF-' . strtoupper(Str::random(10));

            $available = match ($source) {
                'wallet' => (float) ($summary['wallet']['balance'] ?? 0),
                'contribution' => (float) ($summary['contribution']['available'] ?? 0),
                'investment_return' => (float) ($summary['investment_returns']['available'] ?? 0),
                'equity_wallet' => (float) ($summary['equity_wallet']['balance'] ?? 0),
                default => 0,
            };

            if ($amount > $available) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance for the selected source',
                ], 422);
            }

            $walletTransaction = null;
            $equityTransaction = null;

            if ($source === 'wallet') {
                $wallet = $member->user->wallet ?? Wallet::create(['user_id' => $member->user_id, 'balance' => 0]);
                if ($wallet->balance < $amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient wallet balance',
                    ], 400);
                }

                $wallet->decrement('balance', $amount);

                $walletTransaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'status' => 'completed',
                    'description' => "Refund payout ({$request->reason})",
                    'payment_reference' => $reference,
                    'metadata' => [
                        'source' => $source,
                        'reason' => $request->reason,
                        'notes' => $request->notes,
                        'processed_by' => $user->id,
                        'balance_after' => (float) $wallet->fresh()->balance,
                    ],
                ]);
            }

            if ($source === 'equity_wallet') {
                $equityWallet = $member->equityWalletBalance;
                if (!$equityWallet || $equityWallet->balance < $amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient equity wallet balance',
                    ], 400);
                }

                $balanceBefore = (float) $equityWallet->balance;
                $equityWallet->decrement('balance', $amount);
                $equityWallet->increment('total_used', $amount);
                $equityWallet->last_updated_at = now();
                $equityWallet->save();

                $equityTransaction = EquityTransaction::create([
                    'member_id' => $member->id,
                    'equity_wallet_balance_id' => $equityWallet->id,
                    'type' => 'refund',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $equityWallet->fresh()->balance,
                    'reference' => $reference,
                    'reference_type' => 'refund',
                    'description' => "Refund processed from equity wallet ({$request->reason})",
                    'notes' => $request->notes,
                    'metadata' => [
                        'processed_by' => $user->id,
                    ],
                ]);
            }

            $refund = Refund::create([
                'member_id' => $member->id,
                'source' => $source,
                'amount' => $amount,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'processed_by' => $user->id,
                'reference' => $reference,
                'metadata' => array_filter([
                    'wallet_transaction_id' => $walletTransaction?->id,
                    'equity_transaction_id' => $equityTransaction?->id,
                    'auto_approved' => $request->boolean('auto_approve', true),
                ]),
            ]);

            DB::commit();

            $member->load(['user.wallet', 'equityWalletBalance']);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => [
                    'refund' => $refund,
                    'summary' => $this->buildSummary($member),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all refund requests (tickets)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $query = Refund::with(['member.user', 'requestedBy', 'approvedBy', 'rejectedBy', 'processedBy']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by request type
        if ($request->has('request_type') && $request->request_type !== 'all') {
            $query->where('request_type', $request->request_type);
        }

        // Search by ticket number, member name, or reason
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('member.user', function($q2) use ($search) {
                      $q2->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $refunds = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

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
     * Get refund statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $stats = [
            'total_requests' => Refund::count(),
            'pending' => Refund::where('status', 'pending')->count(),
            'approved' => Refund::where('status', 'approved')->count(),
            'rejected' => Refund::where('status', 'rejected')->count(),
            'processing' => Refund::where('status', 'processing')->count(),
            'completed' => Refund::where('status', 'completed')->count(),
            'total_refunded' => Refund::whereIn('status', ['completed', 'processing'])->sum('amount'),
            'by_type' => [
                'refund' => Refund::where('request_type', 'refund')->count(),
                'stoppage_of_deduction' => Refund::where('request_type', 'stoppage_of_deduction')->count(),
                'building_plan' => Refund::where('request_type', 'building_plan')->count(),
                'tdp' => Refund::where('request_type', 'tdp')->count(),
                'change_of_ownership' => Refund::where('request_type', 'change_of_ownership')->count(),
                'other' => Refund::where('request_type', 'other')->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Get a specific refund request
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $refund = Refund::with(['member.user.wallet.transactions', 'requestedBy', 'approvedBy', 'rejectedBy', 'processedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'refund' => $refund
        ]);
    }

    /**
     * Approve a refund request
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'admin_response' => 'nullable|string|max:2000',
            'process_immediately' => 'boolean', // If true, process the refund immediately
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $refund = Refund::with(['member.user'])->findOrFail($id);

        if ($refund->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be approved'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $refund->update([
                'status' => $request->boolean('process_immediately') ? 'processing' : 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'admin_response' => $request->admin_response,
            ]);

            // If process_immediately is true, process the refund
            if ($request->boolean('process_immediately') && $refund->request_type === 'refund' && $refund->source && $refund->amount > 0) {
                $this->processRefund($refund, $user);
            }

            DB::commit();

            $refund->load(['member.user', 'approvedBy']);

            // Notify the member about approval
            if ($refund->member && $refund->member->user) {
                $requestTypeLabels = [
                    'refund' => 'Refund Request',
                    'stoppage_of_deduction' => 'Stoppage of Deduction Request',
                    'building_plan' => 'Building Plan Request',
                    'tdp' => 'TDP Request',
                    'change_of_ownership' => 'Change of Ownership Request',
                    'other' => 'Request',
                ];

                $this->notificationService->sendNotificationToUsers(
                    [$refund->member->user->id],
                    'success',
                    ($requestTypeLabels[$refund->request_type] ?? 'Request') . ' Approved',
                    'Your ' . strtolower($requestTypeLabels[$refund->request_type] ?? 'request') . ' (Ticket: ' . $refund->ticket_number . ') has been approved.' . ($request->admin_response ? ' Response: ' . $request->admin_response : ''),
                    [
                        'refund_id' => $refund->id,
                        'ticket_number' => $refund->ticket_number,
                        'request_type' => $refund->request_type,
                        'admin_response' => $request->admin_response,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund request approved successfully',
                'refund' => $refund->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve refund request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a refund request
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

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

        $refund = Refund::with(['member.user'])->findOrFail($id);

        if ($refund->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be rejected'
            ], 400);
        }

        $refund->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        $refund->load(['member.user', 'rejectedBy']);

        // Notify the member about rejection
        if ($refund->member && $refund->member->user) {
            $requestTypeLabels = [
                'refund' => 'Refund Request',
                'stoppage_of_deduction' => 'Stoppage of Deduction Request',
                'building_plan' => 'Building Plan Request',
                'tdp' => 'TDP Request',
                'change_of_ownership' => 'Change of Ownership Request',
                'other' => 'Request',
            ];

            $this->notificationService->sendNotificationToUsers(
                [$refund->member->user->id],
                'warning',
                ($requestTypeLabels[$refund->request_type] ?? 'Request') . ' Rejected',
                'Your ' . strtolower($requestTypeLabels[$refund->request_type] ?? 'request') . ' (Ticket: ' . $refund->ticket_number . ') has been rejected. Reason: ' . $request->rejection_reason,
                [
                    'refund_id' => $refund->id,
                    'ticket_number' => $refund->ticket_number,
                    'request_type' => $refund->request_type,
                    'rejection_reason' => $request->rejection_reason,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Refund request rejected successfully',
            'refund' => $refund
        ]);
    }

    /**
     * Process a refund (internal method)
     */
    protected function processRefund(Refund $refund, $user): void
    {
        $member = $refund->member;
        $member->loadMissing(['user.wallet', 'equityWalletBalance']);

        $summary = $this->buildSummary($member);
        $source = $refund->source;
        $amount = (float) $refund->amount;
        $reference = $refund->reference ?: 'REF-' . strtoupper(Str::random(10));

        $available = match ($source) {
            'wallet' => (float) ($summary['wallet']['balance'] ?? 0),
            'contribution' => (float) ($summary['contribution']['available'] ?? 0),
            'investment_return' => (float) ($summary['investment_returns']['available'] ?? 0),
            'equity_wallet' => (float) ($summary['equity_wallet']['balance'] ?? 0),
            default => 0,
        };

        if ($amount > $available) {
            throw new \Exception('Insufficient balance for the selected source');
        }

        $walletTransaction = null;
        $equityTransaction = null;

        if ($source === 'wallet') {
            $wallet = $member->user->wallet ?? Wallet::create(['user_id' => $member->user_id, 'balance' => 0]);
            if ($wallet->balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            $wallet->decrement('balance', $amount);

            $walletTransaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'status' => 'completed',
                'description' => "Refund payout ({$refund->reason})",
                'payment_reference' => $reference,
                'metadata' => [
                    'source' => $source,
                    'reason' => $refund->reason,
                    'notes' => $refund->notes,
                    'processed_by' => $user->id,
                    'balance_after' => (float) $wallet->fresh()->balance,
                ],
            ]);
        }

        if ($source === 'equity_wallet') {
            $equityWallet = $member->equityWalletBalance;
            if (!$equityWallet || $equityWallet->balance < $amount) {
                throw new \Exception('Insufficient equity wallet balance');
            }

            $balanceBefore = (float) $equityWallet->balance;
            $equityWallet->decrement('balance', $amount);
            $equityWallet->increment('total_used', $amount);
            $equityWallet->last_updated_at = now();
            $equityWallet->save();

            $equityTransaction = EquityTransaction::create([
                'member_id' => $member->id,
                'equity_wallet_balance_id' => $equityWallet->id,
                'type' => 'refund',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $equityWallet->fresh()->balance,
                'reference' => $reference,
                'reference_type' => 'refund',
                'description' => "Refund processed from equity wallet ({$refund->reason})",
                'notes' => $refund->notes,
                'metadata' => [
                    'processed_by' => $user->id,
                ],
            ]);
        }

        $refund->update([
            'status' => 'completed',
            'processed_by' => $user->id,
            'processed_at' => now(),
            'completed_at' => now(),
            'reference' => $reference,
            'metadata' => array_merge($refund->metadata ?? [], [
                'wallet_transaction_id' => $walletTransaction?->id,
                'equity_transaction_id' => $equityTransaction?->id,
            ]),
        ]);
    }

    protected function buildSummary(Member $member): array
    {
        $member->loadMissing(['user.wallet', 'equityWalletBalance']);

        $walletBalance = (float) ($member->user->wallet->balance ?? 0);

        $totalContributions = (float) Contribution::where('member_id', $member->id)
            ->where('status', 'approved')
            ->sum('amount');

        $contributionRefunds = (float) Refund::where('member_id', $member->id)
            ->where('source', 'contribution')
            ->sum('amount');

        $contributionAvailable = max(0, $totalContributions - $contributionRefunds);

        $investmentReturnsQuery = InvestmentReturn::whereHas('investment', function ($query) use ($member) {
            $query->where('member_id', $member->id);
        });

        $investmentReturnsTotal = (float) (clone $investmentReturnsQuery)
            ->whereIn('status', ['paid', 'completed'])
            ->sum('amount');

        $investmentRefunds = (float) Refund::where('member_id', $member->id)
            ->where('source', 'investment_return')
            ->sum('amount');

        $investmentAvailable = max(0, $investmentReturnsTotal - $investmentRefunds);

        // Calculate equity contributions and refunds
        $totalEquityContributions = (float) EquityContribution::where('member_id', $member->id)
            ->where('status', 'approved')
            ->sum('amount');

        $equityRefunds = (float) Refund::where('member_id', $member->id)
            ->where('source', 'equity_wallet')
            ->sum('amount');

        $equityAvailable = max(0, $totalEquityContributions - $equityRefunds);

        $equityWallet = $member->equityWalletBalance;
        $equityBalance = (float) ($equityWallet->balance ?? 0);

        $loans = Loan::with('repayments')
            ->where('member_id', $member->id)
            ->whereIn('status', ['approved', 'disbursed', 'completed'])
            ->get();

        $loanData = $loans->map(function (Loan $loan) {
            $totalRepaid = (float) $loan->repayments()->sum('amount');
            $totalAmount = (float) ($loan->total_amount ?? ($loan->amount + ($loan->amount * ($loan->interest_rate / 100))));
            $outstanding = max(0, $totalAmount - $totalRepaid);

            return [
                'id' => $loan->id,
                'status' => $loan->status,
                'principal' => (float) $loan->amount,
                'total_amount' => $totalAmount,
                'repaid' => $totalRepaid,
                'outstanding' => $outstanding,
            ];
        });

        $loanOutstanding = (float) $loanData->sum('outstanding');

        return [
            'wallet' => [
                'balance' => $walletBalance,
            ],
            'contribution' => [
                'total' => $totalContributions,
                'refunded' => $contributionRefunds,
                'available' => $contributionAvailable,
            ],
            'investment_returns' => [
                'total' => $investmentReturnsTotal,
                'refunded' => $investmentRefunds,
                'available' => $investmentAvailable,
            ],
            'equity_wallet' => [
                'balance' => $equityBalance,
                'total' => $totalEquityContributions,
                'refunded' => $equityRefunds,
                'available' => $equityAvailable,
            ],
            'loans' => [
                'count' => $loanData->count(),
                'outstanding_total' => $loanOutstanding,
                'items' => $loanData,
            ],
        ];
    }
}

