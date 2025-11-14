<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Central\Role;
use App\Models\Central\Permission;

echo "🚀 Setting up Central Roles and Permissions System...\n\n";

try {
    // Run migrations
    echo "📦 Running migrations...\n";
    Artisan::call('migrate', ['--path' => 'database/migrations/central']);
    echo "✅ Migrations completed successfully!\n\n";

    // Run seeder
    echo "🌱 Running seeder...\n";
    Artisan::call('db:seed', ['--class' => 'CentralRolePermissionSeeder']);
    echo "✅ Seeder completed successfully!\n\n";

    // Verify data
    echo "🔍 Verifying data...\n";
    $rolesCount = Role::count();
    $permissionsCount = Permission::count();
    
    echo "📊 Created {$rolesCount} roles and {$permissionsCount} permissions\n";
    
    // Show roles
    echo "\n📋 Created Roles:\n";
    foreach (Role::with('permissions')->get() as $role) {
        echo "  • {$role->name} ({$role->permissions->count()} permissions)\n";
    }
    
    echo "\n🎉 Central Roles and Permissions setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}


