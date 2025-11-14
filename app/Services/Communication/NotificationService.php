<?php

namespace App\Services\Communication;

use App\Models\Tenant\Notification;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Send a single notification
     */
    public function sendNotification(Notification $notification): void
    {
        try {
            // Send real-time notification (WebSocket, Pusher, etc.)
            // This would integrate with your real-time service
            // Example: event(new NotificationSent($notification));
            
            Log::info('Notification sent successfully', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send bulk notifications to multiple users
     */
    public function sendBulkNotification(array $userIds, string $type, string $title, string $message, array $data = []): array
    {
        $notifications = [];
        
        try {
            DB::beginTransaction();
            
            foreach ($userIds as $userId) {
                $notification = Notification::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                ]);
                
                $this->sendNotification($notification);
                $notifications[] = $notification;
            }
            
            DB::commit();
            
            Log::info('Bulk notifications sent', [
                'count' => count($notifications),
                'type' => $type
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to send bulk notifications', [
                'error' => $e->getMessage(),
                'count' => count($userIds)
            ]);
            
            throw $e;
        }
        
        return $notifications;
    }

    /**
     * Send system notification to all users
     */
    public function sendSystemNotification(string $type, string $title, string $message, array $data = []): array
    {
        // Send to all users in the tenant
        $userIds = User::pluck('id')->toArray();
        
        return $this->sendBulkNotification($userIds, $type, $title, $message, $data);
    }

    /**
     * Send notification to users by role
     */
    public function sendNotificationToRole(string $role, string $type, string $title, string $message, array $data = []): array
    {
        $users = User::role($role)->get();
        $userIds = $users->pluck('id')->toArray();
        
        return $this->sendBulkNotification($userIds, $type, $title, $message, $data);
    }

    /**
     * Send notification to specific users
     */
    public function sendNotificationToUsers(array $userIds, string $type, string $title, string $message, array $data = []): array
    {
        return $this->sendBulkNotification($userIds, $type, $title, $message, $data);
    }

    /**
     * Create notification for specific events
     */
    public function notifyLoanApproved(User $user, $loanId, array $loanData = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'success',
            'title' => 'Loan Approved',
            'message' => 'Your loan application has been approved.',
            'data' => array_merge(['loan_id' => $loanId], $loanData),
        ]);

        $this->sendNotification($notification);
        return $notification;
    }

    public function notifyLoanRejected(User $user, $loanId, string $reason = ''): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'error',
            'title' => 'Loan Rejected',
            'message' => 'Your loan application has been rejected.' . ($reason ? ' Reason: ' . $reason : ''),
            'data' => ['loan_id' => $loanId, 'reason' => $reason],
        ]);

        $this->sendNotification($notification);
        return $notification;
    }

    public function notifyContributionReceived(User $user, $contributionId, array $contributionData = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'success',
            'title' => 'Contribution Received',
            'message' => 'Your contribution has been received and processed.',
            'data' => array_merge(['contribution_id' => $contributionId], $contributionData),
        ]);

        $this->sendNotification($notification);
        return $notification;
    }

    public function notifyKycApproved(User $user): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'success',
            'title' => 'KYC Approved',
            'message' => 'Your KYC verification has been approved.',
            'data' => [],
        ]);

        $this->sendNotification($notification);
        return $notification;
    }

    public function notifyKycRejected(User $user, string $reason = ''): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'warning',
            'title' => 'KYC Rejected',
            'message' => 'Your KYC verification has been rejected.' . ($reason ? ' Reason: ' . $reason : ''),
            'data' => ['reason' => $reason],
        ]);

        $this->sendNotification($notification);
        return $notification;
    }

    public function notifyPaymentReceived(User $user, $paymentId, array $paymentData = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'success',
            'title' => 'Payment Received',
            'message' => 'Your payment has been received successfully.',
            'data' => array_merge(['payment_id' => $paymentId], $paymentData),
        ]);

        $this->sendNotification($notification);
        return $notification;
    }

    /**
     * Notify all admin users in the tenant
     */
    public function notifyAdmins(string $type, string $title, string $message, array $data = []): array
    {
        // Get all admin and super_admin users
        $adminUsers = User::where(function($query) {
            $query->whereIn('role', ['admin', 'super_admin'])
                  ->orWhereHas('roles', function($q) {
                      $q->whereIn('name', ['admin', 'super_admin']);
                  });
        })->get();

        $userIds = $adminUsers->pluck('id')->toArray();
        
        if (empty($userIds)) {
            Log::warning('No admin users found to notify', [
                'type' => $type,
                'title' => $title
            ]);
            return [];
        }
        
        return $this->sendBulkNotification($userIds, $type, $title, $message, $data);
    }

    /**
     * Notify admins about new loan application
     */
    public function notifyAdminsNewLoanApplication($loanId, $memberName, $amount): array
    {
        return $this->notifyAdmins(
            'info',
            'New Loan Application',
            "A new loan application has been submitted by {$memberName} for ₦" . number_format($amount, 2),
            [
                'loan_id' => $loanId,
                'member_name' => $memberName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify admins about new KYC submission
     */
    public function notifyAdminsNewKycSubmission($memberId, $memberName): array
    {
        return $this->notifyAdmins(
            'info',
            'New KYC Submission',
            "{$memberName} has submitted KYC documents for verification",
            [
                'member_id' => $memberId,
                'member_name' => $memberName,
            ]
        );
    }

    /**
     * Notify admins about new contribution submission
     */
    public function notifyAdminsNewContribution($contributionId, $memberName, $amount): array
    {
        return $this->notifyAdmins(
            'info',
            'New Contribution Submission',
            "A new contribution of ₦" . number_format($amount, 2) . " has been submitted by {$memberName}",
            [
                'contribution_id' => $contributionId,
                'member_name' => $memberName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify admins about new equity contribution
     */
    public function notifyAdminsNewEquityContribution($contributionId, $memberName, $amount): array
    {
        return $this->notifyAdmins(
            'info',
            'New Equity Contribution',
            "A new equity contribution of ₦" . number_format($amount, 2) . " has been submitted by {$memberName}",
            [
                'equity_contribution_id' => $contributionId,
                'member_name' => $memberName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify admins about new document submission
     */
    public function notifyAdminsNewDocument($documentId, $memberName, $documentType): array
    {
        return $this->notifyAdmins(
            'info',
            'New Document Submission',
            "{$memberName} has uploaded a new {$documentType} document",
            [
                'document_id' => $documentId,
                'member_name' => $memberName,
                'document_type' => $documentType,
            ]
        );
    }

    /**
     * Notify admins about new investment application
     */
    public function notifyAdminsNewInvestment($investmentId, $memberName, $amount): array
    {
        return $this->notifyAdmins(
            'info',
            'New Investment Application',
            "A new investment application of ₦" . number_format($amount, 2) . " has been submitted by {$memberName}",
            [
                'investment_id' => $investmentId,
                'member_name' => $memberName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify admins about payment failure
     */
    public function notifyAdminsPaymentFailure($paymentId, $memberName, $amount, $reason = ''): array
    {
        return $this->notifyAdmins(
            'error',
            'Payment Failed',
            "Payment of ₦" . number_format($amount, 2) . " from {$memberName} has failed." . ($reason ? " Reason: {$reason}" : ''),
            [
                'payment_id' => $paymentId,
                'member_name' => $memberName,
                'amount' => $amount,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify admins about new member registration
     */
    public function notifyAdminsNewMemberRegistration($memberId, $memberName, $memberNumber): array
    {
        return $this->notifyAdmins(
            'info',
            'New Member Registration',
            "New member {$memberName} ({$memberNumber}) has registered",
            [
                'member_id' => $memberId,
                'member_name' => $memberName,
                'member_number' => $memberNumber,
            ]
        );
    }
}
