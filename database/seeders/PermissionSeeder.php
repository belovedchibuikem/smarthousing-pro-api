<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Central\Role;
use App\Models\Central\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Business Management
            ['name' => 'View Businesses', 'slug' => 'businesses.view', 'group' => 'businesses', 'description' => 'View all businesses'],
            ['name' => 'Create Businesses', 'slug' => 'businesses.create', 'group' => 'businesses', 'description' => 'Create new businesses'],
            ['name' => 'Edit Businesses', 'slug' => 'businesses.edit', 'group' => 'businesses', 'description' => 'Edit existing businesses'],
            ['name' => 'Delete Businesses', 'slug' => 'businesses.delete', 'group' => 'businesses', 'description' => 'Delete businesses'],
            ['name' => 'Suspend Businesses', 'slug' => 'businesses.suspend', 'group' => 'businesses', 'description' => 'Suspend businesses'],
            ['name' => 'Activate Businesses', 'slug' => 'businesses.activate', 'group' => 'businesses', 'description' => 'Activate businesses'],
            
            // User Management
            ['name' => 'View Super Admins', 'slug' => 'super_admins.view', 'group' => 'users', 'description' => 'View super admin users'],
            ['name' => 'Create Super Admins', 'slug' => 'super_admins.create', 'group' => 'users', 'description' => 'Create super admin users'],
            ['name' => 'Edit Super Admins', 'slug' => 'super_admins.edit', 'group' => 'users', 'description' => 'Edit super admin users'],
            ['name' => 'Delete Super Admins', 'slug' => 'super_admins.delete', 'group' => 'users', 'description' => 'Delete super admin users'],
            
            // Role Management
            ['name' => 'View Roles', 'slug' => 'roles.view', 'group' => 'roles', 'description' => 'View roles and permissions'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'group' => 'roles', 'description' => 'Create new roles'],
            ['name' => 'Edit Roles', 'slug' => 'roles.edit', 'group' => 'roles', 'description' => 'Edit existing roles'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'group' => 'roles', 'description' => 'Delete roles'],
            
            // Package Management
            ['name' => 'View Packages', 'slug' => 'packages.view', 'group' => 'packages', 'description' => 'View subscription packages'],
            ['name' => 'Create Packages', 'slug' => 'packages.create', 'group' => 'packages', 'description' => 'Create new packages'],
            ['name' => 'Edit Packages', 'slug' => 'packages.edit', 'group' => 'packages', 'description' => 'Edit existing packages'],
            ['name' => 'Delete Packages', 'slug' => 'packages.delete', 'group' => 'packages', 'description' => 'Delete packages'],
            
            // Module Management
            ['name' => 'View Modules', 'slug' => 'modules.view', 'group' => 'modules', 'description' => 'View system modules'],
            ['name' => 'Create Modules', 'slug' => 'modules.create', 'group' => 'modules', 'description' => 'Create new modules'],
            ['name' => 'Edit Modules', 'slug' => 'modules.edit', 'group' => 'modules', 'description' => 'Edit existing modules'],
            ['name' => 'Delete Modules', 'slug' => 'modules.delete', 'group' => 'modules', 'description' => 'Delete modules'],
            
            // Analytics & Reports
            ['name' => 'View Analytics', 'slug' => 'analytics.view', 'group' => 'analytics', 'description' => 'View system analytics'],
            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports', 'description' => 'View system reports'],
            
            // System Settings
            ['name' => 'View Settings', 'slug' => 'settings.view', 'group' => 'settings', 'description' => 'View system settings'],
            ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'group' => 'settings', 'description' => 'Edit system settings'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::updateOrCreate(
                ['name' => $permissionData['slug']],
                [
                    'name' => $permissionData['slug'],
                    'guard_name' => 'web'
                ]
            );
        }

        // Get all permissions
        $allPermissions = Permission::all();
        
        // Assign permissions to roles using Spatie
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            // Super Admin gets all permissions
            $superAdminRole->syncPermissions($allPermissions);
        }

        $businessManagerRole = Role::where('name', 'Business Manager')->first();
        if ($businessManagerRole) {
            // Business Manager gets business and user management permissions
            $businessManagerPermissions = $allPermissions->whereIn('name', [
                'businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete', 'businesses.suspend', 'businesses.activate',
                'super_admins.view', 'super_admins.create', 'super_admins.edit', 'super_admins.delete',
                'analytics.view', 'reports.view'
            ]);
                
            $businessManagerRole->syncPermissions($businessManagerPermissions);
        }

        $supportAgentRole = Role::where('name', 'Support Agent')->first();
        if ($supportAgentRole) {
            // Support Agent gets limited permissions
            $supportPermissions = $allPermissions->whereIn('name', [
                'businesses.view',
                'super_admins.view',
                'analytics.view',
                'reports.view'
            ]);
            
            $supportAgentRole->syncPermissions($supportPermissions);
        }

        // Assign roles to existing super admins
        $superAdmins = SuperAdmin::all();
        if ($superAdmins->count() >= 1) {
            $firstAdmin = $superAdmins->first();
            if ($superAdminRole) {
                $firstAdmin->assignRole($superAdminRole);
            }
        }

        if ($superAdmins->count() >= 2) {
            $secondAdmin = $superAdmins->skip(1)->first();
            if ($businessManagerRole) {
                $secondAdmin->assignRole($businessManagerRole);
            }
        }

        $this->command->info('Permissions and role assignments created successfully!');
    }
}

