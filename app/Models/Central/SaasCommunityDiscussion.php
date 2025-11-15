<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SaasCommunityDiscussion extends Model
{
    use HasUuids;

    protected $connection = 'mysql';
    protected $table = 'saas_community_discussions';

    protected $fillable = [
        'question',
        'author_name',
        'author_role',
        'author_avatar_url',
        'responses_count',
        'likes_count',
        'views_count',
        'tags',
        'top_answer',
        'other_answers',
        'order_index',
        'is_featured',
        'is_active',
    ];

    protected $casts = [
        'tags' => 'array',
        'top_answer' => 'array',
        'other_answers' => 'array',
        'responses_count' => 'integer',
        'likes_count' => 'integer',
        'views_count' => 'integer',
        'order_index' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get active discussions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get featured discussions
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
