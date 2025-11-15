<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SaasTeamMember extends Model
{
    use HasUuids;

    protected $connection = 'mysql';
    protected $table = 'saas_team_members';

    protected $fillable = [
        'name',
        'role',
        'bio',
        'avatar_url',
        'email',
        'linkedin_url',
        'twitter_url',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get active team members
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by order_index
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index', 'asc');
    }
}
