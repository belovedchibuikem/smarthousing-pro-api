<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "=== MIGRATING DATABASES TO NEW NAMING ===\n\n";

$tenants = ['frsc', 'acme'];

foreach ($tenants as $tenantId) {
    $oldDbName = $tenantId . '_housing_tenant_template';
    $newDbName = $tenantId . '_smart_housing';
    
    echo "Processing tenant: {$tenantId}\n";
    
    try {
        // Check if old database exists
        $result = DB::select("SHOW DATABASES LIKE '{$oldDbName}'");
        
        if (count($result) > 0) {
            echo "   ✅ Found old database: {$oldDbName}\n";
            
            // Create new database
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$newDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "   ✅ Created new database: {$newDbName}\n";
            
            // Copy all tables from old to new database
            $tables = DB::select("SHOW TABLES FROM `{$oldDbName}`");
            
            foreach ($tables as $table) {
                $tableName = array_values((array)$table)[0];
                echo "   - Copying table: {$tableName}\n";
                
                // Create table structure
                DB::statement("CREATE TABLE `{$newDbName}`.`{$tableName}` LIKE `{$oldDbName}`.`{$tableName}`");
                
                // Copy data
                DB::statement("INSERT INTO `{$newDbName}`.`{$tableName}` SELECT * FROM `{$oldDbName}`.`{$tableName}`");
            }
            
            echo "   ✅ All tables copied successfully\n";
            
            // Drop old database
            DB::statement("DROP DATABASE `{$oldDbName}`");
            echo "   ✅ Old database dropped\n";
            
        } else {
            echo "   ⚠️  Old database '{$oldDbName}' not found\n";
            
            // Check if new database already exists
            $result = DB::select("SHOW DATABASES LIKE '{$newDbName}'");
            if (count($result) > 0) {
                echo "   ✅ New database '{$newDbName}' already exists\n";
            } else {
                echo "   ❌ Neither old nor new database exists\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== MIGRATION COMPLETE ===\n";
