<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class TenantHousingRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing roles and permissions
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        Role::truncate();
        Permission::truncate();

        $this->createPermissions();
        $this->createRoles();
        $this->assignPermissionsToRoles();
    }

    /**
     * Create permissions for housing management system
     */
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
                'description' => 'Register new members',
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
                'description' => 'Delete member accounts',
                'group' => 'members',
                'sort_order' => 4,
            ],
            [
                'name' => 'manage_member_kyc',
                'description' => 'Verify and manage member KYC status',
                'group' => 'members',
                'sort_order' => 5,
            ],
            [
                'name' => 'bulk_upload_members',
                'description' => 'Bulk upload members via CSV/Excel',
                'group' => 'members',
                'sort_order' => 6,
            ],
            [
                'name' => 'export_members',
                'description' => 'Export member data',
                'group' => 'members',
                'sort_order' => 7,
            ],

            // Property Management
            [
                'name' => 'view_properties',
                'description' => 'View property listings',
                'group' => 'properties',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_properties',
                'description' => 'Add new properties',
                'group' => 'properties',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_properties',
                'description' => 'Edit property information',
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
                'name' => 'manage_property_allocations',
                'description' => 'Allocate properties to members',
                'group' => 'properties',
                'sort_order' => 5,
            ],
            [
                'name' => 'manage_property_interests',
                'description' => 'Handle property interest expressions',
                'group' => 'properties',
                'sort_order' => 6,
            ],
            [
                'name' => 'upload_property_images',
                'description' => 'Upload property images',
                'group' => 'properties',
                'sort_order' => 7,
            ],

            // Financial Management
            [
                'name' => 'view_contributions',
                'description' => 'View member contributions',
                'group' => 'financial',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_contributions',
                'description' => 'Create contribution records',
                'group' => 'financial',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_contributions',
                'description' => 'Edit contribution records',
                'group' => 'financial',
                'sort_order' => 3,
            ],
            [
                'name' => 'approve_contributions',
                'description' => 'Approve contribution payments',
                'group' => 'financial',
                'sort_order' => 4,
            ],
            [
                'name' => 'view_investments',
                'description' => 'View investment records',
                'group' => 'financial',
                'sort_order' => 5,
            ],
            [
                'name' => 'create_investments',
                'description' => 'Create investment plans',
                'group' => 'financial',
                'sort_order' => 6,
            ],
            [
                'name' => 'approve_investments',
                'description' => 'Approve investment applications',
                'group' => 'financial',
                'sort_order' => 7,
            ],
            [
                'name' => 'manage_wallets',
                'description' => 'Manage member wallets',
                'group' => 'financial',
                'sort_order' => 8,
            ],
            [
                'name' => 'view_financial_reports',
                'description' => 'View financial reports',
                'group' => 'financial',
                'sort_order' => 9,
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

            // Document Management
            [
                'name' => 'view_documents',
                'description' => 'View member documents',
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
                'name' => 'download_documents',
                'description' => 'Download documents',
                'group' => 'documents',
                'sort_order' => 5,
            ],
            [
                'name' => 'delete_documents',
                'description' => 'Delete documents',
                'group' => 'documents',
                'sort_order' => 6,
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
                'description' => 'Export reports to Excel/PDF',
                'group' => 'reports',
                'sort_order' => 2,
            ],
            [
                'name' => 'view_analytics',
                'description' => 'View analytics dashboard',
                'group' => 'reports',
                'sort_order' => 3,
            ],
            [
                'name' => 'view_financial_analytics',
                'description' => 'View financial analytics',
                'group' => 'reports',
                'sort_order' => 4,
            ],
            [
                'name' => 'view_member_analytics',
                'description' => 'View member analytics',
                'group' => 'reports',
                'sort_order' => 5,
            ],

            // User Management
            [
                'name' => 'view_users',
                'description' => 'View admin users',
                'group' => 'users',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_users',
                'description' => 'Create admin users',
                'group' => 'users',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_users',
                'description' => 'Edit admin users',
                'group' => 'users',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_users',
                'description' => 'Delete admin users',
                'group' => 'users',
                'sort_order' => 4,
            ],
            [
                'name' => 'manage_user_roles',
                'description' => 'Assign roles to users',
                'group' => 'users',
                'sort_order' => 5,
            ],

            // Role Management
            [
                'name' => 'view_roles',
                'description' => 'View roles',
                'group' => 'roles',
                'sort_order' => 1,
            ],
            [
                'name' => 'create_roles',
                'description' => 'Create roles',
                'group' => 'roles',
                'sort_order' => 2,
            ],
            [
                'name' => 'edit_roles',
                'description' => 'Edit roles',
                'group' => 'roles',
                'sort_order' => 3,
            ],
            [
                'name' => 'delete_roles',
                'description' => 'Delete roles',
                'group' => 'roles',
                'sort_order' => 4,
            ],
            [
                'name' => 'assign_permissions',
                'description' => 'Assign permissions to roles',
                'group' => 'roles',
                'sort_order' => 5,
            ],

            // Communication Management
            [
                'name' => 'view_notifications',
                'description' => 'View notifications',
                'group' => 'communication',
                'sort_order' => 1,
            ],
            [
                'name' => 'send_notifications',
                'description' => 'Send notifications to members',
                'group' => 'communication',
                'sort_order' => 2,
            ],
            [
                'name' => 'view_emails',
                'description' => 'View email communications',
                'group' => 'communication',
                'sort_order' => 3,
            ],
            [
                'name' => 'send_emails',
                'description' => 'Send emails to members',
                'group' => 'communication',
                'sort_order' => 4,
            ],

            // Settings Management
            [
                'name' => 'view_settings',
                'description' => 'View system settings',
                'group' => 'settings',
                'sort_order' => 1,
            ],
            [
                'name' => 'edit_settings',
                'description' => 'Edit system settings',
                'group' => 'settings',
                'sort_order' => 2,
            ],
            [
                'name' => 'manage_landing_page',
                'description' => 'Manage landing page configuration',
                'group' => 'settings',
                'sort_order' => 3,
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::create([
                'name' => $permissionData['name'],
                'guard_name' => 'web',
                'description' => $permissionData['description'],
                'group' => $permissionData['group'],
                'sort_order' => $permissionData['sort_order'],
            ]);
        }
    }

    /**
     * Create roles for housing management system
     */
    private function createRoles()
    {
        $roles = [
            [
                'name' => 'housing_admin',
                'display_name' => 'Housing Administrator',
                'description' => 'Full access to housing management system',
                'guard_name' => 'web',
                'color' => 'bg-red-500',
                'sort_order' => 1,
            ],
            [
                'name' => 'property_manager',
                'display_name' => 'Property Manager',
                'description' => 'Manage properties, allocations, and property-related operations',
                'guard_name' => 'web',
                'color' => 'bg-blue-500',
                'sort_order' => 2,
            ],
            [
                'name' => 'finance_officer',
                'display_name' => 'Finance Officer',
                'description' => 'Handle financial operations, loans, investments, and contributions',
                'guard_name' => 'web',
                'color' => 'bg-green-500',
                'sort_order' => 3,
            ],
            [
                'name' => 'member_services',
                'display_name' => 'Member Services Officer',
                'description' => 'Member registration, KYC verification, and member support',
                'guard_name' => 'web',
                'color' => 'bg-purple-500',
                'sort_order' => 4,
            ],
            [
                'name' => 'loan_specialist',
                'display_name' => 'Loan Specialist',
                'description' => 'Process loan applications, approvals, and loan management',
                'guard_name' => 'web',
                'color' => 'bg-orange-500',
                'sort_order' => 5,
            ],
            [
                'name' => 'document_verifier',
                'display_name' => 'Document Verifier',
                'description' => 'Verify and approve member documents and KYC submissions',
                'guard_name' => 'web',
                'color' => 'bg-pink-500',
                'sort_order' => 6,
            ],
            [
                'name' => 'reports_analyst',
                'display_name' => 'Reports Analyst',
                'description' => 'Generate and analyze financial and operational reports',
                'guard_name' => 'web',
                'color' => 'bg-indigo-500',
                'sort_order' => 7,
            ],
            [
                'name' => 'communications_officer',
                'display_name' => 'Communications Officer',
                'description' => 'Handle notifications, emails, and member communications',
                'guard_name' => 'web',
                'color' => 'bg-teal-500',
                'sort_order' => 8,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::create([
                'name' => $roleData['name'],
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
                'guard_name' => $roleData['guard_name'],
                'color' => $roleData['color'],
                'sort_order' => $roleData['sort_order'],
            ]);
        }
    }

    /**
     * Assign permissions to roles
     */
    private function assignPermissionsToRoles()
    {
        // Housing Admin - All permissions
        $housingAdmin = Role::where('name', 'housing_admin')->first();
        $housingAdmin->givePermissionTo(Permission::all());

        // Property Manager
        $propertyManager = Role::where('name', 'property_manager')->first();
        $propertyManager->givePermissionTo([
            'view_members', 'edit_members', 'export_members',
            'view_properties', 'create_properties', 'edit_properties', 'delete_properties',
            'manage_property_allocations', 'manage_property_interests', 'upload_property_images',
            'view_reports', 'export_reports', 'view_analytics',
            'view_notifications', 'send_notifications',
        ]);

        // Finance Officer
        $financeOfficer = Role::where('name', 'finance_officer')->first();
        $financeOfficer->givePermissionTo([
            'view_members', 'export_members',
            'view_contributions', 'create_contributions', 'edit_contributions', 'approve_contributions',
            'view_investments', 'create_investments', 'approve_investments',
            'manage_wallets', 'view_financial_reports',
            'view_loans', 'create_loans', 'edit_loans', 'approve_loans', 'reject_loans',
            'manage_loan_repayments', 'view_loan_reports',
            'view_reports', 'export_reports', 'view_analytics', 'view_financial_analytics',
        ]);

        // Member Services
        $memberServices = Role::where('name', 'member_services')->first();
        $memberServices->givePermissionTo([
            'view_members', 'create_members', 'edit_members', 'bulk_upload_members', 'export_members',
            'manage_member_kyc',
            'view_documents', 'upload_documents', 'approve_documents', 'reject_documents', 'download_documents',
            'view_reports', 'export_reports', 'view_member_analytics',
            'view_notifications', 'send_notifications', 'view_emails', 'send_emails',
        ]);

        // Loan Specialist
        $loanSpecialist = Role::where('name', 'loan_specialist')->first();
        $loanSpecialist->givePermissionTo([
            'view_members', 'export_members',
            'view_loans', 'create_loans', 'edit_loans', 'approve_loans', 'reject_loans',
            'manage_loan_repayments', 'view_loan_reports',
            'view_documents', 'download_documents',
            'view_reports', 'export_reports', 'view_analytics',
            'view_notifications', 'send_notifications',
        ]);

        // Document Verifier
        $documentVerifier = Role::where('name', 'document_verifier')->first();
        $documentVerifier->givePermissionTo([
            'view_members', 'export_members',
            'view_documents', 'approve_documents', 'reject_documents', 'download_documents', 'delete_documents',
            'manage_member_kyc',
            'view_reports', 'export_reports',
            'view_notifications',
        ]);

        // Reports Analyst
        $reportsAnalyst = Role::where('name', 'reports_analyst')->first();
        $reportsAnalyst->givePermissionTo([
            'view_members', 'export_members',
            'view_contributions', 'view_investments', 'view_loans',
            'view_reports', 'export_reports', 'view_analytics', 'view_financial_analytics', 'view_member_analytics',
        ]);

        // Communications Officer
        $communicationsOfficer = Role::where('name', 'communications_officer')->first();
        $communicationsOfficer->givePermissionTo([
            'view_members', 'export_members',
            'view_notifications', 'send_notifications', 'view_emails', 'send_emails',
            'view_reports', 'export_reports',
        ]);
    }
}
