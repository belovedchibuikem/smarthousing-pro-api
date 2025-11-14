<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Fixing tenant data with raw SQL...\n";

try {
    // Update FRSC tenant data using raw SQL
    $frscData = json_encode([
        'name' => 'FRSC Housing Management',
        'slug' => 'frsc',
        'status' => 'active',
        'subscription_status' => 'active',
        'primary_color' => '#FDB11E',
        'secondary_color' => '#276254',
        'contact_email' => 'admin@frsc.com',
        'contact_phone' => '+234-123-456-7890',
        'address' => 'FRSC Headquarters, Abuja, Nigeria',
    ]);
    
    DB::connection('mysql')->table('tenants')
        ->where('id', 'frsc')
        ->update(['data' => $frscData]);
    
    echo "✅ FRSC tenant data updated with raw SQL\n";
    
    // Update ACME tenant data using raw SQL
    $acmeData = json_encode([
        'name' => 'ACME Corporation',
        'slug' => 'acme',
        'status' => 'active',
        'subscription_status' => 'trial',
        'primary_color' => '#3B82F6',
        'secondary_color' => '#1E40AF',
        'contact_email' => 'admin@acme.com',
        'contact_phone' => '+234-987-654-3210',
        'address' => 'ACME Building, Lagos, Nigeria',
    ]);
    
    DB::connection('mysql')->table('tenants')
        ->where('id', 'acme')
        ->update(['data' => $acmeData]);
    
    echo "✅ ACME tenant data updated with raw SQL\n";
    
    // Verify the data
    $frscTenant = \App\Models\Central\Tenant::find('frsc');
    echo "FRSC tenant data: " . json_encode($frscTenant->data) . "\n";
    
    // Test tenant resolution
    echo "\nTesting tenant resolution...\n";
    $domain = DB::connection('mysql')->table('domains')->where('domain', 'localhost:8000')->first();
    if ($domain) {
        echo "✅ Domain found: {$domain->domain} -> {$domain->tenant_id}\n";
        $tenant = \App\Models\Central\Tenant::find($domain->tenant_id);
        if ($tenant && $tenant->data) {
            echo "✅ Tenant data: " . json_encode($tenant->data) . "\n";
        } else {
            echo "❌ Tenant data is still null\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
