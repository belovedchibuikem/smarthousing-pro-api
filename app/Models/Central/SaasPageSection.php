<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SaasPageSection extends Model
{
    use HasUuids;

    protected $connection = 'mysql';
    protected $table = 'saas_page_sections';

    protected $fillable = [
        'page_type',
        'section_type',
        'section_key',
        'title',
        'subtitle',
        'content',
        'media',
        'order_index',
        'is_active',
        'is_published',
        'metadata',
    ];

    protected $casts = [
        'content' => 'array',
        'media' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'order_index' => 'integer',
    ];

    /**
     * Scope to get sections for a specific page type
     */
    public function scopeForPage($query, string $pageType)
    {
        return $query->where('page_type', $pageType);
    }

    /**
     * Scope to get published sections
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)->where('is_active', true);
    }

    /**
     * Scope to order by order_index
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index', 'asc');
    }
}
