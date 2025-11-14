<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'display_name',
        'guard_name',
        'description',
        'is_active',
        'color',
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
     * Get users that have this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id')
            ->where('model_type', User::class);
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
            'admin' => 'bg-blue-500',
            'finance_manager' => 'bg-green-500',
            'loan_officer' => 'bg-purple-500',
            'property_manager' => 'bg-orange-500',
            'member_manager' => 'bg-indigo-500',
            'document_manager' => 'bg-pink-500',
            'system_admin' => 'bg-gray-500',
        ];

        return $colors[$this->name] ?? 'bg-gray-500';
    }

    /**
     * Check if role is in use
     */
    public function isInUse(): bool
    {
        return $this->users()->count() > 0;
    }

    /**
     * Get role statistics
     */
    public function getStatsAttribute()
    {
        return [
            'users_count' => $this->users()->count(),
            'permissions_count' => $this->permissions()->count(),
            'is_in_use' => $this->isInUse(),
        ];
    }
}




