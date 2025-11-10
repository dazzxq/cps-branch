<?php
/**
 * Test Order API
 * Run: php test_order_api.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
$config = require __DIR__ . '/config.php';
$branchCode = $config['APP_BRANCH_CODE'];

echo "=== Testing Order API ===\n";
echo "Branch: {$branchCode}\n\n";

// Test data
$testOrder = [
    'branch_code' => $branchCode,
    'customer_name' => 'Test User',
    'customer_phone' => '0123456789',
    'customer_email' => 'test@example.com',
    'customer_address' => '123 Test Street, Hanoi',
    'order_note' => 'Test order from script',
    'items' => [
        [
            'product_id' => 1,
            'name' => 'Test Product',
            'quantity' => 1,
            'price' => 1000000
        ]
    ],
    'total_amount' => 1000000
];

echo "1. Testing via internal require...\n";
echo "-----------------------------------\n";

try {
    // Simulate POST request
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];
    
    // Mock input
    $GLOBALS['mock_input'] = json_encode($testOrder);
    
    // Load dependencies
    require_once __DIR__ . '/app/Models/DB.php';
    require_once __DIR__ . '/app/Controllers/OrderController.php';
    
    // Mock file_get_contents for php://input
    if (!function_exists('mock_file_get_contents')) {
        function mock_file_get_contents($filename) {
            if ($filename === 'php://input') {
                return $GLOBALS['mock_input'] ?? '';
            }
            return file_get_contents($filename);
        }
    }
    
    // Test DB connection
    echo "✓ Testing DB connection...\n";
    $db = DB::getInstance();
    $result = $db->fetchOne("SELECT 1 as test");
    if ($result['test'] == 1) {
        echo "✓ DB connection successful\n\n";
    }
    
    // Test OrderController
    echo "✓ Testing OrderController instantiation...\n";
    $controller = new OrderController();
    echo "✓ OrderController created successfully\n\n";
    
    echo "✓ All internal tests passed!\n\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n\n";
}

echo "2. Testing via cURL to actual API...\n";
echo "-------------------------------------\n";

$url = 'http://localhost/api/orders/create'; // Adjust URL as needed
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testOrder));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "✗ cURL Error: {$error}\n";
} else {
    echo "HTTP Code: {$httpCode}\n";
    echo "Response:\n";
    echo $response . "\n\n";
    
    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['success']) && $decoded['success']) {
        echo "✓ Order created successfully!\n";
        echo "Order Code: " . ($decoded['order']['order_code'] ?? 'N/A') . "\n";
    } else {
        echo "✗ Order creation failed\n";
        echo "Message: " . ($decoded['message'] ?? 'Unknown error') . "\n";
    }
}

echo "\n=== Test Complete ===\n";



