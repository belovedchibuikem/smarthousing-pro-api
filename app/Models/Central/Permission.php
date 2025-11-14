<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'group',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'guard_name' => 'web',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * Get roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions', 'permission_id', 'role_id');
    }

    /**
     * Get super admins that have this permission directly
     */
    public function superAdmins(): BelongsToMany
    {
        return $this->belongsToMany(SuperAdmin::class, 'model_has_permissions', 'permission_id', 'model_id')
            ->where('model_type', SuperAdmin::class);
    }

    /**
     * Scope to get only active permissions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by group
     */
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope to order by group and sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('group')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get permission group display name
     */
    public function getGroupDisplayNameAttribute()
    {
        $groups = [
            'platform' => 'Platform Management',
            'businesses' => 'Business Management',
            'packages' => 'Package Management',
            'subscriptions' => 'Subscription Management',
            'modules' => 'Module Management',
            'users' => 'User Management',
            'reports' => 'Reports & Analytics',
            'system' => 'System Administration',
            'settings' => 'Settings & Configuration',
        ];

        return $groups[$this->group] ?? ucfirst($this->group);
    }

    /**
     * Get permission display name
     */
    public function getDisplayNameAttribute()
    {
        return str_replace('_', ' ', ucwords($this->name, '_'));
    }

    /**
     * Check if permission is in use
     */
    public function isInUse(): bool
    {
        return $this->roles()->count() > 0 || $this->superAdmins()->count() > 0;
    }

    /**
     * Get permission statistics
     */
    public function getStatsAttribute()
    {
        return [
            'roles_count' => $this->roles()->count(),
            'super_admins_count' => $this->superAdmins()->count(),
            'is_in_use' => $this->isInUse(),
        ];
    }
}