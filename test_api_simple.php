<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing API response...\n";
echo "=====================\n\n";

try {
    // Test a simple role query
    $role = App\Models\Central\Role::first();
    
    if ($role) {
        echo "Role found: {$role->name}\n";
        echo "Permissions count: " . $role->permissions()->count() . "\n";
        
        // Test the resource
        $resource = new App\Http\Resources\SuperAdmin\SuperAdminRoleResource($role);
        $array = $resource->toArray(request());
        
        echo "\nResource output:\n";
        echo "ID: " . $array['id'] . "\n";
        echo "Name: " . $array['name'] . "\n";
        echo "Permissions: " . (isset($array['permissions']) ? 'Loaded' : 'Not loaded') . "\n";
        
        if (isset($array['permissions'])) {
            echo "Permission count: " . count($array['permissions']) . "\n";
            if (count($array['permissions']) > 0) {
                echo "First permission: " . $array['permissions'][0]['name'] . "\n";
            }
        }
    } else {
        echo "No roles found!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
