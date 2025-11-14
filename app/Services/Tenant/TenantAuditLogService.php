<?php

namespace App\Services\Tenant;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TenantAuditLogService
{
    /**
     * Log an audit event
     */
    public function log(
        string $action,
        string $description,
        ?string $module = null,
        ?Model $resource = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?User $user = null,
        ?Request $request = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'module' => $module,
            'resource_type' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => $request?->ip() ?? request()?->ip(),
            'user_agent' => $request?->userAgent() ?? request()?->userAgent(),
        ]);
    }

    /**
     * Log a user action
     */
    public function logUserAction(
        string $action,
        string $description,
        User $user,
        ?string $module = null,
        ?Model $resource = null,
        ?array $metadata = null
    ): AuditLog {
        return $this->log(
            $action,
            $description,
            $module,
            $resource,
            null,
            null,
            $metadata,
            $user
        );
    }

    /**
     * Log a model creation
     */
    public function logCreate(
        Model $resource,
        ?User $user = null,
        ?array $metadata = null
    ): AuditLog {
        $module = $this->getModuleFromModel($resource);
        
        return $this->log(
            'create',
            'Created ' . class_basename($resource) . ' (ID: ' . $resource->id . ')',
            $module,
            $resource,
            null,
            $resource->toArray(),
            $metadata,
            $user
        );
    }

    /**
     * Log a model update
     */
    public function logUpdate(
        Model $resource,
        array $oldValues,
        array $newValues,
        ?User $user = null,
        ?array $metadata = null
    ): AuditLog {
        $module = $this->getModuleFromModel($resource);
        
        return $this->log(
            'update',
            'Updated ' . class_basename($resource) . ' (ID: ' . $resource->id . ')',
            $module,
            $resource,
            $oldValues,
            $newValues,
            $metadata,
            $user
        );
    }

    /**
     * Log a model deletion
     */
    public function logDelete(
        Model $resource,
        ?User $user = null,
        ?array $metadata = null
    ): AuditLog {
        $module = $this->getModuleFromModel($resource);
        
        return $this->log(
            'delete',
            'Deleted ' . class_basename($resource) . ' (ID: ' . $resource->id . ')',
            $module,
            $resource,
            $resource->toArray(),
            null,
            $metadata,
            $user
        );
    }

    /**
     * Log login
     */
    public function logLogin(User $user, ?Request $request = null): AuditLog
    {
        return $this->log(
            'login',
            'User logged in: ' . $user->email,
            'auth',
            null,
            null,
            null,
            ['email' => $user->email],
            $user,
            $request
        );
    }

    /**
     * Log logout
     */
    public function logLogout(User $user, ?Request $request = null): AuditLog
    {
        return $this->log(
            'logout',
            'User logged out: ' . $user->email,
            'auth',
            null,
            null,
            null,
            ['email' => $user->email],
            $user,
            $request
        );
    }

    /**
     * Log approval action
     */
    public function logApproval(
        Model $resource,
        string $resourceName,
        ?User $user = null,
        ?array $metadata = null
    ): AuditLog {
        $module = $this->getModuleFromModel($resource);
        
        return $this->log(
            'approve',
            'Approved ' . $resourceName . ' (ID: ' . $resource->id . ')',
            $module,
            $resource,
            null,
            ['status' => 'approved', 'approved_at' => now()],
            $metadata,
            $user
        );
    }

    /**
     * Log rejection action
     */
    public function logRejection(
        Model $resource,
        string $resourceName,
        string $reason,
        ?User $user = null,
        ?array $metadata = null
    ): AuditLog {
        $module = $this->getModuleFromModel($resource);
        
        return $this->log(
            'reject',
            'Rejected ' . $resourceName . ' (ID: ' . $resource->id . '). Reason: ' . $reason,
            $module,
            $resource,
            null,
            ['status' => 'rejected', 'rejection_reason' => $reason, 'rejected_at' => now()],
            $metadata,
            $user
        );
    }

    /**
     * Get module name from model class
     */
    private function getModuleFromModel(Model $model): string
    {
        $className = class_basename($model);
        
        // Map common model names to module names
        $moduleMap = [
            'Loan' => 'loans',
            'Member' => 'members',
            'Contribution' => 'contributions',
            'EquityContribution' => 'equity',
            'Investment' => 'investments',
            'Document' => 'documents',
            'Property' => 'properties',
            'Mortgage' => 'mortgages',
            'Payment' => 'payments',
            'LoanRepayment' => 'loans',
            'User' => 'users',
            'Notification' => 'notifications',
            'StatutoryCharge' => 'statutory_charges',
        ];
        
        return $moduleMap[$className] ?? strtolower($className);
    }
}

