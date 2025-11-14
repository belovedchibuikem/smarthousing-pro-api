<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhiteLabelSetting extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'tenant_id',
        'brand_name',
        'company_name',
        'company_tagline',
        'company_description',
        'logo_url',
        'logo_dark_url',
        'favicon_url',
        'login_background_url',
        'dashboard_hero_url',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'font_family',
        'heading_font',
        'body_font',
        'custom_css',
        'custom_js',
        'email_sender_name',
        'email_reply_to',
        'email_footer_text',
        'email_logo_url',
        'terms_url',
        'privacy_url',
        'support_email',
        'support_phone',
        'help_center_url',
        'footer_text',
        'footer_links',
        'social_links',
        'enabled_modules',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'footer_links' => 'array',
        'social_links' => 'array',
        'enabled_modules' => 'array',
    ];

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
