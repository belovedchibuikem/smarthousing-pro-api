<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RENAMING TENANT DATABASES ===\n\n";

$tenants = ['frsc', 'acme'];

foreach ($tenants as $tenantId) {
    $oldDbName = $tenantId . '_housing_tenant_template';
    $newDbName = $tenantId . '_housing';
    
    echo "Renaming {$oldDbName} to {$newDbName}...\n";
    
    try {
        // Check if old database exists
        $result = DB::select("SHOW DATABASES LIKE '{$oldDbName}'");
        
        if (count($result) > 0) {
            // Rename the database
            DB::statement("RENAME DATABASE `{$oldDbName}` TO `{$newDbName}`");
            echo "   ✅ Database renamed successfully\n";
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
}

echo "\n=== RENAME COMPLETE ===\n";
