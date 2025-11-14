<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Support\Facades\DB;

class EquityPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createPermissions();
        $this->assignPermissionsToRoles();
    }

    private function createPermissions()
    {
        $permissions = [
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

    private function assignPermissionsToRoles()
    {
        // Get all permissions by group
        $equityPermissions = Permission::where('group', 'equity')->get();
        $paymentGatewayPermissions = Permission::where('group', 'payment_gateways')->get();
        $statutoryChargePermissions = Permission::where('group', 'statutory_charges')->get();
        $maintenancePermissions = Permission::where('group', 'maintenance')->get();
        $mailServicePermissions = Permission::where('group', 'mail_service')->get();
        $whiteLabelPermissions = Permission::where('group', 'white_label')->get();
        $loanPlanPermissions = Permission::whereIn('name', [
            'create_loan_plans',
            'edit_loan_plans',
            'delete_loan_plans',
            'disburse_loans',
        ])->get();
        $investmentPlanPermissions = Permission::whereIn('name', [
            'create_investment_plans',
            'edit_investment_plans',
            'delete_investment_plans',
        ])->get();
        $propertyAllotmentPermissions = Permission::whereIn('name', [
            'approve_allotments',
            'reject_allotments',
        ])->get();
        $kycPermissions = Permission::whereIn('name', [
            'view_kyc',
            'approve_kyc',
            'reject_kyc',
        ])->get();
        $paymentPermissions = Permission::whereIn('name', [
            'manage_payments',
        ])->get();

        // Super Admin - All permissions from this seeder
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $allPermissions = $equityPermissions
                ->merge($paymentGatewayPermissions)
                ->merge($statutoryChargePermissions)
                ->merge($maintenancePermissions)
                ->merge($mailServicePermissions)
                ->merge($whiteLabelPermissions)
                ->merge($loanPlanPermissions)
                ->merge($investmentPlanPermissions)
                ->merge($propertyAllotmentPermissions)
                ->merge($kycPermissions)
                ->merge($paymentPermissions);
            $superAdminRole->givePermissionTo($allPermissions);
        }

        // Admin - All permissions except system (which is handled by TenantRolePermissionSeeder)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $allPermissions = $equityPermissions
                ->merge($paymentGatewayPermissions)
                ->merge($statutoryChargePermissions)
                ->merge($maintenancePermissions)
                ->merge($mailServicePermissions)
                ->merge($whiteLabelPermissions)
                ->merge($loanPlanPermissions)
                ->merge($investmentPlanPermissions)
                ->merge($propertyAllotmentPermissions)
                ->merge($kycPermissions)
                ->merge($paymentPermissions);
            $adminRole->givePermissionTo($allPermissions);
        }

        // Finance Manager - Equity, payment gateways, statutory charges, and payments
        $financeRole = Role::where('name', 'finance_manager')->first();
        if ($financeRole) {
            $financePermissions = $equityPermissions
                ->merge($paymentGatewayPermissions)
                ->merge($statutoryChargePermissions)
                ->merge($paymentPermissions);
            $financeRole->givePermissionTo($financePermissions);
        }

        // Loan Officer - Loan plan permissions
        $loanRole = Role::where('name', 'loan_officer')->first();
        if ($loanRole) {
            $loanRole->givePermissionTo($loanPlanPermissions);
        }

        // Property Manager - Maintenance and property allotment permissions
        $propertyRole = Role::where('name', 'property_manager')->first();
        if ($propertyRole) {
            $propertyPermissions = $maintenancePermissions
                ->merge($propertyAllotmentPermissions);
            $propertyRole->givePermissionTo($propertyPermissions);
        }

        // Member Manager - View equity, mail service, and KYC permissions
        $memberRole = Role::where('name', 'member_manager')->first();
        if ($memberRole) {
            $viewEquityPermissions = $equityPermissions->whereIn('name', [
                'view_equity_contributions',
                'manage_equity_plans',
                'view_equity_wallet',
                'view_equity_wallet_transactions',
                'view_equity_reports',
            ]);
            $memberPermissions = $viewEquityPermissions
                ->merge($mailServicePermissions)
                ->merge($kycPermissions);
            $memberRole->givePermissionTo($memberPermissions);
        }

        // System Admin - Payment gateways and white label permissions
        $systemRole = Role::where('name', 'system_admin')->first();
        if ($systemRole) {
            $systemPermissions = $paymentGatewayPermissions
                ->merge($whiteLabelPermissions);
            $systemRole->givePermissionTo($systemPermissions);
        }
    }
}

