<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Central\Tenant;
use App\Models\Central\TenantDetail;

echo "=== TENANT STATUS CHECK ===\n\n";

echo "1. Checking tenants table:\n";
$tenants = Tenant::all();
if ($tenants->count() > 0) {
    foreach ($tenants as $tenant) {
        echo "   - Tenant ID: {$tenant->id}\n";
    }
} else {
    echo "   - No tenants found in database\n";
}

echo "\n2. Checking tenant_details table:\n";
if (class_exists('App\Models\Central\TenantDetail')) {
    $tenantDetails = call_user_func(['App\Models\Central\TenantDetail', 'all']);
    if ($tenantDetails->count() > 0) {
        foreach ($tenantDetails as $detail) {
            echo "   - Tenant: {$detail->tenant_id} | Name: {$detail->name} | Slug: {$detail->slug}\n";
        }
    } else {
        echo "   - No tenant details found in database\n";
    }
} else {
    echo "   - TenantDetail model not found (run central migrations first)\n";
}

echo "\n3. Checking for 'acme' tenant specifically:\n";
$acmeTenant = Tenant::find('acme');
if ($acmeTenant) {
    echo "   - ACME tenant found: {$acmeTenant->id}\n";
} else {
    echo "   - ACME tenant NOT found\n";
}

if (class_exists('App\Models\Central\TenantDetail')) {
    $acmeDetail = call_user_func(['App\Models\Central\TenantDetail', 'where'], 'tenant_id', 'acme')->first();
    if ($acmeDetail) {
        echo "   - ACME tenant details found: {$acmeDetail->name}\n";
    } else {
        echo "   - ACME tenant details NOT found\n";
    }
} else {
    echo "   - Cannot check ACME details (TenantDetail model missing)\n";
}

echo "\n=== END CHECK ===\n";
