<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;
use App\Models\Central\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'View Users', 'slug' => 'users.view', 'description' => 'View user information', 'category' => 'users'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'description' => 'Create new users', 'category' => 'users'],
            ['name' => 'Edit Users', 'slug' => 'users.edit', 'description' => 'Edit user information', 'category' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'description' => 'Delete users', 'category' => 'users'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'description' => 'Full user management', 'category' => 'users'],
            ['name' => 'Impersonate Users', 'slug' => 'users.impersonate', 'description' => 'Impersonate other users', 'category' => 'users'],

            // Tenant Management
            ['name' => 'View Tenants', 'slug' => 'tenants.view', 'description' => 'View tenant information', 'category' => 'tenants'],
            ['name' => 'Create Tenants', 'slug' => 'tenants.create', 'description' => 'Create new tenants', 'category' => 'tenants'],
            ['name' => 'Edit Tenants', 'slug' => 'tenants.edit', 'description' => 'Edit tenant information', 'category' => 'tenants'],
            ['name' => 'Delete Tenants', 'slug' => 'tenants.delete', 'description' => 'Delete tenants', 'category' => 'tenants'],
            ['name' => 'Manage Tenants', 'slug' => 'tenants.manage', 'description' => 'Full tenant management', 'category' => 'tenants'],
            ['name' => 'Suspend Tenants', 'slug' => 'tenants.suspend', 'description' => 'Suspend tenant accounts', 'category' => 'tenants'],
            ['name' => 'Activate Tenants', 'slug' => 'tenants.activate', 'description' => 'Activate tenant accounts', 'category' => 'tenants'],

            // System Administration
            ['name' => 'System Administration', 'slug' => 'system.admin', 'description' => 'Full system administration', 'category' => 'system'],
            ['name' => 'System Settings', 'slug' => 'system.settings', 'description' => 'Manage system settings', 'category' => 'system'],
            ['name' => 'System Maintenance', 'slug' => 'system.maintenance', 'description' => 'Perform system maintenance', 'category' => 'system'],
            ['name' => 'System Backup', 'slug' => 'system.backup', 'description' => 'Create system backups', 'category' => 'system'],
            ['name' => 'System Restore', 'slug' => 'system.restore', 'description' => 'Restore system from backup', 'category' => 'system'],
            ['name' => 'View System Logs', 'slug' => 'system.logs', 'description' => 'View system logs', 'category' => 'system'],

            // Analytics & Reports
            ['name' => 'View Analytics', 'slug' => 'analytics.view', 'description' => 'View system analytics', 'category' => 'analytics'],
            ['name' => 'Export Analytics', 'slug' => 'analytics.export', 'description' => 'Export analytics data', 'category' => 'analytics'],
            ['name' => 'Generate Reports', 'slug' => 'reports.generate', 'description' => 'Generate system reports', 'category' => 'reports'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'description' => 'Export reports', 'category' => 'reports'],
            ['name' => 'Admin Dashboard', 'slug' => 'dashboard.admin', 'description' => 'Access admin dashboard', 'category' => 'dashboard'],

            // Content Management
            ['name' => 'Manage Content', 'slug' => 'content.manage', 'description' => 'Manage system content', 'category' => 'content'],
            ['name' => 'Publish Content', 'slug' => 'content.publish', 'description' => 'Publish content', 'category' => 'content'],
            ['name' => 'Moderate Content', 'slug' => 'content.moderate', 'description' => 'Moderate user content', 'category' => 'content'],

            // Financial Management
            ['name' => 'View Billing', 'slug' => 'billing.view', 'description' => 'View billing information', 'category' => 'billing'],
            ['name' => 'Manage Billing', 'slug' => 'billing.manage', 'description' => 'Manage billing settings', 'category' => 'billing'],
            ['name' => 'View Payments', 'slug' => 'payments.view', 'description' => 'View payment information', 'category' => 'payments'],
            ['name' => 'Process Payments', 'slug' => 'payments.process', 'description' => 'Process payments', 'category' => 'payments'],
            ['name' => 'Manage Subscriptions', 'slug' => 'subscriptions.manage', 'description' => 'Manage user subscriptions', 'category' => 'subscriptions'],

            // Security & Access
            ['name' => 'Manage Security', 'slug' => 'security.manage', 'description' => 'Manage security settings', 'category' => 'security'],
            ['name' => 'Manage Permissions', 'slug' => 'permissions.manage', 'description' => 'Manage user permissions', 'category' => 'security'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'description' => 'Manage user roles', 'category' => 'security'],
            ['name' => 'Manage API', 'slug' => 'api.manage', 'description' => 'Manage API settings', 'category' => 'security'],
            ['name' => 'Manage Tokens', 'slug' => 'tokens.manage', 'description' => 'Manage access tokens', 'category' => 'security'],

            // Communication
            ['name' => 'Send Notifications', 'slug' => 'notifications.send', 'description' => 'Send system notifications', 'category' => 'communication'],
            ['name' => 'Manage Emails', 'slug' => 'emails.manage', 'description' => 'Manage email settings', 'category' => 'communication'],
            ['name' => 'Manage Communications', 'slug' => 'communications.manage', 'description' => 'Manage all communications', 'category' => 'communication'],

            // Data Management
            ['name' => 'Export Data', 'slug' => 'data.export', 'description' => 'Export system data', 'category' => 'data'],
            ['name' => 'Import Data', 'slug' => 'data.import', 'description' => 'Import system data', 'category' => 'data'],
            ['name' => 'Cleanup Data', 'slug' => 'data.cleanup', 'description' => 'Clean up system data', 'category' => 'data'],
            ['name' => 'Migrate Data', 'slug' => 'data.migrate', 'description' => 'Migrate system data', 'category' => 'data'],

            // Monitoring
            ['name' => 'View Monitoring', 'slug' => 'monitoring.view', 'description' => 'View system monitoring', 'category' => 'monitoring'],
            ['name' => 'Manage Alerts', 'slug' => 'monitoring.alerts', 'description' => 'Manage system alerts', 'category' => 'monitoring'],
            ['name' => 'View Performance', 'slug' => 'performance.view', 'description' => 'View performance metrics', 'category' => 'monitoring'],
            ['name' => 'View Logs', 'slug' => 'logs.view', 'description' => 'View system logs', 'category' => 'monitoring'],

            // Full Access
            ['name' => 'Full System Access', 'slug' => '*', 'description' => 'Full access to all system features', 'category' => 'system'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}


