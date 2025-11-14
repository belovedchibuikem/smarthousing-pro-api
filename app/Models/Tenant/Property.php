<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant\Mortgage;

class Property extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'title',
        'description',
        'type',
        'property_type',
        'location',
        'address',
        'city',
        'state',
        'price',
        'size',
        'size_sqft',
        'bedrooms',
        'bathrooms',
        'features',
        'status',
        'is_featured',
        'coordinates',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'size' => 'decimal:2',
        'features' => 'array',
        'coordinates' => 'array',
        'is_featured' => 'boolean',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PropertyAllocation::class);
    }

    public function interests(): HasMany
    {
        return $this->hasMany(PropertyInterest::class);
    }

    public function blockchainRecords(): HasMany
    {
        return $this->hasMany(BlockchainPropertyRecord::class);
    }

    public function mortgages(): HasMany
    {
        return $this->hasMany(Mortgage::class);
    }

    public function getActiveBlockchainRecordAttribute()
    {
        return $this->blockchainRecords()
            ->where('status', 'confirmed')
            ->orderBy('confirmed_at', 'desc')
            ->first();
    }

    public function getPrimaryImageAttribute()
    {
        return $this->images()->where('is_primary', true)->first();
    }
}
