<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Running central database migrations...\n";

// Run central migrations
$exitCode = \Illuminate\Support\Facades\Artisan::call('migrate', [
    '--path' => 'database/migrations/central',
    '--force' => true
]);

echo \Illuminate\Support\Facades\Artisan::output();

if ($exitCode === 0) {
    echo "✅ Central migrations completed successfully!\n";
} else {
    echo "❌ Central migrations failed!\n";
}
