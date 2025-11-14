<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing tenant resolution...\n";

try {
    // Test domain lookup
    $host = 'localhost:8000';
    echo "Looking for host: {$host}\n";
    
    $domain = DB::connection('mysql')->table('domains')->where('domain', $host)->first();
    if ($domain) {
        echo "✅ Found domain: {$domain->domain} -> {$domain->tenant_id}\n";
        
        $tenant = \App\Models\Central\Tenant::find($domain->tenant_id);
        if ($tenant) {
            echo "✅ Found tenant: {$tenant->id}\n";
            echo "Tenant data: " . json_encode($tenant->data) . "\n";
        } else {
            echo "❌ Tenant not found: {$domain->tenant_id}\n";
        }
    } else {
        echo "❌ Domain not found: {$host}\n";
        
        // Show all available domains
        $allDomains = DB::connection('mysql')->table('domains')->get();
        echo "Available domains:\n";
        foreach ($allDomains as $d) {
            echo "  - {$d->domain} -> {$d->tenant_id}\n";
        }
    }
    
    // Test the resolver directly
    echo "\nTesting DomainTenantResolver...\n";
    $resolver = new \Stancl\Tenancy\Resolvers\DomainTenantResolver();
    
    // Create a mock request
    $request = \Illuminate\Http\Request::create('http://localhost:8000/api/tenant/current');
    $request->headers->set('Host', 'localhost:8000');
    
    try {
        $tenant = $resolver->resolve($request);
        if ($tenant) {
            echo "✅ Resolver found tenant: {$tenant->id}\n";
        } else {
            echo "❌ Resolver returned null\n";
        }
    } catch (Exception $e) {
        echo "❌ Resolver error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
