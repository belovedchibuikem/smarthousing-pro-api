<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SaasTestimonial extends Model
{
    use HasUuids;

    protected $connection = 'mysql';
    protected $table = 'saas_testimonials';

    protected $fillable = [
        'name',
        'role',
        'content',
        'rating',
        'avatar_url',
        'company',
        'order_index',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'order_index' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get active testimonials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get featured testimonials
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('is_active', true);
    }

    /**
     * Scope to order by order_index
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index', 'asc');
    }
}
