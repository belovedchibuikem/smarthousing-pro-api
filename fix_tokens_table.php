<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "=== FIXING PERSONAL ACCESS TOKENS TABLE ===\n\n";

$tenants = ['frsc', 'acme'];

foreach ($tenants as $tenantId) {
    $tenantDbName = $tenantId . '_housing';
    
    echo "Fixing tenant: {$tenantDbName}\n";
    try {
        // Set the tenant database connection
        Config::set('database.connections.tenant.database', $tenantDbName);
        DB::purge('tenant');
        
        // Drop the table
        DB::connection('tenant')->statement('DROP TABLE IF EXISTS personal_access_tokens');
        echo "   ✅ Dropped personal_access_tokens table\n";
        
        // Recreate with correct schema
        DB::connection('tenant')->statement("
            CREATE TABLE personal_access_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tokenable_id CHAR(36) NOT NULL,
                tokenable_type VARCHAR(125) NOT NULL,
                name VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                abilities TEXT NULL,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX personal_access_tokens_tokenable_id_tokenable_type_index (tokenable_id, tokenable_type)
            )
        ");
        echo "   ✅ Created personal_access_tokens table with UUID support\n";
        
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== FIX COMPLETE ===\n";
