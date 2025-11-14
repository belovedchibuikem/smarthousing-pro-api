<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Assigning permissions to roles...\n";
echo "================================\n\n";

try {
    // Get all permissions
    $allPermissions = App\Models\Central\Permission::all();
    echo "Total permissions: " . $allPermissions->count() . "\n";
    
    // Assign all permissions to Super Admin role
    $superAdminRole = App\Models\Central\Role::where('name', 'Super Admin')->first();
    if ($superAdminRole) {
        echo "Assigning all permissions to Super Admin role...\n";
        $superAdminRole->syncPermissions($allPermissions);
        echo "✓ Super Admin role assigned " . $allPermissions->count() . " permissions\n";
    }
    
    // Assign specific permissions to other roles
    $rolePermissions = [
        'Business Manager' => [
            'dashboard.view',
            'businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete', 'businesses.suspend', 'businesses.activate',
            'super_admins.view', 'super_admins.create', 'super_admins.edit',
            'analytics.view', 'analytics.dashboard', 'analytics.businesses',
            'reports.view', 'reports.financial',
            'mail.view', 'mail.send',
            'subscriptions.view', 'subscriptions.manage',
            'invoices.view', 'invoices.download',
            'settings.view'
        ],
        'Support Agent' => [
            'dashboard.view',
            'businesses.view',
            'super_admins.view',
            'analytics.view', 'analytics.dashboard',
            'reports.view',
            'mail.view', 'mail.send',
            'subscriptions.view',
            'invoices.view',
            'activity.view'
        ]
    ];
    
    foreach ($rolePermissions as $roleName => $permissionNames) {
        $role = App\Models\Central\Role::where('name', $roleName)->first();
        if ($role) {
            $permissions = App\Models\Central\Permission::whereIn('name', $permissionNames)->get();
            $role->syncPermissions($permissions);
            echo "✓ {$roleName} role assigned " . $permissions->count() . " permissions\n";
        }
    }
    
    // Assign Super Admin role to super admin user
    $superAdmin = App\Models\Central\SuperAdmin::where('email', 'superadmin@smart-housing.com')->first();
    if ($superAdmin && $superAdminRole) {
        $superAdmin->syncRoles([]);
        $superAdmin->assignRole($superAdminRole);
        echo "✓ Super Admin role assigned to super admin user\n";
    }
    
    echo "\n✅ Permission assignment completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
