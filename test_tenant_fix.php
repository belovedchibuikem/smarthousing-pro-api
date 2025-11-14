<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing tenant resolution with fixed data...\n";

try {
    // Clear any caches
    \Cache::flush();
    
    // Get fresh tenant instance
    $tenant = \App\Models\Central\Tenant::find('frsc');
    echo "Tenant ID: " . $tenant->id . "\n";
    echo "Raw data: " . $tenant->getRawOriginal('data') . "\n";
    echo "Casted data: " . json_encode($tenant->data) . "\n";
    
    // Test the domain resolver manually
    echo "\nTesting domain resolution...\n";
    $domain = DB::connection('mysql')->table('domains')->where('domain', 'localhost:8000')->first();
    if ($domain) {
        echo "✅ Domain: {$domain->domain} -> {$domain->tenant_id}\n";
        
        $resolvedTenant = \App\Models\Central\Tenant::find($domain->tenant_id);
        if ($resolvedTenant) {
            echo "✅ Tenant found: {$resolvedTenant->id}\n";
            echo "Tenant data: " . json_encode($resolvedTenant->data) . "\n";
            
            // Check if tenant is active
            if (isset($resolvedTenant->data['status']) && $resolvedTenant->data['status'] === 'active') {
                echo "✅ Tenant is active\n";
            } else {
                echo "❌ Tenant is not active\n";
            }
        }
    }
    
    // Test the actual login endpoint
    echo "\nTesting login endpoint...\n";
    $response = \Illuminate\Support\Facades\Http::post('http://127.0.0.1:8000/api/auth/login', [
        'email' => 'admin@tenant.test',
        'password' => 'Password123!'
    ]);
    
    echo "Login response status: " . $response->status() . "\n";
    echo "Login response body: " . $response->body() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
