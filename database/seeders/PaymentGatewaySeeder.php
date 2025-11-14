<?php

namespace Database\Seeders;

use App\Models\Central\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'name' => 'paystack',
                'display_name' => 'Paystack',
                'description' => 'Nigerian payment gateway for card and bank transfers',
                'is_active' => true,
                'settings' => [
                    'secret_key' => '',
                    'public_key' => '',
                    'webhook_secret' => '',
                    'test_mode' => true,
                ],
                'supported_currencies' => ['NGN', 'USD', 'GBP', 'EUR'],
                'supported_countries' => ['NG', 'US', 'GB', 'EU'],
                'transaction_fee_percentage' => 1.5,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 100,
                'maximum_amount' => 1000000,
                'platform_fee_percentage' => 0.5,
                'platform_fee_fixed' => 0,
            ],
            [
                'name' => 'remita',
                'display_name' => 'Remita',
                'description' => 'Nigerian government payment gateway',
                'is_active' => false,
                'settings' => [
                    'merchant_id' => '',
                    'api_key' => '',
                    'webhook_secret' => '',
                    'test_mode' => true,
                ],
                'supported_currencies' => ['NGN'],
                'supported_countries' => ['NG'],
                'transaction_fee_percentage' => 1.0,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 100,
                'maximum_amount' => 5000000,
                'platform_fee_percentage' => 0.3,
                'platform_fee_fixed' => 0,
            ],
            [
                'name' => 'stripe',
                'display_name' => 'Stripe',
                'description' => 'International payment gateway',
                'is_active' => false,
                'settings' => [
                    'secret_key' => '',
                    'publishable_key' => '',
                    'webhook_secret' => '',
                    'test_mode' => true,
                ],
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'NGN'],
                'supported_countries' => ['US', 'EU', 'GB', 'NG'],
                'transaction_fee_percentage' => 2.9,
                'transaction_fee_fixed' => 30,
                'minimum_amount' => 50,
                'maximum_amount' => 10000000,
                'platform_fee_percentage' => 0.5,
                'platform_fee_fixed' => 0,
            ],
            [
                'name' => 'manual',
                'display_name' => 'Manual Payment',
                'description' => 'Manual bank transfer payments',
                'is_active' => false,
                'settings' => [
                    'bank_accounts' => [],
                    'test_mode' => false,
                ],
                'supported_currencies' => ['NGN'],
                'supported_countries' => ['NG'],
                'transaction_fee_percentage' => 0,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 100,
                'maximum_amount' => 10000000,
                'platform_fee_percentage' => 0,
                'platform_fee_fixed' => 0,
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['name' => $gateway['name']],
                $gateway
            );
        }
    }
}
