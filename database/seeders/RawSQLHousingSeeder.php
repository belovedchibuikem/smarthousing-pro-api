<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RawSQLHousingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::statement('DELETE FROM role_has_permissions');
        DB::statement('DELETE FROM model_has_roles');
        DB::statement('DELETE FROM model_has_permissions');
        DB::statement('DELETE FROM roles');
        DB::statement('DELETE FROM permissions');

        // Insert permissions using raw SQL
        DB::statement("
            INSERT INTO permissions (name, guard_name, description, `group`, sort_order, is_active, created_at, updated_at) VALUES
            -- Member Management
            ('view_members', 'web', 'View member information', 'members', 1, NOW(), NOW()),
            ('create_members', 'web', 'Register new members', 'members', 2, NOW(), NOW()),
            ('edit_members', 'web', 'Edit member information', 'members', 3, NOW(), NOW()),
            ('delete_members', 'web', 'Delete member accounts', 'members', 4, NOW(), NOW()),
            ('manage_member_kyc', 'web', 'Verify and manage member KYC status', 'members', 5, NOW(), NOW()),
            ('bulk_upload_members', 'web', 'Bulk upload members via CSV/Excel', 'members', 6, NOW(), NOW()),
            ('export_members', 'web', 'Export member data', 'members', 7, NOW(), NOW()),
            
            -- Property Management
            ('view_properties', 'web', 'View property listings', 'properties', 1, NOW(), NOW()),
            ('create_properties', 'web', 'Add new properties', 'properties', 2, NOW(), NOW()),
            ('edit_properties', 'web', 'Edit property information', 'properties', 3, NOW(), NOW()),
            ('delete_properties', 'web', 'Delete properties', 'properties', 4, NOW(), NOW()),
            ('manage_property_allocations', 'web', 'Allocate properties to members', 'properties', 5, NOW(), NOW()),
            ('manage_property_interests', 'web', 'Handle property interest expressions', 'properties', 6, NOW(), NOW()),
            ('upload_property_images', 'web', 'Upload property images', 'properties', 7, NOW(), NOW()),
            
            -- Financial Management
            ('view_contributions', 'web', 'View member contributions', 'financial', 1, NOW(), NOW()),
            ('create_contributions', 'web', 'Create contribution records', 'financial', 2, NOW(), NOW()),
            ('edit_contributions', 'web', 'Edit contribution records', 'financial', 3, NOW(), NOW()),
            ('approve_contributions', 'web', 'Approve contribution payments', 'financial', 4, NOW(), NOW()),
            ('view_investments', 'web', 'View investment records', 'financial', 5, NOW(), NOW()),
            ('create_investments', 'web', 'Create investment plans', 'financial', 6, NOW(), NOW()),
            ('approve_investments', 'web', 'Approve investment applications', 'financial', 7, NOW(), NOW()),
            ('manage_wallets', 'web', 'Manage member wallets', 'financial', 8, NOW(), NOW()),
            ('view_financial_reports', 'web', 'View financial reports', 'financial', 9, NOW(), NOW()),
            
            -- Loan Management
            ('view_loans', 'web', 'View loan applications', 'loans', 1, NOW(), NOW()),
            ('create_loans', 'web', 'Create loan applications', 'loans', 2, NOW(), NOW()),
            ('edit_loans', 'web', 'Edit loan applications', 'loans', 3, NOW(), NOW()),
            ('approve_loans', 'web', 'Approve loan applications', 'loans', 4, NOW(), NOW()),
            ('reject_loans', 'web', 'Reject loan applications', 'loans', 5, NOW(), NOW()),
            ('manage_loan_repayments', 'web', 'Manage loan repayments', 'loans', 6, NOW(), NOW()),
            ('view_loan_reports', 'web', 'View loan reports', 'loans', 7, NOW(), NOW()),
            
            -- Document Management
            ('view_documents', 'web', 'View member documents', 'documents', 1, NOW(), NOW()),
            ('upload_documents', 'web', 'Upload documents', 'documents', 2, NOW(), NOW()),
            ('approve_documents', 'web', 'Approve documents', 'documents', 3, NOW(), NOW()),
            ('reject_documents', 'web', 'Reject documents', 'documents', 4, NOW(), NOW()),
            ('download_documents', 'web', 'Download documents', 'documents', 5, NOW(), NOW()),
            ('delete_documents', 'web', 'Delete documents', 'documents', 6, NOW(), NOW()),
            
            -- Reports & Analytics
            ('view_reports', 'web', 'View all reports', 'reports', 1, NOW(), NOW()),
            ('export_reports', 'web', 'Export reports to Excel/PDF', 'reports', 2, NOW(), NOW()),
            ('view_analytics', 'web', 'View analytics dashboard', 'reports', 3, NOW(), NOW()),
            ('view_financial_analytics', 'web', 'View financial analytics', 'reports', 4, NOW(), NOW()),
            ('view_member_analytics', 'web', 'View member analytics', 'reports', 5, NOW(), NOW()),
            
            -- User Management
            ('view_users', 'web', 'View admin users', 'users', 1, NOW(), NOW()),
            ('create_users', 'web', 'Create admin users', 'users', 2, NOW(), NOW()),
            ('edit_users', 'web', 'Edit admin users', 'users', 3, NOW(), NOW()),
            ('delete_users', 'web', 'Delete admin users', 'users', 4, NOW(), NOW()),
            ('manage_user_roles', 'web', 'Assign roles to users', 'users', 5, NOW(), NOW()),
            
            -- Role Management
            ('view_roles', 'web', 'View roles', 'roles', 1, NOW(), NOW()),
            ('create_roles', 'web', 'Create roles', 'roles', 2, NOW(), NOW()),
            ('edit_roles', 'web', 'Edit roles', 'roles', 3, NOW(), NOW()),
            ('delete_roles', 'web', 'Delete roles', 'roles', 4, NOW(), NOW()),
            ('assign_permissions', 'web', 'Assign permissions to roles', 'roles', 5, NOW(), NOW()),
            
            -- Communication Management
            ('view_notifications', 'web', 'View notifications', 'communication', 1, NOW(), NOW()),
            ('send_notifications', 'web', 'Send notifications to members', 'communication', 2, NOW(), NOW()),
            ('view_emails', 'web', 'View email communications', 'communication', 3, NOW(), NOW()),
            ('send_emails', 'web', 'Send emails to members', 'communication', 4, NOW(), NOW()),
            
            -- Settings Management
            ('view_settings', 'web', 'View system settings', 'settings', 1, NOW(), NOW()),
            ('edit_settings', 'web', 'Edit system settings', 'settings', 2, NOW(), NOW()),
            ('manage_landing_page', 'web', 'Manage landing page configuration', 'settings', 3, NOW(), NOW())
        ");

        // Insert roles using raw SQL
        DB::statement("
            INSERT INTO roles (name, display_name, description, guard_name, color, sort_order, is_active, created_at, updated_at) VALUES
            ('housing_admin', 'Housing Administrator', 'Full access to housing management system', 'web', 'bg-red-500', 1, 1, NOW(), NOW()),
            ('property_manager', 'Property Manager', 'Manage properties, allocations, and property-related operations', 'web', 'bg-blue-500', 2, 1, NOW(), NOW()),
            ('finance_officer', 'Finance Officer', 'Handle financial operations, loans, investments, and contributions', 'web', 'bg-green-500', 3, 1, NOW(), NOW()),
            ('member_services', 'Member Services Officer', 'Member registration, KYC verification, and member support', 'web', 'bg-purple-500', 4, 1, NOW(), NOW()),
            ('loan_specialist', 'Loan Specialist', 'Process loan applications, approvals, and loan management', 'web', 'bg-orange-500', 5, 1, NOW(), NOW()),
            ('document_verifier', 'Document Verifier', 'Verify and approve member documents and KYC submissions', 'web', 'bg-pink-500', 6, 1, NOW(), NOW()),
            ('reports_analyst', 'Reports Analyst', 'Generate and analyze financial and operational reports', 'web', 'bg-indigo-500', 7, 1, NOW(), NOW()),
            ('communications_officer', 'Communications Officer', 'Handle notifications, emails, and member communications', 'web', 'bg-teal-500', 8, 1, NOW(), NOW())
        ");

        // Assign permissions to roles using raw SQL
        DB::statement("
            -- Housing Admin gets all permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'housing_admin'
        ");

        DB::statement("
            -- Property Manager permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'property_manager' 
            AND p.name IN ('view_members', 'edit_members', 'export_members', 'view_properties', 'create_properties', 'edit_properties', 'delete_properties', 'manage_property_allocations', 'manage_property_interests', 'upload_property_images', 'view_reports', 'export_reports', 'view_analytics', 'view_notifications', 'send_notifications')
        ");

        DB::statement("
            -- Finance Officer permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'finance_officer' 
            AND p.name IN ('view_members', 'export_members', 'view_contributions', 'create_contributions', 'edit_contributions', 'approve_contributions', 'view_investments', 'create_investments', 'approve_investments', 'manage_wallets', 'view_financial_reports', 'view_loans', 'create_loans', 'edit_loans', 'approve_loans', 'reject_loans', 'manage_loan_repayments', 'view_loan_reports', 'view_reports', 'export_reports', 'view_analytics', 'view_financial_analytics')
        ");

        DB::statement("
            -- Member Services permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'member_services' 
            AND p.name IN ('view_members', 'create_members', 'edit_members', 'bulk_upload_members', 'export_members', 'manage_member_kyc', 'view_documents', 'upload_documents', 'approve_documents', 'reject_documents', 'download_documents', 'view_reports', 'export_reports', 'view_member_analytics', 'view_notifications', 'send_notifications', 'view_emails', 'send_emails')
        ");

        DB::statement("
            -- Loan Specialist permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'loan_specialist' 
            AND p.name IN ('view_members', 'export_members', 'view_loans', 'create_loans', 'edit_loans', 'approve_loans', 'reject_loans', 'manage_loan_repayments', 'view_loan_reports', 'view_documents', 'download_documents', 'view_reports', 'export_reports', 'view_analytics', 'view_notifications', 'send_notifications')
        ");

        DB::statement("
            -- Document Verifier permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'document_verifier' 
            AND p.name IN ('view_members', 'export_members', 'view_documents', 'approve_documents', 'reject_documents', 'download_documents', 'delete_documents', 'manage_member_kyc', 'view_reports', 'export_reports', 'view_notifications')
        ");

        DB::statement("
            -- Reports Analyst permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'reports_analyst' 
            AND p.name IN ('view_members', 'export_members', 'view_contributions', 'view_investments', 'view_loans', 'view_reports', 'export_reports', 'view_analytics', 'view_financial_analytics', 'view_member_analytics')
        ");

        DB::statement("
            -- Communications Officer permissions
            INSERT INTO role_has_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'communications_officer' 
            AND p.name IN ('view_members', 'export_members', 'view_notifications', 'send_notifications', 'view_emails', 'send_emails', 'view_reports', 'export_reports')
        ");

        $this->command->info('Housing management roles and permissions seeded successfully!');
    }
}
