<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking tenant data...\n";

try {
    $tenant = \App\Models\Central\Tenant::find('frsc');
    if ($tenant) {
        echo "✅ Tenant found: {$tenant->id}\n";
        echo "Data: " . json_encode($tenant->data) . "\n";
        echo "Raw data: " . $tenant->getRawOriginal('data') . "\n";
    } else {
        echo "❌ Tenant not found\n";
    }
    
    // Check if we need to run the tenant seeder
    echo "\nRunning TenantSeeder...\n";
    $seeder = new \Database\Seeders\Central\TenantSeeder();
    $seeder->run();
    
    // Check again
    $tenant = \App\Models\Central\Tenant::find('frsc');
    if ($tenant) {
        echo "✅ After seeder - Tenant data: " . json_encode($tenant->data) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
