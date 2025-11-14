<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Checking for Migration Conflicts\n";
echo "===================================\n\n";

// Get all central table names
$centralTables = [];
$centralMigrations = glob('database/migrations/central/*.php');

foreach ($centralMigrations as $migration) {
    $content = file_get_contents($migration);
    if (preg_match('/Schema::create\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        $centralTables[] = $matches[1];
    }
}

// Get all tenant table names
$tenantTables = [];
$tenantMigrations = glob('database/migrations/tenant/*.php');

foreach ($tenantMigrations as $migration) {
    $content = file_get_contents($migration);
    if (preg_match('/Schema::create\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        $tenantTables[] = $matches[1];
    }
}

// Check for conflicts
$conflicts = array_intersect($centralTables, $tenantTables);

echo "📊 Central Database Tables (" . count($centralTables) . "):\n";
foreach ($centralTables as $table) {
    echo "   - $table\n";
}
echo "\n";

echo "🏢 Tenant Database Tables (" . count($tenantTables) . "):\n";
foreach ($tenantTables as $table) {
    echo "   - $table\n";
}
echo "\n";

if (empty($conflicts)) {
    echo "✅ No table conflicts found!\n";
    echo "✅ Central and tenant databases are properly separated!\n\n";
} else {
    echo "❌ Table conflicts found:\n";
    foreach ($conflicts as $table) {
        echo "   - $table (exists in both central and tenant)\n";
    }
    echo "\n";
    echo "🔧 Please fix these conflicts by renaming tables or moving them to the correct location.\n\n";
}

// Check for proper architecture
echo "🏗️ Architecture Validation:\n";

$centralOnlyTables = ['tenants', 'packages', 'super_admins', 'modules', 'subscriptions', 'platform_payment_gateways'];
$tenantOnlyTables = ['users', 'members', 'properties', 'wallets', 'payments', 'loans', 'investments', 'roles', 'permissions'];

$centralValid = true;
$tenantValid = true;

echo "   Central Database (Platform Management):\n";
foreach ($centralOnlyTables as $table) {
    if (in_array($table, $centralTables)) {
        echo "   ✅ $table\n";
    } else {
        echo "   ❌ $table (missing)\n";
        $centralValid = false;
    }
}

echo "\n   Tenant Database (Business Data):\n";
foreach ($tenantOnlyTables as $table) {
    if (in_array($table, $tenantTables)) {
        echo "   ✅ $table\n";
    } else {
        echo "   ❌ $table (missing)\n";
        $tenantValid = false;
    }
}

echo "\n";

if ($centralValid && $tenantValid && empty($conflicts)) {
    echo "🎉 Perfect! Database architecture is correctly implemented!\n";
    echo "   ✅ No conflicts\n";
    echo "   ✅ Proper separation\n";
    echo "   ✅ All required tables present\n";
} else {
    echo "⚠️  Issues found in database architecture!\n";
    echo "   Please fix the issues above before proceeding.\n";
}

echo "\n";
