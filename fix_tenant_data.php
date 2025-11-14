<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Fixing tenant data...\n";

try {
    // Update FRSC tenant data
    $frscTenant = \App\Models\Central\Tenant::find('frsc');
    if ($frscTenant) {
        $frscTenant->update([
            'data' => [
                'name' => 'FRSC Housing Management',
                'slug' => 'frsc',
                'status' => 'active',
                'subscription_status' => 'active',
                'primary_color' => '#FDB11E',
                'secondary_color' => '#276254',
                'contact_email' => 'admin@frsc.com',
                'contact_phone' => '+234-123-456-7890',
                'address' => 'FRSC Headquarters, Abuja, Nigeria',
            ]
        ]);
        echo "✅ FRSC tenant data updated\n";
    }
    
    // Update ACME tenant data
    $acmeTenant = \App\Models\Central\Tenant::find('acme');
    if ($acmeTenant) {
        $acmeTenant->update([
            'data' => [
                'name' => 'ACME Corporation',
                'slug' => 'acme',
                'status' => 'active',
                'subscription_status' => 'trial',
                'primary_color' => '#3B82F6',
                'secondary_color' => '#1E40AF',
                'contact_email' => 'admin@acme.com',
                'contact_phone' => '+234-987-654-3210',
                'address' => 'ACME Building, Lagos, Nigeria',
            ]
        ]);
        echo "✅ ACME tenant data updated\n";
    }
    
    // Verify the data
    $frscTenant = \App\Models\Central\Tenant::find('frsc');
    echo "FRSC tenant data: " . json_encode($frscTenant->data) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
