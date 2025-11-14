<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasUuids, HasApiTokens, HasRoles;

    protected $connection = 'tenant';

    // Ensure Spatie roles use the same guard as roles created (web)
    protected $guard_name = 'web';

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'avatar_url',
        'role',
        'status',
        'email_verified_at',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'password' => 'hashed',
    ];

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdmin(): bool
    {
        // Check both legacy role and Spatie roles
        $legacyAdmin = in_array($this->role, ['admin', 'super_admin']);
        $spatieAdmin = $this->hasAnyRole(['admin', 'super_admin']);
        
        return $legacyAdmin || $spatieAdmin;
    }

    public function isMember(): bool
    {
        // Check both legacy role and Spatie roles
        $legacyMember = $this->role === 'member';
        $spatieMember = $this->hasRole('member');
        
        return $legacyMember || $spatieMember;
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get all roles for debugging purposes
     */
    public function getAllRoles(): array
    {
        $legacyRole = $this->role ? [$this->role] : [];
        $spatieRoles = $this->roles->pluck('name')->toArray();
        
        return array_unique(array_merge($legacyRole, $spatieRoles));
    }

    /**
     * Check if user has any of the specified roles (checks both legacy and Spatie)
     */
    public function hasAnyRoleLegacy(array $roles): bool
    {
        $legacyMatch = in_array($this->role, $roles);
        $spatieMatch = $this->hasAnyRole($roles);
        
        return $legacyMatch || $spatieMatch;
    }

}
