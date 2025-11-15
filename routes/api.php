<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// Handle OPTIONS preflight requests
Route::options('{any}', function (Request $request) {
    $origin = $request->header('Origin');
    
    // Check if origin is allowed
    $allowedOrigins = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://frsc.localhost:3000',
    ];
    
    // Check patterns
    $allowedPatterns = [
        '#^http://(.*\.)?localhost:\d+$#',
        '#^http://(.*\.)?127\.0\.0\.1:\d+$#',
        '#^http://.*\.localhost:\d+$#',
    ];
    
    $isAllowed = false;
    if ($origin) {
        $isAllowed = in_array($origin, $allowedOrigins);
        if (!$isAllowed) {
            foreach ($allowedPatterns as $pattern) {
                if (preg_match($pattern, $origin)) {
                    $isAllowed = true;
                    break;
                }
            }
        }
    }
    
    return response('', 200)
        ->header('Access-Control-Allow-Origin', $isAllowed ? $origin : '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With, X-Tenant-Slug')
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Max-Age', '86400');
})->where('any', '.*');

// Public routes (no authentication required, but tenant context needed for registration/login)
Route::middleware(['tenant'])->prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);
    Route::post('verify-otp', [App\Http\Controllers\Api\Auth\OtpController::class, 'verifyOtp']);
    Route::post('resend-otp', [App\Http\Controllers\Api\Auth\OtpController::class, 'resendOtp']);
    Route::post('forgot-password', [App\Http\Controllers\Api\Auth\ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('reset-password', [App\Http\Controllers\Api\Auth\ResetPasswordController::class, 'reset']);
});

// Public tenant validation endpoint
Route::get('tenant/validate', [App\Http\Controllers\Api\Public\TenantValidationController::class, 'validate']);

// Public tenant routes (no authentication required, but tenant context needed)
Route::middleware(['tenant'])->group(function () {
    // White label settings - public GET endpoint (needed for login page branding)
    Route::prefix('admin')->group(function () {
        Route::get('white-label', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'index']);
        // Tenant settings - public GET endpoint (needed for login page and public pages)
        Route::get('settings', [App\Http\Controllers\Api\Admin\SettingsController::class, 'index']);
    });
});

