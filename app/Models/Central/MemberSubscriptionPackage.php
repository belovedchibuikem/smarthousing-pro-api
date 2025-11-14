<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberSubscriptionPackage extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $table = 'member_subscription_packages';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'duration_days',
        'trial_days',
        'features',
        'benefits',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'benefits' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class, 'package_id');
    }

    public function getDurationLabelAttribute(): string
    {
        return match($this->billing_cycle) {
            'weekly' => '7 days',
            'monthly' => '30 days',
            'quarterly' => '90 days',
            'yearly' => '365 days',
            default => "{$this->duration_days} days"
        };
    }
}

