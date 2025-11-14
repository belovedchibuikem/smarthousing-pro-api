<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantPaymentApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
      
        $query = Payment::with(['user', 'approver'])
            ->where('approval_status', 'pending');

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by payment type (from metadata)
        if ($request->has('payment_type') && $request->payment_type !== 'all') {
            $query->whereJsonContains('metadata->payment_type', $request->payment_type);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('payer_name', 'like', "%{$search}%")
                  ->orWhere('payer_phone', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'user_id' => $payment->user_id,
                    'user_name' => $payment->user->name ?? 'Unknown',
                    'user_email' => $payment->user->email ?? 'Unknown',
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'payment_type' => $payment->metadata['payment_type'] ?? 'general',
                    'description' => $payment->description,
                    'payer_name' => $payment->payer_name,
                    'payer_phone' => $payment->payer_phone,
                    'account_details' => $payment->account_details,
                    'payment_evidence' => $payment->payment_evidence ?? [],
                    'bank_reference' => $payment->bank_reference,
                    'bank_name' => $payment->bank_name,
                    'account_number' => $payment->account_number,
                    'account_name' => $payment->account_name,
                    'payment_date' => $payment->payment_date?->format('Y-m-d H:i:s'),
                    'status' => $payment->status,
                    'approval_status' => $payment->approval_status,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ]
        ]);
    }

    public function show(string $payment): JsonResponse
    {
        $paymentModel = Payment::with(['user', 'approver'])->findOrFail($payment);
        
        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $paymentModel->id,
                'reference' => $paymentModel->reference,
                'user_id' => $paymentModel->user_id,
                'user_name' => $paymentModel->user->name ?? 'Unknown',
                'user_email' => $paymentModel->user->email ?? 'Unknown',
                'amount' => (float) $paymentModel->amount,
                'currency' => $paymentModel->currency,
                'payment_method' => $paymentModel->payment_method,
                'payment_type' => $paymentModel->metadata['payment_type'] ?? 'general',
                'description' => $paymentModel->description,
                'payer_name' => $paymentModel->payer_name,
                'payer_phone' => $paymentModel->payer_phone,
                'account_details' => $paymentModel->account_details,
                'payment_evidence' => $paymentModel->payment_evidence ?? [],
                'bank_reference' => $paymentModel->bank_reference,
                'bank_name' => $paymentModel->bank_name,
                'account_number' => $paymentModel->account_number,
                'account_name' => $paymentModel->account_name,
                'payment_date' => $paymentModel->payment_date?->format('Y-m-d H:i:s'),
                'status' => $paymentModel->status,
                'approval_status' => $paymentModel->approval_status,
                'approval_notes' => $paymentModel->approval_notes,
                'rejection_reason' => $paymentModel->rejection_reason,
                'approved_by' => $paymentModel->approved_by,
                'approver_name' => $paymentModel->approver->name ?? null,
                'approved_at' => $paymentModel->approved_at?->format('Y-m-d H:i:s'),
                'created_at' => $paymentModel->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $paymentModel->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    public function approve(Request $request, String $payment): JsonResponse
    {
        
        $request->validate([
            'approval_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

        $paymentModel = Payment::findOrFail($payment);
       
        $paymentModel->update([
                'approval_status' => 'approved',
            'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        
       

            // Update related services based on payment metadata
        $this->updateRelatedServices($paymentModel);
        
            
            // If this is a member subscription payment, update the subscription
        if (isset($paymentModel->metadata['subscription_id'])) {
                DB::connection('mysql')
                    ->table('member_subscriptions')
                ->where('id', $paymentModel->metadata['subscription_id'])
                    ->update([
                        'payment_status' => 'approved',
                        'status' => 'active',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);
            }

            // If this is a wallet funding payment, credit the wallet
        if (isset($paymentModel->metadata['wallet_id'])) {
           
            $wallet = \App\Models\Tenant\Wallet::find($paymentModel->metadata['wallet_id']);
           
                if ($wallet) {
                        $oldBalance = $wallet->balance;
                $wallet->increment('balance', $paymentModel->amount);
                        $newBalance = $wallet->fresh()->balance;

                $walletTransaction = \App\Models\Tenant\WalletTransaction::create([
                            'wallet_id' => $wallet->id,
                            'type' => 'credit',
                    'amount' => $paymentModel->amount,
                    'description' => $paymentModel->description,
                    'payment_reference' => $paymentModel->reference,
                            'status' => 'completed',
                            'metadata' => [
                                'balance_before' => $oldBalance,
                                'balance_after' => $newBalance,
                        'payment_id' => $paymentModel->id,
                            ],
                        ]);

                
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment approved successfully',
            'payment' => $paymentModel->fresh(['user', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tenant payment approval failed', [
            'payment_id' => $paymentModel->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString() // Add stack trace for debugging
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment approval failed',
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    public function reject(Request $request, string $payment): JsonResponse
    {
        
       
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);
        

        $paymentModel = Payment::findOrFail($payment);
        

        try {
            DB::beginTransaction();


            $paymentModel->update([
                'approval_status' => 'rejected',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
                'status' => 'failed',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully',
                'payment' => $paymentModel->fresh(['user', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tenant payment rejection failed', [
                'payment_id' => $paymentModel->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment rejection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPaymentLogs(Request $request): JsonResponse
    {
        $query = Payment::with(['user', 'approver']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by approval status
        if ($request->has('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('payer_name', 'like', "%{$search}%")
                    ->orWhere('payer_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'payments' => $payments->getCollection()->map(function (Payment $payment) {
                return [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'user_id' => $payment->user_id,
                    'user_name' => $payment->user->name ?? 'Unknown',
                    'user_email' => $payment->user->email ?? 'Unknown',
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'payment_type' => $payment->metadata['payment_type'] ?? null,
                    'status' => $payment->status,
                    'approval_status' => $payment->approval_status,
                    'approval_notes' => $payment->approval_notes,
                    'rejection_reason' => $payment->rejection_reason,
                    'approved_by' => $payment->approved_by,
                    'approver_name' => $payment->approver->name ?? null,
                    'approver_email' => $payment->approver->email ?? null,
                    'approved_at' => $payment->approved_at?->format('Y-m-d H:i:s'),
                    'payment_date' => $payment->payment_date?->format('Y-m-d H:i:s'),
                    'description' => $payment->description,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ]
        ]);
    }

    public function getReconciliationData(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());

        $data = [
            'summary' => [
                'total_payments' => Payment::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_amount' => Payment::whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
                'completed_payments' => Payment::where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate])->count(),
                'completed_amount' => Payment::where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
                'pending_approvals' => Payment::where('approval_status', 'pending')
                    ->whereBetween('created_at', [$startDate, $endDate])->count(),
                'pending_amount' => Payment::where('approval_status', 'pending')
                    ->whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
            ],
            'by_payment_method' => Payment::select('payment_method')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount) as total_amount')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('payment_method')
                ->get(),
            'by_status' => Payment::select('status')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount) as total_amount')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('status')
                ->get(),
            'by_approval_status' => Payment::select('approval_status')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount) as total_amount')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('approval_status')
                ->get(),
        ];

        return response()->json([
            'reconciliation_data' => $data,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    public function submitManualPayment(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'amount' => 'required|numeric|min:100',
            'currency' => 'required|string|size:3',
            'description' => 'required|string|max:255',
            'bank_reference' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:20',
            'account_name' => 'required|string|max:255',
            'payment_date' => 'required|date',
            'payment_evidence' => 'nullable|array',
            'payment_evidence.*' => 'string|url',
            'metadata' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            $reference = 'MAN_' . strtoupper(uniqid());
            
            $payment = Payment::create([
                'user_id' => $request->user_id,
                'reference' => $reference,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'payment_method' => 'bank_transfer',
                'status' => 'pending',
                'description' => $request->description,
                'approval_status' => 'pending',
                'bank_reference' => $request->bank_reference,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name' => $request->account_name,
                'payment_date' => $request->payment_date,
                'payment_evidence' => $request->payment_evidence ?? [],
                'metadata' => $request->metadata ?? [],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Manual payment submitted successfully',
                'payment' => $payment,
                'reference' => $reference,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Manual payment submission failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function updateRelatedServices(Payment $payment): void
    {
        $metadata = $payment->metadata ?? [];
        
        // Handle different payment types based on metadata
        if (isset($metadata['type'])) {
            switch ($metadata['type']) {
                case 'contribution':
                    app(\App\Services\Tenant\TenantPaymentService::class)->finalizeContributionPayment($payment);
                    break;

                case 'equity_contribution':
                    app(\App\Services\Tenant\TenantPaymentService::class)->finalizeEquityContributionPayment($payment);
                    break;
                    
                case 'loan_repayment':
                    if (isset($metadata['loan_id'])) {
                        $loan = \App\Models\Tenant\Loan::find($metadata['loan_id']);
                        if ($loan) {
                            // Update loan repayment status
                            $loan->update(['status' => 'active']);
                        }
                    }
                    break;
                    
                case 'investment':
                    if (isset($metadata['investment_id'])) {
                        $investment = \App\Models\Tenant\Investment::find($metadata['investment_id']);
                        if ($investment) {
                            $investment->update(['status' => 'active']);
                        }
                    }
                    break;
                    
                case 'property_payment':
                    if (isset($metadata['property_id'])) {
                        // Handle property payment logic
                        // This would depend on your property payment structure
                    }
                    break;
            }
        }
    }
}
