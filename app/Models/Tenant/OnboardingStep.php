<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStep extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'member_id',
        'step_number',
        'title',
        'description',
        'type',
        'status',
        'completed_at',
        'skipped_at',
        'skip_reason',
        'data',
        'is_required',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'skipped_at' => 'datetime',
        'data' => 'array',
        'is_required' => 'boolean',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
