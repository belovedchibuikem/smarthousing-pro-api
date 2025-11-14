<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DirectHousingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Increase memory limit
        ini_set('memory_limit', '512M');
        
        // Clear existing data
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();

        $this->createPermissions();
        $this->createRoles();
        $this->assignPermissionsToRoles();
    }

    private function createPermissions()
    {
        $permissions = [
            // Member Management
            ['view_members', 'View member information', 'members', 1],
            ['create_members', 'Register new members', 'members', 2],
            ['edit_members', 'Edit member information', 'members', 3],
            ['delete_members', 'Delete member accounts', 'members', 4],
            ['manage_member_kyc', 'Verify and manage member KYC status', 'members', 5],
            ['bulk_upload_members', 'Bulk upload members via CSV/Excel', 'members', 6],
            ['export_members', 'Export member data', 'members', 7],
            
            // Property Management
            ['view_properties', 'View property listings', 'properties', 1],
            ['create_properties', 'Add new properties', 'properties', 2],
            ['edit_properties', 'Edit property information', 'properties', 3],
            ['delete_properties', 'Delete properties', 'properties', 4],
            ['manage_property_allocations', 'Allocate properties to members', 'properties', 5],
            ['manage_property_interests', 'Handle property interest expressions', 'properties', 6],
            ['upload_property_images', 'Upload property images', 'properties', 7],
            
            // Financial Management
            ['view_contributions', 'View member contributions', 'financial', 1],
            ['create_contributions', 'Create contribution records', 'financial', 2],
            ['edit_contributions', 'Edit contribution records', 'financial', 3],
            ['approve_contributions', 'Approve contribution payments', 'financial', 4],
            ['view_investments', 'View investment records', 'financial', 5],
            ['create_investments', 'Create investment plans', 'financial', 6],
            ['approve_investments', 'Approve investment applications', 'financial', 7],
            ['manage_wallets', 'Manage member wallets', 'financial', 8],
            ['view_financial_reports', 'View financial reports', 'financial', 9],
            
            // Loan Management
            ['view_loans', 'View loan applications', 'loans', 1],
            ['create_loans', 'Create loan applications', 'loans', 2],
            ['edit_loans', 'Edit loan applications', 'loans', 3],
            ['approve_loans', 'Approve loan applications', 'loans', 4],
            ['reject_loans', 'Reject loan applications', 'loans', 5],
            ['manage_loan_repayments', 'Manage loan repayments', 'loans', 6],
            ['view_loan_reports', 'View loan reports', 'loans', 7],
            
            // Document Management
            ['view_documents', 'View member documents', 'documents', 1],
            ['upload_documents', 'Upload documents', 'documents', 2],
            ['approve_documents', 'Approve documents', 'documents', 3],
            ['reject_documents', 'Reject documents', 'documents', 4],
            ['download_documents', 'Download documents', 'documents', 5],
            ['delete_documents', 'Delete documents', 'documents', 6],
            
            // Reports & Analytics
            ['view_reports', 'View all reports', 'reports', 1],
            ['export_reports', 'Export reports to Excel/PDF', 'reports', 2],
            ['view_analytics', 'View analytics dashboard', 'reports', 3],
            ['view_financial_analytics', 'View financial analytics', 'reports', 4],
            ['view_member_analytics', 'View member analytics', 'reports', 5],
            
            // User Management
            ['view_users', 'View admin users', 'users', 1],
            ['create_users', 'Create admin users', 'users', 2],
            ['edit_users', 'Edit admin users', 'users', 3],
            ['delete_users', 'Delete admin users', 'users', 4],
            ['manage_user_roles', 'Assign roles to users', 'users', 5],
            
            // Role Management
            ['view_roles', 'View roles', 'roles', 1],
            ['create_roles', 'Create roles', 'roles', 2],
            ['edit_roles', 'Edit roles', 'roles', 3],
            ['delete_roles', 'Delete roles', 'roles', 4],
            ['assign_permissions', 'Assign permissions to roles', 'roles', 5],
            
            // Communication Management
            ['view_notifications', 'View notifications', 'communication', 1],
            ['send_notifications', 'Send notifications to members', 'communication', 2],
            ['view_emails', 'View email communications', 'communication', 3],
            ['send_emails', 'Send emails to members', 'communication', 4],
            
            // Settings Management
            ['view_settings', 'View system settings', 'settings', 1],
            ['edit_settings', 'Edit system settings', 'settings', 2],
            ['manage_landing_page', 'Manage landing page configuration', 'settings', 3],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->insert([
                'name' => $perm[0],
                'guard_name' => 'web',
                'description' => $perm[1],
                'group' => $perm[2],
                'sort_order' => $perm[3],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createRoles()
    {
        $roles = [
            ['housing_admin', 'Housing Administrator', 'Full access to housing management system', 'bg-red-500', 1],
            ['property_manager', 'Property Manager', 'Manage properties, allocations, and property-related operations', 'bg-blue-500', 2],
            ['finance_officer', 'Finance Officer', 'Handle financial operations, loans, investments, and contributions', 'bg-green-500', 3],
            ['member_services', 'Member Services Officer', 'Member registration, KYC verification, and member support', 'bg-purple-500', 4],
            ['loan_specialist', 'Loan Specialist', 'Process loan applications, approvals, and loan management', 'bg-orange-500', 5],
            ['document_verifier', 'Document Verifier', 'Verify and approve member documents and KYC submissions', 'bg-pink-500', 6],
            ['reports_analyst', 'Reports Analyst', 'Generate and analyze financial and operational reports', 'bg-indigo-500', 7],
            ['communications_officer', 'Communications Officer', 'Handle notifications, emails, and member communications', 'bg-teal-500', 8],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'name' => $role[0],
                'display_name' => $role[1],
                'description' => $role[2],
                'guard_name' => 'web',
                'color' => $role[3],
                'sort_order' => $role[4],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function assignPermissionsToRoles()
    {
        // Get all permissions
        $permissions = DB::table('permissions')->pluck('id', 'name')->toArray();
        
        // Get all roles
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();
        
        // Define role-permission assignments
        $assignments = [
            'housing_admin' => array_keys($permissions), // All permissions
            'property_manager' => ['view_members', 'edit_members', 'export_members', 'view_properties', 'create_properties', 'edit_properties', 'delete_properties', 'manage_property_allocations', 'manage_property_interests', 'upload_property_images', 'view_reports', 'export_reports', 'view_analytics', 'view_notifications', 'send_notifications'],
            'finance_officer' => ['view_members', 'export_members', 'view_contributions', 'create_contributions', 'edit_contributions', 'approve_contributions', 'view_investments', 'create_investments', 'approve_investments', 'manage_wallets', 'view_financial_reports', 'view_loans', 'create_loans', 'edit_loans', 'approve_loans', 'reject_loans', 'manage_loan_repayments', 'view_loan_reports', 'view_reports', 'export_reports', 'view_analytics', 'view_financial_analytics'],
            'member_services' => ['view_members', 'create_members', 'edit_members', 'bulk_upload_members', 'export_members', 'manage_member_kyc', 'view_documents', 'upload_documents', 'approve_documents', 'reject_documents', 'download_documents', 'view_reports', 'export_reports', 'view_member_analytics', 'view_notifications', 'send_notifications', 'view_emails', 'send_emails'],
            'loan_specialist' => ['view_members', 'export_members', 'view_loans', 'create_loans', 'edit_loans', 'approve_loans', 'reject_loans', 'manage_loan_repayments', 'view_loan_reports', 'view_documents', 'download_documents', 'view_reports', 'export_reports', 'view_analytics', 'view_notifications', 'send_notifications'],
            'document_verifier' => ['view_members', 'export_members', 'view_documents', 'approve_documents', 'reject_documents', 'download_documents', 'delete_documents', 'manage_member_kyc', 'view_reports', 'export_reports', 'view_notifications'],
            'reports_analyst' => ['view_members', 'export_members', 'view_contributions', 'view_investments', 'view_loans', 'view_reports', 'export_reports', 'view_analytics', 'view_financial_analytics', 'view_member_analytics'],
            'communications_officer' => ['view_members', 'export_members', 'view_notifications', 'send_notifications', 'view_emails', 'send_emails', 'view_reports', 'export_reports'],
        ];
        
        foreach ($assignments as $roleName => $permissionNames) {
            $roleId = $roles[$roleName];
            foreach ($permissionNames as $permName) {
                if (isset($permissions[$permName])) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissions[$permName],
                    ]);
                }
            }
        }
    }
}
