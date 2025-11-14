<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking raw database data...\n";

try {
    // Check raw tenant data
    $tenant = DB::connection('mysql')->table('tenants')->where('id', 'frsc')->first();
    if ($tenant) {
        echo "Raw tenant data: " . $tenant->data . "\n";
        echo "Raw tenant data type: " . gettype($tenant->data) . "\n";
        
        // Try to decode it
        $decoded = json_decode($tenant->data, true);
        echo "Decoded data: " . json_encode($decoded) . "\n";
    }
    
    // Check using Eloquent
    $eloquentTenant = \App\Models\Central\Tenant::find('frsc');
    if ($eloquentTenant) {
        echo "Eloquent data: " . json_encode($eloquentTenant->data) . "\n";
        echo "Eloquent raw data: " . $eloquentTenant->getRawOriginal('data') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
