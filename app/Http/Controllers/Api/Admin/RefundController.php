<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contribution;
use App\Models\Tenant\EquityTransaction;
use App\Models\Tenant\InvestmentReturn;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Member;
use App\Models\Tenant\Refund;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RefundController extends Controller
{
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
            ],
            'loans' => [
                'count' => $loanData->count(),
                'outstanding_total' => $loanOutstanding,
                'items' => $loanData,
            ],
        ];
    }
}

