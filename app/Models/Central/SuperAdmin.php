<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class SuperAdmin extends Authenticatable
{
    use HasFactory, HasUuids, HasApiTokens, HasRoles;

    protected $connection = 'mysql';

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'role',
        'permissions', // Keep for backward compatibility during transition
        'is_active',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'permissions' => 'array', // Keep for backward compatibility
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'password' => 'hashed',
    ];

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the guard name for Spatie Permission.
     */
    public function getGuardName(): string
    {
        return 'super_admin';
    }

    /**
     * Get permission slugs for this user.
     * This method is required by the authentication system.
     */
    public function getPermissionSlugs(): array
    {
        try {
            \Log::info('Getting permissions for SuperAdmin', [
                'user_id' => $this->id,
                'email' => $this->email
            ]);
            
            // Check what roles the user has
            $userRoles = \DB::table('model_has_roles')
                ->where('model_id', $this->id)
                ->where('model_type', 'App\\Models\\Central\\SuperAdmin')
                ->pluck('role_id')
                ->toArray();
            
            \Log::info('User roles found', [
                'user_id' => $this->id,
                'role_ids' => $userRoles
            ]);
            
            // Check if roles have permissions
            if (!empty($userRoles)) {
                $rolePermissions = \DB::table('permissions')
                    ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->whereIn('role_has_permissions.role_id', $userRoles)
                    ->where('permissions.guard_name', 'super_admin')
                    ->pluck('permissions.name')
                    ->toArray();
                
                \Log::info('Role permissions found', [
                    'user_id' => $this->id,
                    'permissions_count' => count($rolePermissions),
                    'permissions' => $rolePermissions
                ]);
            } else {
                $rolePermissions = [];
            }
            
            // Get direct permissions
            $directPermissions = \DB::table('permissions')
                ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('model_has_permissions.model_id', $this->id)
                ->where('model_has_permissions.model_type', 'App\\Models\\Central\\SuperAdmin')
                ->where('permissions.guard_name', 'super_admin')
                ->pluck('permissions.name')
                ->toArray();
            
            \Log::info('Direct permissions found', [
                'user_id' => $this->id,
                'permissions_count' => count($directPermissions),
                'permissions' => $directPermissions
            ]);
            
            // Combine and deduplicate permissions
            $allPermissions = array_unique(array_merge($directPermissions, $rolePermissions));
            
            \Log::info('Final permissions', [
                'user_id' => $this->id,
                'total_permissions' => count($allPermissions),
                'permissions' => $allPermissions
            ]);
            
            return $allPermissions;
            
        } catch (\Exception $e) {
            \Log::error('SuperAdmin getPermissionSlugs error', [
                'user_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
