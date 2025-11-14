<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;
use App\Models\Central\Role;
use App\Models\Central\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create Super Admin Role
        $superAdminRole = Role::updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'type' => 'system',
                'is_active' => true,
                'is_system_role' => true,
            ]
        );

        // Create System Administrator Role
        $systemAdminRole = Role::updateOrCreate(
            ['slug' => 'system-admin'],
            [
                'name' => 'System Administrator',
                'description' => 'System administration with most permissions',
                'type' => 'system',
                'is_active' => true,
                'is_system_role' => true,
            ]
        );

        // Create Tenant Administrator Role
        $tenantAdminRole = Role::updateOrCreate(
            ['slug' => 'tenant-admin'],
            [
                'name' => 'Tenant Administrator',
                'description' => 'Tenant management and administration',
                'type' => 'system',
                'is_active' => true,
                'is_system_role' => false,
            ]
        );

        // Create Support Role
        $supportRole = Role::updateOrCreate(
            ['slug' => 'support'],
            [
                'name' => 'Support Staff',
                'description' => 'Customer support and basic administration',
                'type' => 'system',
                'is_active' => true,
                'is_system_role' => false,
            ]
        );

        // Assign permissions to Super Admin Role (ALL permissions)
        $allPermissions = Permission::all();
        $superAdminRole->permissions()->sync($allPermissions->pluck('id'));

        // Assign permissions to System Admin Role (most permissions except sensitive ones)
        $systemAdminPermissions = Permission::whereNotIn('slug', [
            'users.impersonate',
            'system.backup',
            'system.restore',
            'data.migrate',
            '*'
        ])->get();
        $systemAdminRole->permissions()->sync($systemAdminPermissions->pluck('id'));

        // Assign permissions to Tenant Admin Role
        $tenantAdminPermissions = Permission::whereIn('category', [
            'tenants',
            'users',
            'analytics',
            'reports',
            'dashboard'
        ])->get();
        $tenantAdminRole->permissions()->sync($tenantAdminPermissions->pluck('id'));

        // Assign permissions to Support Role
        $supportPermissions = Permission::whereIn('slug', [
            'users.view',
            'tenants.view',
            'analytics.view',
            'reports.generate',
            'dashboard.admin',
            'notifications.send',
            'emails.manage',
            'communications.manage'
        ])->get();
        $supportRole->permissions()->sync($supportPermissions->pluck('id'));
    }
}


