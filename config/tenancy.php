<?php

return [
    'tenant_model' => \App\Models\Central\Tenant::class,
    
    'id_generator' => \Stancl\Tenancy\UUIDGenerator::class,
    
    'database' => [
        'based_on' => env('TENANCY_DB_BASED_ON', 'tenant'),
        'prefix' => env('TENANCY_DB_PREFIX', ''),
        'suffix' => env('TENANCY_DB_SUFFIX', '_smart_housing'),
        'tenant_connection' => env('TENANCY_TEMPLATE_CONNECTION', 'tenant'),
    ],
    
    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => ['default'],
    ],
    
    'cache' => [
        'tag_base' => 'tenant',
    ],
    
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => ['local', 'public', 's3'],
        'root_override' => [
            'local' => '%storage_path%/app/tenant%tenant_id%',
            'public' => '%storage_path%/app/public/tenant%tenant_id%',
        ],
    ],
    
    'features' => [
        \Stancl\Tenancy\Features\TenantConfig::class,
        \Stancl\Tenancy\Features\UniversalRoutes::class,
    ],
    
    'migration_parameters' => [
        '--force' => true,
        '--path' => 'database/migrations/tenant',
        '--realpath' => true,
    ],
    
    'seeder_parameters' => [
        '--class' => 'TenantDatabaseSeeder',
    ],
];
