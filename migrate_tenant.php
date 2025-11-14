<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$tenantId = $argv[1] ?? null;

if (!$tenantId) {
    echo "Usage: php migrate_tenant.php <tenant_id>\n";
    exit(1);
}

echo "Running tenant migrations for: {$tenantId}\n\n";

try {
    // Set the tenant database name
    $tenantDbName = $tenantId . '_smart_housing';
    
    // Temporarily set the tenant database connection
    Config::set('database.connections.tenant.database', $tenantDbName);
    DB::purge('tenant'); // Clear any cached connections
    
    echo "Using database: {$tenantDbName}\n";
    
    // Run tenant migrations directly on the tenant connection
    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);
    
    echo Artisan::output();
    echo "\n✅ Tenant migrations completed successfully for {$tenantId}!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error running tenant migrations for {$tenantId}: " . $e->getMessage() . "\n";
    exit(1);
}
