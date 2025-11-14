<?php

namespace App\Services;

use App\Models\Central\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function log(
        string $action,
        string $description,
        ?string $module = null,
        ?array $properties = null,
        ?Model $causer = null,
        ?string $tenantId = null,
        ?Request $request = null
    ): ActivityLog {
        return ActivityLog::create([
            'tenant_id' => $tenantId ?? tenant('id'),
            'causer_type' => $causer ? get_class($causer) : null,
            'causer_id' => $causer?->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function logUserAction(
        string $action,
        string $description,
        Model $user,
        ?string $module = null,
        ?array $properties = null
    ): ActivityLog {
        return $this->log($action, $description, $module, $properties, $user);
    }

    public function logSystemAction(
        string $action,
        string $description,
        ?string $module = null,
        ?array $properties = null
    ): ActivityLog {
        return $this->log($action, $description, $module, $properties);
    }

    public function logModelAction(
        string $action,
        Model $model,
        ?Model $causer = null,
        ?array $properties = null
    ): ActivityLog {
        $description = ucfirst($action) . ' ' . class_basename($model) . ' (ID: ' . $model->id . ')';
        
        return $this->log(
            $action,
            $description,
            class_basename($model),
            $properties,
            $causer
        );
    }

    public function getActivityForTenant(string $tenantId, int $limit = 50)
    {
        return ActivityLog::forTenant($tenantId)
            ->with(['causer'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getActivityForModule(string $module, int $limit = 50)
    {
        return ActivityLog::forModule($module)
            ->with(['causer', 'tenant'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
