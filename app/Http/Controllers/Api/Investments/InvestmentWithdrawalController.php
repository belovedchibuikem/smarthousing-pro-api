<?php

namespace App\Http\Controllers\Api\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investments\InvestmentWithdrawalRequest;
use App\Models\Tenant\Investment;
use App\Models\Tenant\InvestmentReturn;
use App\Models\Tenant\InvestmentWithdrawalRequest as WithdrawalRequest;
use App\Models\Tenant\Wallet;
use App\Models\Tenant\WalletTransaction;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvestmentWithdrawalController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function withdraw(InvestmentWithdrawalRequest $request, Investment $investment): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $member = $user->member;

            if (!$member || $investment->member_id !== $member->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($investment->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Investment is not active'
                ], 400);
            }

            // Check if investment has matured (calculate maturity date from investment_date + duration_months)
            $maturityDate = $investment->investment_date->copy()->addMonths($investment->duration_months);
            if (now()->lt($maturityDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investment has not matured yet',
                    'maturity_date' => $maturityDate->toDateString(),
                ], 400);
            }

            // Calculate withdrawal amount
            $withdrawalAmount = $this->calculateWithdrawalAmount($investment, $request->withdrawal_type);

            if ($withdrawalAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No funds available for withdrawal'
                ], 400);
            }

            // Check if partial withdrawal is allowed
            $actualAmount = $request->withdrawal_type === 'partial' ? $request->amount : $withdrawalAmount;
            
            if ($request->withdrawal_type === 'partial' && $actualAmount > $withdrawalAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal amount exceeds available balance'
                ], 400);
            }

            // Check for existing pending withdrawal request
            $existingRequest = WithdrawalRequest::where('investment_id', $investment->id)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending withdrawal request for this investment'
                ], 400);
            }

            // Create withdrawal request instead of processing immediately
            $withdrawalRequest = WithdrawalRequest::create([
                'investment_id' => $investment->id,
                'member_id' => $member->id,
                'withdrawal_type' => $request->withdrawal_type,
                'amount' => $actualAmount,
                'status' => 'pending',
                'reason' => $request->reason ?? null,
                'requested_by' => $user->id,
                'requested_at' => now(),
            ]);

            DB::commit();

            // Notify admins about new withdrawal request
            $memberName = trim($member->first_name . ' ' . $member->last_name);
            $this->notificationService->notifyAdmins(
                'info',
                'New Investment Withdrawal Request',
                "A new withdrawal request of " . number_format($actualAmount, 2) . " has been submitted by {$memberName} for Investment #{$investment->id}.",
                [
                    'withdrawal_request_id' => $withdrawalRequest->id,
                    'investment_id' => $investment->id,
                    'member_id' => $member->id,
                    'amount' => $actualAmount,
                    'withdrawal_type' => $request->withdrawal_type,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully. It will be reviewed by an administrator.',
                'withdrawal_request' => [
                    'id' => $withdrawalRequest->id,
                    'investment_id' => $investment->id,
                    'amount' => $actualAmount,
                    'type' => $request->withdrawal_type,
                    'status' => 'pending',
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Investment withdrawal request failed',
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
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Get withdrawal requests for this investment
        $withdrawals = WithdrawalRequest::where('investment_id', $investment->id)
            ->with(['approvedBy', 'rejectedBy', 'processedBy'])
            ->orderBy('requested_at', 'desc')
            ->paginate(15);

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

    public function getWithdrawalOptions(Investment $investment): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (!$member || $investment->member_id !== $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $maturityDate = $investment->investment_date->copy()->addMonths($investment->duration_months);
        $isMatured = $this->isInvestmentMatured($investment);
        $withdrawalAmount = $this->calculateWithdrawalAmount($investment, 'full');
        
        // Get total withdrawn from completed withdrawal requests
        $totalWithdrawn = WithdrawalRequest::where('investment_id', $investment->id)
            ->whereIn('status', ['completed', 'processing'])
            ->sum('amount');

        $options = [
            'is_matured' => $isMatured,
            'maturity_date' => $maturityDate->toDateString(),
            'total_invested' => $investment->amount,
            'total_withdrawn' => $totalWithdrawn,
            'available_for_withdrawal' => $withdrawalAmount,
            'withdrawal_types' => [
                'full' => [
                    'available' => $isMatured && $withdrawalAmount > 0,
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
                'maturity_date' => $maturityDate->toDateString(),
            ],
            'withdrawal_options' => $options,
        ]);
    }

    private function isInvestmentMatured(Investment $investment): bool
    {
        $maturityDate = $investment->investment_date->copy()->addMonths($investment->duration_months);
        return now()->gte($maturityDate);
    }

    private function calculateWithdrawalAmount(Investment $investment, string $type): float
    {
        $totalInvested = $investment->amount;
        
        // Get total withdrawn from withdrawal requests that are completed
        $totalWithdrawn = WithdrawalRequest::where('investment_id', $investment->id)
            ->whereIn('status', ['completed', 'processing'])
            ->sum('amount');

        $availableAmount = $totalInvested - $totalWithdrawn;

        if ($type === 'partial') {
            return max(0, $availableAmount);
        }

        // For full withdrawal, include any accrued returns
        $accruedReturns = $this->calculateAccruedReturns($investment);
        return max(0, $availableAmount + $accruedReturns);
    }

    private function calculateAccruedReturns(Investment $investment): float
    {
        // Calculate returns based on investment duration and expected return rate
        $monthsInvested = $investment->investment_date->diffInMonths(now());
        $annualReturn = $investment->amount * ($investment->expected_return_rate / 100);
        $monthlyReturn = $annualReturn / 12;
        
        return max(0, $monthlyReturn * $monthsInvested);
    }

}
