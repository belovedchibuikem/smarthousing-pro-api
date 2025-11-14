<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Central\Role;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Permission;

class SuperAdminSystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Setting up Super Admin System...');
        $this->command->info('================================');

        // 1. Create all permissions
        $this->command->info("\n1. Creating permissions...");
        $permissions = $this->getAllPermissions();
        
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'super_admin'],
                ['name' => $permissionName, 'guard_name' => 'super_admin']
            );
        }
        $this->command->info('✓ ' . count($permissions) . ' permissions created');

        // 2. Create all roles
        $this->command->info("\n2. Creating roles...");
        $roles = $this->getAllRoles();
        
        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => 'super_admin'],
                [
                    'name' => $roleData['name'],
                    'slug' => Str::slug($roleData['name']),
                    'description' => $roleData['description'],
                    'permissions' => json_encode($roleData['permissions']),
                    'is_active' => true,
                    'guard_name' => 'super_admin'
                ]
            );
        }
        $this->command->info('✓ ' . count($roles) . ' roles created');

        // 3. Assign permissions to roles
        $this->command->info("\n3. Assigning permissions to roles...");
        $this->assignPermissionsToRoles();

        // 4. Create super admin user if not exists
        $this->command->info("\n4. Creating super admin user...");
        $this->createSuperAdminUser();

        // 5. Assign Super Admin role to super admin user
        $this->command->info("\n5. Assigning Super Admin role...");
        $this->assignSuperAdminRole();

        $this->command->info("\n✅ Super Admin System setup completed successfully!");
    }

    private function getAllPermissions(): array
    {
        return [
            // Dashboard
            'dashboard.view',
            
            // Business Management
            'businesses.view',
            'businesses.create',
            'businesses.edit',
            'businesses.delete',
            'businesses.suspend',
            'businesses.activate',
            'businesses.manage_domains',
            
            // Super Admin Management
            'super_admins.view',
            'super_admins.create',
            'super_admins.edit',
            'super_admins.delete',
            'super_admins.toggle_status',
            
            // Role & Permission Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
            
            // Package Management
            'packages.view',
            'packages.create',
            'packages.edit',
            'packages.delete',
            'packages.toggle',
            
            // Module Management
            'modules.view',
            'modules.create',
            'modules.edit',
            'modules.delete',
            'modules.toggle',
            
            // White Label Packages
            'white_label_packages.view',
            'white_label_packages.create',
            'white_label_packages.edit',
            'white_label_packages.delete',
            'white_label_packages.toggle',
            
            // Analytics & Reports
            'analytics.view',
            'analytics.dashboard',
            'analytics.revenue',
            'analytics.businesses',
            'analytics.activity',
            'reports.view',
            'reports.financial',
            'reports.export',
            
            // Mail Service
            'mail.view',
            'mail.send',
            'mail.manage_templates',
            'mail.view_history',
            
            // Payment Management
            'payments.view',
            'payments.approve',
            'payments.reject',
            'payments.manage_gateways',
            'payments.view_transactions',
            
            // Subscription Management
            'subscriptions.view',
            'subscriptions.manage',
            'subscriptions.cancel',
            'subscriptions.reactivate',
            'subscriptions.extend',
            
            // Member Subscriptions
            'member_subscriptions.view',
            'member_subscriptions.create',
            'member_subscriptions.edit',
            'member_subscriptions.delete',
            'member_subscriptions.cancel',
            'member_subscriptions.extend',
            
            // Invoice Management
            'invoices.view',
            'invoices.download',
            'invoices.resend',
            
            // Domain Requests
            'domain_requests.view',
            'domain_requests.review',
            'domain_requests.verify',
            'domain_requests.activate',
            
            // System Settings
            'settings.view',
            'settings.edit',
            'settings.platform',
            'settings.payment_gateways',
            
            // Activity Logs
            'activity.view',
            'activity.logs',
            
            // Tenant Management
            'tenants.create',
            'tenants.manage',
        ];
    }

    private function getAllRoles(): array
    {
        return [
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'permissions' => ['*'] // All permissions
            ],
            [
                'name' => 'Business Manager',
                'description' => 'Business management and user administration',
                'permissions' => [
                    'dashboard.view',
                    'businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete', 'businesses.suspend', 'businesses.activate',
                    'super_admins.view', 'super_admins.create', 'super_admins.edit',
                    'analytics.view', 'analytics.dashboard', 'analytics.businesses',
                    'reports.view', 'reports.financial',
                    'mail.view', 'mail.send',
                    'subscriptions.view', 'subscriptions.manage',
                    'invoices.view', 'invoices.download',
                    'settings.view'
                ]
            ],
            [
                'name' => 'Support Agent',
                'description' => 'Customer support and limited system access',
                'permissions' => [
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
            ],
            [
                'name' => 'Analyst',
                'description' => 'Analytics and reporting access',
                'permissions' => [
                    'dashboard.view',
                    'analytics.view', 'analytics.dashboard', 'analytics.revenue', 'analytics.businesses', 'analytics.activity',
                    'reports.view', 'reports.financial', 'reports.export',
                    'businesses.view',
                    'subscriptions.view',
                    'invoices.view'
                ]
            ],
            [
                'name' => 'Finance Manager',
                'description' => 'Financial management and payment processing',
                'permissions' => [
                    'dashboard.view',
                    'payments.view', 'payments.approve', 'payments.reject', 'payments.view_transactions',
                    'subscriptions.view', 'subscriptions.manage', 'subscriptions.cancel', 'subscriptions.reactivate',
                    'member_subscriptions.view', 'member_subscriptions.manage', 'member_subscriptions.cancel',
                    'invoices.view', 'invoices.download', 'invoices.resend',
                    'analytics.view', 'analytics.revenue',
                    'reports.view', 'reports.financial', 'reports.export'
                ]
            ],
            [
                'name' => 'Technical Support',
                'description' => 'Technical support and system maintenance',
                'permissions' => [
                    'dashboard.view',
                    'businesses.view', 'businesses.suspend', 'businesses.activate',
                    'modules.view', 'modules.toggle',
                    'packages.view', 'packages.toggle',
                    'domain_requests.view', 'domain_requests.review', 'domain_requests.verify', 'domain_requests.activate',
                    'settings.view', 'settings.platform',
                    'activity.view', 'activity.logs'
                ]
            ]
        ];
    }

    private function assignPermissionsToRoles(): void
    {
        $allPermissions = Permission::all();
        
        // Super Admin gets all permissions
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions($allPermissions);
            $this->command->info('✓ Super Admin role assigned all permissions');
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
            ],
            'Analyst' => [
                'dashboard.view',
                'analytics.view', 'analytics.dashboard', 'analytics.revenue', 'analytics.businesses', 'analytics.activity',
                'reports.view', 'reports.financial', 'reports.export',
                'businesses.view',
                'subscriptions.view',
                'invoices.view'
            ],
            'Finance Manager' => [
                'dashboard.view',
                'payments.view', 'payments.approve', 'payments.reject', 'payments.view_transactions',
                'subscriptions.view', 'subscriptions.manage', 'subscriptions.cancel', 'subscriptions.reactivate',
                'member_subscriptions.view', 'member_subscriptions.manage', 'member_subscriptions.cancel',
                'invoices.view', 'invoices.download', 'invoices.resend',
                'analytics.view', 'analytics.revenue',
                'reports.view', 'reports.financial', 'reports.export'
            ],
            'Technical Support' => [
                'dashboard.view',
                'businesses.view', 'businesses.suspend', 'businesses.activate',
                'modules.view', 'modules.toggle',
                'packages.view', 'packages.toggle',
                'domain_requests.view', 'domain_requests.review', 'domain_requests.verify', 'domain_requests.activate',
                'settings.view', 'settings.platform',
                'activity.view', 'activity.logs'
            ]
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $permissions = Permission::whereIn('name', $permissionNames)->get();
                $role->syncPermissions($permissions);
                $this->command->info("✓ {$roleName} role assigned " . $permissions->count() . " permissions");
            }
        }
    }

    private function createSuperAdminUser(): void
    {
        $superAdmin = SuperAdmin::firstOrCreate(
            ['email' => 'superadmin@smart-housing.com'],
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@smart-housing.com',
                'password' => bcrypt('admin123'),
                'is_active' => true
            ]
        );

        if ($superAdmin->wasRecentlyCreated) {
            $this->command->info('✓ Super admin user created (email: superadmin@smart-housing.com, password: admin123)');
        } else {
            $this->command->info('✓ Super admin user already exists');
        }
    }

    private function assignSuperAdminRole(): void
    {
        $superAdmin = SuperAdmin::where('email', 'superadmin@smart-housing.com')->first();
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        if ($superAdmin && $superAdminRole) {
            // Remove any existing roles
            $superAdmin->syncRoles([]);
            // Assign Super Admin role
            $superAdmin->assignRole($superAdminRole);
            $this->command->info('✓ Super Admin role assigned to super admin user');
        }
    }
}