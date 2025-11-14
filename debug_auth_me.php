<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "=== DEBUGGING /api/auth/me ENDPOINT ===\n";
    
    // Test token
    $token = "25|GPgy8MKINAwpSF7iGAHsSSz25FfaQHT4kQ7sjihRc092ca99";
    
    echo "1. Testing token validation...\n";
    
    // Check if token exists in database
    $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if ($tokenRecord) {
        echo "   ✅ Token found in database\n";
        echo "   Token ID: " . $tokenRecord->id . "\n";
        echo "   Tokenable Type: " . $tokenRecord->tokenable_type . "\n";
        echo "   Tokenable ID: " . $tokenRecord->tokenable_id . "\n";
        echo "   Database: " . $tokenRecord->getConnectionName() . "\n";
    } else {
        echo "   ❌ Token not found in database\n";
    }
    
    echo "\n2. Testing user authentication...\n";
    
    // Test Auth::user() with token
    $request = Request::create('/api/auth/me', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $token);
    
    // Set the request in the container
    app()->instance('request', $request);
    
    // Test authentication
    $user = \Illuminate\Support\Facades\Auth::user();
    if ($user) {
        echo "   ✅ User authenticated: " . $user->email . "\n";
        echo "   User ID: " . $user->id . "\n";
        echo "   User Connection: " . $user->getConnectionName() . "\n";
    } else {
        echo "   ❌ User not authenticated\n";
    }
    
    echo "\n3. Testing tenant context...\n";
    
    // Check tenant context
    $host = '127.0.0.1:8000';
    $domain = \DB::connection('mysql')->table('domains')->where('domain', $host)->first();
    if ($domain) {
        echo "   ✅ Domain found: " . $domain->domain . "\n";
        echo "   Tenant ID: " . $domain->tenant_id . "\n";
        
        $tenant = \App\Models\Central\Tenant::find($domain->tenant_id);
        if ($tenant) {
            echo "   ✅ Tenant found: " . $tenant->id . "\n";
        } else {
            echo "   ❌ Tenant not found\n";
        }
    } else {
        echo "   ❌ Domain not found for host: " . $host . "\n";
    }
    
    echo "\n4. Testing database connections...\n";
    
    // Test central database
    try {
        $centralCount = \DB::connection('mysql')->table('tenants')->count();
        echo "   ✅ Central DB: " . $centralCount . " tenants\n";
    } catch (\Exception $e) {
        echo "   ❌ Central DB error: " . $e->getMessage() . "\n";
    }
    
    // Test tenant database
    try {
        $tenantCount = \DB::connection('tenant')->table('users')->count();
        echo "   ✅ Tenant DB: " . $tenantCount . " users\n";
    } catch (\Exception $e) {
        echo "   ❌ Tenant DB error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== DEBUG COMPLETE ===\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
