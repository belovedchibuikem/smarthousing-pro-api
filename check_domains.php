<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking domains...\n";

try {
    $domainCount = DB::connection('mysql')->table('domains')->count();
    echo "✅ Domain count: {$domainCount}\n";
    
    if ($domainCount > 0) {
        $domains = DB::connection('mysql')->table('domains')->get();
        echo "Domains:\n";
        foreach ($domains as $domain) {
            echo "  - {$domain->domain} -> {$domain->tenant_id}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
