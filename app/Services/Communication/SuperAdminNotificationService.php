<?php

namespace App\Services\Communication;

use App\Models\Central\SuperAdminNotification;
use App\Models\Central\SuperAdmin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SuperAdminNotificationService
{
    /**
     * Send a single notification
     */
    public function sendNotification(SuperAdminNotification $notification): void
    {
        try {
            // Send real-time notification (WebSocket, Pusher, etc.)
            // This would integrate with your real-time service
            // Example: event(new SuperAdminNotificationSent($notification));
            
            Log::info('SuperAdmin notification sent successfully', [
                'notification_id' => $notification->id,
                'super_admin_id' => $notification->super_admin_id,
                'type' => $notification->type
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send super admin notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send bulk notifications to multiple super admins
     */
    public function sendBulkNotification(array $superAdminIds, string $type, string $title, string $message, array $data = []): array
    {
        $notifications = [];
        
        try {
            DB::beginTransaction();
            
            foreach ($superAdminIds as $superAdminId) {
                $notification = SuperAdminNotification::create([
                    'super_admin_id' => $superAdminId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data' => $data,
                ]);
                
                $this->sendNotification($notification);
                $notifications[] = $notification;
            }
            
            DB::commit();
            
            Log::info('Bulk super admin notifications sent', [
                'count' => count($notifications),
                'type' => $type
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to send bulk super admin notifications', [
                'error' => $e->getMessage(),
                'count' => count($superAdminIds)
            ]);
            
            throw $e;
        }
        
        return $notifications;
    }

    /**
     * Notify all super admin users
     */
    public function notifyAllSuperAdmins(string $type, string $title, string $message, array $data = []): array
    {
        // Get all active super admin users
        $superAdminIds = SuperAdmin::where('is_active', true)
            ->pluck('id')
            ->toArray();
        
        if (empty($superAdminIds)) {
            Log::warning('No active super admin users found to notify', [
                'type' => $type,
                'title' => $title
            ]);
            return [];
        }
        
        return $this->sendBulkNotification($superAdminIds, $type, $title, $message, $data);
    }

    /**
     * Notify super admins about new tenant registration
     */
    public function notifyNewTenantRegistration($tenantId, $tenantName, $packageName, $contactEmail): array
    {
        return $this->notifyAllSuperAdmins(
            'info',
            'New Tenant Registration',
            "A new tenant '{$tenantName}' has registered with package '{$packageName}'",
            [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'package_name' => $packageName,
                'contact_email' => $contactEmail,
            ]
        );
    }

    /**
     * Notify super admins about tenant suspension
     */
    public function notifyTenantSuspended($tenantId, $tenantName, $reason = ''): array
    {
        return $this->notifyAllSuperAdmins(
            'warning',
            'Tenant Suspended',
            "Tenant '{$tenantName}' has been suspended." . ($reason ? " Reason: {$reason}" : ''),
            [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify super admins about tenant activation
     */
    public function notifyTenantActivated($tenantId, $tenantName): array
    {
        return $this->notifyAllSuperAdmins(
            'success',
            'Tenant Activated',
            "Tenant '{$tenantName}' has been activated",
            [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
            ]
        );
    }

    /**
     * Notify super admins about tenant cancellation
     */
    public function notifyTenantCancelled($tenantId, $tenantName): array
    {
        return $this->notifyAllSuperAdmins(
            'error',
            'Tenant Cancelled',
            "Tenant '{$tenantName}' has been cancelled",
            [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
            ]
        );
    }

    /**
     * Notify super admins about subscription activation
     */
    public function notifySubscriptionActivated($subscriptionId, $tenantName, $packageName, $amount): array
    {
        return $this->notifyAllSuperAdmins(
            'success',
            'Subscription Activated',
            "Subscription for tenant '{$tenantName}' has been activated with package '{$packageName}' (₦" . number_format($amount, 2) . ")",
            [
                'subscription_id' => $subscriptionId,
                'tenant_name' => $tenantName,
                'package_name' => $packageName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify super admins about subscription expiration
     */
    public function notifySubscriptionExpiring($subscriptionId, $tenantName, $expiresAt): array
    {
        return $this->notifyAllSuperAdmins(
            'warning',
            'Subscription Expiring',
            "Subscription for tenant '{$tenantName}' is expiring on {$expiresAt}",
            [
                'subscription_id' => $subscriptionId,
                'tenant_name' => $tenantName,
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Notify super admins about subscription payment failure
     */
    public function notifySubscriptionPaymentFailure($subscriptionId, $tenantName, $amount, $reason = ''): array
    {
        return $this->notifyAllSuperAdmins(
            'error',
            'Subscription Payment Failed',
            "Subscription payment of ₦" . number_format($amount, 2) . " for tenant '{$tenantName}' has failed." . ($reason ? " Reason: {$reason}" : ''),
            [
                'subscription_id' => $subscriptionId,
                'tenant_name' => $tenantName,
                'amount' => $amount,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify super admins about custom domain request
     */
    public function notifyCustomDomainRequest($tenantId, $tenantName, $domain): array
    {
        return $this->notifyAllSuperAdmins(
            'info',
            'Custom Domain Request',
            "Tenant '{$tenantName}' has requested custom domain '{$domain}'",
            [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'domain' => $domain,
            ]
        );
    }

    /**
     * Notify super admins about payment gateway test failure
     */
    public function notifyPaymentGatewayTestFailure($gatewayName, $reason = ''): array
    {
        return $this->notifyAllSuperAdmins(
            'error',
            'Payment Gateway Test Failed',
            "Payment gateway '{$gatewayName}' connection test failed." . ($reason ? " Reason: {$reason}" : ''),
            [
                'gateway_name' => $gatewayName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify super admins about payment gateway deactivated
     */
    public function notifyPaymentGatewayDeactivated($gatewayName, $reason = ''): array
    {
        return $this->notifyAllSuperAdmins(
            'warning',
            'Payment Gateway Deactivated',
            "Payment gateway '{$gatewayName}' has been deactivated." . ($reason ? " Reason: {$reason}" : ''),
            [
                'gateway_name' => $gatewayName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify super admins about trial expiration
     */
    public function notifyTrialExpiring($tenantId, $tenantName, $expiresAt): array
    {
        return $this->notifyAllSuperAdmins(
            'warning',
            'Trial Expiring',
            "Trial period for tenant '{$tenantName}' is expiring on {$expiresAt}",
            [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Notify super admins about subscription upgrade
     */
    public function notifySubscriptionUpgrade($subscriptionId, $tenantName, $oldPackage, $newPackage, $amount): array
    {
        return $this->notifyAllSuperAdmins(
            'info',
            'Subscription Upgraded',
            "Tenant '{$tenantName}' has upgraded from '{$oldPackage}' to '{$newPackage}' (₦" . number_format($amount, 2) . ")",
            [
                'subscription_id' => $subscriptionId,
                'tenant_name' => $tenantName,
                'old_package' => $oldPackage,
                'new_package' => $newPackage,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Notify super admins about subscription downgrade
     */
    public function notifySubscriptionDowngrade($subscriptionId, $tenantName, $oldPackage, $newPackage): array
    {
        return $this->notifyAllSuperAdmins(
            'info',
            'Subscription Downgraded',
            "Tenant '{$tenantName}' has downgraded from '{$oldPackage}' to '{$newPackage}'",
            [
                'subscription_id' => $subscriptionId,
                'tenant_name' => $tenantName,
                'old_package' => $oldPackage,
                'new_package' => $newPackage,
            ]
        );
    }
}

