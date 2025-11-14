<?php

namespace App\Http\Controllers\Api\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investments\InvestmentWithdrawalRequest;
use App\Models\Tenant\Investment;
use App\Models\Tenant\InvestmentReturn;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvestmentWithdrawalController extends Controller
{
    public function withdraw(InvestmentWithdrawalRequest $request, Investment $investment): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $member = $user->member;

            if (!$member || $investment->member_id !== $member->id) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($investment->status !== 'active') {
                return response()->json([
                    'message' => 'Investment is not active'
                ], 400);
            }

            // Check if investment has matured
            if (!$this->isInvestmentMatured($investment)) {
                return response()->json([
                    'message' => 'Investment has not matured yet',
                    'maturity_date' => $investment->maturity_date,
                ], 400);
            }

            // Calculate withdrawal amount
            $withdrawalAmount = $this->calculateWithdrawalAmount($investment, $request->withdrawal_type);

            if ($withdrawalAmount <= 0) {
                return response()->json([
                    'message' => 'No funds available for withdrawal'
                ], 400);
            }

            // Check if partial withdrawal is allowed
            if ($request->withdrawal_type === 'partial' && $request->amount > $withdrawalAmount) {
                return response()->json([
                    'message' => 'Withdrawal amount exceeds available balance'
                ], 400);
            }

            $actualAmount = $request->withdrawal_type === 'partial' ? $request->amount : $withdrawalAmount;

            // Process withdrawal
            $withdrawalResult = $this->processWithdrawal($investment, $actualAmount, $request->withdrawal_type);

            if (!$withdrawalResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $withdrawalResult['message'],
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Investment withdrawal processed successfully',
                'withdrawal' => [
                    'investment_id' => $investment->id,
                    'amount' => $actualAmount,
                    'type' => $request->withdrawal_type,
                    'wallet_balance' => $withdrawalResult['wallet_balance'],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Investment withdrawal failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getWithdrawalHistory(Investment $investment): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || $investment->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $returns = $investment->returns()
            ->where('type', 'withdrawal')
            ->orderBy('return_date', 'desc')
            ->paginate(15);

        return response()->json([
            'withdrawals' => $returns,
            'pagination' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ]
        ]);
    }

    public function getWithdrawalOptions(Investment $investment): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || $investment->member_id !== $member->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $isMatured = $this->isInvestmentMatured($investment);
        $withdrawalAmount = $this->calculateWithdrawalAmount($investment, 'full');
        $totalReturns = $investment->returns()->where('type', 'withdrawal')->sum('amount');

        $options = [
            'is_matured' => $isMatured,
            'maturity_date' => $investment->maturity_date,
            'total_invested' => $investment->amount,
            'total_returns' => $totalReturns,
            'available_for_withdrawal' => $withdrawalAmount,
            'withdrawal_types' => [
                'full' => [
                    'available' => $isMatured,
                    'amount' => $withdrawalAmount,
                    'description' => 'Withdraw all available funds',
                ],
                'partial' => [
                    'available' => $isMatured && $withdrawalAmount > 0,
                    'min_amount' => 1000,
                    'max_amount' => $withdrawalAmount,
                    'description' => 'Withdraw a specific amount',
                ],
            ],
        ];

        return response()->json([
            'investment' => [
                'id' => $investment->id,
                'amount' => $investment->amount,
                'status' => $investment->status,
                'maturity_date' => $investment->maturity_date,
            ],
            'withdrawal_options' => $options,
        ]);
    }

    private function isInvestmentMatured(Investment $investment): bool
    {
        return now()->gte($investment->maturity_date);
    }

    private function calculateWithdrawalAmount(Investment $investment, string $type): float
    {
        $totalInvested = $investment->amount;
        $totalWithdrawn = $investment->returns()
            ->where('type', 'withdrawal')
            ->sum('amount');

        $availableAmount = $totalInvested - $totalWithdrawn;

        if ($type === 'partial') {
            return $availableAmount;
        }

        // For full withdrawal, include any accrued returns
        $accruedReturns = $this->calculateAccruedReturns($investment);
        return $availableAmount + $accruedReturns;
    }

    private function calculateAccruedReturns(Investment $investment): float
    {
        // This would typically calculate returns based on the investment plan
        // For now, we'll use a simple calculation
        $monthsInvested = $investment->created_at->diffInMonths(now());
        $monthlyReturn = $investment->amount * ($investment->return_rate / 100) / 12;
        
        return $monthlyReturn * $monthsInvested;
    }

    private function processWithdrawal(Investment $investment, float $amount, string $type): array
    {
        try {
            // Get member's wallet
            $member = $investment->member;
            $wallet = $member->wallet;

            if (!$wallet) {
                return [
                    'success' => false,
                    'message' => 'Wallet not found',
                ];
            }

            // Credit wallet
            $wallet->increment('balance', $amount);

            // Create wallet transaction
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'investment_withdrawal',
                'amount' => $amount,
                'balance_after' => $wallet->fresh()->balance,
                'reference' => 'WITHDRAW-' . time() . '-' . rand(1000, 9999),
                'description' => "Investment withdrawal from investment #{$investment->id}",
                'metadata' => [
                    'investment_id' => $investment->id,
                    'withdrawal_type' => $type,
                ],
            ]);

            // Create investment return record
            InvestmentReturn::create([
                'investment_id' => $investment->id,
                'amount' => $amount,
                'return_date' => now(),
                'status' => 'completed',
                'type' => 'withdrawal',
                'metadata' => [
                    'withdrawal_type' => $type,
                ],
            ]);

            // Update investment status if fully withdrawn
            if ($type === 'full') {
                $investment->update(['status' => 'withdrawn']);
            }

            return [
                'success' => true,
                'message' => 'Withdrawal processed successfully',
                'wallet_balance' => $wallet->fresh()->balance,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Withdrawal processing failed: ' . $e->getMessage(),
            ];
        }
    }
}
