<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing Tenant User Login\n";
echo "========================\n\n";

try {
    // Check if there are any tenant databases and users
    echo "1. Checking for tenant databases...\n";
    
    // Get list of databases
    $databases = DB::select("SHOW DATABASES");
    $tenantDbs = [];
    foreach ($databases as $db) {
        if (strpos($db->Database, '_smart_housing') !== false && $db->Database !== 'smart_housing_central') {
            $tenantDbs[] = $db->Database;
        }
    }
    
    if (empty($tenantDbs)) {
        echo "   No tenant databases found\n";
        echo "   Available databases:\n";
        foreach ($databases as $db) {
            echo "   - {$db->Database}\n";
        }
    } else {
        echo "   Found tenant databases:\n";
        foreach ($tenantDbs as $db) {
            echo "   - $db\n";
        }
        
        // Test with first tenant database
        $tenantDb = $tenantDbs[0];
        echo "\n2. Testing with tenant database: $tenantDb\n";
        
        // Connect to tenant database
        config(['database.connections.tenant.database' => $tenantDb]);
        DB::purge('tenant');
        
        // Check for users in tenant database
        $users = DB::connection('tenant')->table('users')->select('id', 'email', 'first_name', 'last_name')->limit(3)->get();
        
        if ($users->isEmpty()) {
            echo "   No users found in tenant database\n";
        } else {
            echo "   Found users in tenant database:\n";
            foreach ($users as $user) {
                echo "   - {$user->email} ({$user->first_name} {$user->last_name})\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTenant user check completed.\n";
