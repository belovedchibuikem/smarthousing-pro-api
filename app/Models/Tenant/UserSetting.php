<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'email_notifications',
        'sms_notifications',
        'payment_reminders',
        'loan_updates',
        'investment_updates',
        'property_updates',
        'contribution_updates',
        'language',
        'timezone',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'profile_visible',
        'show_email',
        'show_phone',
        'preferences',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'payment_reminders' => 'boolean',
        'loan_updates' => 'boolean',
        'investment_updates' => 'boolean',
        'property_updates' => 'boolean',
        'contribution_updates' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_recovery_codes' => 'array',
        'profile_visible' => 'boolean',
        'show_email' => 'boolean',
        'show_phone' => 'boolean',
        'preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
