<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Config;

echo "=== DEBUGGING LOGIN ISSUE ===\n\n";

try {
    // Test 1: Check if we can connect to tenant database
    echo "1. Testing tenant database connection...\n";
    $tenantDbName = 'frsc_housing';
    Config::set('database.connections.tenant.database', $tenantDbName);
    
    $user = User::where('email', 'admin@tenant.test')->first();
    
    if ($user) {
        echo "   ✅ User found: {$user->email}\n";
        echo "   - Role: {$user->role}\n";
        echo "   - Status: {$user->status}\n";
    } else {
        echo "   ❌ User not found\n";
    }
    
    // Test 2: Check password verification
    echo "\n2. Testing password verification...\n";
    if ($user && \Hash::check('Password123!', $user->password)) {
        echo "   ✅ Password verification successful\n";
    } else {
        echo "   ❌ Password verification failed\n";
    }
    
    // Test 3: Test token creation
    echo "\n3. Testing token creation...\n";
    try {
        $token = $user->createToken('auth_token')->plainTextToken;
        echo "   ✅ Token created successfully: " . substr($token, 0, 20) . "...\n";
    } catch (\Exception $e) {
        echo "   ❌ Token creation failed: " . $e->getMessage() . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
