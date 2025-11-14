<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use Illuminate\Support\Facades\DB;

class ComprehensiveTenantRolePermissionSeeder extends Seeder
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
            // Member Management
            [
                'name' => 'view_members',
                'description' => 'View member information',
                'group' => 'members',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_members',
                'description' => 'Create new members',
                'group' => 'members',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_members',
                'description' => 'Edit member information',
                'group' => 'members',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_members',
                'description' => 'Delete members',
                'group' => 'members',
                'sort_order' => 4,
            ],
            [
                'name' => 'bulk_upload_members',
                'description' => 'Bulk upload members',
                'group' => 'members',
                'sort_order' => 5,
            ],
            [
                'name' => 'manage_member_kyc',
                'description' => 'Manage member KYC status',
                'group' => 'members',
                'sort_order' => 6,
            ],

            // Financial Management
            [
                'name' => 'view_contributions',
                'description' => 'View contributions',
                'group' => 'financial',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_contributions',
                'description' => 'Create contributions',
                'group' => 'financial',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_contributions',
                'description' => 'Edit contributions',
                'group' => 'financial',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_contributions',
                'description' => 'Delete contributions',
                'group' => 'financial',
                'sort_order' => 4,
            ],
            [
                'name' => 'bulk_upload_contributions',
                'description' => 'Bulk upload contributions',
                'group' => 'financial',
                'sort_order' => 5,
            ],
            [
                'name' => 'view_wallets',
                'description' => 'View wallet information',
                'group' => 'financial',
                'sort_order' => 6,
            ],
            [
                'name' => 'manage_wallets',
                'description' => 'Manage wallet transactions',
                'group' => 'financial',
                'sort_order' => 7,
            ],
            [
                'name' => 'view_wallet_transactions',
                'description' => 'View wallet transactions',
                'group' => 'financial',
                'sort_order' => 8,
            ],
            [
                'name' => 'view_pending_wallets',
                'description' => 'View pending wallet transactions',
                'group' => 'financial',
                'sort_order' => 9,
            ],
            [
                'name' => 'view_financial_reports',
                'description' => 'View financial reports',
                'group' => 'financial',
                'sort_order' => 10,
            ],
            [
                'name' => 'view_contribution_reports',
                'description' => 'View contribution reports',
                'group' => 'financial',
                'sort_order' => 11,
            ],
            [
                'name' => 'manage_statutory_charges',
                'description' => 'Manage statutory charges',
                'group' => 'financial',
                'sort_order' => 12,
            ],
            [
                'name' => 'manage_statutory_charge_types',
                'description' => 'Manage statutory charge types',
                'group' => 'financial',
                'sort_order' => 13,
            ],
            [
                'name' => 'manage_statutory_charge_payments',
                'description' => 'Manage statutory charge payments',
                'group' => 'financial',
                'sort_order' => 14,
            ],
            [
                'name' => 'manage_statutory_charge_departments',
                'description' => 'Manage statutory charge departments',
                'group' => 'financial',
                'sort_order' => 15,
            ],

            // Loan Management
            [
                'name' => 'view_loans',
                'description' => 'View loan applications',
                'group' => 'loans',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_loans',
                'description' => 'Create loan applications',
                'group' => 'loans',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_loans',
                'description' => 'Edit loan applications',
                'group' => 'loans',
                'sort_order' => 3,
            ],
            [
                'name' => 'approve_loans',
                'description' => 'Approve loan applications',
                'group' => 'loans',
                'sort_order' => 4,
            ],
            [
                'name' => 'reject_loans',
                'description' => 'Reject loan applications',
                'group' => 'loans',
                'sort_order' => 5,
            ],
            [
                'name' => 'manage_loan_repayments',
                'description' => 'Manage loan repayments',
                'group' => 'loans',
                'sort_order' => 6,
            ],
            [
                'name' => 'bulk_upload_loan_repayments',
                'description' => 'Bulk upload loan repayments',
                'group' => 'loans',
                'sort_order' => 7,
            ],
            [
                'name' => 'manage_loan_products',
                'description' => 'Manage loan products',
                'group' => 'loans',
                'sort_order' => 8,
            ],
            [
                'name' => 'manage_loan_settings',
                'description' => 'Manage loan settings',
                'group' => 'loans',
                'sort_order' => 9,
            ],
            [
                'name' => 'manage_mortgages',
                'description' => 'Manage mortgages',
                'group' => 'loans',
                'sort_order' => 10,
            ],
            [
                'name' => 'view_loan_reports',
                'description' => 'View loan reports',
                'group' => 'loans',
                'sort_order' => 11,
            ],

            // Property Management
            [
                'name' => 'view_properties',
                'description' => 'View properties',
                'group' => 'properties',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_properties',
                'description' => 'Create properties',
                'group' => 'properties',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_properties',
                'description' => 'Edit properties',
                'group' => 'properties',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_properties',
                'description' => 'Delete properties',
                'group' => 'properties',
                'sort_order' => 4,
            ],
            [
                'name' => 'manage_eoi_forms',
                'description' => 'Manage Expression of Interest forms',
                'group' => 'properties',
                'sort_order' => 5,
            ],
            [
                'name' => 'manage_property_estates',
                'description' => 'Manage property estates',
                'group' => 'properties',
                'sort_order' => 6,
            ],
            [
                'name' => 'manage_property_allottees',
                'description' => 'Manage property allottees',
                'group' => 'properties',
                'sort_order' => 7,
            ],
            [
                'name' => 'manage_property_maintenance',
                'description' => 'Manage property maintenance',
                'group' => 'properties',
                'sort_order' => 8,
            ],
            [
                'name' => 'view_property_reports',
                'description' => 'View property reports',
                'group' => 'properties',
                'sort_order' => 9,
            ],
            [
                'name' => 'manage_blockchain',
                'description' => 'Manage blockchain transactions',
                'group' => 'properties',
                'sort_order' => 10,
            ],

            // Investment Management
            [
                'name' => 'view_investments',
                'description' => 'View investments',
                'group' => 'investments',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_investments',
                'description' => 'Create investments',
                'group' => 'investments',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_investments',
                'description' => 'Edit investments',
                'group' => 'investments',
                'sort_order' => 3,
            ],
            [
                'name' => 'approve_investments',
                'description' => 'Approve investments',
                'group' => 'investments',
                'sort_order' => 4,
            ],
            [
                'name' => 'manage_investment_plans',
                'description' => 'Manage investment plans',
                'group' => 'investments',
                'sort_order' => 5,
            ],
            [
                'name' => 'view_investment_reports',
                'description' => 'View investment reports',
                'group' => 'investments',
                'sort_order' => 6,
            ],

            // Document Management
            [
                'name' => 'view_documents',
                'description' => 'View documents',
                'group' => 'documents',
                'sort_order' => 1,
            ],
            [
                'name' => 'upload_documents',
                'description' => 'Upload documents',
                'group' => 'documents',
                'sort_order' => 2,
            ],
            [
                'name' => 'approve_documents',
                'description' => 'Approve documents',
                'group' => 'documents',
                'sort_order' => 3,
            ],
            [
                'name' => 'reject_documents',
                'description' => 'Reject documents',
                'group' => 'documents',
                'sort_order' => 4,
            ],
            [
                'name' => 'delete_documents',
                'description' => 'Delete documents',
                'group' => 'documents',
                'sort_order' => 5,
            ],

            // Reports & Analytics
            [
                'name' => 'view_reports',
                'description' => 'View all reports',
                'group' => 'reports',
                'sort_order' => 1,
            ],
            [
                'name' => 'export_reports',
                'description' => 'Export reports',
                'group' => 'reports',
                'sort_order' => 2,
            ],
            [
                'name' => 'view_analytics',
                'description' => 'View analytics dashboard',
                'group' => 'reports',
                'sort_order' => 3,
            ],

            // User Management
            [
                'name' => 'view_users',
                'description' => 'View users',
                'group' => 'users',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_users',
                'description' => 'Create users',
                'group' => 'users',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_users',
                'description' => 'Edit users',
                'group' => 'users',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_users',
                'description' => 'Delete users',
                'group' => 'users',
                'sort_order' => 4,
            ],
            [
                'name' => 'manage_roles',
                'description' => 'Manage roles and permissions',
                'group' => 'users',
                'sort_order' => 5,
            ],

            // System Administration
            [
                'name' => 'view_activity_logs',
                'description' => 'View activity logs',
                'group' => 'system',
                'sort_order' => 1,
            ],
            [
                'name' => 'manage_settings',
                'description' => 'Manage system settings',
                'group' => 'system',
                'sort_order' => 2,
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
                'display_name' => 'Super Admin',
                'description' => 'Full access to all system features and settings',
                'color' => 'bg-red-500',
                'sort_order' => 1,
            ],
            [
                'name' => 'admin',
                'display_name' => 'Business Admin',
                'description' => 'Full access to business management features',
                'color' => 'bg-blue-500',
                'sort_order' => 2,
            ],
            [
                'name' => 'finance_manager',
                'display_name' => 'Finance Manager',
                'description' => 'Manage contributions, wallets, and financial reports',
                'color' => 'bg-green-500',
                'sort_order' => 3,
            ],
            [
                'name' => 'loan_officer',
                'display_name' => 'Loan Officer',
                'description' => 'Manage loans, mortgages, and repayments',
                'color' => 'bg-purple-500',
                'sort_order' => 4,
            ],
            [
                'name' => 'property_manager',
                'display_name' => 'Property Manager',
                'description' => 'Manage properties, estates, and maintenance',
                'color' => 'bg-orange-500',
                'sort_order' => 5,
            ],
            [
                'name' => 'member_manager',
                'display_name' => 'Member Manager',
                'description' => 'Manage members, subscriptions, and KYC',
                'color' => 'bg-indigo-500',
                'sort_order' => 6,
            ],
            [
                'name' => 'document_manager',
                'display_name' => 'Document Manager',
                'description' => 'Manage documents and approvals',
                'color' => 'bg-pink-500',
                'sort_order' => 7,
            ],
            [
                'name' => 'investment_manager',
                'display_name' => 'Investment Manager',
                'description' => 'Manage investment plans and portfolios',
                'color' => 'bg-yellow-500',
                'sort_order' => 8,
            ],
            [
                'name' => 'system_admin',
                'display_name' => 'System Administrator',
                'description' => 'Manage users, roles, and system settings',
                'color' => 'bg-gray-500',
                'sort_order' => 9,
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

        // Admin - Most permissions except system admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminPermissions = Permission::whereNotIn('group', ['system'])->get();
            $adminRole->syncPermissions($adminPermissions);
        }

        // Finance Manager
        $financeRole = Role::where('name', 'finance_manager')->first();
        if ($financeRole) {
            $financePermissions = Permission::whereIn('group', ['financial', 'reports'])
                ->orWhereIn('name', ['view_members', 'view_loans', 'view_investments', 'view_documents'])
                ->get();
            $financeRole->syncPermissions($financePermissions);
        }

        // Loan Officer
        $loanRole = Role::where('name', 'loan_officer')->first();
        if ($loanRole) {
            $loanPermissions = Permission::whereIn('group', ['loans', 'reports'])
                ->orWhereIn('name', ['view_members', 'view_financial_reports', 'view_documents'])
                ->get();
            $loanRole->syncPermissions($loanPermissions);
        }

        // Property Manager
        $propertyRole = Role::where('name', 'property_manager')->first();
        if ($propertyRole) {
            $propertyPermissions = Permission::whereIn('group', ['properties', 'reports'])
                ->orWhereIn('name', ['view_members', 'view_documents'])
                ->get();
            $propertyRole->syncPermissions($propertyPermissions);
        }

        // Member Manager
        $memberRole = Role::where('name', 'member_manager')->first();
        if ($memberRole) {
            $memberPermissions = Permission::whereIn('group', ['members', 'documents', 'reports'])
                ->orWhereIn('name', ['view_contributions', 'view_loans', 'view_investments'])
                ->get();
            $memberRole->syncPermissions($memberPermissions);
        }

        // Document Manager
        $documentRole = Role::where('name', 'document_manager')->first();
        if ($documentRole) {
            $documentPermissions = Permission::whereIn('group', ['documents', 'reports'])
                ->orWhereIn('name', ['view_members'])
                ->get();
            $documentRole->syncPermissions($documentPermissions);
        }

        // Investment Manager
        $investmentRole = Role::where('name', 'investment_manager')->first();
        if ($investmentRole) {
            $investmentPermissions = Permission::whereIn('group', ['investments', 'reports'])
                ->orWhereIn('name', ['view_members', 'view_financial_reports'])
                ->get();
            $investmentRole->syncPermissions($investmentPermissions);
        }

        // System Admin
        $systemRole = Role::where('name', 'system_admin')->first();
        if ($systemRole) {
            $systemPermissions = Permission::whereIn('group', ['users', 'system', 'reports'])
                ->orWhereIn('name', ['view_members', 'view_contributions', 'view_loans', 'view_properties', 'view_investments', 'view_documents'])
                ->get();
            $systemRole->syncPermissions($systemPermissions);
        }
    }
}