// Protected routes (authentication required)
Route::middleware(['tenant', 'tenant_auth'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('me', [LoginController::class, 'me']);
        Route::post('logout', [LoginController::class, 'logout']);
    });


    // AI recommendations - requires active subscription
    Route::middleware(['member_subscription'])->group(function () {
        Route::get('ai/recommendations', [App\Http\Controllers\Api\AI\RecommendationController::class, 'index']);
    });

    // User routes that require active subscription (wrapped in middleware)
    Route::middleware(['member_subscription'])->group(function () {
        // Loan Products routes
        Route::prefix('loans')->group(function () {
            Route::get('/products', [App\Http\Controllers\Api\Loans\LoanProductController::class, 'index']);
            Route::get('/products/{product}', [App\Http\Controllers\Api\Loans\LoanProductController::class, 'show']);
        });

        // Investment Plans routes
        Route::prefix('investments')->group(function () {
            Route::get('/plans', [App\Http\Controllers\Api\Investments\InvestmentPlanController::class, 'index']);
            Route::get('/plans/{plan}', [App\Http\Controllers\Api\Investments\InvestmentPlanController::class, 'show']);
            Route::get('/payment-methods', [App\Http\Controllers\Api\User\InvestmentController::class, 'paymentMethods']);
            Route::post('/pay', [App\Http\Controllers\Api\User\InvestmentController::class, 'pay']);
        });

        // Wallet Transfer routes
        Route::prefix('wallet')->group(function () {
            Route::post('/transfer', [App\Http\Controllers\Api\Wallet\WalletTransferController::class, 'transfer']);
            Route::get('/transfer-history', [App\Http\Controllers\Api\Wallet\WalletTransferController::class, 'getTransferHistory']);
        });

        // Mail Service routes
        Route::prefix('mail')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'index']);
            Route::post('/compose', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'compose']);
            Route::get('/{mail}', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'show']);
            Route::post('/{mail}/reply', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'reply']);
            Route::patch('/{mail}/read', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'markAsRead']);
            Route::patch('/{mail}/unread', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'markAsUnread']);
            Route::patch('/{mail}/trash', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'moveToTrash']);
            Route::delete('/{mail}', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'delete']);
            Route::get('/unread/count', [App\Http\Controllers\Api\Mail\MailServiceController::class, 'getUnreadCount']);
        });

        // Blockchain Ledger routes
        Route::prefix('blockchain')->group(function () {
            Route::get('/transactions', [App\Http\Controllers\Api\Blockchain\BlockchainLedgerController::class, 'index']);
            Route::get('/transactions/{transaction}', [App\Http\Controllers\Api\Blockchain\BlockchainLedgerController::class, 'show']);
            Route::get('/stats', [App\Http\Controllers\Api\Blockchain\BlockchainLedgerController::class, 'getStats']);
            Route::get('/property-ownership', [App\Http\Controllers\Api\Blockchain\BlockchainLedgerController::class, 'getPropertyOwnership']);
            Route::post('/verify/{hash}', [App\Http\Controllers\Api\Blockchain\BlockchainLedgerController::class, 'verifyTransaction']);
        });

        // Loan routes
        Route::prefix('loans')->group(function () {
            Route::get('/payment-methods', [App\Http\Controllers\Api\Loans\LoanRepaymentController::class, 'paymentMethods']);

            Route::post('/apply', [App\Http\Controllers\Api\Loans\LoanApplicationController::class, 'apply']);
            Route::get('/my-applications', [App\Http\Controllers\Api\Loans\LoanApplicationController::class, 'getMyApplications']);
            Route::get('/application/{loanId}/status', [App\Http\Controllers\Api\Loans\LoanApplicationController::class, 'getApplicationStatus']);

            Route::post('/{loan}/repay', [App\Http\Controllers\Api\Loans\LoanRepaymentController::class, 'repay']);
            Route::get('/{loan}/repayment-schedule', [App\Http\Controllers\Api\Loans\LoanRepaymentController::class, 'getRepaymentSchedule']);
            Route::get('/{loan}/repayment-history', [App\Http\Controllers\Api\Loans\LoanRepaymentController::class, 'getRepaymentHistory']);

            Route::get('/{loanId}', [App\Http\Controllers\Api\Loans\LoanApplicationController::class, 'show']);
        });

        // Property Management routes
        Route::prefix('properties')->group(function () {
            Route::get('/manage', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'index']);
            Route::post('/manage', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'store']);
            Route::get('/manage/{property}', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'show']);
            Route::put('/manage/{property}', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'update']);
            Route::post('/manage/{property}/allocate', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'allocate']);
            Route::post('/manage/{property}/deallocate', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'deallocate']);
            Route::get('/available', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'getAvailableProperties']);
            Route::get('/my', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'myProperties']);
            Route::get('/{property}/payment-setup', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'paymentSetup']);
            Route::post('/{property}/payments', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'recordPayment']);
            Route::get('/manage/{property}/allocations', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'getPropertyAllocations']);
            Route::post('/mortgages/{mortgage}/approve-schedule', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'approveMortgageSchedule']);
            Route::post('/internal-mortgages/{plan}/approve-schedule', [App\Http\Controllers\Api\Properties\PropertyManagementController::class, 'approveInternalMortgageSchedule']);
            Route::post('/transfer', [App\Http\Controllers\Api\Properties\PropertyTransferController::class, 'transfer']);
            Route::get('/transfer-history', [App\Http\Controllers\Api\Properties\PropertyTransferController::class, 'getTransferHistory']);
        });

        // Wallet Top-up routes
        Route::prefix('wallet')->group(function () {
            Route::post('/top-up', [App\Http\Controllers\Api\Wallet\WalletTopUpController::class, 'topUp']);
            Route::get('/top-up/verify/{reference}', [App\Http\Controllers\Api\Wallet\WalletTopUpController::class, 'verifyTopUp']);
            Route::get('/top-up/history', [App\Http\Controllers\Api\Wallet\WalletTopUpController::class, 'getTopUpHistory']);
        });

        // Investment Withdrawal routes
        Route::prefix('investments')->group(function () {
            Route::post('/{investment}/withdraw', [App\Http\Controllers\Api\Investments\InvestmentWithdrawalController::class, 'withdraw']);
            Route::get('/{investment}/withdrawal-history', [App\Http\Controllers\Api\Investments\InvestmentWithdrawalController::class, 'getWithdrawalHistory']);
            Route::get('/{investment}/withdrawal-options', [App\Http\Controllers\Api\Investments\InvestmentWithdrawalController::class, 'getWithdrawalOptions']);
        });

        // User routes
        Route::prefix('user')->group(function () {
            Route::get('profile', [App\Http\Controllers\Api\User\ProfileController::class, 'show']);
        Route::put('profile', [App\Http\Controllers\Api\User\ProfileController::class, 'update']);
        Route::post('profile/avatar', [App\Http\Controllers\Api\User\ProfileController::class, 'uploadAvatar']);
        Route::post('profile/upload-payment-evidence', [App\Http\Controllers\Api\User\ProfileController::class, 'uploadPaymentEvidence']);
        
        // User Settings routes
        Route::prefix('settings')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\User\UserSettingsController::class, 'index']);
            Route::put('/', [App\Http\Controllers\Api\User\UserSettingsController::class, 'update']);
            Route::post('/change-password', [App\Http\Controllers\Api\User\UserSettingsController::class, 'changePassword']);
            Route::post('/two-factor', [App\Http\Controllers\Api\User\UserSettingsController::class, 'toggleTwoFactor']);
        });
        
        Route::prefix('kyc')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\User\KycController::class, 'show']);
            Route::post('/submit', [App\Http\Controllers\Api\User\KycController::class, 'submit']);
            Route::post('/documents', [App\Http\Controllers\Api\User\KycController::class, 'uploadDocuments']);
        });
        
        Route::prefix('wallet')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\User\WalletController::class, 'show']);
            Route::get('/transactions/export', [App\Http\Controllers\Api\User\WalletController::class, 'exportTransactions']);
            Route::get('/transactions', [App\Http\Controllers\Api\User\WalletController::class, 'transactions']);
            Route::get('/payment-methods', [App\Http\Controllers\Api\User\WalletController::class, 'paymentMethods']);
            Route::post('/fund', [App\Http\Controllers\Api\User\WalletController::class, 'fund']);
            Route::post('/verify', [App\Http\Controllers\Api\User\WalletController::class, 'verify']);
            Route::get('/virtual-account', [App\Http\Controllers\Api\User\WalletController::class, 'virtualAccount']);
            Route::post('/virtual-account/refresh', [App\Http\Controllers\Api\User\WalletController::class, 'refreshVirtualAccount']);
        });

        Route::prefix('contributions')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\User\ContributionController::class, 'index']);
            Route::get('/plans', [App\Http\Controllers\Api\User\ContributionController::class, 'plans']);
            Route::get('/payment-methods', [App\Http\Controllers\Api\User\ContributionController::class, 'paymentMethods']);
            Route::post('/pay', [App\Http\Controllers\Api\User\ContributionController::class, 'pay']);
            Route::post('/plans/{plan}/switch', [App\Http\Controllers\Api\User\ContributionController::class, 'switchPlan']);
            Route::get('/auto-pay', [App\Http\Controllers\Api\User\ContributionController::class, 'showAutoPay']);
            Route::put('/auto-pay', [App\Http\Controllers\Api\User\ContributionController::class, 'updateAutoPay']);
        });
        
        // Member subscriptions (for individual members)
        Route::prefix('member-subscriptions')->group(function () {
            Route::get('packages', [App\Http\Controllers\Api\User\MemberSubscriptionController::class, 'packages']);
            Route::get('current', [App\Http\Controllers\Api\User\MemberSubscriptionController::class, 'current']);
            Route::get('history', [App\Http\Controllers\Api\User\MemberSubscriptionController::class, 'history']);
            Route::get('payment-methods', [App\Http\Controllers\Api\User\MemberSubscriptionController::class, 'paymentMethods']);
            Route::post('initialize', [App\Http\Controllers\Api\User\MemberSubscriptionController::class, 'initialize']);
            Route::post('verify', [App\Http\Controllers\Api\User\MemberSubscriptionController::class, 'verify']);
        });
        
        // User Dashboard routes
        Route::get('/dashboard/stats', [App\Http\Controllers\Api\User\UserDashboardController::class, 'stats']);
        Route::get('/dashboard/quick-actions', [App\Http\Controllers\Api\User\UserDashboardController::class, 'quickActions']);
        Route::get('/dashboard/notifications', [App\Http\Controllers\Api\User\UserDashboardController::class, 'notifications']);
        Route::post('/dashboard/notifications/{id}/read', [App\Http\Controllers\Api\User\UserDashboardController::class, 'markNotificationAsRead']);

        // Member Reports routes
        Route::prefix('reports')->group(function () {
            // Export routes must come FIRST (more specific routes before less specific ones)
            Route::get('/contributions/export', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'exportContributions']);
            Route::get('/equity-contributions/export', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'exportEquityContributions']);
            Route::get('/investments/export', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'exportInvestments']);
            Route::get('/loans/export', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'exportLoans']);
            Route::get('/properties/export', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'exportProperties']);
            Route::get('/financial-summary/export', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'exportFinancialSummary']);
            Route::get('/mortgages/export', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'exportMortgages']);
            
            // Regular report routes (less specific, must come after export routes)
            Route::get('/contributions', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'contributions']);
            Route::get('/equity-contributions', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'equityContributions']);
            Route::get('/investments', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'investments']);
            Route::get('/loans', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'loans']);
            Route::get('/properties', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'properties']);
            Route::get('/financial-summary', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'financialSummary']);
            Route::get('/mortgages', [App\Http\Controllers\Api\Reports\MemberReportsController::class, 'mortgages']);
        });

        // Equity Wallet routes (User)
        Route::prefix('equity-wallet')->group(function () {
            Route::get('/balance', [App\Http\Controllers\Api\User\EquityWalletController::class, 'balance']);
            
            Route::get('/transactions', [App\Http\Controllers\Api\User\EquityWalletController::class, 'transactions']);
        });

        // Equity Contribution routes (User)
        Route::prefix('equity-contributions')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\User\EquityContributionController::class, 'index']);
            Route::get('/payment-methods', [App\Http\Controllers\Api\User\EquityContributionController::class, 'paymentMethods']);
            Route::post('/', [App\Http\Controllers\Api\User\EquityContributionController::class, 'store']);
            Route::get('/{id}', [App\Http\Controllers\Api\User\EquityContributionController::class, 'show']);
        });

                
        // Equity Plans routes (User - view active plans)
        Route::prefix('equity-plans')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Admin\EquityPlanController::class, 'index']);
        });

        // Statutory Charges routes (User/Member)
        Route::prefix('statutory')->group(function () {
            Route::get('/charges/payment-methods', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'paymentMethods']);
            Route::get('/charges/types', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'getChargeTypes']);
            Route::post('/charges/create-and-pay', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'createAndPay']);
            Route::get('/charges', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'index']);
            Route::post('/charges', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'store']);
            Route::get('/charges/{charge}', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'show']);
            Route::put('/charges/{charge}', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'update']);
            Route::post('/charges/{charge}/approve', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'approve']);
            Route::post('/charges/{charge}/reject', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'reject']);
            Route::post('/charges/{charge}/pay', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'pay']);
            Route::delete('/charges/{charge}', [App\Http\Controllers\Api\Statutory\StatutoryChargeController::class, 'destroy']);
        });
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('initialize', [App\Http\Controllers\Api\Payments\PaymentController::class, 'initialize']);
        Route::get('verify', [App\Http\Controllers\Api\Payments\PaymentController::class, 'verify']);
        Route::get('callback', [App\Http\Controllers\Api\Payments\PaymentController::class, 'callback']);
        // Webhooks (providers post here)
        Route::post('webhook/paystack', [App\Http\Controllers\Api\Payments\WebhookController::class, 'paystack']);
        Route::post('webhook/stripe', [App\Http\Controllers\Api\Payments\WebhookController::class, 'stripe']);
        Route::post('webhook/remita', [App\Http\Controllers\Api\Payments\WebhookController::class, 'remita']);
    });

    // Subscription routes
    // Tenant subscriptions (for businesses)
    Route::prefix('subscriptions')->group(function () {
        Route::get('packages', [App\Http\Controllers\Api\Subscriptions\SubscriptionController::class, 'packages']);
        Route::get('current', [App\Http\Controllers\Api\Subscriptions\SubscriptionController::class, 'current']);
        Route::get('history', [App\Http\Controllers\Api\Subscriptions\SubscriptionController::class, 'history']);
        Route::get('payment-methods', [App\Http\Controllers\Api\Subscriptions\SubscriptionController::class, 'paymentMethods']);
        Route::post('initialize', [App\Http\Controllers\Api\Subscriptions\SubscriptionController::class, 'initialize']);
        Route::get('verify', [App\Http\Controllers\Api\Subscriptions\SubscriptionController::class, 'verify']);
        Route::get('callback', [App\Http\Controllers\Api\Subscriptions\SubscriptionController::class, 'callback']);
    });

    // User/Member Notifications routes (direct access for frontend)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Communication\NotificationController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Communication\NotificationController::class, 'store']);
        Route::get('/unread-count', [App\Http\Controllers\Api\Communication\NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read', [App\Http\Controllers\Api\Communication\NotificationController::class, 'markAllAsRead']);
        Route::get('/{notification}', [App\Http\Controllers\Api\Communication\NotificationController::class, 'show']);
        Route::post('/{notification}/read', [App\Http\Controllers\Api\Communication\NotificationController::class, 'markAsRead']);
        Route::delete('/{notification}', [App\Http\Controllers\Api\Communication\NotificationController::class, 'destroy']);
    });

    // User/Member Refund Request routes (ticket-based)
    Route::prefix('refunds')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\User\RefundController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Api\User\RefundController::class, 'stats']);
        Route::post('/', [App\Http\Controllers\Api\User\RefundController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\User\RefundController::class, 'show']);
    });

    // Communication routes
    Route::prefix('communication')->group(function () {
        Route::prefix('mail')->group(function () {
            
            Route::get('/', [App\Http\Controllers\Api\Communication\MailController::class, 'index']);
            Route::get('/recipients', [App\Http\Controllers\Api\Communication\MailController::class, 'getRecipients']);
            Route::get('/inbox', [App\Http\Controllers\Api\Communication\MailController::class, 'inbox']);
            Route::get('/sent', [App\Http\Controllers\Api\Communication\MailController::class, 'sent']);
            Route::get('/outbox', [App\Http\Controllers\Api\Communication\MailController::class, 'outbox']);
            Route::get('/drafts', [App\Http\Controllers\Api\Communication\MailController::class, 'drafts']);
            Route::post('/', [App\Http\Controllers\Api\Communication\MailController::class, 'store']);
            Route::post('/bulk-delete', [App\Http\Controllers\Api\Communication\MailController::class, 'bulkDelete']);
            Route::post('/bulk-mark-read', [App\Http\Controllers\Api\Communication\MailController::class, 'bulkMarkAsRead']);
            Route::get('/{mail}', [App\Http\Controllers\Api\Communication\MailController::class, 'show']);
            Route::post('/{mail}/read', [App\Http\Controllers\Api\Communication\MailController::class, 'markAsRead']);
            Route::post('/{mail}/unread', [App\Http\Controllers\Api\Communication\MailController::class, 'markAsUnread']);
            Route::post('/{mail}/star', [App\Http\Controllers\Api\Communication\MailController::class, 'toggleStar']);
            Route::post('/{mail}/reply', [App\Http\Controllers\Api\Communication\MailController::class, 'reply']);
            Route::delete('/{mail}', [App\Http\Controllers\Api\Communication\MailController::class, 'destroy']);
        });
    });

    // Financial routes
    Route::prefix('financial')->group(function () {
        Route::prefix('loans')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Financial\LoanController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Financial\LoanController::class, 'store']);
            Route::get('/{loan}', [App\Http\Controllers\Api\Financial\LoanController::class, 'show']);
            Route::put('/{loan}', [App\Http\Controllers\Api\Financial\LoanController::class, 'update']);
            Route::post('/{loan}/approve', [App\Http\Controllers\Api\Financial\LoanController::class, 'approve']);
            Route::post('/{loan}/reject', [App\Http\Controllers\Api\Financial\LoanController::class, 'reject']);
            Route::delete('/{loan}', [App\Http\Controllers\Api\Financial\LoanController::class, 'destroy']);
        });
        
        Route::prefix('investments')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Financial\InvestmentController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Financial\InvestmentController::class, 'store']);
            Route::get('/{investment}', [App\Http\Controllers\Api\Financial\InvestmentController::class, 'show']);
            Route::put('/{investment}', [App\Http\Controllers\Api\Financial\InvestmentController::class, 'update']);
            Route::post('/{investment}/approve', [App\Http\Controllers\Api\Financial\InvestmentController::class, 'approve']);
            Route::post('/{investment}/reject', [App\Http\Controllers\Api\Financial\InvestmentController::class, 'reject']);
            Route::delete('/{investment}', [App\Http\Controllers\Api\Financial\InvestmentController::class, 'destroy']);
        });
        
        Route::prefix('contributions')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Financial\ContributionController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Financial\ContributionController::class, 'store']);
            Route::get('/{contribution}', [App\Http\Controllers\Api\Financial\ContributionController::class, 'show']);
            Route::put('/{contribution}', [App\Http\Controllers\Api\Financial\ContributionController::class, 'update']);
            Route::post('/{contribution}/approve', [App\Http\Controllers\Api\Financial\ContributionController::class, 'approve']);
            Route::post('/{contribution}/reject', [App\Http\Controllers\Api\Financial\ContributionController::class, 'reject']);
            Route::delete('/{contribution}', [App\Http\Controllers\Api\Financial\ContributionController::class, 'destroy']);
        });
        
    });

      // Property routes
      Route::prefix('properties')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Properties\PropertyController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Properties\PropertyController::class, 'store']);
            Route::get('/{property}', [App\Http\Controllers\Api\Properties\PropertyController::class, 'show']);
            Route::put('/{property}', [App\Http\Controllers\Api\Properties\PropertyController::class, 'update']);
            Route::delete('/{property}', [App\Http\Controllers\Api\Properties\PropertyController::class, 'destroy']);
        
            Route::prefix('allocations')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\Properties\AllocationController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\Properties\AllocationController::class, 'store']);
                Route::get('/{allocation}', [App\Http\Controllers\Api\Properties\AllocationController::class, 'show']);
                Route::put('/{allocation}', [App\Http\Controllers\Api\Properties\AllocationController::class, 'update']);
                Route::post('/{allocation}/approve', [App\Http\Controllers\Api\Properties\AllocationController::class, 'approve']);
                Route::post('/{allocation}/reject', [App\Http\Controllers\Api\Properties\AllocationController::class, 'reject']);
                Route::delete('/{allocation}', [App\Http\Controllers\Api\Properties\AllocationController::class, 'destroy']);
            });
        });

    // Document Management routes (authenticated)
    Route::prefix('documents')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Documents\DocumentController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Documents\DocumentController::class, 'store']);
        Route::get('/{document}', [App\Http\Controllers\Api\Documents\DocumentController::class, 'show']);
        Route::put('/{document}', [App\Http\Controllers\Api\Documents\DocumentController::class, 'update']);
        Route::post('/{document}/approve', [App\Http\Controllers\Api\Documents\DocumentController::class, 'approve']);
        Route::post('/{document}/reject', [App\Http\Controllers\Api\Documents\DocumentController::class, 'reject']);
        Route::get('/{document}/download', [App\Http\Controllers\Api\Documents\DocumentController::class, 'download']);
        Route::delete('/{document}', [App\Http\Controllers\Api\Documents\DocumentController::class, 'destroy']);
    });
    
    // Bulk Upload routes (authenticated)
    Route::prefix('bulk')->group(function () {
        Route::get('/members/template', [App\Http\Controllers\Api\Members\BulkMemberController::class, 'downloadTemplate']);
        Route::post('/members/upload', [App\Http\Controllers\Api\Members\BulkMemberController::class, 'uploadBulk']);
    });
    

     // Membership routes
    Route::prefix('membership')->group(function () {
        Route::post('/upgrade', [App\Http\Controllers\Api\Membership\MembershipController::class, 'upgrade']);
        Route::get('/levels', [App\Http\Controllers\Api\Membership\MembershipController::class, 'getMembershipLevels']);
    });

    // Property Interest routes
    Route::prefix('properties')->group(function () {
        Route::get('/{property}/mortgage', [App\Http\Controllers\Api\Properties\PropertyInterestController::class, 'getPropertyMortgage']);
        Route::post('/{property}/express-interest', [App\Http\Controllers\Api\Properties\PropertyInterestController::class, 'expressInterest']);
        Route::get('/my-interests', [App\Http\Controllers\Api\Properties\PropertyInterestController::class, 'getMyInterests']);
        Route::delete('/interests/{interest}', [App\Http\Controllers\Api\Properties\PropertyInterestController::class, 'withdrawInterest']);
        Route::get('/{property}/documents', [App\Http\Controllers\Api\Properties\PropertyDocumentController::class, 'index']);
        Route::post('/documents', [App\Http\Controllers\Api\Properties\PropertyDocumentController::class, 'store']);
        Route::delete('/documents/{document}', [App\Http\Controllers\Api\Properties\PropertyDocumentController::class, 'destroy']);
    });

    // Member Property Management routes
    Route::prefix('property-management')->group(function () {
        Route::get('/my-estates', [App\Http\Controllers\Api\Properties\MemberPropertyManagementController::class, 'getMyEstates']);
        Route::get('/allottee-status', [App\Http\Controllers\Api\Properties\MemberPropertyManagementController::class, 'getAllotteeStatus']);
        Route::get('/my-properties', [App\Http\Controllers\Api\Properties\MemberPropertyManagementController::class, 'getMyProperties']);
        Route::prefix('maintenance')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Properties\MemberPropertyManagementController::class, 'getMyMaintenanceRequests']);
            Route::post('/', [App\Http\Controllers\Api\Properties\MemberPropertyManagementController::class, 'createMaintenanceRequest']);
            Route::get('/{id}', [App\Http\Controllers\Api\Properties\MemberPropertyManagementController::class, 'getMaintenanceRequest']);
        });
    });

});

