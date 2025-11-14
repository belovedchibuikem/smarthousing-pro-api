<?php

namespace Database\Seeders\Central;

use App\Models\Central\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaultSettings = [
            // General Settings
            [
                'key' => 'platform_name',
                'value' => 'FRSC Housing Platform',
                'type' => 'string',
                'category' => 'general',
                'description' => 'The name of the platform',
                'is_public' => true
            ],
            [
                'key' => 'platform_domain',
                'value' => 'frschousing.com',
                'type' => 'string',
                'category' => 'general',
                'description' => 'The main domain of the platform',
                'is_public' => true
            ],
            [
                'key' => 'support_email',
                'value' => 'support@frschousing.com',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Support email address',
                'is_public' => true
            ],
            [
                'key' => 'timezone',
                'value' => 'Africa/Lagos',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Default timezone',
                'is_public' => true
            ],
            [
                'key' => 'maintenance_mode',
                'value' => false,
                'type' => 'boolean',
                'category' => 'general',
                'description' => 'Enable maintenance mode',
                'is_public' => false
            ],

            // Email Settings
            [
                'key' => 'smtp_host',
                'value' => '',
                'type' => 'string',
                'category' => 'email',
                'description' => 'SMTP host for email sending',
                'is_public' => false
            ],
            [
                'key' => 'smtp_port',
                'value' => '587',
                'type' => 'integer',
                'category' => 'email',
                'description' => 'SMTP port',
                'is_public' => false
            ],
            [
                'key' => 'smtp_username',
                'value' => '',
                'type' => 'string',
                'category' => 'email',
                'description' => 'SMTP username',
                'is_public' => false
            ],
            [
                'key' => 'smtp_password',
                'value' => '',
                'type' => 'string',
                'category' => 'email',
                'description' => 'SMTP password',
                'is_public' => false
            ],
            [
                'key' => 'smtp_encryption',
                'value' => 'tls',
                'type' => 'string',
                'category' => 'email',
                'description' => 'SMTP encryption type',
                'is_public' => false
            ],
            [
                'key' => 'from_email',
                'value' => 'noreply@frschousing.com',
                'type' => 'string',
                'category' => 'email',
                'description' => 'Default from email address',
                'is_public' => false
            ],
            [
                'key' => 'from_name',
                'value' => 'FRSC Housing Platform',
                'type' => 'string',
                'category' => 'email',
                'description' => 'Default from name',
                'is_public' => false
            ],

            // Security Settings
            [
                'key' => 'require_2fa',
                'value' => true,
                'type' => 'boolean',
                'category' => 'security',
                'description' => 'Require two-factor authentication for super admins',
                'is_public' => false
            ],
            [
                'key' => 'session_timeout',
                'value' => '120',
                'type' => 'integer',
                'category' => 'security',
                'description' => 'Session timeout in minutes',
                'is_public' => false
            ],
            [
                'key' => 'ip_whitelist_enabled',
                'value' => false,
                'type' => 'boolean',
                'category' => 'security',
                'description' => 'Enable IP whitelist for super admin access',
                'is_public' => false
            ],
            [
                'key' => 'allowed_ips',
                'value' => [],
                'type' => 'json',
                'category' => 'security',
                'description' => 'List of allowed IP addresses',
                'is_public' => false
            ],

            // Notification Settings
            [
                'key' => 'notify_new_business',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Notify when a new business registers',
                'is_public' => false
            ],
            [
                'key' => 'notify_subscription_expiring',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Notify when subscriptions are about to expire',
                'is_public' => false
            ],
            [
                'key' => 'notify_payment_failed',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Notify when a payment fails',
                'is_public' => false
            ],
            [
                'key' => 'notify_usage_limit',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Notify when a business reaches usage limits',
                'is_public' => false
            ],

            // Database Settings
            [
                'key' => 'backup_frequency',
                'value' => 'daily',
                'type' => 'string',
                'category' => 'database',
                'description' => 'Database backup frequency',
                'is_public' => false
            ],
            [
                'key' => 'backup_retention_days',
                'value' => '30',
                'type' => 'integer',
                'category' => 'database',
                'description' => 'Number of days to retain backups',
                'is_public' => false
            ],
            [
                'key' => 'auto_optimize',
                'value' => true,
                'type' => 'boolean',
                'category' => 'database',
                'description' => 'Automatically optimize database',
                'is_public' => false
            ]
        ];

        foreach ($defaultSettings as $setting) {
            PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
