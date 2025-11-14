<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'trial_days',
        'is_active',
        'is_featured',
        'limits',
    ];

    protected $casts = [
        'limits' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'package_modules')
            ->withPivot('limits')
            ->withTimestamps();
    }
}
