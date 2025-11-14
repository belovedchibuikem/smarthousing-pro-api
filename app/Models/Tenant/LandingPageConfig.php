<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPageConfig extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'tenant_id',
        'template_id',
        'is_published',
        'sections',
        'theme',
        'seo',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sections' => 'array',
        'theme' => 'array',
        'seo' => 'array',
    ];
}
