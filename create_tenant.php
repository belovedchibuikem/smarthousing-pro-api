<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create frsc tenant
\App\Models\Central\Tenant::create([
    'id' => 'frsc',
    'data' => [
        'name' => 'FRSC Housing Cooperative',
        'slug' => 'frsc',
        'custom_domain' => null,
        'logo_url' => null,
        'primary_color' => '#FDB11E',
        'secondary_color' => '#276254',
        'contact_email' => 'info@frsc-housing.com',
        'contact_phone' => '+234 800 000 0000',
        'address' => 'Abuja, Nigeria',
        'status' => 'active',
        'subscription_status' => 'active',
        'trial_ends_at' => null,
        'subscription_ends_at' => '2025-12-31T23:59:59Z',
        'settings' => [],
        'metadata' => [],
    ]
]);

echo "Tenant 'frsc' created successfully!\n";