// Reports routes
Route::prefix('reports')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Api\Reports\FinancialReportController::class, 'dashboard']);
    Route::get('/loans', [App\Http\Controllers\Api\Reports\FinancialReportController::class, 'loans']);
    Route::get('/investments', [App\Http\Controllers\Api\Reports\FinancialReportController::class, 'investments']);
    Route::get('/contributions', [App\Http\Controllers\Api\Reports\FinancialReportController::class, 'contributions']);
    Route::get('/payments', [App\Http\Controllers\Api\Reports\FinancialReportController::class, 'payments']);
    Route::get('/monthly-trends', [App\Http\Controllers\Api\Reports\FinancialReportController::class, 'monthlyTrends']);
});
 

}); // End member_subscription middleware group

    

    // Document routes - Public (for registration/onboarding)
    Route::prefix('documents')->group(function () {
        // Public document upload during registration (no auth required)
        Route::post('/upload', [App\Http\Controllers\Api\Documents\DocumentController::class, 'uploadPublic']);
        Route::get('/{document}/view', [App\Http\Controllers\Api\Documents\DocumentController::class, 'viewPublic']);
    });



    // Onboarding routes
    Route::prefix('onboarding')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Onboarding\OnboardingController::class, 'index']);
        Route::put('/steps/{step}', [App\Http\Controllers\Api\Onboarding\OnboardingController::class, 'updateStep']);
        Route::post('/steps/{step}/complete', [App\Http\Controllers\Api\Onboarding\OnboardingController::class, 'completeStep']);
        Route::post('/steps/{step}/skip', [App\Http\Controllers\Api\Onboarding\OnboardingController::class, 'skipStep']);
        Route::post('/reset', [App\Http\Controllers\Api\Onboarding\OnboardingController::class, 'reset']);
        Route::get('/next-step', [App\Http\Controllers\Api\Onboarding\OnboardingController::class, 'getNextStep']);
    });

    // Business Onboarding routes
    Route::prefix('business-onboarding')->group(function () {
        Route::post('/', [App\Http\Controllers\Api\Onboarding\BusinessOnboardingController::class, 'store']);
        Route::get('/check-slug/{slug}', [App\Http\Controllers\Api\Onboarding\BusinessOnboardingController::class, 'checkSlugAvailability']);
    });

    
    

