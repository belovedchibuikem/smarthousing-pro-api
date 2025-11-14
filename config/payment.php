<?php

return [
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
        'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'remita' => [
        'merchant_id' => env('REMITA_MERCHANT_ID', ''),
        'api_key' => env('REMITA_API_KEY', ''),
        'service_type_id' => env('REMITA_SERVICE_TYPE_ID', ''),
        'base_url' => env('REMITA_BASE_URL', 'https://remitademo.net/remita/exapp/api'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
        'public' => env('STRIPE_PUBLIC', ''),
    ],

    'callback_url' => env('PAYMENT_CALLBACK_URL', env('APP_URL').'/api/payments/callback'),
];


