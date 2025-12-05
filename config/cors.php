<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:3001',
        'http://127.0.0.1:3001',
        'http://frsc.localhost:3000',
        env('FRONTEND_URL'), // Dynamic frontend URL from environment
        env('PLATFORM_DOMAIN') ? 'https://' . env('PLATFORM_DOMAIN') : null, // Dynamic platform domain
    ])),

    'allowed_origins_patterns' => array_merge([
        '#^http://(.*\.)?localhost:\d+$#',
        '#^http://(.*\.)?127\.0\.0\.1:\d+$#',
        '#^http://.*\.localhost:\d+$#', // Match subdomains like frsc.localhost:3000
    ], env('PLATFORM_DOMAIN') ? [
        '#^https://(.*\.)?' . preg_quote(env('PLATFORM_DOMAIN'), '#') . '$#', // Dynamic pattern for platform domain subdomains
    ] : []),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
