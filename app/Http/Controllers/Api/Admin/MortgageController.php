<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Mortgage;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MortgageController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}
    public function index(Request $request): JsonResponse
    {
       
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        
        $query = Mortgage::with(['member.user', 'provider', 'property']);

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('member.user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })->orWhereHas('member', function($q) use ($search) {
                $q->where('member_id', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $mortgages = $query->latest('application_date')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $mortgages->items(),
            'pagination' => [
                'current_page' => $mortgages->currentPage(),
                'last_page' => $mortgages->lastPage(),
                'per_page' => $mortgages->perPage(),
                'total' => $mortgages->total(),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|uuid|exists:members,id',
            'provider_id' => 'nullable|uuid|exists:mortgage_providers,id',
            'property_id' => 'nullable|uuid|exists:properties,id',
            'loan_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'tenure_years' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Calculate monthly payment using amortization formula (PMT)
            $monthlyPayment = $this->calculateAmortizedPayment(
                $request->loan_amount,
                $request->interest_rate,
                $request->tenure_years
            );

            $mortgage = Mortgage::create([
                'member_id' => $request->member_id,
                'provider_id' => $request->provider_id ?: null,
                'property_id' => $request->property_id ?: null,
                'loan_amount' => $request->loan_amount,
                'interest_rate' => $request->interest_rate,
                'tenure_years' => $request->tenure_years,
                'monthly_payment' => $monthlyPayment,
                'status' => 'pending',
                'application_date' => now(),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mortgage created successfully',
                'data' => $mortgage->fresh()->load(['member.user', 'provider', 'property'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create mortgage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $mortgage = Mortgage::with(['member.user', 'provider', 'property', 'repayments'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $mortgage
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $mortgage = Mortgage::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'provider_id' => 'nullable|uuid|exists:mortgage_providers,id',
            'property_id' => 'nullable|uuid|exists:properties,id',
            'loan_amount' => 'sometimes|numeric|min:0',
            'interest_rate' => 'sometimes|numeric|min:0|max:100',
            'tenure_years' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];
            if ($request->has('provider_id')) {
                $updateData['provider_id'] = $request->provider_id ?: null;
            }
            if ($request->has('property_id')) {
                $updateData['property_id'] = $request->property_id ?: null;
            }
            if ($request->has('notes')) {
                $updateData['notes'] = $request->notes;
            }
            
            if ($request->has('loan_amount') || $request->has('interest_rate') || $request->has('tenure_years')) {
                $loanAmount = $request->loan_amount ?? $mortgage->loan_amount;
                $interestRate = $request->interest_rate ?? $mortgage->interest_rate;
                $tenureYears = $request->tenure_years ?? $mortgage->tenure_years;
                
                $updateData['loan_amount'] = $loanAmount;
                $updateData['interest_rate'] = $interestRate;
                $updateData['tenure_years'] = $tenureYears;
                $updateData['monthly_payment'] = $this->calculateAmortizedPayment(
                    $loanAmount,
                    $interestRate,
                    $tenureYears
                );
            }

            $mortgage->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Mortgage updated successfully',
                'data' => $mortgage->fresh()->load(['member.user', 'provider', 'property'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update mortgage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $mortgage = Mortgage::findOrFail($id);

        if ($mortgage->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete active mortgage'
            ], 400);
        }

        try {
            $mortgage->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mortgage deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete mortgage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $mortgage = Mortgage::findOrFail($id);

        if ($mortgage->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Mortgage is not pending approval'
            ], 400);
        }

        $mortgage->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        $mortgage->load('member.user');

        // Notify the member about mortgage approval
        if ($mortgage->member && $mortgage->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$mortgage->member->user->id],
                'success',
                'Mortgage Approved',
                'Your mortgage application of ₦' . number_format($mortgage->loan_amount, 2) . ' has been approved.',
                [
                    'mortgage_id' => $mortgage->id,
                    'loan_amount' => $mortgage->loan_amount,
                    'monthly_payment' => $mortgage->monthly_payment,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Mortgage approved successfully',
            'data' => $mortgage->fresh()
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $mortgage = Mortgage::findOrFail($id);

        if ($mortgage->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Mortgage is not pending approval'
            ], 400);
        }

        $mortgage->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason ?? null,
            'rejected_at' => now(),
            'rejected_by' => $user->id,
            'notes' => ($mortgage->notes ?? '') . ($request->reason ? "\nRejected: " . $request->reason : ''),
        ]);

        $mortgage->load('member.user');

        // Notify the member about mortgage rejection
        if ($mortgage->member && $mortgage->member->user) {
            $this->notificationService->sendNotificationToUsers(
                [$mortgage->member->user->id],
                'warning',
                'Mortgage Rejected',
                'Your mortgage application of ₦' . number_format($mortgage->loan_amount, 2) . ' has been rejected.' . ($request->reason ? ' Reason: ' . $request->reason : ''),
                [
                    'mortgage_id' => $mortgage->id,
                    'loan_amount' => $mortgage->loan_amount,
                    'reason' => $request->reason ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Mortgage rejected successfully',
            'data' => $mortgage->fresh()
        ]);
    }

    /**
     * Calculate monthly payment using amortization formula (PMT)
     * PMT = P * (r * (1 + r)^n) / ((1 + r)^n - 1)
     * Where:
     * P = Principal (loan amount)
     * r = Monthly interest rate (annual rate / 12 / 100)
     * n = Total number of payments (tenure_years * 12)
     */
    private function calculateAmortizedPayment(float $loanAmount, float $interestRate, int $tenureYears): float
    {
        if ($loanAmount <= 0 || $tenureYears <= 0) {
            return 0;
        }

        $numberOfPayments = $tenureYears * 12;
        $monthlyRate = ($interestRate / 100) / 12;

        if ($monthlyRate <= 0) {
            // If no interest, just divide principal by number of payments
            return round($loanAmount / $numberOfPayments, 2);
        }

        $factor = pow(1 + $monthlyRate, $numberOfPayments);

        if ($factor === 1.0) {
            return round($loanAmount / $numberOfPayments, 2);
        }

        $monthlyPayment = $loanAmount * ($monthlyRate * $factor) / ($factor - 1);

        return round($monthlyPayment, 2);
    }
}

