<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mail extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'sender_id',
        'recipient_id',
        'subject',
        'body',
        'type',
        'status',
        'folder',
        'category',
        'is_starred',
        'is_archived',
        'is_read',
        'is_urgent',
        'recipient_type',
        'cc',
        'bcc',
        'sent_at',
        'read_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
        'parent_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'is_starred' => 'boolean',
        'is_archived' => 'boolean',
        'is_read' => 'boolean',
        'is_urgent' => 'boolean',
        'cc' => 'array',
        'bcc' => 'array',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Mail::class, 'parent_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class)->orderBy('order');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Mail::class, 'parent_id');
    }

    public function isRead(): bool
    {
        return $this->is_read || !is_null($this->read_at);
    }

    public function isUnread(): bool
    {
        return !$this->is_read && is_null($this->read_at);
    }
}
