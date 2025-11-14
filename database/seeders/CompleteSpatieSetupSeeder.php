<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Central\SuperAdmin;

class CompleteSpatieSetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Setting up Complete Spatie Permission System...');
        $this->command->info('==============================================');

        // Clear existing data to avoid conflicts
        $this->clearExistingData();

        // 1. Create all permissions
        $this->createPermissions();

        // 2. Create roles
        $this->createRoles();

        // 3. Assign permissions to roles
        $this->assignPermissionsToRoles();

        // 4. Create super admin user
        $this->createSuperAdminUser();

        // 5. Assign roles to super admin
        $this->assignRolesToSuperAdmin();

        $this->command->info("\n✅ Complete Spatie setup completed successfully!");
    }

    private function clearExistingData(): void
    {
        $this->command->info("\n1. Clearing existing data...");
        
        // Clear in correct order to avoid foreign key constraints
        DB::table('model_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('role_has_permissions')->truncate();
        DB::table('permissions')->truncate();
        
        // Don't truncate roles table as it might have other data
        // Just clear the relationships
        DB::table('model_has_roles')->where('model_type', 'App\\Models\\Central\\SuperAdmin')->delete();
        DB::table('role_has_permissions')->delete();
        
        $this->command->info('✓ Existing data cleared');
    }

    private function createPermissions(): void
    {
        $this->command->info("\n2. Creating permissions...");
        
        $permissions = [
            // Dashboard
            'dashboard.view',
            
            // Business Management
            'businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete', 
            'businesses.suspend', 'businesses.activate', 'businesses.manage_domains',
            
            // Super Admin Management
            'super_admins.view', 'super_admins.create', 'super_admins.edit', 'super_admins.delete', 
            'super_admins.toggle_status',
            
            // Role & Permission Management
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete',
            
            // Package Management
            'packages.view', 'packages.create', 'packages.edit', 'packages.delete', 'packages.toggle',
            
            // Module Management
            'modules.view', 'modules.create', 'modules.edit', 'modules.delete', 'modules.toggle',
            
            // White Label Packages
            'white_label_packages.view', 'white_label_packages.create', 'white_label_packages.edit', 
            'white_label_packages.delete', 'white_label_packages.toggle',
            
            // Analytics & Reports
            'analytics.view', 'analytics.dashboard', 'analytics.revenue', 'analytics.businesses', 
            'analytics.activity', 'reports.view', 'reports.financial', 'reports.export',
            
            // Mail Service
            'mail.view', 'mail.send', 'mail.manage_templates', 'mail.view_history',
            
            // Payment Management
            'payments.view', 'payments.approve', 'payments.reject', 'payments.manage_gateways', 
            'payments.view_transactions',
            
            // Subscription Management
            'subscriptions.view', 'subscriptions.manage', 'subscriptions.cancel', 
            'subscriptions.reactivate', 'subscriptions.extend',
            
            // Member Subscriptions
            'member_subscriptions.view', 'member_subscriptions.create', 'member_subscriptions.edit', 
            'member_subscriptions.delete', 'member_subscriptions.cancel', 'member_subscriptions.extend',
            
            // Invoice Management
            'invoices.view', 'invoices.download', 'invoices.resend',
            
            // Domain Requests
            'domain_requests.view', 'domain_requests.review', 'domain_requests.verify', 
            'domain_requests.activate',
            
            // System Settings
            'settings.view', 'settings.edit', 'settings.platform', 'settings.payment_gateways',
            
            // Activity Logs
            'activity.view', 'activity.logs',
            
            // Tenant Management
            'tenants.create', 'tenants.manage',
        ];

        $createdCount = 0;
        foreach ($permissions as $permissionName) {
            try {
                Permission::create([
                    'id' => Str::uuid()->toString(),
                    'name' => $permissionName,
                    'guard_name' => 'web'
                ]);
                $createdCount++;
            } catch (\Exception $e) {
                // Permission might already exist, skip
                $this->command->warn("Permission {$permissionName} might already exist");
            }
        }
        
        $this->command->info("✓ {$createdCount} permissions created");
    }

    private function createRoles(): void
    {
        $this->command->info("\n3. Creating roles...");
        
        $roles = [
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'permissions' => 'all' // Special case for all permissions
            ],
            [
                'name' => 'Business Manager',
                'description' => 'Business management and user administration',
                'permissions' => [
                    'dashboard.view',
                    'businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete', 
                    'businesses.suspend', 'businesses.activate',
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
                    'analytics.view', 'analytics.dashboard', 'analytics.revenue', 
                    'analytics.businesses', 'analytics.activity',
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
                    'subscriptions.view', 'subscriptions.manage', 'subscriptions.cancel', 
                    'subscriptions.reactivate',
                    'member_subscriptions.view', 'member_subscriptions.manage', 
                    'member_subscriptions.cancel',
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
                    'domain_requests.view', 'domain_requests.review', 'domain_requests.verify', 
                    'domain_requests.activate',
                    'settings.view', 'settings.platform',
                    'activity.view', 'activity.logs'
                ]
            ]
        ];

        $createdCount = 0;
        foreach ($roles as $roleData) {
            try {
                // Check if role already exists
                $existingRole = DB::table('roles')->where('name', $roleData['name'])->first();
                
                if ($existingRole) {
                    // Update existing role
                    DB::table('roles')->where('id', $existingRole->id)->update([
                        'description' => $roleData['description'],
                        'guard_name' => 'web',
                        'updated_at' => now()
                    ]);
                    $roleId = $existingRole->id;
                } else {
                    // Create new role
                    $roleId = Str::uuid()->toString();
                    DB::table('roles')->insert([
                        'id' => $roleId,
                        'name' => $roleData['name'],
                        'slug' => Str::slug($roleData['name']),
                        'description' => $roleData['description'],
                        'is_active' => true,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                $createdCount++;
            } catch (\Exception $e) {
                $this->command->error("Error creating role {$roleData['name']}: " . $e->getMessage());
            }
        }
        
        $this->command->info("✓ {$createdCount} roles processed");
    }

    private function assignPermissionsToRoles(): void
    {
        $this->command->info("\n4. Assigning permissions to roles...");
        
        // Get all permissions
        $allPermissions = Permission::all();
        
        // Get all roles
        $roles = [
            'Super Admin' => 'all',
            'Business Manager' => [
                'dashboard.view',
                'businesses.view', 'businesses.create', 'businesses.edit', 'businesses.delete', 
                'businesses.suspend', 'businesses.activate',
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
                'analytics.view', 'analytics.dashboard', 'analytics.revenue', 
                'analytics.businesses', 'analytics.activity',
                'reports.view', 'reports.financial', 'reports.export',
                'businesses.view',
                'subscriptions.view',
                'invoices.view'
            ],
            'Finance Manager' => [
                'dashboard.view',
                'payments.view', 'payments.approve', 'payments.reject', 'payments.view_transactions',
                'subscriptions.view', 'subscriptions.manage', 'subscriptions.cancel', 
                'subscriptions.reactivate',
                'member_subscriptions.view', 'member_subscriptions.manage', 
                'member_subscriptions.cancel',
                'invoices.view', 'invoices.download', 'invoices.resend',
                'analytics.view', 'analytics.revenue',
                'reports.view', 'reports.financial', 'reports.export'
            ],
            'Technical Support' => [
                'dashboard.view',
                'businesses.view', 'businesses.suspend', 'businesses.activate',
                'modules.view', 'modules.toggle',
                'packages.view', 'packages.toggle',
                'domain_requests.view', 'domain_requests.review', 'domain_requests.verify', 
                'domain_requests.activate',
                'settings.view', 'settings.platform',
                'activity.view', 'activity.logs'
            ]
        ];

        foreach ($roles as $roleName => $permissionNames) {
            try {
                $role = Role::where('name', $roleName)->first();
                if (!$role) {
                    $this->command->warn("Role {$roleName} not found, skipping...");
                    continue;
                }

                if ($permissionNames === 'all') {
                    // Assign all permissions to Super Admin
                    $permissions = $allPermissions;
                } else {
                    // Assign specific permissions
                    $permissions = Permission::whereIn('name', $permissionNames)->get();
                }

                // Clear existing permissions
                DB::table('role_has_permissions')->where('role_id', $role->id)->delete();

                // Insert new permissions in batches to avoid memory issues
                $batchSize = 20;
                $permissionChunks = $permissions->chunk($batchSize);
                
                foreach ($permissionChunks as $chunk) {
                    $insertData = [];
                    foreach ($chunk as $permission) {
                        $insertData[] = [
                            'id' => Str::uuid()->toString(),
                            'role_id' => $role->id,
                            'permission_id' => $permission->id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                    DB::table('role_has_permissions')->insert($insertData);
                }

                $this->command->info("✓ {$roleName} assigned " . $permissions->count() . " permissions");
                
            } catch (\Exception $e) {
                $this->command->error("Error assigning permissions to {$roleName}: " . $e->getMessage());
            }
        }
    }

    private function createSuperAdminUser(): void
    {
        $this->command->info("\n5. Creating super admin user...");
        
        try {
            $superAdmin = SuperAdmin::firstOrCreate(
                ['email' => 'superadmin@smart-housing.com'],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => 'Super Administrator',
                    'email' => 'superadmin@smart-housing.com',
                    'password' => bcrypt('admin123'),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            if ($superAdmin->wasRecentlyCreated) {
                $this->command->info('✓ Super admin user created (email: superadmin@smart-housing.com, password: admin123)');
            } else {
                $this->command->info('✓ Super admin user already exists');
            }
        } catch (\Exception $e) {
            $this->command->error("Error creating super admin: " . $e->getMessage());
        }
    }

    private function assignRolesToSuperAdmin(): void
    {
        $this->command->info("\n6. Assigning roles to super admin...");
        
        try {
            $superAdmin = SuperAdmin::where('email', 'superadmin@smart-housing.com')->first();
            $superAdminRole = Role::where('name', 'Super Admin')->first();

            if ($superAdmin && $superAdminRole) {
                // Clear existing roles
                DB::table('model_has_roles')->where('model_id', $superAdmin->id)->delete();

                // Assign Super Admin role
                DB::table('model_has_roles')->insert([
                    'id' => Str::uuid()->toString(),
                    'role_id' => $superAdminRole->id,
                    'model_type' => 'App\\Models\\Central\\SuperAdmin',
                    'model_id' => $superAdmin->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $this->command->info('✓ Super Admin role assigned to super admin user');
            } else {
                $this->command->warn('Super admin user or Super Admin role not found');
            }
        } catch (\Exception $e) {
            $this->command->error("Error assigning roles: " . $e->getMessage());
        }
    }
}
