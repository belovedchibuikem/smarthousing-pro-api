<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Central\Package;
use App\Models\Central\Module;
use App\Models\Central\SuperAdmin;
use Illuminate\Support\Facades\Hash;

class SetupInitialData extends Command
{
    protected $signature = 'setup:initial-data';
    protected $description = 'Set up initial data for the application';

    public function handle()
    {
        $this->info('Setting up initial data...');

        // Create default packages
        $this->createDefaultPackages();
        
        // Create super admin
        $this->createSuperAdmin();
        
        $this->info('Initial data setup completed!');
    }

    private function createDefaultPackages()
    {
        $this->info('Creating default packages...');

        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small cooperatives',
                'price' => 50000,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'limits' => [
                    'max_members' => 100,
                    'max_properties' => 50,
                    'max_loans' => 20,
                    'max_contributions' => 100,
                    'max_mortgages' => 10,
                    'role_management' => false,
                    'custom_domain' => false,
                    'white_label' => false,
                ]
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing cooperatives',
                'price' => 150000,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => true,
                'limits' => [
                    'max_members' => 500,
                    'max_properties' => 200,
                    'max_loans' => 100,
                    'max_contributions' => 500,
                    'max_mortgages' => 50,
                    'role_management' => true,
                    'custom_domain' => true,
                    'white_label' => true,
                ]
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large cooperatives with advanced needs',
                'price' => 500000,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'limits' => [
                    'max_members' => -1, // Unlimited
                    'max_properties' => -1,
                    'max_loans' => -1,
                    'max_contributions' => -1,
                    'max_mortgages' => -1,
                    'role_management' => true,
                    'custom_domain' => true,
                    'white_label' => true,
                    'priority_support' => true,
                ]
            ]
        ];

        foreach ($packages as $packageData) {
            Package::updateOrCreate(
                ['slug' => $packageData['slug']],
                $packageData
            );
        }

        $this->info('Default packages created successfully!');
    }

    private function createSuperAdmin()
    {
        $this->info('Creating super admin...');

        $email = $this->ask('Enter super admin email', 'admin@frsc-housing.com');
        $password = $this->secret('Enter super admin password');

        SuperAdmin::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        $this->info('Super admin created successfully!');
    }
}
