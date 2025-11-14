<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "=== VERIFYING SEEDED DATA ===\n\n";

// 1. Check central database
echo "1. Central Database (frsc_housing_central):\n";
try {
    // Check packages
    $packages = DB::table('packages')->count();
    echo "   - Packages: {$packages}\n";
    
    // Check super admins
    $superAdmins = DB::table('super_admins')->count();
    echo "   - Super Admins: {$superAdmins}\n";
    
    // Check tenants
    $tenants = DB::table('tenants')->count();
    echo "   - Tenants: {$tenants}\n";
    
    echo "✅ Central database has data\n\n";
} catch (\Exception $e) {
    echo "❌ Error checking central database: " . $e->getMessage() . "\n\n";
}

// 2. Check tenant databases
$tenants = ['frsc', 'acme'];

foreach ($tenants as $tenantId) {
    $tenantDbName = $tenantId . '_housing';
    
    echo "2. Tenant Database ({$tenantDbName}):\n";
    try {
        // Set the tenant database connection
        Config::set('database.connections.tenant.database', $tenantDbName);
        DB::purge('tenant');
        
        // Check users
        $users = DB::connection('tenant')->table('users')->count();
        echo "   - Users: {$users}\n";
        
        // Check roles
        $roles = DB::connection('tenant')->table('roles')->count();
        echo "   - Roles: {$roles}\n";
        
        // Check landing page configs
        $landingPages = DB::connection('tenant')->table('landing_page_configs')->count();
        echo "   - Landing Page Configs: {$landingPages}\n";
        
        // Check wallets
        $wallets = DB::connection('tenant')->table('wallets')->count();
        echo "   - Wallets: {$wallets}\n";
        
        echo "✅ Tenant database '{$tenantDbName}' has data\n\n";
    } catch (\Exception $e) {
        echo "❌ Error checking tenant database '{$tenantDbName}': " . $e->getMessage() . "\n\n";
    }
}

echo "=== VERIFICATION COMPLETE ===\n";
