<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDomainRequest extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $fillable = [
        'tenant_id',
        'domain',
        'status',
        'verification_token',
        'verified_at',
        'activated_at',
        'admin_notes',
        'dns_records',
        'ssl_enabled',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'activated_at' => 'datetime',
        'dns_records' => 'array',
        'ssl_enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
