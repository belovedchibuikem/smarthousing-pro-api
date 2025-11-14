<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "=== RUNNING SEEDERS ===\n\n";

// 1. Run central database seeders
echo "1. Seeding central database...\n";
try {
    // Run PackageSeeder
    echo "   - Running PackageSeeder...\n";
    $packageSeeder = new \Database\Seeders\Central\PackageSeeder();
    $packageSeeder->run();
    echo "   ✅ PackageSeeder completed\n";
    
    // Run SuperAdminSeeder
    echo "   - Running SuperAdminSeeder...\n";
    $superAdminSeeder = new \Database\Seeders\Central\SuperAdminSeeder();
    $superAdminSeeder->run();
    echo "   ✅ SuperAdminSeeder completed\n";
    
    echo "✅ Central database seeded successfully!\n\n";
} catch (\Exception $e) {
    echo "❌ Error seeding central database: " . $e->getMessage() . "\n\n";
}

// 2. Run tenant database seeders for each tenant
$tenants = ['frsc', 'acme'];

foreach ($tenants as $tenantId) {
    $tenantDbName = $tenantId . '_housing_tenant_template';
    
    echo "2. Seeding tenant database: {$tenantDbName}\n";
    try {
        // Set the tenant database connection
        Config::set('database.connections.tenant.database', $tenantDbName);
        DB::purge('tenant');
        
        // Run TenantDatabaseSeeder
        echo "   - Running TenantDatabaseSeeder...\n";
        $tenantSeeder = new \Database\Seeders\Tenant\TenantDatabaseSeeder();
        $tenantSeeder->run();
        echo "   ✅ TenantDatabaseSeeder completed\n";
        
        echo "✅ Tenant database '{$tenantDbName}' seeded successfully!\n\n";
    } catch (\Exception $e) {
        echo "❌ Error seeding tenant database '{$tenantDbName}': " . $e->getMessage() . "\n\n";
    }
}

echo "=== SEEDING COMPLETE ===\n";
