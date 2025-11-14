<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        if (!class_exists(\App\Models\Central\Package::class)) {
            return;
        }

        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small cooperatives',
                'price' => 2999,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'limits' => [
                    'max_members' => 100,
                    'max_properties' => 20,
                    'max_loans' => 5,
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For growing organizations',
                'price' => 7999,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => true,
                'limits' => [
                    'max_members' => 500,
                    'max_properties' => 100,
                    'max_loans' => 20,
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited everything',
                'price' => 19999,
                'billing_cycle' => 'monthly',
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'limits' => [
                    'max_members' => null,
                    'max_properties' => null,
                    'max_loans' => null,
                ],
            ],
        ];

        foreach ($packages as $data) {
            \App\Models\Central\Package::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'billing_cycle' => $data['billing_cycle'],
                'trial_days' => $data['trial_days'],
                'is_active' => $data['is_active'],
                'is_featured' => $data['is_featured'],
                'limits' => $data['limits'],
            ]);
        }
    }
}


