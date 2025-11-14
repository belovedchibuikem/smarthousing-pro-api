<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Central\Role;
use App\Models\Central\Permission;
use Illuminate\Support\Facades\DB;

class CentralRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createPermissions();
        $this->createRoles();
        $this->assignPermissionsToRoles();
    }

    private function createPermissions()
    {
        $permissions = [
            // Platform Management
            [
                'name' => 'view_platform_dashboard',
                'description' => 'View platform dashboard and overview',
                'group' => 'platform',
                'sort_order' => 1,
            ],
            [
                'name' => 'manage_platform_settings',
                'description' => 'Manage platform-wide settings',
                'group' => 'platform',
                'sort_order' => 2,
            ],
            [
                'name' => 'view_platform_analytics',
                'description' => 'View platform analytics and metrics',
                'group' => 'platform',
                'sort_order' => 3,
            ],

            // Business Management
            [
                'name' => 'view_businesses',
                'description' => 'View all businesses',
                'group' => 'businesses',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_businesses',
                'description' => 'Create new businesses',
                'group' => 'businesses',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_businesses',
                'description' => 'Edit business information',
                'group' => 'businesses',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_businesses',
                'description' => 'Delete businesses',
                'group' => 'businesses',
                'sort_order' => 4,
            ],
            [
                'name' => 'suspend_businesses',
                'description' => 'Suspend business accounts',
                'group' => 'businesses',
                'sort_order' => 5,
            ],
            [
                'name' => 'activate_businesses',
                'description' => 'Activate business accounts',
                'group' => 'businesses',
                'sort_order' => 6,
            ],
            [
                'name' => 'manage_business_domains',
                'description' => 'Manage business custom domains',
                'group' => 'businesses',
                'sort_order' => 7,
            ],

            // Package Management
            [
                'name' => 'view_packages',
                'description' => 'View subscription packages',
                'group' => 'packages',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_packages',
                'description' => 'Create subscription packages',
                'group' => 'packages',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_packages',
                'description' => 'Edit subscription packages',
                'group' => 'packages',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_packages',
                'description' => 'Delete subscription packages',
                'group' => 'packages',
                'sort_order' => 4,
            ],
            [
                'name' => 'toggle_packages',
                'description' => 'Enable/disable packages',
                'group' => 'packages',
                'sort_order' => 5,
            ],

            // Subscription Management
            [
                'name' => 'view_subscriptions',
                'description' => 'View business subscriptions',
                'group' => 'subscriptions',
                'sort_order' => 1,
            ],
            [
                'name' => 'manage_subscriptions',
                'description' => 'Manage business subscriptions',
                'group' => 'subscriptions',
                'sort_order' => 2,
            ],
            [
                'name' => 'cancel_subscriptions',
                'description' => 'Cancel business subscriptions',
                'group' => 'subscriptions',
                'sort_order' => 3,
            ],
            [
                'name' => 'extend_subscriptions',
                'description' => 'Extend business subscriptions',
                'group' => 'subscriptions',
                'sort_order' => 4,
            ],

            // Module Management
            [
                'name' => 'view_modules',
                'description' => 'View platform modules',
                'group' => 'modules',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_modules',
                'description' => 'Create platform modules',
                'group' => 'modules',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_modules',
                'description' => 'Edit platform modules',
                'group' => 'modules',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_modules',
                'description' => 'Delete platform modules',
                'group' => 'modules',
                'sort_order' => 4,
            ],
            [
                'name' => 'toggle_modules',
                'description' => 'Enable/disable modules',
                'group' => 'modules',
                'sort_order' => 5,
            ],

            // User Management
            [
                'name' => 'view_super_admins',
                'description' => 'View super admin users',
                'group' => 'users',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_super_admins',
                'description' => 'Create super admin users',
                'group' => 'users',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_super_admins',
                'description' => 'Edit super admin users',
                'group' => 'users',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_super_admins',
                'description' => 'Delete super admin users',
                'group' => 'users',
                'sort_order' => 4,
            ],
            [
                'name' => 'manage_roles',
                'description' => 'Manage roles and permissions',
                'group' => 'users',
                'sort_order' => 5,
            ],

            // Reports & Analytics
            [
                'name' => 'view_reports',
                'description' => 'View platform reports',
                'group' => 'reports',
                'sort_order' => 1,
            ],
            [
                'name' => 'export_reports',
                'description' => 'Export platform reports',
                'group' => 'reports',
                'sort_order' => 2,
            ],
            [
                'name' => 'view_analytics',
                'description' => 'View platform analytics',
                'group' => 'reports',
                'sort_order' => 3,
            ],

            // System Administration
            [
                'name' => 'view_activity_logs',
                'description' => 'View system activity logs',
                'group' => 'system',
                'sort_order' => 1,
            ],
            [
                'name' => 'manage_system_settings',
                'description' => 'Manage system settings',
                'group' => 'system',
                'sort_order' => 2,
            ],
            [
                'name' => 'view_system_health',
                'description' => 'View system health status',
                'group' => 'system',
                'sort_order' => 3,
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                array_merge($permission, [
                    'guard_name' => 'web',
                    'is_active' => true,
                ])
            );
        }
    }

    private function createRoles()
    {
        $roles = [
            [
                'name' => 'super_admin',
                'description' => 'Full access to all platform features and settings',
                'color' => 'bg-red-500',
                'sort_order' => 1,
            ],
            [
                'name' => 'platform_admin',
                'description' => 'Platform management and business oversight',
                'color' => 'bg-blue-500',
                'sort_order' => 2,
            ],
            [
                'name' => 'support_admin',
                'description' => 'Customer support and business assistance',
                'color' => 'bg-green-500',
                'sort_order' => 3,
            ],
            [
                'name' => 'billing_admin',
                'description' => 'Billing and subscription management',
                'color' => 'bg-purple-500',
                'sort_order' => 4,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                array_merge($role, [
                    'guard_name' => 'web',
                    'is_active' => true,
                ])
            );
        }
    }

    private function assignPermissionsToRoles()
    {
        // Super Admin - All permissions
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions(Permission::all());
        }

        // Platform Admin - Most permissions except user management
        $platformRole = Role::where('name', 'platform_admin')->first();
        if ($platformRole) {
            $platformPermissions = Permission::whereNotIn('group', ['users'])
                ->orWhereIn('name', ['view_super_admins', 'view_roles'])
                ->get();
            $platformRole->syncPermissions($platformPermissions);
        }

        // Support Admin - Business and subscription management
        $supportRole = Role::where('name', 'support_admin')->first();
        if ($supportRole) {
            $supportPermissions = Permission::whereIn('group', ['businesses', 'subscriptions', 'reports'])
                ->orWhereIn('name', ['view_platform_dashboard', 'view_platform_analytics'])
                ->get();
            $supportRole->syncPermissions($supportPermissions);
        }

        // Billing Admin - Package and subscription management
        $billingRole = Role::where('name', 'billing_admin')->first();
        if ($billingRole) {
            $billingPermissions = Permission::whereIn('group', ['packages', 'subscriptions', 'reports'])
                ->orWhereIn('name', ['view_platform_dashboard'])
                ->get();
            $billingRole->syncPermissions($billingPermissions);
        }
    }
}


