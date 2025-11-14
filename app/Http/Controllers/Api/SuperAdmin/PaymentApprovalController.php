<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformTransaction;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlatformTransaction::with(['tenant', 'approver'])
            ->where('approval_status', 'pending');

        // Filter by payment gateway
        if ($request->has('gateway')) {
            $query->where('payment_gateway', $request->gateway);
        }

        // Filter by transaction type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by tenant
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    public function show(PlatformTransaction $transaction): JsonResponse
    {
        $transaction->load(['tenant', 'approver']);
        
        return response()->json([
            'transaction' => $transaction
        ]);
    }

    public function approve(Request $request, PlatformTransaction $transaction): JsonResponse
    {
        $request->validate([
            'approval_notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = Auth::guard('super_admin')->id();
            }
            if (!$userId) {
                $userId = Auth::id();
            }

            $transaction->update([
                'approval_status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_notes' => $request->approval_notes,
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            // Update related subscription or service based on transaction type
            $this->updateRelatedService($transaction);

            // Log the approval
            Log::info('Payment approved', [
                'transaction_id' => $transaction->id,
                'approved_by' => $userId,
                'amount' => $transaction->amount,
                'tenant_id' => $transaction->tenant_id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment approved successfully',
                'transaction' => $transaction->fresh(['tenant', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment approval failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment approval failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, PlatformTransaction $transaction): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Try multiple ways to get the authenticated user ID
            $userId = $request->user()?->id;
            if (!$userId) {
                $userId = Auth::guard('super_admin')->id();
            }
            if (!$userId) {
                $userId = Auth::id();
            }

            $transaction->update([
                'approval_status' => 'rejected',
                'approved_by' => $userId,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
                'status' => 'failed',
            ]);

            // Log the rejection
            Log::info('Payment rejected', [
                'transaction_id' => $transaction->id,
                'rejected_by' => $userId,
                'reason' => $request->rejection_reason,
                'tenant_id' => $transaction->tenant_id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully',
                'transaction' => $transaction->fresh(['tenant', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment rejection failed', [
                'transaction_id' => $transaction->id,
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
        $query = PlatformTransaction::with(['tenant', 'approver']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by approval status
        if ($request->has('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        // Filter by payment gateway
        if ($request->has('gateway')) {
            $query->where('payment_gateway', $request->gateway);
        }

        // Filter by tenant
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    public function getReconciliationData(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());

        $data = [
            'summary' => [
                'total_transactions' => PlatformTransaction::whereBetween('created_at', [$startDate, $endDate])->count(),
                'total_amount' => PlatformTransaction::whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
                'completed_transactions' => PlatformTransaction::where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate])->count(),
                'completed_amount' => PlatformTransaction::where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
                'pending_approvals' => PlatformTransaction::where('approval_status', 'pending')
                    ->whereBetween('created_at', [$startDate, $endDate])->count(),
                'pending_amount' => PlatformTransaction::where('approval_status', 'pending')
                    ->whereBetween('created_at', [$startDate, $endDate])->sum('amount'),
            ],
            'by_gateway' => PlatformTransaction::select('payment_gateway')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount) as total_amount')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('payment_gateway')
                ->get(),
            'by_status' => PlatformTransaction::select('status')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(amount) as total_amount')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('status')
                ->get(),
            'by_approval_status' => PlatformTransaction::select('approval_status')
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

    private function updateRelatedService(PlatformTransaction $transaction): void
    {
        switch ($transaction->type) {
            case 'subscription':
                // Update subscription status
                if (isset($transaction->metadata['subscription_id'])) {
                    $subscription = \App\Models\Central\Subscription::find($transaction->metadata['subscription_id']);
                    if ($subscription) {
                        $subscription->update(['status' => 'active']);
                    }
                }
                break;
            case 'member_subscription':
                // Update member subscription status
                if (isset($transaction->metadata['member_subscription_id'])) {
                    $memberSubscription = \App\Models\Central\MemberSubscription::find($transaction->metadata['member_subscription_id']);
                    if ($memberSubscription) {
                        $memberSubscription->update(['status' => 'active']);
                    }
                }
                break;
            case 'white_label':
                // Update white label service
                if (isset($transaction->metadata['tenant_id'])) {
                    $tenant = Tenant::find($transaction->metadata['tenant_id']);
                    if ($tenant) {
                        $tenant->update(['white_label_enabled' => true]);
                    }
                }
                break;
            case 'custom_domain':
                // Update custom domain request
                if (isset($transaction->metadata['domain_request_id'])) {
                    $domainRequest = \App\Models\Central\CustomDomainRequest::find($transaction->metadata['domain_request_id']);
                    if ($domainRequest) {
                        $domainRequest->update(['status' => 'approved']);
                    }
                }
                break;
        }
    }
}
