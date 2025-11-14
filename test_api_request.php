<?php

// Test API endpoint directly
$url = 'http://127.0.0.1:8000/api/auth/login';
$data = [
    'email' => 'admin@tenant.test',
    'password' => 'Password123!'
];

$options = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo "❌ Failed to connect to API\n";
    echo "Error: " . error_get_last()['message'] . "\n";
} else {
    echo "✅ API Response:\n";
    echo $result . "\n";
}
