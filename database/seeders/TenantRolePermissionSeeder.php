<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use Illuminate\Support\Facades\DB;

class TenantRolePermissionSeeder extends Seeder
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
                'name' => 'view_wallets',
                'description' => 'View wallet information',
                'group' => 'financial',
                'sort_order' => 5,
            ],
            [
                'name' => 'manage_wallets',
                'description' => 'Manage wallet transactions',
                'group' => 'financial',
                'sort_order' => 6,
            ],
            [
                'name' => 'view_financial_reports',
                'description' => 'View financial reports',
                'group' => 'financial',
                'sort_order' => 7,
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
                'name' => 'view_loan_reports',
                'description' => 'View loan reports',
                'group' => 'loans',
                'sort_order' => 7,
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
                'name' => 'manage_property_estates',
                'description' => 'Manage property estates',
                'group' => 'properties',
                'sort_order' => 5,
            ],
            [
                'name' => 'manage_property_allottees',
                'description' => 'Manage property allottees',
                'group' => 'properties',
                'sort_order' => 6,
            ],
            [
                'name' => 'view_property_reports',
                'description' => 'View property reports',
                'group' => 'properties',
                'sort_order' => 7,
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
                'name' => 'view_investment_reports',
                'description' => 'View investment reports',
                'group' => 'investments',
                'sort_order' => 5,
            ],

            // Equity Contribution Management
            [
                'name' => 'view_equity_contributions',
                'description' => 'View equity contributions',
                'group' => 'equity',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_equity_contributions',
                'description' => 'Create equity contributions',
                'group' => 'equity',
                'sort_order' => 2,
            ],
            [
                'name' => 'approve_equity_contributions',
                'description' => 'Approve equity contributions',
                'group' => 'equity',
                'sort_order' => 3,
            ],
            [
                'name' => 'reject_equity_contributions',
                'description' => 'Reject equity contributions',
                'group' => 'equity',
                'sort_order' => 4,
            ],
            [
                'name' => 'bulk_upload_equity_contributions',
                'description' => 'Bulk upload equity contributions',
                'group' => 'equity',
                'sort_order' => 5,
            ],
            [
                'name' => 'manage_equity_plans',
                'description' => 'Manage equity plans',
                'group' => 'equity',
                'sort_order' => 6,
            ],
            [
                'name' => 'view_equity_wallet',
                'description' => 'View equity wallet balances',
                'group' => 'equity',
                'sort_order' => 7,
            ],
            [
                'name' => 'view_equity_wallet_transactions',
                'description' => 'View equity wallet transactions',
                'group' => 'equity',
                'sort_order' => 8,
            ],
            [
                'name' => 'view_equity_reports',
                'description' => 'View equity contribution reports',
                'group' => 'equity',
                'sort_order' => 9,
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

            // System Administration
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

            // Payment Gateway Management
            [
                'name' => 'view_payment_gateways',
                'description' => 'View payment gateways',
                'group' => 'payment_gateways',
                'sort_order' => 1,
            ],
            [
                'name' => 'manage_payment_gateways',
                'description' => 'Manage payment gateway configurations',
                'group' => 'payment_gateways',
                'sort_order' => 2,
            ],
            [
                'name' => 'test_payment_gateways',
                'description' => 'Test payment gateway connections',
                'group' => 'payment_gateways',
                'sort_order' => 3,
            ],

            // Statutory Charges Management
            [
                'name' => 'view_statutory_charges',
                'description' => 'View statutory charges',
                'group' => 'statutory_charges',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_statutory_charges',
                'description' => 'Create statutory charges',
                'group' => 'statutory_charges',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_statutory_charges',
                'description' => 'Edit statutory charges',
                'group' => 'statutory_charges',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_statutory_charges',
                'description' => 'Delete statutory charges',
                'group' => 'statutory_charges',
                'sort_order' => 4,
            ],
            [
                'name' => 'approve_statutory_charges',
                'description' => 'Approve statutory charge payments',
                'group' => 'statutory_charges',
                'sort_order' => 5,
            ],
            [
                'name' => 'reject_statutory_charges',
                'description' => 'Reject statutory charge payments',
                'group' => 'statutory_charges',
                'sort_order' => 6,
            ],
            [
                'name' => 'manage_statutory_charge_types',
                'description' => 'Manage statutory charge types',
                'group' => 'statutory_charges',
                'sort_order' => 7,
            ],
            [
                'name' => 'manage_statutory_charge_departments',
                'description' => 'Manage statutory charge departments',
                'group' => 'statutory_charges',
                'sort_order' => 8,
            ],

            // Maintenance Management
            [
                'name' => 'view_maintenance',
                'description' => 'View maintenance requests',
                'group' => 'maintenance',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_maintenance',
                'description' => 'Create maintenance requests',
                'group' => 'maintenance',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_maintenance',
                'description' => 'Edit maintenance requests',
                'group' => 'maintenance',
                'sort_order' => 3,
            ],
            [
                'name' => 'assign_maintenance',
                'description' => 'Assign maintenance requests',
                'group' => 'maintenance',
                'sort_order' => 4,
            ],
            [
                'name' => 'complete_maintenance',
                'description' => 'Complete maintenance requests',
                'group' => 'maintenance',
                'sort_order' => 5,
            ],
            [
                'name' => 'delete_maintenance',
                'description' => 'Delete maintenance requests',
                'group' => 'maintenance',
                'sort_order' => 6,
            ],

            // Mail Service Management
            [
                'name' => 'view_mail',
                'description' => 'View mail messages',
                'group' => 'mail_service',
                'sort_order' => 1,
            ],
            [
                'name' => 'compose_mail',
                'description' => 'Compose mail messages',
                'group' => 'mail_service',
                'sort_order' => 2,
            ],
            [
                'name' => 'reply_mail',
                'description' => 'Reply to mail messages',
                'group' => 'mail_service',
                'sort_order' => 3,
            ],
            [
                'name' => 'assign_mail',
                'description' => 'Assign mail messages',
                'group' => 'mail_service',
                'sort_order' => 4,
            ],
            [
                'name' => 'bulk_mail',
                'description' => 'Send bulk mail messages',
                'group' => 'mail_service',
                'sort_order' => 5,
            ],
            [
                'name' => 'delete_mail',
                'description' => 'Delete mail messages',
                'group' => 'mail_service',
                'sort_order' => 6,
            ],

            // White Label Management
            [
                'name' => 'view_white_label',
                'description' => 'View white label settings',
                'group' => 'white_label',
                'sort_order' => 1,
            ],
            [
                'name' => 'manage_white_label',
                'description' => 'Manage white label settings',
                'group' => 'white_label',
                'sort_order' => 2,
            ],

            // KYC Management (additional specific permissions)
            [
                'name' => 'view_kyc',
                'description' => 'View KYC submissions',
                'group' => 'members',
                'sort_order' => 7,
            ],
            [
                'name' => 'approve_kyc',
                'description' => 'Approve KYC submissions',
                'group' => 'members',
                'sort_order' => 8,
            ],
            [
                'name' => 'reject_kyc',
                'description' => 'Reject KYC submissions',
                'group' => 'members',
                'sort_order' => 9,
            ],

            // Loan Plan Management
            [
                'name' => 'create_loan_plans',
                'description' => 'Create loan plans',
                'group' => 'loans',
                'sort_order' => 8,
            ],
            [
                'name' => 'edit_loan_plans',
                'description' => 'Edit loan plans',
                'group' => 'loans',
                'sort_order' => 9,
            ],
            [
                'name' => 'delete_loan_plans',
                'description' => 'Delete loan plans',
                'group' => 'loans',
                'sort_order' => 10,
            ],
            [
                'name' => 'disburse_loans',
                'description' => 'Disburse approved loans',
                'group' => 'loans',
                'sort_order' => 11,
            ],

            // Investment Plan Management
            [
                'name' => 'create_investment_plans',
                'description' => 'Create investment plans',
                'group' => 'investments',
                'sort_order' => 6,
            ],
            [
                'name' => 'edit_investment_plans',
                'description' => 'Edit investment plans',
                'group' => 'investments',
                'sort_order' => 7,
            ],
            [
                'name' => 'delete_investment_plans',
                'description' => 'Delete investment plans',
                'group' => 'investments',
                'sort_order' => 8,
            ],

            // Property Allotment Management
            [
                'name' => 'approve_allotments',
                'description' => 'Approve property allotments',
                'group' => 'properties',
                'sort_order' => 8,
            ],
            [
                'name' => 'reject_allotments',
                'description' => 'Reject property allotments',
                'group' => 'properties',
                'sort_order' => 9,
            ],

            // Payment Management
            [
                'name' => 'manage_payments',
                'description' => 'Manage payment transactions',
                'group' => 'financial',
                'sort_order' => 8,
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
                'description' => 'Full access to all system features and settings',
                'color' => 'bg-red-500',
                'sort_order' => 1,
            ],
            [
                'name' => 'admin',
                'description' => 'Full access to business management features',
                'color' => 'bg-blue-500',
                'sort_order' => 2,
            ],
            [
                'name' => 'finance_manager',
                'description' => 'Manage contributions, wallets, and financial reports',
                'color' => 'bg-green-500',
                'sort_order' => 3,
            ],
            [
                'name' => 'loan_officer',
                'description' => 'Manage loans, mortgages, and repayments',
                'color' => 'bg-purple-500',
                'sort_order' => 4,
            ],
            [
                'name' => 'property_manager',
                'description' => 'Manage properties, estates, and maintenance',
                'color' => 'bg-orange-500',
                'sort_order' => 5,
            ],
            [
                'name' => 'member_manager',
                'description' => 'Manage members, subscriptions, and KYC',
                'color' => 'bg-indigo-500',
                'sort_order' => 6,
            ],
            [
                'name' => 'document_manager',
                'description' => 'Manage documents and approvals',
                'color' => 'bg-pink-500',
                'sort_order' => 7,
            ],
            [
                'name' => 'system_admin',
                'description' => 'Manage users, roles, and system settings',
                'color' => 'bg-gray-500',
                'sort_order' => 8,
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
            $financePermissions = Permission::whereIn('group', ['financial', 'reports', 'equity', 'statutory_charges', 'payment_gateways'])
                ->orWhereIn('name', ['view_members', 'view_loans', 'view_investments', 'manage_payments'])
                ->get();
            $financeRole->syncPermissions($financePermissions);
        }

        // Loan Officer
        $loanRole = Role::where('name', 'loan_officer')->first();
        if ($loanRole) {
            $loanPermissions = Permission::whereIn('group', ['loans', 'reports'])
                ->orWhereIn('name', ['view_members', 'view_financial_reports'])
                ->get();
            $loanRole->syncPermissions($loanPermissions);
        }

        // Property Manager
        $propertyRole = Role::where('name', 'property_manager')->first();
        if ($propertyRole) {
            $propertyPermissions = Permission::whereIn('group', ['properties', 'reports', 'maintenance'])
                ->orWhereIn('name', ['view_members', 'view_documents'])
                ->get();
            $propertyRole->syncPermissions($propertyPermissions);
        }

        // Member Manager
        $memberRole = Role::where('name', 'member_manager')->first();
        if ($memberRole) {
            $memberPermissions = Permission::whereIn('group', ['members', 'documents', 'reports', 'mail_service'])
                ->orWhereIn('name', [
                    'view_contributions', 
                    'view_loans', 
                    'view_investments',
                    'view_equity_contributions',
                    'manage_equity_plans',
                    'view_equity_wallet',
                    'view_equity_wallet_transactions',
                    'view_equity_reports'
                ])
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

        // System Admin
        $systemRole = Role::where('name', 'system_admin')->first();
        if ($systemRole) {
            $systemPermissions = Permission::whereIn('group', ['users', 'system', 'reports', 'white_label', 'payment_gateways'])
                ->orWhereIn('name', ['view_members', 'view_contributions', 'view_loans', 'view_properties', 'view_investments', 'view_documents'])
                ->get();
            $systemRole->syncPermissions($systemPermissions);
        }
    }
}




