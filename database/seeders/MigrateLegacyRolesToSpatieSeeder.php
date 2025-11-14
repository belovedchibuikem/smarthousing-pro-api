<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\User;
use App\Models\Tenant\Role;
use Illuminate\Support\Facades\DB;

class MigrateLegacyRolesToSpatieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting migration of legacy roles to Spatie Permission system...');

        // First, ensure roles exist
        $this->ensureRolesExist();

        // Get all users with legacy roles
        $users = User::whereNotNull('role')->get();

        $this->command->info("Found {$users->count()} users with legacy roles");

        foreach ($users as $user) {
            $this->migrateUserRole($user);
        }

        $this->command->info('Legacy role migration completed successfully!');
    }

    private function ensureRolesExist()
    {
        $roles = [
            'super_admin' => 'Super Admin',
            'admin' => 'Business Admin',
            'finance_manager' => 'Finance Manager',
            'loan_officer' => 'Loan Officer',
            'property_manager' => 'Property Manager',
            'member_manager' => 'Member Manager',
            'document_manager' => 'Document Manager',
            'investment_manager' => 'Investment Manager',
            'system_admin' => 'System Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'member' => 'Member',
        ];

        foreach ($roles as $name => $displayName) {
            Role::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'guard_name' => 'web',
                    'is_active' => true,
                    'description' => $this->getRoleDescription($name),
                    'color' => $this->getRoleColor($name),
                    'sort_order' => $this->getRoleSortOrder($name),
                ]
            );
        }
    }

    private function migrateUserRole(User $user)
    {
        $legacyRole = $user->role;
        $spatieRole = $this->mapLegacyRoleToSpatie($legacyRole);

        if ($spatieRole) {
            // Remove any existing roles
            $user->syncRoles([]);
            
            // Assign the new role
            $user->assignRole($spatieRole);
            
            $this->command->info("Migrated user {$user->email} from '{$legacyRole}' to '{$spatieRole}'");
        } else {
            $this->command->warn("No mapping found for legacy role: {$legacyRole} for user {$user->email}");
        }
    }

    private function mapLegacyRoleToSpatie(string $legacyRole): ?string
    {
        $mapping = [
            'admin' => 'admin',
            'manager' => 'manager',
            'staff' => 'staff',
            'member' => 'member',
        ];

        return $mapping[$legacyRole] ?? null;
    }

    private function getRoleDescription(string $roleName): string
    {
        $descriptions = [
            'super_admin' => 'Full access to all system features and settings',
            'admin' => 'Full access to business management features',
            'finance_manager' => 'Manage contributions, wallets, and financial reports',
            'loan_officer' => 'Manage loans, mortgages, and repayments',
            'property_manager' => 'Manage properties, estates, and maintenance',
            'member_manager' => 'Manage members, subscriptions, and KYC',
            'document_manager' => 'Manage documents and approvals',
            'investment_manager' => 'Manage investment plans and portfolios',
            'system_admin' => 'Manage users, roles, and system settings',
            'manager' => 'Operational management with limited access',
            'staff' => 'Data entry and basic operations',
            'member' => 'Personal account management',
        ];

        return $descriptions[$roleName] ?? 'User role';
    }

    private function getRoleColor(string $roleName): string
    {
        $colors = [
            'super_admin' => 'bg-red-500',
            'admin' => 'bg-blue-500',
            'finance_manager' => 'bg-green-500',
            'loan_officer' => 'bg-purple-500',
            'property_manager' => 'bg-orange-500',
            'member_manager' => 'bg-indigo-500',
            'document_manager' => 'bg-pink-500',
            'investment_manager' => 'bg-yellow-500',
            'system_admin' => 'bg-gray-500',
            'manager' => 'bg-cyan-500',
            'staff' => 'bg-teal-500',
            'member' => 'bg-slate-500',
        ];

        return $colors[$roleName] ?? 'bg-gray-500';
    }

    private function getRoleSortOrder(string $roleName): int
    {
        $sortOrders = [
            'super_admin' => 1,
            'admin' => 2,
            'finance_manager' => 3,
            'loan_officer' => 4,
            'property_manager' => 5,
            'member_manager' => 6,
            'document_manager' => 7,
            'investment_manager' => 8,
            'system_admin' => 9,
            'manager' => 10,
            'staff' => 11,
            'member' => 12,
        ];

        return $sortOrders[$roleName] ?? 99;
    }
}
