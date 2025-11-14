<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Create tenant database
$databaseName = 'acme_housing_tenant_template';
$connection = \Illuminate\Support\Facades\DB::connection('mysql');

try {
    $connection->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$databaseName}' created successfully!\n";
} catch (Exception $e) {
    echo "Error creating database: " . $e->getMessage() . "\n";
}
