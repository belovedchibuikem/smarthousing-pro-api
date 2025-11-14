<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'color',
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
     * Get super admins that have this role
     */
    public function superAdmins(): BelongsToMany
    {
        return $this->belongsToMany(SuperAdmin::class, 'model_has_roles', 'role_id', 'model_id')
            ->where('model_type', SuperAdmin::class);
    }

    /**
     * Get the permissions for this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id');
    }

    /**
     * Scope to get only active roles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get the role color for UI display
     */
    public function getColorAttribute($value)
    {
        return $value ?: $this->getDefaultColor();
    }

    /**
     * Get default color based on role name
     */
    private function getDefaultColor()
    {
        $colors = [
            'super_admin' => 'bg-red-500',
            'platform_admin' => 'bg-blue-500',
            'support_admin' => 'bg-green-500',
            'billing_admin' => 'bg-purple-500',
        ];

        return $colors[$this->name] ?? 'bg-gray-500';
    }

    /**
     * Check if role is in use
     */
    public function isInUse(): bool
    {
        return $this->superAdmins()->count() > 0;
    }

    /**
     * Get role statistics
     */
    public function getStatsAttribute()
    {
        return [
            'super_admins_count' => $this->superAdmins()->count(),
            'permissions_count' => $this->permissions()->count(),
            'is_in_use' => $this->isInUse(),
        ];
    }
}