// Tenant-specific routes
Route::middleware(['tenant'])->group(function () {
    Route::get('tenant/current', function () {
        return response()->json([
            'tenant' => tenant()
        ]);
    });
    
  
    // Tenant analytics
    Route::get('tenant/analytics/summary', [App\Http\Controllers\Api\Tenant\AnalyticsController::class, 'summary']);
    // Public tenant landing page (no auth, tenant context required)
    Route::get('landing-page', [App\Http\Controllers\Api\Public\LandingPageController::class, 'show']);
});

// Public platform routes (no tenant context required)
Route::prefix('platform')->group(function () {
    Route::get('stats', [App\Http\Controllers\Api\Public\PlatformController::class, 'stats']);
});

// Admin routes  
Route::prefix('admin')->middleware(['tenant', 'tenant_auth', 'role:admin', 'tenant_subscription'])->group(function () {
    // Dashboard routes (tenant admin dashboard)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Dashboard\DashboardController::class, 'stats']);
        Route::get('/admin-stats', [App\Http\Controllers\Api\Dashboard\DashboardController::class, 'adminStats']);
    });
    
    // Member Management routes
    Route::prefix('members')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Members\MemberController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Members\MemberController::class, 'store']);
        Route::get('/stats', [App\Http\Controllers\Api\Members\MemberController::class, 'stats']);
        Route::get('/{id}', [App\Http\Controllers\Api\Members\MemberController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Members\MemberController::class, 'update']);
        Route::post('/{id}/activate', [App\Http\Controllers\Api\Members\MemberController::class, 'activate']);
        Route::post('/{id}/deactivate', [App\Http\Controllers\Api\Members\MemberController::class, 'deactivate']);
        Route::post('/{id}/suspend', [App\Http\Controllers\Api\Members\MemberController::class, 'suspend']);
        Route::post('/{id}/unsuspend', [App\Http\Controllers\Api\Members\MemberController::class, 'unsuspend']);
        Route::get('/{id}/kyc-status', [App\Http\Controllers\Api\Members\MemberController::class, 'kycStatus']);
        Route::post('/{id}/kyc/approve', [App\Http\Controllers\Api\Members\MemberController::class, 'approveKyc']);
        Route::post('/{id}/kyc/reject', [App\Http\Controllers\Api\Members\MemberController::class, 'rejectKyc']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Members\MemberController::class, 'destroy']);
    });
    
    // User Management routes (authenticated)
    Route::prefix('users')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\UserController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\UserController::class, 'store']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\UserController::class, 'stats']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\UserController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\UserController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\UserController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Api\Admin\UserController::class, 'toggleStatus']);
    });
    
    // Role Management routes (authenticated)
    Route::prefix('roles')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\RoleController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\RoleController::class, 'store']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\RoleController::class, 'stats']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\RoleController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\RoleController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\RoleController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Api\Admin\RoleController::class, 'toggleStatus']);
        Route::get('/{id}/permissions', [App\Http\Controllers\Api\Admin\RoleController::class, 'permissions']);
    });
    
    // Permission Management routes (authenticated)
    Route::prefix('permissions')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\PermissionController::class, 'index']);
        Route::get('/grouped', [App\Http\Controllers\Api\Admin\PermissionController::class, 'grouped']);
        Route::get('/groups', [App\Http\Controllers\Api\Admin\PermissionController::class, 'groups']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\PermissionController::class, 'stats']);
        Route::post('/', [App\Http\Controllers\Api\Admin\PermissionController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\PermissionController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\PermissionController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\PermissionController::class, 'destroy']);
    });
    
    Route::prefix('custom-domains')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\CustomDomainController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\CustomDomainController::class, 'store']);
        Route::post('/{id}/verify', [App\Http\Controllers\Api\Admin\CustomDomainController::class, 'verify']);
        Route::post('/{id}/check-verification', [App\Http\Controllers\Api\Admin\CustomDomainController::class, 'checkVerification']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\CustomDomainController::class, 'destroy']);
    });
    
    Route::prefix('settings')->group(function () {
        // GET route is public (moved above), only modification routes are protected
        Route::post('/', [App\Http\Controllers\Api\Admin\SettingsController::class, 'store']);
        Route::get('/category/{category}', [App\Http\Controllers\Api\Admin\SettingsController::class, 'getByCategory']);
        Route::post('/test-email', [App\Http\Controllers\Api\Admin\SettingsController::class, 'testEmail']);
    });
    
    Route::prefix('landing-page')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\LandingPageController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\LandingPageController::class, 'store']);
        Route::post('publish', [App\Http\Controllers\Api\Admin\LandingPageController::class, 'publish']);
    });

     // Admin - Landing Page management (tenant)
        Route::get('landing-page', [App\Http\Controllers\Api\Admin\LandingPageController::class, 'index']);
        Route::post('landing-page', [App\Http\Controllers\Api\Admin\LandingPageController::class, 'store']);
        Route::post('landing-page/publish', [App\Http\Controllers\Api\Admin\LandingPageController::class, 'publish']);
        Route::get('landing-page/available-items', [App\Http\Controllers\Api\Admin\LandingPageController::class, 'availableItems']);

    
    Route::prefix('payment-gateways')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'store']);
        Route::get('/{gateway}', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'show'])->where('gateway', '[a-zA-Z0-9_-]+');
        Route::put('/{gateway}', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'update'])->where('gateway', '[a-zA-Z0-9_-]+');
        Route::post('/{gateway}/toggle', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'toggle'])->where('gateway', '[a-zA-Z0-9_-]+');
        Route::post('/{gateway}/test', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'test'])->where('gateway', '[a-zA-Z0-9_-]+');
        Route::delete('/{gateway}', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'destroy'])->where('gateway', '[a-zA-Z0-9_-]+');
    });
    
    // Tenant Payment Approval Routes
    Route::prefix('payment-approvals')->group(function () {

        Route::get('/', [App\Http\Controllers\Api\Admin\TenantPaymentApprovalController::class, 'index']);
        Route::get('/reconciliation/data', [App\Http\Controllers\Api\Admin\TenantPaymentApprovalController::class, 'getReconciliationData']);
        Route::get('/logs/payments', [App\Http\Controllers\Api\Admin\TenantPaymentApprovalController::class, 'getPaymentLogs']);
        Route::post('/manual-payment', [App\Http\Controllers\Api\Admin\TenantPaymentApprovalController::class, 'submitManualPayment']);
        Route::get('/{payment}', [App\Http\Controllers\Api\Admin\TenantPaymentApprovalController::class, 'show']);
       
        Route::post('/{payment}/approve', [App\Http\Controllers\Api\Admin\TenantPaymentApprovalController::class, 'approve']);
        Route::post('/{payment}/reject', [App\Http\Controllers\Api\Admin\TenantPaymentApprovalController::class, 'reject']);
    });
    
    Route::prefix('white-label')->group(function () {
        // GET route is public (moved above), only modification routes are protected
        Route::post('/', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'store']);
        Route::put('/{settings}', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'update']);
        Route::post('/toggle', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'toggle']);
        Route::post('/upload-logo', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'uploadLogo']);
        Route::post('/upload-logo-dark', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'uploadLogoDark']);
        Route::post('/upload-favicon', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'uploadFavicon']);
        Route::post('/upload-login-background', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'uploadLoginBackground']);
        Route::post('/upload-dashboard-hero', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'uploadDashboardHero']);
        Route::post('/upload-email-logo', [App\Http\Controllers\Api\Admin\WhiteLabelController::class, 'uploadEmailLogo']);
    });
    
    // Wallet Management routes
    Route::prefix('wallets')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\WalletController::class, 'index']);
        Route::get('/transactions', [App\Http\Controllers\Api\Admin\WalletController::class, 'transactions']);
        Route::get('/pending', [App\Http\Controllers\Api\Admin\WalletController::class, 'pending']);
        Route::get('/{walletId}', [App\Http\Controllers\Api\Admin\WalletController::class, 'show']);
        Route::post('/withdrawals/{withdrawalId}/approve', [App\Http\Controllers\Api\Admin\WalletController::class, 'approveWithdrawal']);
        Route::post('/withdrawals/{withdrawalId}/reject', [App\Http\Controllers\Api\Admin\WalletController::class, 'rejectWithdrawal']);
    });
    
    // Investment Withdrawal Request routes
    Route::prefix('investment-withdrawal-requests')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\InvestmentWithdrawalRequestController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\InvestmentWithdrawalRequestController::class, 'stats']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\InvestmentWithdrawalRequestController::class, 'show']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\InvestmentWithdrawalRequestController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\InvestmentWithdrawalRequestController::class, 'reject']);
        Route::post('/{id}/process', [App\Http\Controllers\Api\Admin\InvestmentWithdrawalRequestController::class, 'process']);
    });
    
    // Refund routes
    Route::prefix('refunds')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\RefundController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\RefundController::class, 'stats']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\RefundController::class, 'show']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\RefundController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\RefundController::class, 'reject']);
    });
    
    // Refund member routes (legacy - direct refund processing)
    Route::prefix('refund-member')->group(function () {
        Route::get('/{member}', [App\Http\Controllers\Api\Admin\RefundController::class, 'summary']);
        Route::post('/', [App\Http\Controllers\Api\Admin\RefundController::class, 'refundMember']);
    });
    
    // Mortgage Provider routes
    Route::prefix('mortgage-providers')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\MortgageProviderController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\MortgageProviderController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\MortgageProviderController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\MortgageProviderController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\MortgageProviderController::class, 'destroy']);
    });
    
    // Mortgage routes
    Route::prefix('mortgages')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\MortgageController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\MortgageController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\MortgageController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\MortgageController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\MortgageController::class, 'destroy']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\MortgageController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\MortgageController::class, 'reject']);
        Route::post('/{id}/repay', [App\Http\Controllers\Api\Admin\MortgageRepaymentController::class, 'repayMortgage']);
        Route::get('/{id}/repayment-schedule', [App\Http\Controllers\Api\Admin\MortgageRepaymentController::class, 'getRepaymentSchedule']);
        Route::get('/{id}/next-payment', [App\Http\Controllers\Api\Admin\MortgageRepaymentController::class, 'getNextPayment']);
    });
    
    // Contribution routes
    Route::prefix('contributions')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\ContributionController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\ContributionController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\ContributionController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\ContributionController::class, 'destroy']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\ContributionController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\ContributionController::class, 'reject']);
    });
    
    // Loan routes
    Route::prefix('loans')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\LoanController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\LoanController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\LoanController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\LoanController::class, 'destroy']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\LoanController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\LoanController::class, 'reject']);
        Route::post('/{id}/disburse', [App\Http\Controllers\Api\Admin\LoanController::class, 'disburse']);
    });

    Route::prefix('loan-repayments')->group(function () {
        Route::get('/members', [App\Http\Controllers\Api\Admin\LoanRepaymentController::class, 'searchMembers']);
        Route::get('/members/{member}/loans', [App\Http\Controllers\Api\Admin\LoanRepaymentController::class, 'memberLoans']);
        Route::post('/', [App\Http\Controllers\Api\Admin\LoanRepaymentController::class, 'store']);
    });
    
    // Property routes
    Route::prefix('properties')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\PropertyController::class, 'index']);
        Route::post('/upload-image', [App\Http\Controllers\Api\Admin\PropertyController::class, 'uploadImage']);
        Route::post('/', [App\Http\Controllers\Api\Admin\PropertyController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\PropertyController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\PropertyController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\PropertyController::class, 'destroy']);
    });

    Route::prefix('property-payment-plans')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\PropertyPaymentPlanController::class, 'index']);
        Route::get('/pending-interests', [App\Http\Controllers\Api\Admin\PropertyPaymentPlanController::class, 'pendingInterests']);
        Route::get('/details', [App\Http\Controllers\Api\Admin\PropertyPaymentPlanController::class, 'getPropertyPaymentPlanDetails']);
        Route::post('/', [App\Http\Controllers\Api\Admin\PropertyPaymentPlanController::class, 'store']);
        Route::get('/{plan}', [App\Http\Controllers\Api\Admin\PropertyPaymentPlanController::class, 'show']);
        Route::put('/{plan}', [App\Http\Controllers\Api\Admin\PropertyPaymentPlanController::class, 'update']);
    });

    Route::prefix('internal-mortgages')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\InternalMortgagePlanController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\InternalMortgagePlanController::class, 'store']);
        Route::get('/{plan}', [App\Http\Controllers\Api\Admin\InternalMortgagePlanController::class, 'show']);
        Route::post('/{plan}/repay', [App\Http\Controllers\Api\Admin\MortgageRepaymentController::class, 'repayInternalMortgage']);
        Route::get('/{plan}/repayment-schedule', [App\Http\Controllers\Api\Admin\MortgageRepaymentController::class, 'getInternalRepaymentSchedule']);
        Route::get('/{plan}/next-payment', [App\Http\Controllers\Api\Admin\MortgageRepaymentController::class, 'getNextInternalPayment']);
    });
    
    // EOI Forms routes
    Route::prefix('eoi-forms')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\EoiFormController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\EoiFormController::class, 'show']);
        Route::get('/{id}/download', [App\Http\Controllers\Api\Admin\EoiFormController::class, 'download']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\EoiFormController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\EoiFormController::class, 'reject']);
    });
    
    // Investment Plans routes
    Route::prefix('investment-plans')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Admin\InvestmentPlanController::class, 'stats']);
        Route::get('/', [App\Http\Controllers\Api\Admin\InvestmentPlanController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\InvestmentPlanController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\InvestmentPlanController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\InvestmentPlanController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\InvestmentPlanController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Api\Admin\InvestmentPlanController::class, 'toggleStatus']);
    });
    
    // Contribution Plans routes
    Route::prefix('contribution-plans')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\ContributionPlanController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\ContributionPlanController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\ContributionPlanController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\ContributionPlanController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\ContributionPlanController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Api\Admin\ContributionPlanController::class, 'toggleStatus']);
    });
    
    // Equity Contribution routes
    Route::prefix('equity-contributions')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\EquityContributionController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\EquityContributionController::class, 'show']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\EquityContributionController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\EquityContributionController::class, 'reject']);
    });
    
    // Equity Plans routes
    Route::prefix('equity-plans')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\EquityPlanController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\EquityPlanController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\EquityPlanController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\EquityPlanController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\EquityPlanController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Api\Admin\EquityPlanController::class, 'toggleStatus']);
    });
    
    // Loan Products routes (Admin)
    Route::prefix('loan-products')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\LoanProductController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\LoanProductController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\LoanProductController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\LoanProductController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\LoanProductController::class, 'destroy']);
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Api\Admin\LoanProductController::class, 'toggleStatus']);
    });
    
    // Statutory Charges routes
    Route::prefix('statutory-charges')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'stats']);
        Route::get('/', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'store']);
        // Move specific routes before parameterized routes to avoid route conflicts
        Route::prefix('types')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Admin\StatutoryChargeTypeController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Admin\StatutoryChargeTypeController::class, 'store']);
            Route::put('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargeTypeController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargeTypeController::class, 'destroy']);
        });
        Route::prefix('payments')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Admin\StatutoryChargePaymentController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Admin\StatutoryChargePaymentController::class, 'store']);
            Route::get('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargePaymentController::class, 'show']);
        });
        Route::prefix('departments')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Admin\StatutoryChargeDepartmentController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Admin\StatutoryChargeDepartmentController::class, 'store']);
            Route::put('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargeDepartmentController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargeDepartmentController::class, 'destroy']);
        });
        // Parameterized routes must come after specific routes
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'destroy']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\StatutoryChargeController::class, 'reject']);
    });
    
    // Property Management routes
    Route::prefix('property-management')->group(function () {
        Route::prefix('estates')->group(function () {
            
            Route::get('/stats', [App\Http\Controllers\Api\Admin\PropertyEstateController::class, 'stats']);
            Route::get('/', [App\Http\Controllers\Api\Admin\PropertyEstateController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Admin\PropertyEstateController::class, 'store']);
            Route::get('/{id}', [App\Http\Controllers\Api\Admin\PropertyEstateController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\Api\Admin\PropertyEstateController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Api\Admin\PropertyEstateController::class, 'destroy']);
        });
        Route::prefix('allottees')->group(function () {
            Route::get('/stats', [App\Http\Controllers\Api\Admin\PropertyAllotteeController::class, 'stats']);
            Route::get('/', [App\Http\Controllers\Api\Admin\PropertyAllotteeController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Admin\PropertyAllotteeController::class, 'store']);
            Route::get('/{id}', [App\Http\Controllers\Api\Admin\PropertyAllotteeController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\Api\Admin\PropertyAllotteeController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Api\Admin\PropertyAllotteeController::class, 'destroy']);
        });
        Route::prefix('maintenance')->group(function () {
            Route::get('/stats', [App\Http\Controllers\Api\Admin\PropertyMaintenanceController::class, 'stats']);
            Route::get('/', [App\Http\Controllers\Api\Admin\PropertyMaintenanceController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\Admin\PropertyMaintenanceController::class, 'store']);
            Route::get('/{id}', [App\Http\Controllers\Api\Admin\PropertyMaintenanceController::class, 'show']);
            Route::put('/{id}', [App\Http\Controllers\Api\Admin\PropertyMaintenanceController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\Api\Admin\PropertyMaintenanceController::class, 'destroy']);
        });
        Route::prefix('reports')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\Admin\PropertyManagementReportController::class, 'index']);
            Route::get('/estate', [App\Http\Controllers\Api\Admin\PropertyManagementReportController::class, 'estateReport']);
            Route::get('/allottee', [App\Http\Controllers\Api\Admin\PropertyManagementReportController::class, 'allotteeReport']);
            Route::get('/maintenance', [App\Http\Controllers\Api\Admin\PropertyManagementReportController::class, 'maintenanceReport']);
        });
    });
    
    // Blockchain Setup Wizard routes
    Route::prefix('blockchain-setup')->group(function () {
        
        Route::get('/status', [App\Http\Controllers\Api\Admin\BlockchainSetupController::class, 'status']);
        Route::post('/step-1-network', [App\Http\Controllers\Api\Admin\BlockchainSetupController::class, 'step1_NetworkSettings']);
        Route::post('/step-2-explorer', [App\Http\Controllers\Api\Admin\BlockchainSetupController::class, 'step2_ExplorerApiKeys']);
        Route::post('/step-3-contracts', [App\Http\Controllers\Api\Admin\BlockchainSetupController::class, 'step3_SmartContracts']);
        Route::post('/step-4-wallet', [App\Http\Controllers\Api\Admin\BlockchainSetupController::class, 'step4_CreateWallet']);
        Route::post('/step-5-complete', [App\Http\Controllers\Api\Admin\BlockchainSetupController::class, 'step5_CompleteSetup']);
        Route::post('/test-connection', [App\Http\Controllers\Api\Admin\BlockchainSetupController::class, 'testConnection']);
    });
    
    // Blockchain Wallet Management routes
    Route::prefix('blockchain-wallets')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\BlockchainWalletController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\BlockchainWalletController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\BlockchainWalletController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\BlockchainWalletController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\BlockchainWalletController::class, 'destroy']);
        Route::post('/{id}/set-default', [App\Http\Controllers\Api\Admin\BlockchainWalletController::class, 'setDefault']);
        Route::post('/{id}/sync-balance', [App\Http\Controllers\Api\Admin\BlockchainWalletController::class, 'syncBalance']);
    });
    
    // Blockchain Property Management routes
    Route::prefix('blockchain')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Admin\BlockchainPropertyController::class, 'stats']);
        Route::get('/', [App\Http\Controllers\Api\Admin\BlockchainPropertyController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\BlockchainPropertyController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\BlockchainPropertyController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\BlockchainPropertyController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\BlockchainPropertyController::class, 'destroy']);
        Route::post('/{id}/verify', [App\Http\Controllers\Api\Admin\BlockchainPropertyController::class, 'verifyTransaction']);
    });
    
    // Mail Service routes
    Route::prefix('mail-service')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'stats']);
        Route::get('/inbox', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'inbox']);
        Route::get('/drafts', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'drafts']);
        Route::get('/sent', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'sent']);
        Route::get('/outbox', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'outbox']);
        Route::post('/compose', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'compose']);
        Route::post('/bulk', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'bulk']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\AdminMailServiceController::class, 'destroy']);
    });
    
    // Admin Reports routes
    Route::prefix('reports')->group(function () {
        Route::get('/members', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'members']);
        Route::get('/financial', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'financial']);
        Route::get('/contributions', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'contributions']);
        Route::get('/equity-contributions', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'equityContributions']);
        Route::get('/investments', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'investments']);
        Route::get('/loans', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'loans']);
        Route::get('/properties', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'properties']);
        Route::get('/mail-service', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'mailService']);
        Route::get('/audit', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'audit']);
        Route::post('/export', [App\Http\Controllers\Api\Admin\AdminReportsController::class, 'export']);
    });
    
    // Admin Activity Logs routes
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\AdminActivityLogsController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\AdminActivityLogsController::class, 'stats']);
        Route::get('/{activityLog}', [App\Http\Controllers\Api\Admin\AdminActivityLogsController::class, 'show']);
    });
    
    // Admin Audit Logs routes
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\AdminAuditLogController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\AdminAuditLogController::class, 'stats']);
        Route::get('/export', [App\Http\Controllers\Api\Admin\AdminAuditLogController::class, 'export']);
        Route::get('/resource/{resourceType}/{resourceId}', [App\Http\Controllers\Api\Admin\AdminAuditLogController::class, 'getResourceLogs']);
        Route::get('/user/{user}', [App\Http\Controllers\Api\Admin\AdminAuditLogController::class, 'getUserLogs']);
        Route::get('/{auditLog}', [App\Http\Controllers\Api\Admin\AdminAuditLogController::class, 'show']);
    });
    
    // Admin Documents routes
    Route::prefix('documents')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'show']);
        Route::get('/{id}/download', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'download']);
        Route::get('/{id}/view', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'view']);
        Route::post('/{id}/approve', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'reject']);
        Route::delete('/{id}', [App\Http\Controllers\Api\Admin\AdminDocumentsController::class, 'destroy']);
    });
    
    // Admin Notifications routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'stats']);
        Route::get('/unread-count', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'unreadCount']);
        Route::post('/', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'store']);
        Route::post('/bulk', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'bulkStore']);
        Route::post('/broadcast', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'broadcast']);
        Route::get('/user/{user}', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'getByUser']);
        Route::get('/{notification}', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'show']);
        Route::put('/{notification}', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'update']);
        Route::post('/{notification}/read', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'markAsRead']);
        Route::post('/mark-multiple-read', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'markMultipleAsRead']);
        Route::post('/mark-all-read', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'markAllAsRead']);
        Route::post('/bulk-delete', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'bulkDestroy']);
        Route::delete('/{notification}', [App\Http\Controllers\Api\Admin\AdminNotificationController::class, 'destroy']);
    });
    
    // Bulk Upload routes
    Route::prefix('bulk')->group(function () {
        Route::get('/mortgages/template', [App\Http\Controllers\Api\Admin\BulkMortgageController::class, 'downloadTemplate']);
        Route::post('/mortgages/upload', [App\Http\Controllers\Api\Admin\BulkMortgageController::class, 'uploadBulk']);
        Route::get('/properties/template', [App\Http\Controllers\Api\Admin\BulkPropertyController::class, 'downloadTemplate']);
        Route::post('/properties/upload', [App\Http\Controllers\Api\Admin\BulkPropertyController::class, 'uploadBulk']);
        Route::get('/contributions/template', [App\Http\Controllers\Api\Admin\BulkContributionController::class, 'downloadTemplate']);
        Route::post('/contributions/upload', [App\Http\Controllers\Api\Admin\BulkContributionController::class, 'uploadBulk']);
        Route::get('/equity-contributions/template', [App\Http\Controllers\Api\Admin\BulkEquityContributionController::class, 'downloadTemplate']);
        Route::post('/equity-contributions/upload', [App\Http\Controllers\Api\Admin\BulkEquityContributionController::class, 'uploadBulk']);
        Route::get('/loans/template', [App\Http\Controllers\Api\Admin\BulkLoanController::class, 'downloadTemplate']);
        Route::post('/loans/upload', [App\Http\Controllers\Api\Admin\BulkLoanController::class, 'uploadBulk']);
        Route::get('/loan-repayments/template', [App\Http\Controllers\Api\Admin\BulkLoanRepaymentController::class, 'downloadTemplate']);
        Route::post('/loan-repayments/upload', [App\Http\Controllers\Api\Admin\BulkLoanRepaymentController::class, 'uploadBulk']);
        Route::get('/mortgage-repayments/template', [App\Http\Controllers\Api\Admin\BulkMortgageRepaymentController::class, 'downloadTemplate']);
        Route::post('/mortgage-repayments/upload', [App\Http\Controllers\Api\Admin\BulkMortgageRepaymentController::class, 'uploadBulk']);
        Route::get('/internal-mortgage-repayments/template', [App\Http\Controllers\Api\Admin\BulkMortgageRepaymentController::class, 'downloadInternalTemplate']);
        Route::post('/internal-mortgage-repayments/upload', [App\Http\Controllers\Api\Admin\BulkMortgageRepaymentController::class, 'uploadInternalBulk']);
        Route::get('/refund/template', [App\Http\Controllers\Api\Admin\BulkRefundController::class, 'downloadTemplate']);
        Route::post('/refund/upload', [App\Http\Controllers\Api\Admin\BulkRefundController::class, 'uploadBulk']);
    });
});

