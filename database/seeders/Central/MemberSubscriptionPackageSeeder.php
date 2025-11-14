<?php

namespace Database\Seeders\Central;

use App\Models\Central\MemberSubscriptionPackage;
use Illuminate\Database\Seeder;

class MemberSubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Basic Member',
                'slug' => 'basic-member',
                'description' => 'Basic membership plan for individual members with essential features',
                'price' => 5000,
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 7,
                'features' => [
                    'Access to contribution plans and tracking',
                    'Apply for loans (basic tier)',
                    'Digital wallet services',
                    'Basic financial reports',
                    'Payment reminder notifications',
                    'Flexible payment options',
                ],
                'benefits' => [
                    'Community membership access',
                    'Email support (48-hour response)',
                    'Mobile app access',
                    'Secure online platform',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Premium Member',
                'slug' => 'premium-member',
                'description' => 'Premium membership with advanced features and priority support',
                'price' => 15000,
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 14,
                'features' => [
                    'All Basic Member features',
                    'Access to all loan products',
                    'Priority loan processing and approval',
                    'Buy and invest in properties',
                    'AI-powered property recommendations',
                    'Advanced reporting and analytics',
                    'Investment opportunities',
                    'Equity contribution plans',
                    'Real-time payment reminders',
                    'Multiple payment gateway options',
                ],
                'benefits' => [
                    'Priority customer support (24-hour response)',
                    'Reduced interest rates on loans',
                    'Early access to exclusive property listings',
                    'Higher investment return rates',
                    'Flexible repayment schedules',
                    'Custom financial planning tools',
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Elite Member',
                'slug' => 'elite-member',
                'description' => 'Elite membership with all premium features and exclusive benefits',
                'price' => 50000,
                'billing_cycle' => 'yearly',
                'duration_days' => 365,
                'trial_days' => 30,
                'features' => [
                    'All Premium Member features',
                    'Unlimited loan applications',
                    'Best interest rates on all loans',
                    'Exclusive property portfolio access',
                    'AI-powered personalized recommendations',
                    'Advanced reporting dashboard',
                    'Custom investment plans',
                    'Dedicated account manager',
                    'Priority property purchase processing',
                    'Automated payment reminders and scheduling',
                    'Multi-payment gateway support',
                    'Flexible contribution options',
                ],
                'benefits' => [
                    'Lowest interest rates available',
                    'Dedicated account manager support',
                    'First access to premium property listings',
                    'VIP priority processing',
                    'Custom financial solutions',
                    '24/7 priority support',
                    'Exclusive member events and webinars',
                    'White-glove service experience',
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            MemberSubscriptionPackage::updateOrCreate(
                ['slug' => $package['slug']],
                $package
            );
        }
    }
}

