<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyMaintenanceRecord extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'property_id',
        'reported_by',
        'issue_type',
        'priority',
        'description',
        'status',
        'assigned_to',
        'estimated_cost',
        'actual_cost',
        'reported_date',
        'started_date',
        'completed_date',
        'resolution_notes',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'reported_date' => 'date',
        'started_date' => 'date',
        'completed_date' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }
}

