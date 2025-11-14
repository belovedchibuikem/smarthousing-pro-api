<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$tenantId = $argv[1] ?? null;

if (!$tenantId) {
    echo "Usage: php clean_tenant_db.php <tenant_id>\n";
    exit(1);
}

$tenantDbName = $tenantId . '_housing';

echo "Cleaning tenant database: {$tenantDbName}\n\n";

try {
    // Set the tenant database connection
    Config::set('database.connections.tenant.database', $tenantDbName);
    DB::purge('tenant');
    
    // Drop and recreate the database
    $centralDbName = env('DB_DATABASE', 'frsc_housing_central');
    Config::set('database.connections.mysql.database', $centralDbName);
    DB::purge('mysql');
    
    // Drop the tenant database
    DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$tenantDbName}`");
    echo "✅ Dropped database: {$tenantDbName}\n";
    
    // Create the tenant database
    DB::connection('mysql')->statement("CREATE DATABASE `{$tenantDbName}`");
    echo "✅ Created database: {$tenantDbName}\n";
    
    echo "\n✅ Tenant database cleaned successfully!\n";
    echo "You can now run: php migrate_tenant.php {$tenantId}\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error cleaning tenant database: " . $e->getMessage() . "\n";
    exit(1);
}
