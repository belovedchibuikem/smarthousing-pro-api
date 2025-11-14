<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Central\Tenant;
use App\Models\Central\Package;
use App\Models\Central\Subscription;
use App\Models\Central\PlatformTransaction;
use Carbon\Carbon;

class DashboardDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create packages
        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small cooperatives',
                'price' => 29.99,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'limits' => [
                    'max_members' => 100,
                    'max_properties' => 10,
                    'max_loans' => 50
                ]
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing cooperatives',
                'price' => 79.99,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => true,
                'limits' => [
                    'max_members' => 1000,
                    'max_properties' => 100,
                    'max_loans' => 500
                ]
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations',
                'price' => 199.99,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'limits' => [
                    'max_members' => 10000,
                    'max_properties' => 1000,
                    'max_loans' => 5000
                ]
            ]
        ];

        $createdPackages = [];
        foreach ($packages as $packageData) {
            $package = Package::create($packageData);
            $createdPackages[] = $package;
        }

        // Create sample tenants
        $tenants = [
            [
                'id' => 'frsc-housing',
                'data' => [
                    'name' => 'FRSC Housing Cooperative',
                    'contact_email' => 'admin@frsc-housing.com',
                    'contact_phone' => '+234 800 000 0000',
                    'logo_url' => null,
                    'address' => 'Lagos, Nigeria'
                ],
                'status' => 'active',
                'created_at' => Carbon::now()->subDays(30)
            ],
            [
                'id' => 'police-housing',
                'data' => [
                    'name' => 'Police Housing Society',
                    'contact_email' => 'admin@police-housing.ng',
                    'contact_phone' => '+234 800 111 2222',
                    'logo_url' => null,
                    'address' => 'Abuja, Nigeria'
                ],
                'status' => 'active',
                'created_at' => Carbon::now()->subDays(25)
            ],
            [
                'id' => 'teachers-coop',
                'data' => [
                    'name' => 'Teachers Cooperative',
                    'contact_email' => 'admin@teachers-coop.com',
                    'contact_phone' => '+234 800 333 4444',
                    'logo_url' => null,
                    'address' => 'Kano, Nigeria'
                ],
                'status' => 'trial',
                'trial_ends_at' => Carbon::now()->addDays(5),
                'created_at' => Carbon::now()->subDays(10)
            ],
            [
                'id' => 'doctors-housing',
                'data' => [
                    'name' => 'Doctors Housing Association',
                    'contact_email' => 'admin@doctors-housing.com',
                    'contact_phone' => '+234 800 555 6666',
                    'logo_url' => null,
                    'address' => 'Port Harcourt, Nigeria'
                ],
                'status' => 'active',
                'created_at' => Carbon::now()->subDays(20)
            ],
            [
                'id' => 'engineers-coop',
                'data' => [
                    'name' => 'Engineers Cooperative',
                    'contact_email' => 'admin@engineers-coop.com',
                    'contact_phone' => '+234 800 777 8888',
                    'logo_url' => null,
                    'address' => 'Ibadan, Nigeria'
                ],
                'status' => 'trial',
                'trial_ends_at' => Carbon::now()->addDays(2),
                'created_at' => Carbon::now()->subDays(5)
            ]
        ];

        $createdTenants = [];
        foreach ($tenants as $tenantData) {
            $tenant = Tenant::create($tenantData);
            $createdTenants[] = $tenant;
        }

        // Create subscriptions
        $subscriptions = [
            [
                'tenant_id' => 'frsc-housing',
                'package_id' => $createdPackages[1]->id, // Professional
                'status' => 'active',
                'amount' => 79.99,
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::now()->subDays(30),
                'end_date' => Carbon::now()->addDays(30),
                'auto_renew' => true
            ],
            [
                'tenant_id' => 'police-housing',
                'package_id' => $createdPackages[2]->id, // Enterprise
                'status' => 'active',
                'amount' => 199.99,
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::now()->subDays(25),
                'end_date' => Carbon::now()->addDays(35),
                'auto_renew' => true
            ],
            [
                'tenant_id' => 'doctors-housing',
                'package_id' => $createdPackages[1]->id, // Professional
                'status' => 'active',
                'amount' => 79.99,
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::now()->subDays(20),
                'end_date' => Carbon::now()->addDays(40),
                'auto_renew' => true
            ],
            [
                'tenant_id' => 'teachers-coop',
                'package_id' => $createdPackages[0]->id, // Starter
                'status' => 'trial',
                'amount' => 0,
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(5),
                'auto_renew' => false
            ],
            [
                'tenant_id' => 'engineers-coop',
                'package_id' => $createdPackages[0]->id, // Starter
                'status' => 'trial',
                'amount' => 0,
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(2),
                'auto_renew' => false
            ]
        ];

        foreach ($subscriptions as $subscriptionData) {
            Subscription::create($subscriptionData);
        }

        // Create platform transactions
        $transactions = [];
        
        // Create transactions for the last 30 days
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::now()->subDays($i);
            
            // FRSC Housing payments
            if ($i % 30 == 0) { // Monthly payment
                $transactions[] = [
                    'tenant_id' => 'frsc-housing',
                    'type' => 'subscription_payment',
                    'amount' => 79.99,
                    'currency' => 'NGN',
                    'status' => 'completed',
                    'payment_method' => 'paystack',
                    'reference' => 'FRSC-' . $date->format('Ymd') . '-' . rand(1000, 9999),
                    'description' => 'Monthly subscription payment',
                    'created_at' => $date
                ];
            }
            
            // Police Housing payments
            if ($i % 30 == 0) {
                $transactions[] = [
                    'tenant_id' => 'police-housing',
                    'type' => 'subscription_payment',
                    'amount' => 199.99,
                    'currency' => 'NGN',
                    'status' => 'completed',
                    'payment_method' => 'paystack',
                    'reference' => 'POLICE-' . $date->format('Ymd') . '-' . rand(1000, 9999),
                    'description' => 'Monthly subscription payment',
                    'created_at' => $date
                ];
            }
            
            // Doctors Housing payments
            if ($i % 30 == 0) {
                $transactions[] = [
                    'tenant_id' => 'doctors-housing',
                    'type' => 'subscription_payment',
                    'amount' => 79.99,
                    'currency' => 'NGN',
                    'status' => 'completed',
                    'payment_method' => 'paystack',
                    'reference' => 'DOCTORS-' . $date->format('Ymd') . '-' . rand(1000, 9999),
                    'description' => 'Monthly subscription payment',
                    'created_at' => $date
                ];
            }
        }

        foreach ($transactions as $transactionData) {
            PlatformTransaction::create($transactionData);
        }

        echo "Dashboard data seeded successfully!\n";
        echo "Created " . count($createdPackages) . " packages\n";
        echo "Created " . count($createdTenants) . " tenants\n";
        echo "Created " . count($subscriptions) . " subscriptions\n";
        echo "Created " . count($transactions) . " transactions\n";
    }
}