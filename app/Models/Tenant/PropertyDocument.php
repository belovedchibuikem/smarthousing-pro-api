<?php

namespace App\Models\Tenant;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyDocument extends Model
{
    use HasFactory;
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'property_id',
        'member_id',
        'uploaded_by',
        'uploaded_by_role',
        'title',
        'description',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'document_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}


