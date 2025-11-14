<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'type',
        'category',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get setting value with proper type casting
     */
    public function getTypedValue()
    {
        return match($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json', 'array' => json_decode($this->value, true),
            default => $this->value
        };
    }

    /**
     * Set setting value with proper type conversion
     */
    public function setTypedValue($value)
    {
        $this->value = match($this->type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json', 'array' => json_encode($value),
            default => (string) $value
        };
    }
}

