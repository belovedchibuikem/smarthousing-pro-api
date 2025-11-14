<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SuperAdminPaymentGatewayRequest;
use App\Http\Resources\SuperAdmin\SuperAdminPaymentGatewayResource;
use App\Models\Central\PaymentGateway;
use App\Models\Central\PlatformTransaction;
use App\Models\Central\Subscription;
use App\Models\Central\MemberSubscription;
use App\Services\SuperAdmin\SuperAdminPaymentService;
use App\Services\Communication\SuperAdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuperAdminPaymentGatewayController extends Controller
{
    public function __construct(
        protected SuperAdminPaymentService $paymentService,
        protected SuperAdminNotificationService $notificationService
    ) {}

    public function index(): JsonResponse
    {
        $gateways = PaymentGateway::all();

        return response()->json([
            'gateways' => SuperAdminPaymentGatewayResource::collection($gateways)
        ]);
    }

    public function update(SuperAdminPaymentGatewayRequest $request, PaymentGateway $gateway): JsonResponse
    {
        // Try multiple ways to get the authenticated user ID
        $userId = $request->user()?->id;
        if (!$userId) {
            $userId = Auth::guard('super_admin')->id();
        }
        if (!$userId) {
            $userId = Auth::id();
        }
        
        $wasActive = $gateway->is_active;
        $gateway->update([
            'is_active' => $request->is_active,
            'settings' => $request->settings,
            'updated_by' => $userId,
        ]);

        // Notify super admins if gateway was deactivated
        if ($wasActive && !$request->is_active) {
            $this->notificationService->notifyPaymentGatewayDeactivated(
                $gateway->name
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Super admin payment gateway updated successfully',
            'gateway' => new SuperAdminPaymentGatewayResource($gateway)
        ]);
    }

    public function testConnection(PaymentGateway $gateway): JsonResponse
    {
        try {
            $result = $this->paymentService->testGatewayConnection($gateway);
            
            // Notify super admins if test fails
            if (!$result['success']) {
                $this->notificationService->notifyPaymentGatewayTestFailure(
                    $gateway->name,
                    $result['message'] ?? 'Connection test failed'
                );
            }
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'response_time' => $result['response_time'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Notify super admins about test failure
            $this->notificationService->notifyPaymentGatewayTestFailure(
                $gateway->name,
                $e->getMessage()
            );
            
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getPlatformStats(): JsonResponse
    {
        $stats = [
            'total_gateways' => PaymentGateway::count(),
            'active_gateways' => PaymentGateway::where('is_active', true)->count(),
            'platform_transactions' => PlatformTransaction::count(),
            'total_revenue' => PlatformTransaction::where('status', 'completed')->sum('amount'),
            'pending_transactions' => PlatformTransaction::where('status', 'pending')->count(),
            'subscription_payments' => $this->getSubscriptionPaymentStats(),
            'member_subscription_payments' => $this->getMemberSubscriptionPaymentStats(),
        ];

        return response()->json([
            'stats' => $stats
        ]);
    }

    public function initializeSubscriptionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_id' => 'required|uuid|exists:subscriptions,id',
            'amount' => 'required|numeric|min:100',
            'payment_method' => 'required|in:card,bank_transfer',
        ]);

        try {
            DB::beginTransaction();

            $subscription = Subscription::findOrFail($request->subscription_id);
            
            $paymentResult = $this->paymentService->initializeSubscriptionPayment(
                $subscription,
                $request->amount,
                $request->payment_method
            );

            if (!$paymentResult['success']) {
                // Notify super admins about subscription payment failure
                $tenant = $subscription->tenant;
                $tenantName = $tenant->data['name'] ?? $tenant->id;
                $this->notificationService->notifySubscriptionPaymentFailure(
                    $subscription->id,
                    $tenantName,
                    $request->amount,
                    $paymentResult['message'] ?? 'Payment initialization failed'
                );
                
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'],
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription payment initialized successfully',
                'payment' => $paymentResult['payment'],
                'payment_data' => $paymentResult['data'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Subscription payment initialization failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function initializeMemberSubscriptionPayment(Request $request): JsonResponse
    {
        $request->validate([
            'member_subscription_id' => 'required|uuid|exists:member_subscriptions,id',
            'amount' => 'required|numeric|min:100',
            'payment_method' => 'required|in:card,bank_transfer',
        ]);

        try {
            DB::beginTransaction();

            $memberSubscription = MemberSubscription::findOrFail($request->member_subscription_id);
            
            $paymentResult = $this->paymentService->initializeMemberSubscriptionPayment(
                $memberSubscription,
                $request->amount,
                $request->payment_method
            );

            if (!$paymentResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'],
                ], 400);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Member subscription payment initialized successfully',
                'payment' => $paymentResult['payment'],
                'payment_data' => $paymentResult['data'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Member subscription payment initialization failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyPayment(string $reference): JsonResponse
    {
        try {
            $verificationResult = $this->paymentService->verifyPayment($reference);

            if ($verificationResult['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'transaction' => $verificationResult['transaction'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'error' => $verificationResult['message'],
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTransactionHistory(Request $request): JsonResponse
    {
        $query = PlatformTransaction::with(['tenant', 'subscription', 'memberSubscription']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
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

    public function getRevenueAnalytics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subMonths(6)->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $analytics = [
            'total_revenue' => PlatformTransaction::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'subscription_revenue' => PlatformTransaction::where('status', 'completed')
                ->where('type', 'subscription')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'member_subscription_revenue' => PlatformTransaction::where('status', 'completed')
                ->where('type', 'member_subscription')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'monthly_breakdown' => $this->getMonthlyRevenueBreakdown($startDate, $endDate),
            'gateway_performance' => $this->getGatewayPerformanceStats($startDate, $endDate),
        ];

        return response()->json([
            'analytics' => $analytics,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    private function getSubscriptionPaymentStats(): array
    {
        return [
            'total_payments' => PlatformTransaction::where('type', 'subscription')->count(),
            'successful_payments' => PlatformTransaction::where('type', 'subscription')
                ->where('status', 'completed')->count(),
            'pending_payments' => PlatformTransaction::where('type', 'subscription')
                ->where('status', 'pending')->count(),
            'total_amount' => PlatformTransaction::where('type', 'subscription')
                ->where('status', 'completed')->sum('amount'),
        ];
    }

    private function getMemberSubscriptionPaymentStats(): array
    {
        return [
            'total_payments' => PlatformTransaction::where('type', 'member_subscription')->count(),
            'successful_payments' => PlatformTransaction::where('type', 'member_subscription')
                ->where('status', 'completed')->count(),
            'pending_payments' => PlatformTransaction::where('type', 'member_subscription')
                ->where('status', 'pending')->count(),
            'total_amount' => PlatformTransaction::where('type', 'member_subscription')
                ->where('status', 'completed')->sum('amount'),
        ];
    }

    private function getMonthlyRevenueBreakdown($startDate, $endDate): array
    {
        return PlatformTransaction::select(
                DB::raw('DATE_TRUNC(\'month\', created_at) as month'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $reference = $request->get('reference');
            $status = $request->get('status');
            
            if (!$reference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reference not provided',
                ], 400);
            }

            $transaction = PlatformTransaction::where('reference', $reference)->first();
            
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                ], 404);
            }

            // Update transaction status based on callback
            $transaction->update([
                'status' => $status === 'success' ? 'completed' : 'failed',
                'callback_data' => $request->all(),
            ]);

            if ($status === 'success') {
                $this->updateRelatedSubscription($transaction);
            }

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successfully',
                'transaction' => $transaction,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getGatewayPerformanceStats($startDate, $endDate): array
    {
        return PlatformTransaction::select(
                'gateway',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('AVG(amount) as average_amount')
            )
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('gateway')
            ->get();
    }

    public function submitManualPayment(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'type' => 'required|in:subscription,member_subscription,white_label,custom_domain,addon',
            'amount' => 'required|numeric|min:100',
            'currency' => 'required|string|size:3',
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
            
            $transaction = PlatformTransaction::create([
                'tenant_id' => $request->tenant_id,
                'reference' => $reference,
                'type' => $request->type,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'status' => 'pending',
                'payment_gateway' => 'manual',
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
                'transaction' => $transaction,
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

    private function updateRelatedSubscription(PlatformTransaction $transaction): void
    {
        if ($transaction->type === 'subscription') {
            $subscription = Subscription::find($transaction->metadata['subscription_id']);
            if ($subscription) {
                $subscription->update(['status' => 'active']);
            }
        } elseif ($transaction->type === 'member_subscription') {
            $memberSubscription = MemberSubscription::find($transaction->metadata['member_subscription_id']);
            if ($memberSubscription) {
                $memberSubscription->update(['status' => 'active']);
            }
        }
    }
}
