<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing tenant data fix...\n";

try {
    $tenant = \App\Models\Central\Tenant::find('frsc');
    if ($tenant) {
        echo "✅ Tenant found: {$tenant->id}\n";
        echo "Data attribute: " . json_encode($tenant->data) . "\n";
        echo "Data type: " . gettype($tenant->data) . "\n";
        
        if (isset($tenant->data['status'])) {
            echo "✅ Status: {$tenant->data['status']}\n";
        } else {
            echo "❌ Status not found\n";
        }
    }
    
    // Test domain resolution
    echo "\nTesting domain resolution...\n";
    $domain = DB::connection('mysql')->table('domains')->where('domain', 'localhost:8000')->first();
    if ($domain) {
        echo "✅ Domain: {$domain->domain} -> {$domain->tenant_id}\n";
        
        $resolvedTenant = \App\Models\Central\Tenant::find($domain->tenant_id);
        if ($resolvedTenant && $resolvedTenant->data) {
            echo "✅ Tenant resolved with data: " . json_encode($resolvedTenant->data) . "\n";
        } else {
            echo "❌ Tenant resolution failed\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
