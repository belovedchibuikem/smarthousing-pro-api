<?php

namespace App\Http\Controllers\Api\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\LoanApplicationRequest;
use App\Http\Resources\Loans\LoanApplicationResource;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanProduct;
use App\Models\Tenant\Member;
use Illuminate\Http\Request;
use App\Services\Loans\LoanEligibilityService;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanApplicationController extends Controller
{
    public function __construct(
        protected LoanEligibilityService $eligibilityService,
        protected NotificationService $notificationService
    ) {}

    public function apply(LoanApplicationRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'message' => 'User not authenticated'
                ], 401);
            }
            $member = $user->member;

            if (!$member) {
                return response()->json([
                    'message' => 'Member profile not found'
                ], 404);
            }

            // Get loan product
            $product = LoanProduct::find($request->product_id);
            if (!$product || !$product->isActive()) {
                return response()->json([
                    'message' => 'Loan product not available'
                ], 400);
            }

            // Check eligibility
            $eligibility = $this->eligibilityService->checkEligibility($member, $product, $request->amount, $request->tenure_months);
            
            if (!$eligibility['eligible']) {
                return response()->json([
                    'message' => 'Loan application not eligible:'.implode(', ', $eligibility['reasons']),
                    'reasons' => $eligibility['reasons']
                ], 400);
            }
            
            // Calculate loan details
            $interestAmount = $product->calculateInterest($request->amount, $request->tenure_months);
            $totalAmount = $request->amount + $interestAmount;
            $monthlyPayment = $product->calculateMonthlyPayment($request->amount, $request->tenure_months);

            // Create loan application
            $loan = Loan::create([
                'member_id' => $member->id,
                'product_id' => $product->id,
                'amount' => $request->amount,
                'interest_rate' => $product->interest_rate,
                'duration_months' => $request->tenure_months,
                'type' => $this->determineLoanType($product),
                'purpose' => $request->purpose,
                'status' => 'pending',
                'application_date' => now(),
                'monthly_payment' => $monthlyPayment,
                'total_amount' => $totalAmount,
                'interest_amount' => $interestAmount,
                'processing_fee' => $request->amount * ($product->processing_fee_percentage / 100),
                'required_documents' => $product->required_documents,
                'application_metadata' => [
                    'net_pay' => $request->net_pay,
                    'employment_status' => $request->employment_status,
                    'guarantor_name' => $request->guarantor_name,
                    'guarantor_phone' => $request->guarantor_phone,
                    'guarantor_relationship' => $request->guarantor_relationship,
                ],
            ]);

            DB::commit();

            // Notify admins about new loan application
            $memberName = $member->first_name . ' ' . $member->last_name;
            $this->notificationService->notifyAdminsNewLoanApplication(
                $loan->id,
                $memberName,
                $request->amount
            );

            return response()->json([
                'success' => true,
                'message' => 'Loan application submitted successfully',
                'loan' => new LoanApplicationResource($loan->load(['member.user', 'product'])),
                'loan_details' => [
                    'amount' => $request->amount,
                    'interest_rate' => $product->interest_rate,
                    'tenure_months' => $request->tenure_months,
                    'monthly_payment' => $monthlyPayment,
                    'total_amount' => $totalAmount,
                    'interest_amount' => $interestAmount,
                    'processing_fee' => $loan->processing_fee,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Loan application failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, String $loanId): JsonResponse
    {

      
        $user = $request->user();
        $member = $user?->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $loan = Loan::where('id', $loanId)
            ->where('member_id', $member->id)
            ->with(['product', 'repayments'])
            ->first();
        

        if (!$loan) {
            return response()->json([
                'message' => 'Loan application not found'
            ], 404);
        }

        return response()->json([
            'loan' => new LoanApplicationResource($loan)
        ]);
    }

    public function getApplicationStatus(Request $request, string $loanId): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $loan = Loan::where('id', $loanId)
            ->where('member_id', $member->id)
            ->with(['product'])
            ->first();

        if (!$loan) {
            return response()->json([
                'message' => 'Loan application not found'
            ], 404);
        }

        return response()->json([
            'loan' => new LoanApplicationResource($loan)
        ]);
    }

    public function getMyApplications(Request $request): JsonResponse
    {
        $user = $request->user();
        $member = $user->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found'
            ], 404);
        }

        $loans = Loan::where('member_id', $member->id)
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'loans' => LoanApplicationResource::collection($loans),
            'pagination' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ]
        ]);
    }

    protected function determineLoanType(LoanProduct $product): string
    {
        $name = strtolower($product->name ?? '');

        if (str_contains($name, 'house') || str_contains($name, 'home') || str_contains($name, 'mortgage')) {
            return 'housing';
        }

        if (str_contains($name, 'business') || str_contains($name, 'enterprise')) {
            return 'business';
        }

        if (str_contains($name, 'emergency') || str_contains($name, 'urgent')) {
            return 'emergency';
        }

        return 'personal';
    }
}
