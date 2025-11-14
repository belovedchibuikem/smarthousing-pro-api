<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Central\Role;
use App\Models\Central\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class SpatiePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing permissions and roles
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('permissions')->truncate();

        // Create permissions
        $permissions = [
            // Business Management
            'businesses.view',
            'businesses.create',
            'businesses.edit',
            'businesses.delete',
            'businesses.suspend',
            'businesses.activate',
            
            // User Management
            'super_admins.view',
            'super_admins.create',
            'super_admins.edit',
            'super_admins.delete',
            
            // Role Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            
            // Package Management
            'packages.view',
            'packages.create',
            'packages.edit',
            'packages.delete',
            
            // Module Management
            'modules.view',
            'modules.create',
            'modules.edit',
            'modules.delete',
            
            // Analytics & Reports
            'analytics.view',
            'reports.view',
            
            // System Settings
            'settings.view',
            'settings.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // Get all permissions
        $allPermissions = Permission::all();
        
        // Update existing roles with guard_name
        $roles = Role::all();
        foreach ($roles as $role) {
            $role->update(['guard_name' => 'web']);
        }

        // Assign permissions to roles
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            // Super Admin gets all permissions
            $superAdminRole->syncPermissions($allPermissions);
        }

        $businessManagerRole = Role::where('name', 'Business Manager')->first();
        if ($businessManagerRole) {
            // Business Manager gets business and user management permissions
            $businessPermissions = $allPermissions->whereIn('name', [
                'businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete', 'businesses.suspend', 'businesses.activate',
                'super_admins.view', 'super_admins.create', 'super_admins.edit', 'super_admins.delete',
                'analytics.view', 'reports.view'
            ]);
            $businessManagerRole->syncPermissions($businessPermissions);
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

        $this->command->info('Spatie permissions and role assignments created successfully!');
    }
}
