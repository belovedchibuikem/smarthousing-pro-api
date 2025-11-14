<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
        'packages_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'packages_count' => 'integer',
    ];

    /**
     * Get the packages that include this module
     */
    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_modules');
    }
}