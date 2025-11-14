<?php

namespace App\Models\Central;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'mysql'; // Use central database connection
    
    protected $table = 'personal_access_tokens';
    
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'tokenable_type',
        'tokenable_id',
        'tenant_id', // Add tenant_id to track which tenant the token belongs to
    ];
    
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
    ];
    
    /**
     * Get the tenant that owns the token.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Central\Tenant::class, 'tenant_id');
    }
}
