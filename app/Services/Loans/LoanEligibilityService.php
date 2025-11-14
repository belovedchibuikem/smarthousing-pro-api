<?php

namespace App\Services\Loans;

use App\Models\Tenant\Member;
use App\Models\Tenant\LoanProduct;
use App\Models\Tenant\Loan;

class LoanEligibilityService
{
    public function checkEligibility(Member $member, LoanProduct $product, float $amount, int $tenureMonths): array
    {
        $reasons = [];

        // Check if member has active KYC
        if ($member->kyc_status !== 'verified') {
            $reasons[] = 'KYC verification required';
        }

        // Check if member is active
        if ($member->status !== 'active') {
            $reasons[] = 'Member account is not active';
        }

        // Check amount limits
        if ($amount < $product->min_amount) {
            $reasons[] = "Minimum loan amount is ₦" . number_format($product->min_amount);
        }

        if ($amount > $product->max_amount) {
            $reasons[] = "Maximum loan amount is ₦" . number_format($product->max_amount);
        }

        // Check tenure limits
        if ($tenureMonths < $product->min_tenure_months) {
            $reasons[] = "Minimum tenure is {$product->min_tenure_months} months";
        }

        if ($tenureMonths > $product->max_tenure_months) {
            $reasons[] = "Maximum tenure is {$product->max_tenure_months} months";
        }

        // Check for existing active loans
        $activeLoans = Loan::where('member_id', $member->id)
            ->whereIn('status', ['approved', 'active'])
            ->count();

        if ($activeLoans > 0) {
            $reasons[] = 'Member has existing active loans';
        }

        // Check membership duration (if required)
        if (isset($product->eligibility_criteria['min_membership_months'])) {
            $membershipMonths = $member->created_at->diffInMonths(now());
            if ($membershipMonths < $product->eligibility_criteria['min_membership_months']) {
                $reasons[] = "Minimum membership duration of {$product->eligibility_criteria['min_membership_months']} months required";
            }
        }

        // Check contribution history (if required)
        if (isset($product->eligibility_criteria['min_contributions'])) {
            $totalContributions = $member->contributions()
                ->where('status', 'approved')
                ->sum('amount');
            
            if ($totalContributions < $product->eligibility_criteria['min_contributions']) {
                $reasons[] = "Minimum contribution amount of ₦" . number_format($product->eligibility_criteria['min_contributions']) . " required";
            }
        }

        return [
            'eligible' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    public function calculateAffordability(Member $member, float $amount, int $tenureMonths, float $interestRate): array
    {
        // Calculate monthly payment
        $monthlyPayment = $amount * (1 + ($interestRate / 100)) / $tenureMonths;
        
        // Get member's estimated monthly income (this would come from member profile)
        $monthlyIncome = $member->estimated_monthly_income ?? 0;
        
        // Calculate debt-to-income ratio
        $debtToIncomeRatio = $monthlyIncome > 0 ? ($monthlyPayment / $monthlyIncome) * 100 : 0;
        
        // Check if affordable (typically should be less than 40% of income)
        $isAffordable = $debtToIncomeRatio <= 40;
        
        return [
            'monthly_payment' => $monthlyPayment,
            'monthly_income' => $monthlyIncome,
            'debt_to_income_ratio' => $debtToIncomeRatio,
            'is_affordable' => $isAffordable,
            'recommendation' => $isAffordable ? 'Approved' : 'Review required - high debt-to-income ratio',
        ];
    }
}
