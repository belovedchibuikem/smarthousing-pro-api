<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $origin = request()->header('Origin');
        $tenantId = tenant('id');
        
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context not available',
                'error' => 'Tenant not initialized'
            ], 500)->withHeaders([
                'Access-Control-Allow-Origin' => $origin ?? '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
        
        $settings = TenantSetting::where('tenant_id', $tenantId)
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->getTypedValue()];
            });

        return response()->json([
            'success' => true,
            'settings' => $settings
        ])->withHeaders([
            'Access-Control-Allow-Origin' => $origin ?? '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Forwarded-Host, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }

    public function getByCategory(string $category): JsonResponse
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))
            ->where('category', $category)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->getTypedValue()];
            });

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $saved = [];
        $defaultTypes = $this->getDefaultTypes();
        $defaultCategories = $this->getDefaultCategories();

        foreach ($request->settings as $key => $value) {
            $category = $defaultCategories[$key] ?? 'general';
            $type = $defaultTypes[$key] ?? 'string';

            $setting = TenantSetting::updateOrCreate(
                [
                    'tenant_id' => tenant('id'),
                    'key' => $key,
                ],
                [
                    'value' => $this->convertValueForStorage($value, $type),
                    'type' => $type,
                    'category' => $category,
                    'description' => $this->getDescription($key),
                ]
            );

            $saved[$key] = $setting->getTypedValue();
        }

        // If email settings were updated, apply them to the mail configuration
        if (isset($request->settings['smtp_host']) || isset($request->settings['smtp_port'])) {
            $this->applyEmailSettings($saved);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully',
            'settings' => $saved
        ]);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address'
            ], 422);
        }

        try {
            // Get email settings and apply them
            $emailSettings = TenantSetting::where('tenant_id', tenant('id'))
                ->where('category', 'email')
                ->get()
                ->mapWithKeys(function ($setting) {
                    return [$setting->key => $setting->getTypedValue()];
                });

            $this->applyEmailSettings($emailSettings->toArray());

            $testEmail = $request->email;
            $siteName = TenantSetting::where('tenant_id', tenant('id'))
                ->where('key', 'site_name')
                ->value('value') ?? config('app.name');

            $fromAddress = $emailSettings['smtp_from_address'] ?? config('mail.from.address');
            $fromName = $emailSettings['smtp_from_name'] ?? $siteName;

            Mail::raw('This is a test email from ' . $siteName . '. Your email configuration is working correctly.', function ($message) use ($testEmail, $siteName, $fromAddress, $fromName) {
                $message->to($testEmail)
                    ->from($fromAddress, $fromName)
                    ->subject('Test Email from ' . $siteName);
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $testEmail
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    private function applyEmailSettings(array $settings): void
    {
        if (isset($settings['smtp_host'])) {
            Config::set('mail.mailers.smtp.host', $settings['smtp_host']);
        }
        if (isset($settings['smtp_port'])) {
            Config::set('mail.mailers.smtp.port', $settings['smtp_port']);
        }
        if (isset($settings['smtp_username'])) {
            Config::set('mail.mailers.smtp.username', $settings['smtp_username']);
        }
        if (isset($settings['smtp_password'])) {
            Config::set('mail.mailers.smtp.password', $settings['smtp_password']);
        }
        if (isset($settings['smtp_encryption'])) {
            Config::set('mail.mailers.smtp.encryption', $settings['smtp_encryption'] === 'none' ? null : $settings['smtp_encryption']);
        }
        if (isset($settings['smtp_from_address'])) {
            Config::set('mail.from.address', $settings['smtp_from_address']);
        }
        if (isset($settings['smtp_from_name'])) {
            Config::set('mail.from.name', $settings['smtp_from_name']);
        }
    }

    private function convertValueForStorage($value, string $type): string
    {
        return match($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json', 'array' => is_string($value) ? $value : json_encode($value),
            default => (string) $value
        };
    }

    private function getDefaultTypes(): array
    {
        return [
            // General
            'site_name' => 'string',
            'site_email' => 'string',
            'support_email' => 'string',
            'default_currency' => 'string',
            'timezone' => 'string',
            'maintenance_mode' => 'boolean',
            
            // Email
            'smtp_host' => 'string',
            'smtp_port' => 'integer',
            'smtp_username' => 'string',
            'smtp_password' => 'string',
            'smtp_encryption' => 'string',
            'smtp_from_address' => 'string',
            'smtp_from_name' => 'string',
            
            // Security
            'allow_registration' => 'boolean',
            'require_email_verification' => 'boolean',
            'session_timeout' => 'integer',
            'password_min_length' => 'integer',
            'require_strong_password' => 'boolean',
            'enable_two_factor' => 'boolean',
            'max_login_attempts' => 'integer',
            
            // Notifications
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'admin_alerts' => 'boolean',
            'notification_email' => 'string',
        ];
    }

    private function getDefaultCategories(): array
    {
        return [
            // General
            'site_name' => 'general',
            'site_email' => 'general',
            'support_email' => 'general',
            'default_currency' => 'general',
            'timezone' => 'general',
            'maintenance_mode' => 'general',
            
            // Email
            'smtp_host' => 'email',
            'smtp_port' => 'email',
            'smtp_username' => 'email',
            'smtp_password' => 'email',
            'smtp_encryption' => 'email',
            'smtp_from_address' => 'email',
            'smtp_from_name' => 'email',
            
            // Security
            'allow_registration' => 'security',
            'require_email_verification' => 'security',
            'session_timeout' => 'security',
            'password_min_length' => 'security',
            'require_strong_password' => 'security',
            'enable_two_factor' => 'security',
            'max_login_attempts' => 'security',
            
            // Notifications
            'email_notifications' => 'notifications',
            'sms_notifications' => 'notifications',
            'admin_alerts' => 'notifications',
            'notification_email' => 'notifications',
        ];
    }

    private function getDescription(string $key): ?string
    {
        $descriptions = [
            'site_name' => 'The name of your site',
            'site_email' => 'Primary contact email for the site',
            'support_email' => 'Support email address',
            'default_currency' => 'Default currency code (e.g., NGN, USD)',
            'timezone' => 'Timezone (e.g., Africa/Lagos)',
            'maintenance_mode' => 'Enable maintenance mode to disable public access',
            'smtp_host' => 'SMTP server hostname',
            'smtp_port' => 'SMTP server port',
            'smtp_username' => 'SMTP username',
            'smtp_password' => 'SMTP password',
            'smtp_encryption' => 'SMTP encryption (tls or ssl)',
            'smtp_from_address' => 'Default sender email address',
            'smtp_from_name' => 'Default sender name',
            'allow_registration' => 'Allow new users to register',
            'require_email_verification' => 'Require email verification before access',
            'session_timeout' => 'Session timeout in minutes',
            'password_min_length' => 'Minimum password length',
            'require_strong_password' => 'Require strong passwords',
            'enable_two_factor' => 'Enable two-factor authentication',
            'max_login_attempts' => 'Maximum login attempts before lockout',
            'email_notifications' => 'Enable email notifications',
            'sms_notifications' => 'Enable SMS notifications',
            'admin_alerts' => 'Send alerts to administrators',
            'notification_email' => 'Email address for notifications',
        ];

        return $descriptions[$key] ?? null;
    }
}

