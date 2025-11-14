<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Setting up permissions and role system...\n";
echo "========================================\n\n";

try {
    // Run migrations
    echo "1. Running migrations...\n";
    \Artisan::call('migrate', ['--path' => 'database/migrations/central']);
    echo "✓ Migrations completed\n\n";

    // Run seeder
    echo "2. Seeding permissions and roles...\n";
    \Artisan::call('db:seed', ['--class' => 'PermissionSeeder']);
    echo "✓ Seeding completed\n\n";

    // Verify setup
    echo "3. Verifying setup...\n";
    
    $roles = \App\Models\Central\Role::withCount('superAdmins')->get();
    echo "Roles found: " . $roles->count() . "\n";
    
    foreach ($roles as $role) {
        echo "  - {$role->name}: {$role->super_admins_count} users, " . $role->permissions()->count() . " permissions\n";
    }
    
    $permissions = \App\Models\Central\Permission::count();
    echo "\nTotal permissions: {$permissions}\n";
    
    $userRoles = \DB::table('user_roles')->count();
    echo "User-role assignments: {$userRoles}\n";
    
    $rolePermissions = \DB::table('role_permissions')->count();
    echo "Role-permission assignments: {$rolePermissions}\n";

    echo "\n✅ Permission system setup completed successfully!\n";
    echo "The role count should now display correctly in the frontend.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check the error and try again.\n";
}