// Admin Payment Evidence Upload
Route::post('admin/payment-evidence/upload', [App\Http\Controllers\Api\Admin\AdminPaymentEvidenceController::class, 'uploadPaymentEvidence']);

// Webhook routes (no auth required, signature verified)
Route::prefix('webhooks')->group(function () {
    Route::post('/blockchain', [App\Http\Controllers\Api\Webhooks\BlockchainWebhookController::class, 'handle']);
});



//=================================================== Super Admin Routes ===================================================
// Super Admin Payment Gateways routes (moved outside admin group)
Route::prefix('super-admin')->middleware(['super_admin_auth'])->group(function () {

        // Dashboard routes
        Route::prefix('dashboard')->group(function () {
            Route::get('/overview', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'overview']);
            Route::get('/metrics', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'metrics']);
            Route::get('/recent-businesses', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'recentBusinesses']);
            Route::get('/revenue-analytics', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'revenueAnalytics']);
            Route::get('/subscription-analytics', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'subscriptionAnalytics']);
            Route::get('/system-health', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'systemHealth']);
            Route::get('/alerts', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'alerts']);
            Route::get('/platform-stats', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'platformStats']);
            Route::get('/test-member-count', [App\Http\Controllers\Api\SuperAdmin\DashboardController::class, 'testMemberCount']);
        });
            
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\BusinessSubscriptionController::class, 'index']);
            Route::get('/{subscription}', [App\Http\Controllers\Api\SuperAdmin\BusinessSubscriptionController::class, 'show']);
            Route::post('/{subscription}/cancel', [App\Http\Controllers\Api\SuperAdmin\BusinessSubscriptionController::class, 'cancel']);
            Route::post('/{subscription}/reactivate', [App\Http\Controllers\Api\SuperAdmin\BusinessSubscriptionController::class, 'reactivate']);
            Route::post('/{subscription}/extend', [App\Http\Controllers\Api\SuperAdmin\BusinessSubscriptionController::class, 'extend']);
            Route::post('/{subscription}/approve-payment', [App\Http\Controllers\Api\SuperAdmin\BusinessSubscriptionController::class, 'approvePayment']);
            Route::post('/{subscription}/reject-payment', [App\Http\Controllers\Api\SuperAdmin\BusinessSubscriptionController::class, 'rejectPayment']);
        });

        Route::prefix('member-subscriptions')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionController::class, 'store']);
            Route::get('/pending', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionApprovalController::class, 'pending']);
            Route::post('/{subscription}/approve', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionApprovalController::class, 'approve']);
            Route::post('/{subscription}/reject', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionApprovalController::class, 'reject']);
            Route::get('/{subscription}', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionController::class, 'show']);
            Route::put('/{subscription}', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionController::class, 'update']);
            Route::post('/{subscription}/cancel', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionController::class, 'cancel']);
            Route::post('/{subscription}/extend', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionController::class, 'extend']);
            Route::delete('/{subscription}', [App\Http\Controllers\Api\SuperAdmin\MemberSubscriptionController::class, 'destroy']);
        });
        Route::get('/payment-gateways', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'index']);
        Route::put('/payment-gateways/{gateway}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'update']);
        Route::post('/payment-gateways/{gateway}/test', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'testConnection']);
        Route::get('/payment-gateways/platform-stats', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'getPlatformStats']);
        Route::post('/payment-gateways/subscription-payment', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'initializeSubscriptionPayment']);
        Route::post('/payment-gateways/member-subscription-payment', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'initializeMemberSubscriptionPayment']);
        Route::get('/payment-gateways/verify/{reference}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'verifyPayment']);
        Route::get('/payment-gateways/transactions', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'getTransactionHistory']);
        Route::get('/payment-gateways/revenue-analytics', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'getRevenueAnalytics']);
        Route::get('/payment-gateways/callback', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'handleCallback']);
        Route::post('/payment-gateways/manual-payment', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPaymentGatewayController::class, 'submitManualPayment']);
        
        // Payment Approval Routes
        Route::prefix('payment-approvals')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\PaymentApprovalController::class, 'index']);
            Route::get('/{transaction}', [App\Http\Controllers\Api\SuperAdmin\PaymentApprovalController::class, 'show']);
            Route::post('/{transaction}/approve', [App\Http\Controllers\Api\SuperAdmin\PaymentApprovalController::class, 'approve']);
            Route::post('/{transaction}/reject', [App\Http\Controllers\Api\SuperAdmin\PaymentApprovalController::class, 'reject']);
            Route::get('/logs/payments', [App\Http\Controllers\Api\SuperAdmin\PaymentApprovalController::class, 'getPaymentLogs']);
            Route::get('/reconciliation/data', [App\Http\Controllers\Api\SuperAdmin\PaymentApprovalController::class, 'getReconciliationData']);
        });
        
        // Invoices routes
        Route::prefix('invoices')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\InvoiceController::class, 'index']);
            Route::get('/{invoice}', [App\Http\Controllers\Api\SuperAdmin\InvoiceController::class, 'show']);
            Route::get('/{invoice}/download', [App\Http\Controllers\Api\SuperAdmin\InvoiceController::class, 'download']);
            Route::post('/{invoice}/resend', [App\Http\Controllers\Api\SuperAdmin\InvoiceController::class, 'resend']);
        });
        
        // Domain requests routes
        Route::prefix('domain-requests')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\DomainRequestController::class, 'index']);
            Route::get('/{domainRequest}', [App\Http\Controllers\Api\SuperAdmin\DomainRequestController::class, 'show']);
            Route::post('/{domainRequest}/review', [App\Http\Controllers\Api\SuperAdmin\DomainRequestController::class, 'review']);
            Route::post('/{domainRequest}/verify', [App\Http\Controllers\Api\SuperAdmin\DomainRequestController::class, 'verify']);
            Route::post('/{domainRequest}/activate', [App\Http\Controllers\Api\SuperAdmin\DomainRequestController::class, 'activate']);
        });

        // Profile routes
        Route::prefix('profile')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminProfileController::class, 'show']);
            Route::put('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminProfileController::class, 'update']);
            Route::post('/change-password', [App\Http\Controllers\Api\SuperAdmin\SuperAdminProfileController::class, 'changePassword']);
        });

        // Notification routes
        Route::prefix('notifications')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminNotificationController::class, 'index']);
            Route::get('/{notification}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminNotificationController::class, 'show']);
            Route::post('/{notification}/read', [App\Http\Controllers\Api\SuperAdmin\SuperAdminNotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [App\Http\Controllers\Api\SuperAdmin\SuperAdminNotificationController::class, 'markAllAsRead']);
            Route::get('/unread-count', [App\Http\Controllers\Api\SuperAdmin\SuperAdminNotificationController::class, 'unreadCount']);
            Route::delete('/{notification}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminNotificationController::class, 'destroy']);
        });

        Route::prefix('businesses')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'store']);
        Route::get('/{business}', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'show']);
        Route::put('/{business}', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'update']);
        Route::post('/{business}/suspend', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'suspend']);
        Route::post('/{business}/activate', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'activate']);
        Route::delete('/{business}', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'destroy']);
        });
        // Business domains management
        Route::prefix('{business}/domains')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'domains']);
            Route::post('/', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'addDomain']);
            Route::post('/{domain}/verify', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'verifyDomain']);
            Route::delete('/{domain}', [App\Http\Controllers\Api\SuperAdmin\BusinessController::class, 'deleteDomain']);
        });

        // Tenant provisioning
        Route::post('/tenants', [App\Http\Controllers\Api\SuperAdmin\TenantProvisioningController::class, 'store']);

        Route::prefix('packages')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\SuperAdmin\PackageController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\SuperAdmin\PackageController::class, 'store']);
        Route::get('/{package}', [App\Http\Controllers\Api\SuperAdmin\PackageController::class, 'show']);
        Route::put('/{package}', [App\Http\Controllers\Api\SuperAdmin\PackageController::class, 'update']);
        Route::post('/{package}/toggle', [App\Http\Controllers\Api\SuperAdmin\PackageController::class, 'toggle']);
        Route::delete('/{package}', [App\Http\Controllers\Api\SuperAdmin\PackageController::class, 'destroy']);
        });

        Route::prefix('modules')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\ModuleController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\SuperAdmin\ModuleController::class, 'store']);
            Route::get('/{module}', [App\Http\Controllers\Api\SuperAdmin\ModuleController::class, 'show']);
            Route::put('/{module}', [App\Http\Controllers\Api\SuperAdmin\ModuleController::class, 'update']);
            Route::post('/{module}/toggle', [App\Http\Controllers\Api\SuperAdmin\ModuleController::class, 'toggle']);
            Route::delete('/{module}', [App\Http\Controllers\Api\SuperAdmin\ModuleController::class, 'destroy']);
        });

        Route::prefix('white-label-packages')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\WhiteLabelPackageController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\SuperAdmin\WhiteLabelPackageController::class, 'store']);
            Route::get('/{whiteLabelPackage}', [App\Http\Controllers\Api\SuperAdmin\WhiteLabelPackageController::class, 'show']);
            Route::put('/{whiteLabelPackage}', [App\Http\Controllers\Api\SuperAdmin\WhiteLabelPackageController::class, 'update']);
            Route::post('/{whiteLabelPackage}/toggle', [App\Http\Controllers\Api\SuperAdmin\WhiteLabelPackageController::class, 'toggle']);
            Route::delete('/{whiteLabelPackage}', [App\Http\Controllers\Api\SuperAdmin\WhiteLabelPackageController::class, 'destroy']);
        });

        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [App\Http\Controllers\Api\SuperAdmin\AnalyticsController::class, 'dashboard']);
            Route::get('/revenue', [App\Http\Controllers\Api\SuperAdmin\AnalyticsController::class, 'revenue']);
            Route::get('/businesses', [App\Http\Controllers\Api\SuperAdmin\AnalyticsController::class, 'businesses']);
            Route::get('/activity', [App\Http\Controllers\Api\SuperAdmin\AnalyticsController::class, 'activity']);
            Route::get('/test', [App\Http\Controllers\Api\SuperAdmin\AnalyticsController::class, 'test']);
        });

        Route::prefix('mail')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminMailController::class, 'index']);
            Route::post('/send', [App\Http\Controllers\Api\SuperAdmin\SuperAdminMailController::class, 'sendMail']);
            Route::post('/save-template', [App\Http\Controllers\Api\SuperAdmin\SuperAdminMailController::class, 'saveTemplate']);
            Route::get('/templates', [App\Http\Controllers\Api\SuperAdmin\SuperAdminMailController::class, 'getTemplates']);
            Route::get('/history', [App\Http\Controllers\Api\SuperAdmin\SuperAdminMailController::class, 'getHistory']);
        });

        Route::prefix('admins')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminManagementController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminManagementController::class, 'store']);
            Route::get('/{admin}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminManagementController::class, 'show']);
            Route::put('/{admin}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminManagementController::class, 'update']);
            Route::delete('/{admin}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminManagementController::class, 'destroy']);
            Route::post('/{admin}/toggle-status', [App\Http\Controllers\Api\SuperAdmin\SuperAdminManagementController::class, 'toggleStatus']);
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminRoleController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminRoleController::class, 'store']);
            Route::get('/{role}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminRoleController::class, 'show']);
            Route::put('/{role}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminRoleController::class, 'update']);
            Route::delete('/{role}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminRoleController::class, 'destroy']);
            Route::get('/{role}/permissions', [App\Http\Controllers\Api\SuperAdmin\SuperAdminRoleController::class, 'getRolePermissions']);
        });

        Route::prefix('permissions')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPermissionController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPermissionController::class, 'store']);
            Route::get('/grouped', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPermissionController::class, 'getGroupedPermissions']);
            Route::get('/{permission}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPermissionController::class, 'show']);
            Route::put('/{permission}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPermissionController::class, 'update']);
            Route::delete('/{permission}', [App\Http\Controllers\Api\SuperAdmin\SuperAdminPermissionController::class, 'destroy']);
        });

            Route::prefix('settings')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'store']);
                Route::post('/bulk-update', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'bulkUpdate']);
                Route::post('/test-email', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'testEmailSettings']);
                Route::get('/category/{category}', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'getByCategory']);
                Route::get('/{setting}', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'show']);
                Route::put('/{setting}', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'update']);
                Route::delete('/{setting}', [App\Http\Controllers\Api\SuperAdmin\PlatformSettingsController::class, 'destroy']);
            });

 });


//=================================================== Super Admin Routes ===================================================