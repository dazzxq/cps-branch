<?php
/**
 * Debug Sync Products - Test Central API response
 * Run: php debug_sync_products.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Debug Sync Products from Central ===\n\n";

// Load config
$config = require __DIR__ . '/config.php';
$branchCode = $config['APP_BRANCH_CODE'];
$centralUrl = $config['CENTRAL_API_URL'];
$apiKey = $config['CENTRAL_API_KEY'];

echo "Config:\n";
echo "  Branch Code: {$branchCode}\n";
echo "  Central URL: {$centralUrl}\n";
echo "  API Key: " . substr($apiKey, 0, 10) . "...\n";
echo "\n";

// Build request URL
$url = rtrim($centralUrl, '/') . '/products?branch=' . urlencode($branchCode);

echo "Request:\n";
echo "  URL: {$url}\n";
echo "  Headers:\n";
echo "    X-API-Key: {$apiKey}\n";
echo "    X-Branch-Code: {$branchCode}\n";
echo "\n";

// Call Central API
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $apiKey,
    'X-Branch-Code: ' . $branchCode
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Response:\n";
echo "  HTTP Code: {$httpCode}\n";

if ($error) {
    echo "  ❌ cURL Error: {$error}\n";
    exit(1);
}

if ($httpCode >= 400) {
    echo "  ❌ HTTP Error\n";
    echo "  Response Body:\n";
    echo "  " . substr($response, 0, 500) . "\n";
    exit(1);
}

$decoded = json_decode($response, true);

if (!$decoded) {
    echo "  ❌ Invalid JSON response\n";
    echo "  Response Body:\n";
    echo "  " . substr($response, 0, 500) . "\n";
    exit(1);
}

echo "  ✅ Success\n";
echo "  Products count: " . count($decoded['data'] ?? []) . "\n";
echo "\n";

// Analyze first 5 products
echo "First 5 Products Analysis:\n";
echo "-----------------------------------\n";

$products = $decoded['data'] ?? [];
$limit = min(5, count($products));

for ($i = 0; $i < $limit; $i++) {
    $p = $products[$i];
    echo "\n";
    echo "Product #" . ($i + 1) . ":\n";
    echo "  ID: " . ($p['id'] ?? 'N/A') . "\n";
    echo "  Name: " . ($p['name'] ?? 'N/A') . "\n";
    echo "  SKU: " . ($p['sku'] ?? 'N/A') . "\n";
    
    // Check stock field
    if (isset($p['stock'])) {
        echo "  ✅ Stock field exists: " . $p['stock'] . "\n";
    } else {
        echo "  ❌ Stock field MISSING in response\n";
        echo "  Available fields: " . implode(', ', array_keys($p)) . "\n";
    }
}

echo "\n";
echo "=== Analysis Complete ===\n\n";

// Check if stock field exists in ALL products
$hasStockField = true;
$stockZeroCount = 0;
$stockNonZeroCount = 0;

foreach ($products as $p) {
    if (!isset($p['stock'])) {
        $hasStockField = false;
        break;
    }
    if ($p['stock'] == 0) {
        $stockZeroCount++;
    } else {
        $stockNonZeroCount++;
    }
}

echo "Summary:\n";
echo "  Total products: " . count($products) . "\n";
echo "  Has stock field: " . ($hasStockField ? 'YES ✅' : 'NO ❌') . "\n";
echo "  Products with stock > 0: {$stockNonZeroCount}\n";
echo "  Products with stock = 0: {$stockZeroCount}\n";
echo "\n";

if (!$hasStockField) {
    echo "🐛 ISSUE FOUND: Response không có field 'stock'!\n";
    echo "   Central API không trả về stock data cho branch {$branchCode}\n";
    echo "\n";
} elseif ($stockZeroCount == count($products)) {
    echo "🐛 ISSUE FOUND: TẤT CẢ products đều có stock = 0!\n";
    echo "   Central API đang trả về stock = 0 cho branch {$branchCode}\n";
    echo "   Check Central database: central_inventory WHERE branch_code = '{$branchCode}'\n";
    echo "\n";
} else {
    echo "✅ Stock field exists và có giá trị > 0\n";
    echo "   Sync logic có vẻ OK. Check branch database sau khi sync:\n";
    echo "   SELECT * FROM branch_inventory;\n";
    echo "\n";
}

