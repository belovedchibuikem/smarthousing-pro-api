<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mortgage;
use App\Models\Tenant\MortgageRepayment;
use App\Models\Tenant\InternalMortgagePlan;
use App\Models\Tenant\InternalMortgageRepayment;
use App\Models\Tenant\PropertyPaymentTransaction;
use App\Models\Tenant\PropertyPaymentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MortgageRepaymentController extends Controller
{
    /**
     * Record a mortgage repayment (admin)
     */
    public function repayMortgage(Request $request, string $mortgageId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'principal_paid' => 'required|numeric|min:0',
            'interest_paid' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'payment_method' => 'required|in:monthly,yearly,bi-yearly',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure principal_paid + interest_paid = amount
        $total = $request->principal_paid + $request->interest_paid;
        if (abs($total - $request->amount) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Principal paid + Interest paid must equal total amount'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $mortgage = Mortgage::findOrFail($mortgageId);

            if ($mortgage->status !== 'approved' && $mortgage->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Mortgage is not approved or active'
                ], 400);
            }

            // Check if schedule has been approved by member
            if (!$mortgage->schedule_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayment schedule must be approved by the member before repayments can be processed'
                ], 400);
            }

            $remainingPrincipal = $mortgage->getRemainingPrincipal();
            if ($request->principal_paid > $remainingPrincipal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Principal paid exceeds remaining principal balance'
                ], 400);
            }

            $reference = 'MORT-REP-' . strtoupper(Str::random(10));

            $repayment = MortgageRepayment::create([
                'mortgage_id' => $mortgage->id,
                'property_id' => $mortgage->property_id,
                'amount' => $request->amount,
                'principal_paid' => $request->principal_paid,
                'interest_paid' => $request->interest_paid,
                'due_date' => $request->due_date,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $request->payment_method,
                'reference' => $reference,
                'recorded_by' => $user->id,
                'notes' => $request->notes,
            ]);

            // Update mortgage status if fully repaid
            if ($mortgage->isFullyRepaid()) {
                $mortgage->update(['status' => 'completed']);
            } else {
                $mortgage->update(['status' => 'active']);
            }

            // Create PropertyPaymentTransaction if mortgage is tied to property
            if ($mortgage->property_id) {
                $this->createPropertyTransaction(
                    $mortgage->property_id,
                    $mortgage->member_id,
                    $request->principal_paid, // Only principal counts toward property progress
                    $reference,
                    'mortgage',
                    $mortgage->id
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mortgage repayment recorded successfully',
                'data' => [
                    'repayment' => $repayment->load('mortgage', 'property'),
                    'mortgage' => [
                        'total_principal_repaid' => $mortgage->getTotalPrincipalRepaid(),
                        'total_interest_paid' => $mortgage->getTotalInterestPaid(),
                        'remaining_principal' => $mortgage->getRemainingPrincipal(),
                        'is_fully_repaid' => $mortgage->isFullyRepaid(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record repayment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record an internal mortgage (cooperative deduction) repayment (admin)
     */
    public function repayInternalMortgage(Request $request, string $planId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'principal_paid' => 'required|numeric|min:0',
            'interest_paid' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'payment_method' => 'required|in:monthly,yearly,bi-yearly',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure principal_paid + interest_paid = amount
        $total = $request->principal_paid + $request->interest_paid;
        if (abs($total - $request->amount) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Principal paid + Interest paid must equal total amount'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $plan = InternalMortgagePlan::findOrFail($planId);

            if ($plan->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Internal mortgage plan is not active'
                ], 400);
            }

            // Check if schedule has been approved by member
            if (!$plan->schedule_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayment schedule must be approved by the member before repayments can be processed'
                ], 400);
            }

            $remainingPrincipal = $plan->getRemainingPrincipal();
            if ($request->principal_paid > $remainingPrincipal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Principal paid exceeds remaining principal balance'
                ], 400);
            }

            $reference = 'INT-MORT-REP-' . strtoupper(Str::random(10));

            $repayment = InternalMortgageRepayment::create([
                'internal_mortgage_plan_id' => $plan->id,
                'property_id' => $plan->property_id,
                'amount' => $request->amount,
                'principal_paid' => $request->principal_paid,
                'interest_paid' => $request->interest_paid,
                'due_date' => $request->due_date,
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $request->payment_method,
                'frequency' => $plan->frequency,
                'reference' => $reference,
                'recorded_by' => $user->id,
                'notes' => $request->notes,
            ]);

            // Update plan status if fully repaid
            if ($plan->isFullyRepaid()) {
                $plan->update(['status' => 'completed']);
            }

            // Create PropertyPaymentTransaction if plan is tied to property
            if ($plan->property_id) {
                $this->createPropertyTransaction(
                    $plan->property_id,
                    $plan->member_id,
                    $request->principal_paid, // Only principal counts toward property progress
                    $reference,
                    'cooperative',
                    null,
                    $plan->id
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Internal mortgage repayment recorded successfully',
                'data' => [
                    'repayment' => $repayment->load('internalMortgagePlan', 'property'),
                    'plan' => [
                        'total_principal_repaid' => $plan->getTotalPrincipalRepaid(),
                        'total_interest_paid' => $plan->getTotalInterestPaid(),
                        'remaining_principal' => $plan->getRemainingPrincipal(),
                        'is_fully_repaid' => $plan->isFullyRepaid(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record repayment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create PropertyPaymentTransaction record
     */
    private function createPropertyTransaction(
        string $propertyId,
        string $memberId,
        float $amount,
        string $reference,
        string $source,
        ?string $mortgageId = null,
        ?string $mortgagePlanId = null
    ): void {
        // Find the property payment plan for this property and member
        $plan = PropertyPaymentPlan::where('property_id', $propertyId)
            ->whereHas('interest', function ($query) use ($memberId) {
                $query->where('member_id', $memberId);
            })
            ->first();

        PropertyPaymentTransaction::create([
            'property_id' => $propertyId,
            'member_id' => $memberId,
            'plan_id' => $plan?->id,
            'mortgage_plan_id' => $mortgagePlanId,
            'source' => $source,
            'amount' => $amount, // Only principal amount for property progress
            'direction' => 'credit',
            'reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => [
                'mortgage_id' => $mortgageId,
                'mortgage_plan_id' => $mortgagePlanId,
                'recorded_by_admin' => true,
            ],
        ]);
    }

    /**
     * Get next payment details for a mortgage (automated calculation)
     */
    public function getNextPayment(Request $request, string $mortgageId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $mortgage = Mortgage::findOrFail($mortgageId);

            if ($mortgage->status !== 'approved' && $mortgage->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Mortgage is not approved or active'
                ], 400);
            }

            // Check if schedule has been approved by member
            if (!$mortgage->schedule_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayment schedule must be approved by the member before repayments can be processed'
                ], 400);
            }

            $loanAmount = $mortgage->loan_amount;
            $interestRate = $mortgage->interest_rate / 100;
            $tenureMonths = $mortgage->tenure_years * 12;
            $monthlyRate = $interestRate / 12;
            $monthlyPayment = $mortgage->monthly_payment;
            $remainingPrincipal = $mortgage->getRemainingPrincipal();

            if ($remainingPrincipal <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mortgage is fully repaid'
                ], 400);
            }

            // Calculate next payment
            $interestPortion = $remainingPrincipal * $monthlyRate;
            $principalPortion = min($monthlyPayment - $interestPortion, $remainingPrincipal);
            
            // Adjust if remaining principal is less than calculated principal
            if ($remainingPrincipal < $principalPortion) {
                $principalPortion = $remainingPrincipal;
                $interestPortion = $remainingPrincipal * $monthlyRate;
            }

            $totalAmount = $principalPortion + $interestPortion;

            // Find the next due date (first unpaid installment)
            $startDate = $mortgage->application_date ?? now();
            $totalPaidPayments = $mortgage->repayments()->where('status', 'paid')->count();
            $nextDueDate = $startDate->copy()->addMonths($totalPaidPayments + 1);

            return response()->json([
                'success' => true,
                'data' => [
                    'principal_paid' => round($principalPortion, 2),
                    'interest_paid' => round($interestPortion, 2),
                    'total_amount' => round($totalAmount, 2),
                    'due_date' => $nextDueDate->format('Y-m-d'),
                    'remaining_principal' => round($remainingPrincipal - $principalPortion, 2),
                    'payment_method' => 'monthly', // Default to monthly
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate next payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next payment details for an internal mortgage (automated calculation)
     */
    public function getNextInternalPayment(Request $request, string $planId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $plan = InternalMortgagePlan::findOrFail($planId);

            if ($plan->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Internal mortgage plan is not active'
                ], 400);
            }

            // Check if schedule has been approved by member
            if (!$plan->schedule_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayment schedule must be approved by the member before repayments can be processed'
                ], 400);
            }

            $principal = $plan->principal;
            $interestRate = $plan->interest_rate / 100;
            $tenureMonths = $plan->tenure_months;
            $frequency = $plan->frequency;
            $remainingPrincipal = $plan->getRemainingPrincipal();

            if ($remainingPrincipal <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Internal mortgage plan is fully repaid'
                ], 400);
            }

            $frequencyMultiplier = match($frequency) {
                'monthly' => 1,
                'quarterly' => 3,
                'biannually' => 6,
                'annually' => 12,
                default => 1,
            };

            $paymentsPerYear = 12 / $frequencyMultiplier;
            $periodicRate = $interestRate / $paymentsPerYear;
            $numberOfPayments = $tenureMonths / $frequencyMultiplier;

            // Calculate periodic payment
            $factor = pow(1 + $periodicRate, $numberOfPayments);
            $periodicPayment = $principal * ($periodicRate * $factor) / ($factor - 1);

            // Calculate next payment
            $interestPortion = $remainingPrincipal * $periodicRate;
            $principalPortion = min($periodicPayment - $interestPortion, $remainingPrincipal);
            
            if ($remainingPrincipal < $principalPortion) {
                $principalPortion = $remainingPrincipal;
                $interestPortion = $remainingPrincipal * $periodicRate;
            }

            $totalAmount = $principalPortion + $interestPortion;

            // Find the next due date
            $startDate = $plan->starts_on ? \Carbon\Carbon::parse($plan->starts_on) : now();
            $totalPaidPayments = $plan->repayments()->where('status', 'paid')->count();
            $nextDueDate = $startDate->copy()->addMonths(($totalPaidPayments + 1) * $frequencyMultiplier);

            return response()->json([
                'success' => true,
                'data' => [
                    'principal_paid' => round($principalPortion, 2),
                    'interest_paid' => round($interestPortion, 2),
                    'total_amount' => round($totalAmount, 2),
                    'due_date' => $nextDueDate->format('Y-m-d'),
                    'remaining_principal' => round($remainingPrincipal - $principalPortion, 2),
                    'payment_method' => $frequency,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate next payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repayment schedule for a mortgage
     */
    public function getRepaymentSchedule(Request $request, string $mortgageId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $mortgage = Mortgage::with(['repayments', 'member.user', 'property'])->findOrFail($mortgageId);

            $loanAmount = $mortgage->loan_amount;
            $interestRate = $mortgage->interest_rate / 100;
            $tenureMonths = $mortgage->tenure_years * 12;
            $monthlyRate = $interestRate / 12;
            $monthlyPayment = $mortgage->monthly_payment;

            $schedule = [];
            $remainingBalance = $loanAmount;
            $totalPrincipalRepaid = $mortgage->getTotalPrincipalRepaid();

            for ($month = 1; $month <= $tenureMonths; $month++) {
                $interestPortion = $remainingBalance * $monthlyRate;
                $principalPortion = $monthlyPayment - $interestPortion;
                
                if ($remainingBalance < $principalPortion) {
                    $principalPortion = $remainingBalance;
                    $monthlyPayment = $principalPortion + $interestPortion;
                }

                $dueDate = $mortgage->application_date->copy()->addMonths($month);
                
                // Find matching repayment
                $repayment = $mortgage->repayments()
                    ->where('due_date', '<=', $dueDate)
                    ->where('status', 'paid')
                    ->orderBy('due_date', 'desc')
                    ->first();

                $isPaid = $repayment && $repayment->principal_paid >= $principalPortion * 0.99; // 99% tolerance

                $schedule[] = [
                    'month' => $month,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'principal' => round($principalPortion, 2),
                    'interest' => round($interestPortion, 2),
                    'total' => round($monthlyPayment, 2),
                    'remaining_balance' => round($remainingBalance - $principalPortion, 2),
                    'status' => $isPaid ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'pending'),
                    'paid_date' => $repayment ? $repayment->paid_at?->format('Y-m-d') : null,
                    'repayment_id' => $repayment?->id,
                ];

                $remainingBalance -= $principalPortion;
                if ($remainingBalance <= 0) {
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'mortgage_id' => $mortgage->id,
                    'loan_amount' => (float) $loanAmount,
                    'interest_rate' => (float) $mortgage->interest_rate,
                    'tenure_years' => $mortgage->tenure_years,
                    'monthly_payment' => (float) $monthlyPayment,
                    'total_principal_repaid' => (float) $totalPrincipalRepaid,
                    'total_interest_paid' => (float) $mortgage->getTotalInterestPaid(),
                    'remaining_principal' => (float) $mortgage->getRemainingPrincipal(),
                    'is_fully_repaid' => $mortgage->isFullyRepaid(),
                    'schedule' => $schedule,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch repayment schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get repayment schedule for an internal mortgage plan
     */
    public function getInternalRepaymentSchedule(Request $request, string $planId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $plan = InternalMortgagePlan::with(['repayments', 'member.user', 'property'])->findOrFail($planId);

            $principal = $plan->principal;
            $interestRate = $plan->interest_rate / 100;
            $tenureMonths = $plan->tenure_months;
            $frequency = $plan->frequency;
            
            // Calculate payment frequency multiplier
            $frequencyMultiplier = match($frequency) {
                'monthly' => 1,
                'quarterly' => 3,
                'biannually' => 6,
                'annually' => 12,
                default => 1,
            };

            $paymentsPerYear = 12 / $frequencyMultiplier;
            $periodicRate = $interestRate / $paymentsPerYear;
            $numberOfPayments = $tenureMonths / $frequencyMultiplier;

            // Calculate periodic payment using amortization
            $factor = pow(1 + $periodicRate, $numberOfPayments);
            $periodicPayment = $principal * ($periodicRate * $factor) / ($factor - 1);

            $schedule = [];
            $remainingBalance = $principal;
            $totalPrincipalRepaid = $plan->getTotalPrincipalRepaid();
            $startDate = $plan->starts_on ? \Carbon\Carbon::parse($plan->starts_on) : now();

            for ($period = 1; $period <= $numberOfPayments; $period++) {
                $interestPortion = $remainingBalance * $periodicRate;
                $principalPortion = $periodicPayment - $interestPortion;
                
                if ($remainingBalance < $principalPortion) {
                    $principalPortion = $remainingBalance;
                    $periodicPayment = $principalPortion + $interestPortion;
                }

                $dueDate = $startDate->copy()->addMonths($period * $frequencyMultiplier);
                
                // Find matching repayment
                $repayment = $plan->repayments()
                    ->where('due_date', '<=', $dueDate)
                    ->where('status', 'paid')
                    ->orderBy('due_date', 'desc')
                    ->first();

                $isPaid = $repayment && $repayment->principal_paid >= $principalPortion * 0.99;

                $schedule[] = [
                    'period' => $period,
                    'frequency' => $frequency,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'principal' => round($principalPortion, 2),
                    'interest' => round($interestPortion, 2),
                    'total' => round($periodicPayment, 2),
                    'remaining_balance' => round($remainingBalance - $principalPortion, 2),
                    'status' => $isPaid ? 'paid' : ($dueDate->isPast() ? 'overdue' : 'pending'),
                    'paid_date' => $repayment ? $repayment->paid_at?->format('Y-m-d') : null,
                    'repayment_id' => $repayment?->id,
                ];

                $remainingBalance -= $principalPortion;
                if ($remainingBalance <= 0) {
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'plan_id' => $plan->id,
                    'principal' => (float) $principal,
                    'interest_rate' => (float) $plan->interest_rate,
                    'tenure_months' => $plan->tenure_months,
                    'frequency' => $frequency,
                    'periodic_payment' => (float) $periodicPayment,
                    'total_principal_repaid' => (float) $totalPrincipalRepaid,
                    'total_interest_paid' => (float) $plan->getTotalInterestPaid(),
                    'remaining_principal' => (float) $plan->getRemainingPrincipal(),
                    'is_fully_repaid' => $plan->isFullyRepaid(),
                    'schedule' => $schedule,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch repayment schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

