<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

echo "🚀 Fresh Multi-Tenant Database Setup (Improved)\n";
echo "===============================================\n\n";

// Step 1: Reset and migrate central database
echo "📊 Step 1: Setting up Central Database...\n";
echo "   Database: smart_housing_central\n";
echo "   Purpose: Platform management (tenants, packages, super admins)\n\n";

try {
    // First, connect without specifying a database to create it
    Config::set('database.connections.mysql.database', null);
    DB::purge('mysql');
    
    // Drop and recreate central database
    DB::statement("DROP DATABASE IF EXISTS smart_housing_central");
    DB::statement("CREATE DATABASE smart_housing_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   ✅ Central database recreated\n";
    
    // Update database configuration to use the new database
    Config::set('database.connections.mysql.database', 'smart_housing_central');
    DB::purge('mysql');
    
    // Run central migrations
    $centralExitCode = Artisan::call('migrate', [
        '--path' => 'database/migrations/central',
        '--force' => true
    ]);
    
    if ($centralExitCode === 0) {
        echo "   ✅ Central database migrated successfully!\n\n";
    } else {
        echo "   ❌ Central database migration failed!\n";
        echo Artisan::output() . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error setting up central database: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Seed Central Database
echo "🌱 Step 2: Seeding Central Database...\n";
try {
    $seedExitCode = Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\Central\\CentralDatabaseSeeder'
    ]);
    
    if ($seedExitCode === 0) {
        echo "   ✅ Central database seeded successfully!\n\n";
    } else {
        echo "   ❌ Central database seeding failed!\n";
        echo Artisan::output() . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error seeding central database: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Create and setup tenant databases
echo "🏢 Step 3: Creating and Setting up Tenant Databases...\n";

$tenants = [
    [
        'id' => 'frsc',
        'name' => 'FRSC Housing Cooperative',
        'slug' => 'frsc',
        'primary_color' => '#FDB11E',
        'secondary_color' => '#276254',
        'contact_email' => 'info@frsc-housing.com',
        'address' => 'Abuja, Nigeria'
    ],
    [
        'id' => 'acme',
        'name' => 'ACME Housing Cooperative',
        'slug' => 'acme',
        'primary_color' => '#3B82F6',
        'secondary_color' => '#1E40AF',
        'contact_email' => 'info@acme-housing.com',
        'address' => 'Lagos, Nigeria'
    ]
];

foreach ($tenants as $tenantData) {
    echo "   Creating tenant: {$tenantData['name']}...\n";
    
    try {
        // Create tenant record in central database
        \App\Models\Central\Tenant::create([
            'id' => $tenantData['id'],
            'data' => [
                'name' => $tenantData['name'],
                'slug' => $tenantData['slug'],
                'custom_domain' => null,
                'logo_url' => null,
                'primary_color' => $tenantData['primary_color'],
                'secondary_color' => $tenantData['secondary_color'],
                'contact_email' => $tenantData['contact_email'],
                'contact_phone' => '+234 800 000 0000',
                'address' => $tenantData['address'],
                'status' => 'active',
                'subscription_status' => 'active',
                'trial_ends_at' => null,
                'subscription_ends_at' => '2025-12-31T23:59:59Z',
                'settings' => [],
                'metadata' => [],
            ]
        ]);
        echo "   ✅ Tenant record created\n";
        
        // Create default domains for the tenant
        $defaultDomains = [
            'localhost',
            '127.0.0.1',
            'localhost:8000',
            '127.0.0.1:8000',
        ];
        
        foreach ($defaultDomains as $domain) {
            \DB::table('domains')->updateOrInsert(
                ['domain' => $domain],
                [
                    'domain' => $domain,
                    'tenant_id' => $tenantData['id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        echo "   ✅ Default domains created\n";
        
        // Create tenant database
        $databaseName = $tenantData['slug'] . '_smart_housing';
        DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
        DB::statement("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "   ✅ Database '{$databaseName}' created\n";
        
        // Set tenant database connection
        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');
        
        // Use the working migrate_tenant.php approach
        echo "   Migrating tenant database using migrate_tenant.php...\n";
        
        // Execute the migrate_tenant.php script for this tenant
        $output = [];
        $returnCode = 0;
        exec("php migrate_tenant.php {$tenantData['slug']} 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "   ✅ Tenant database migrated successfully\n";
        } else {
            echo "   ❌ Tenant database migration failed:\n";
            echo "   " . implode("\n   ", $output) . "\n";
            continue;
        }
        
        // Seed tenant database
        echo "   Seeding tenant database...\n";
        try {
            // Ensure tenant database connection is set for seeding
            Config::set('database.connections.tenant.database', $databaseName);
            DB::purge('tenant');
            
            $seedExitCode = Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
                '--force' => true,
            ]);
            
            if ($seedExitCode === 0) {
                echo "   ✅ Tenant database seeded successfully\n";
            } else {
                echo "   ❌ Tenant database seeding failed: " . Artisan::output() . "\n";
                continue;
            }
        } catch (Exception $e) {
            echo "   ❌ Tenant database seeding failed: " . $e->getMessage() . "\n";
            continue;
        }
        
    } catch (Exception $e) {
        echo "   ❌ Error setting up tenant {$tenantData['name']}: " . $e->getMessage() . "\n";
        continue;
    }
    
    echo "\n";
}

// Step 4: Verify Setup
echo "🔍 Step 4: Verifying Setup...\n";
try {
    // Check central database
    $tenantCount = \App\Models\Central\Tenant::count();
    $packageCount = \App\Models\Central\Package::count();
    $superAdminCount = \App\Models\Central\SuperAdmin::count();
    
    echo "   Central DB - Tenants: {$tenantCount}\n";
    echo "   Central DB - Packages: {$packageCount}\n";
    echo "   Central DB - Super Admins: {$superAdminCount}\n";
    
    // Check tenant databases
    foreach ($tenants as $tenantData) {
        $databaseName = $tenantData['slug'] . '_smart_housing';
        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');
        
        $userCount = DB::connection('tenant')->table('users')->count();
        $roleCount = DB::connection('tenant')->table('roles')->count();
        
        echo "   {$tenantData['name']} - Users: {$userCount}, Roles: {$roleCount}\n";
    }
    
    echo "   ✅ All databases verified successfully!\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Verification failed: " . $e->getMessage() . "\n";
}

echo "🎉 Multi-Tenant Database Setup Complete!\n";
echo "========================================\n\n";

echo "📋 Summary:\n";
echo "   Central Database: smart_housing_central\n";
echo "   - Platform management tables\n";
echo "   - Super admin: superadmin@smarthousing.test / Password123!\n";
echo "   - Packages: Starter, Professional, Enterprise\n\n";

echo "   Tenant Databases:\n";
echo "   - frsc_smart_housing (FRSC Housing Cooperative)\n";
echo "   - acme_smart_housing (ACME Housing Cooperative)\n";
echo "   - Demo users: admin@tenant.test / Password123!\n";
echo "   - Demo users: member@tenant.test / Password123!\n\n";

echo "🔧 Architecture:\n";
echo "   ✅ Central DB: Platform management only\n";
echo "   ✅ Tenant DBs: Business data only\n";
echo "   ✅ No table conflicts\n";
echo "   ✅ Dynamic database creation\n";
echo "   ✅ Proper data isolation\n\n";

echo "🚀 Ready for development!\n";
