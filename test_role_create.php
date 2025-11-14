<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Role Creation...\n";
echo "=======================\n\n";

try {
    // Test the controller store method
    $controller = new App\Http\Controllers\Api\SuperAdmin\SuperAdminRoleController();
    
    // Create a mock request with permissions
    $request = new Illuminate\Http\Request();
    $request->merge([
        'name' => 'Test Role',
        'description' => 'Test role for testing purposes',
        'permissions' => [
            'dashboard.view',
            'businesses.view',
            'businesses.create',
            'analytics.view'
        ]
    ]);
    
    echo "Testing role creation with permissions...\n";
    $response = $controller->store($request);
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    $data = $response->getData(true);
    
    if (isset($data['success'])) {
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    }
    
    if (isset($data['message'])) {
        echo "Message: " . $data['message'] . "\n";
    }
    
    if (isset($data['error'])) {
        echo "Error: " . $data['error'] . "\n";
    }
    
    if (isset($data['role'])) {
        $role = $data['role'];
        echo "Created role: {$role['name']}\n";
        echo "Permissions count: {$role['permissions_count']}\n";
        echo "User count: {$role['user_count']}\n";
    }
    
    echo "\n✅ Role creation test completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
