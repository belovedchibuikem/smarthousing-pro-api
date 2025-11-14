<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create acme tenant
\App\Models\Central\Tenant::create([
    'id' => 'acme',
    'data' => [
        'name' => 'ACME Housing Cooperative',
        'slug' => 'acme',
        'custom_domain' => null,
        'logo_url' => null,
        'primary_color' => '#3B82F6',
        'secondary_color' => '#1E40AF',
        'contact_email' => 'info@acme-housing.com',
        'contact_phone' => '+234 800 000 0001',
        'address' => 'Lagos, Nigeria',
        'status' => 'active',
        'subscription_status' => 'active',
        'trial_ends_at' => null,
        'subscription_ends_at' => '2025-12-31T23:59:59Z',
        'settings' => [],
        'metadata' => [],
    ]
]);

echo "Tenant 'acme' created successfully!\n";
