<?php

require_once __DIR__ . '/../Models/DB.php';

/**
 * OrderController - Handle order creation from Storefront
 * No authentication required for storefront orders
 */
class OrderController {
    private $db;
    private $branchCode;
    
    public function __construct() {
        $this->db = DB::getInstance();
        $config = require __DIR__ . '/../../config.php';
        $this->branchCode = $config['APP_BRANCH_CODE'] ?? 'HN'; // Fallback to HN
    }
    
    /**
     * POST /api/orders/create-sp
     * Create order using Stored Procedure (ACID + Transaction)
     * DEMO VERSION cho giảng viên
     */
    public function createWithStoredProcedure() {
        header('Content-Type: application/json');
        
        try {
            // Get JSON payload
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid JSON payload');
            }
            
            // Validate required fields
            $required = ['branch_code', 'customer_name', 'customer_phone', 'items', 'total_amount'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
            
            // Verify branch code matches
            if ($input['branch_code'] !== $this->branchCode) {
                throw new Exception("Branch code mismatch. This is {$this->branchCode} branch");
            }
            
            // Validate items
            if (!is_array($input['items']) || count($input['items']) === 0) {
                throw new Exception('Order must have at least one item');
            }
            
            // Generate order_code
            $timestamp = date('YmdHis');
            $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $orderCode = $this->branchCode . '-' . $timestamp . $random;
            
            // Prepare items JSON for stored procedure
            $items = [];
            foreach ($input['items'] as $item) {
                $items[] = [
                    'product_id' => (int)$item['product_id'],
                    'qty' => (int)$item['quantity'],
                    'price' => (int)$item['price']
                ];
            }
            $itemsJson = json_encode($items);
            
            // Call stored procedure
            $pdo = $this->db->getPdo();
            $stmt = $pdo->prepare("CALL sp_create_order(?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $orderCode,
                $this->branchCode,
                $input['customer_name'],
                $input['customer_phone'],
                $input['customer_email'] ?? '',
                $input['customer_address'] ?? '',
                $input['order_note'] ?? '',
                $itemsJson,
                (int)$input['total_amount']
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor(); // Close cursor để tránh pending results
            
            if ($result['status'] === 'SUCCESS') {
                // Fetch full order details
                $order = $this->db->fetchOne(
                    "SELECT id, order_code, branch_code, total, status, json_ext, created_at 
                     FROM orders WHERE order_code = ?",
                    [$orderCode]
                );
                
                if ($order) {
                    $order['customer_info'] = json_decode($order['json_ext'], true);
                    unset($order['json_ext']);
                }
                
                // AUTO SYNC TO CENTRAL (Real-time sync)
                try {
                    $this->syncToCentral($orderCode, $items, $input);
                    error_log("Order {$orderCode} synced to Central successfully");
                } catch (Exception $e) {
                    // Log error but don't fail the order creation
                    error_log("Failed to sync order {$orderCode} to Central: " . $e->getMessage());
                    // Note: In production, this would be handled by outbox pattern
                    // For this assignment, we just log and continue
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order created successfully (via Stored Procedure)',
                    'method' => 'STORED_PROCEDURE',
                    'acid_compliant' => true,
                    'order' => $order ?? $result
                ]);
            } elseif ($result['status'] === 'DUPLICATE') {
                // Idempotency: order đã tồn tại
                echo json_encode([
                    'success' => true,
                    'message' => 'Order already exists (Idempotency)',
                    'method' => 'STORED_PROCEDURE',
                    'order' => [
                        'order_code' => $result['order_code'],
                        'order_id' => $result['order_id']
                    ]
                ]);
            } else {
                throw new Exception($result['message'] ?? 'Stored procedure failed');
            }
            
        } catch (PDOException $e) {
            // Catch stored procedure errors (INSUFFICIENT_STOCK, etc.)
            http_response_code(400);
            error_log("Order creation failed (SP): " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'method' => 'STORED_PROCEDURE',
                'acid_rollback' => true
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            error_log("Order creation failed: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * POST /api/orders/create
     * Create order from Storefront (no auth required)
     * LEGACY VERSION (không dùng SP)
     */
    public function create() {
        header('Content-Type: application/json');
        
        try {
            // Get JSON payload
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid JSON payload');
            }
            
            // Validate required fields
            $required = ['branch_code', 'customer_name', 'customer_phone', 'items', 'total_amount'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
            
            // Verify branch code matches
            if ($input['branch_code'] !== $this->branchCode) {
                throw new Exception("Branch code mismatch. This is {$this->branchCode} branch");
            }
            
            // Validate items
            if (!is_array($input['items']) || count($input['items']) === 0) {
                throw new Exception('Order must have at least one item');
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            try {
                // Generate order_code: YYYYMMDDHHmmss + random 3 digits (001-999)
                $timestamp = date('YmdHis'); // 20251101153045
                $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT); // 001-999
                $orderCode = $this->branchCode . '-' . $timestamp . $random; // HN-20251101153045123
                
                // Prepare customer info for json_ext
                $customerInfo = [
                    'name' => $input['customer_name'],
                    'phone' => $input['customer_phone'],
                    'email' => $input['customer_email'] ?? null,
                    'address' => $input['customer_address'] ?? null,
                    'note' => $input['order_note'] ?? null,
                    'source' => 'storefront',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Insert order with explicit order_code
                $orderId = $this->db->insert(
                    "INSERT INTO orders (order_code, branch_code, total, status, json_ext, created_at) 
                     VALUES (?, ?, ?, 'NEW', ?, NOW())",
                    [
                        $orderCode,
                        $this->branchCode,
                        (int)$input['total_amount'],
                        json_encode($customerInfo, JSON_UNESCAPED_UNICODE)
                    ]
                );
                
                if (!$orderId) {
                    throw new Exception('Failed to create order');
                }
                
                // Get created order
                $order = $this->db->fetchOne(
                    "SELECT id, order_code, branch_code, total, status, json_ext, created_at 
                     FROM orders WHERE id = ?",
                    [$orderId]
                );
                
                if (!$order) {
                    throw new Exception('Order created but not found');
                }
                
                // Insert order items
                foreach ($input['items'] as $item) {
                    if (empty($item['product_id']) || empty($item['quantity']) || empty($item['price'])) {
                        throw new Exception('Invalid item data');
                    }
                    
                    $this->db->execute(
                        "INSERT INTO order_item (order_id, product_id, qty, unit_price) 
                         VALUES (?, ?, ?, ?)",
                        [
                            $orderId,
                            (int)$item['product_id'],
                            (int)$item['quantity'],
                            (int)$item['price']
                        ]
                    );
                    
                    // TODO: Reserve inventory (will add in transaction phase)
                    // For now, just log
                    error_log("Order {$order['order_code']}: Reserved {$item['quantity']} units of product {$item['product_id']}");
                }
                
                // Commit transaction
                $this->db->commit();
                
                // Parse json_ext for response
                $order['customer_info'] = json_decode($order['json_ext'], true);
                unset($order['json_ext']); // Remove raw JSON from response
                
                // Log success
                error_log("Order created successfully: {$order['order_code']} for {$customerInfo['name']}");
                
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'order' => $order
                ]);
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            http_response_code(400);
            error_log("Order creation failed: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * GET /api/orders/{order_code}
     * Get order details (for tracking)
     */
    public function getByCode($params) {
        header('Content-Type: application/json');
        
        try {
            $orderCode = $params['order_code'] ?? null;
            
            if (!$orderCode) {
                throw new Exception('Order code required');
            }
            
            $order = $this->db->fetchOne(
                "SELECT o.*, 
                        (SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'product_id', oi.product_id,
                                'product_name', pr.name,
                                'qty', oi.qty,
                                'unit_price', oi.unit_price
                            )
                        ) FROM order_item oi 
                         LEFT JOIN products_replica pr ON pr.id = oi.product_id
                         WHERE oi.order_id = o.id) as items
                 FROM orders o 
                 WHERE o.order_code = ?",
                [$orderCode]
            );
            
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            // Parse JSON fields
            $order['customer_info'] = json_decode($order['json_ext'], true);
            $order['items'] = json_decode($order['items'], true) ?? [];
            unset($order['json_ext']);
            
            echo json_encode([
                'success' => true,
                'order' => $order
            ]);
            
        } catch (Exception $e) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Auto sync order to Central (Real-time)
     * Called immediately after order commit
     */
    private function syncToCentral($orderCode, $items, $input) {
        $config = require __DIR__ . '/../../config.php';
        $centralUrl = rtrim($config['CENTRAL_API_URL'] ?? 'https://cps.duyet.dev/api', '/');
        $apiKey = $config['CENTRAL_API_KEY'] ?? '';
        
        // Prepare payload
        $payload = [
            'order_code' => $orderCode,
            'branch_code' => $this->branchCode,
            'total' => (int)$input['total_amount'],
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'PAID',
            'customer_name' => $input['customer_name'],
            'customer_phone' => $input['customer_phone'],
            'customer_email' => $input['customer_email'] ?? '',
            'customer_address' => $input['customer_address'] ?? '',
            'items' => $items,
            'customer_info' => [
                'name' => $input['customer_name'],
                'phone' => $input['customer_phone'],
                'email' => $input['customer_email'] ?? '',
                'address' => $input['customer_address'] ?? '',
                'note' => $input['order_note'] ?? ''
            ]
        ];
        
        // Call Central API
        $ch = curl_init("{$centralUrl}/sync/order");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "X-API-Key: {$apiKey}"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10s timeout for real-time sync
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }
        
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errorMsg = $decoded['error'] ?? $decoded['message'] ?? 'Unknown error';
            throw new Exception("Central API Error ({$httpCode}): {$errorMsg}");
        }
        
        // Update Central stock for each item
        foreach ($items as $item) {
            try {
                $this->updateCentralStock($item['product_id'], $item['qty']);
            } catch (Exception $e) {
                error_log("Failed to update Central stock for product {$item['product_id']}: " . $e->getMessage());
                // Continue anyway - stock sync is secondary
            }
        }
        
        return true;
    }
    
    /**
     * Update Central stock after order sync
     */
    private function updateCentralStock($productId, $qtyDeducted) {
        $config = require __DIR__ . '/../../config.php';
        $centralUrl = rtrim($config['CENTRAL_API_URL'] ?? 'https://cps.duyet.dev/api', '/');
        $apiKey = $config['CENTRAL_API_KEY'] ?? '';
        
        // Get current stock from Central
        $ch = curl_init("{$centralUrl}/stock/{$productId}/{$this->branchCode}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "X-API-Key: {$apiKey}"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        $currentStock = $decoded['stock'] ?? 0;
        $newStock = max(0, $currentStock - $qtyDeducted);
        
        // Update stock at Central
        $ch = curl_init("{$centralUrl}/stock/update");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'product_id' => $productId,
            'branch_code' => $this->branchCode,
            'qty' => $newStock
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "X-API-Key: {$apiKey}"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("Failed to update Central stock: HTTP {$httpCode}");
        }
        
        return true;
    }
}

