<?php

require_once __DIR__ . '/../Models/DB.php';

/**
 * OutboxController - Sync outbox events to Central
 */
class OutboxController {
    private $db;
    private $config;
    private $branchCode;
    
    public function __construct() {
        $this->db = DB::getInstance();
        $this->config = require __DIR__ . '/../../config.php';
        $this->branchCode = $this->config['APP_BRANCH_CODE'] ?? 'HN';
    }
    
    /**
     * POST /outbox/sync
     * Manual sync outbox events to Central (BATCH MODE)
     */
    public function sync() {
        header('Content-Type: application/json');
        
        try {
            // Get pending outbox events
            $events = $this->db->fetchAll(
                "SELECT * FROM outbox_events 
                 WHERE status = 'PENDING' 
                 ORDER BY created_at ASC 
                 LIMIT 50" // Increase limit for batch processing
            );
            
            if (empty($events)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No pending events to sync',
                    'synced' => 0
                ]);
                return;
            }
            
            // Prepare batch payload
            $batchOrders = [];
            $eventIdMap = []; // Map order_code to event_id for updating status later
            
            foreach ($events as $event) {
                if ($event['event_type'] === 'ORDER_CREATED') {
                    $payload = json_decode($event['payload_json'], true);
                    
                    // Get order details from local DB (if not in payload)
                    $order = $this->db->fetchOne(
                        "SELECT * FROM orders WHERE order_code = ?",
                        [$payload['order_code']]
                    );
                    
                    if (!$order) {
                        error_log("Order {$payload['order_code']} not found in local DB, skipping event {$event['id']}");
                        continue;
                    }
                    
                    // Get order items (if not in payload)
                    if (!isset($payload['items']) || empty($payload['items'])) {
                        $items = $this->db->fetchAll(
                            "SELECT product_id, qty, unit_price FROM order_item WHERE order_id = ?",
                            [$order['id']]
                        );
                        $payload['items'] = $items;
                    }
                    
                    // Get customer info (if not in payload)
                    if (!isset($payload['customer_info']) && !empty($order['json_ext'])) {
                        $payload['customer_info'] = json_decode($order['json_ext'], true);
                        $payload['customer_name'] = $payload['customer_info']['name'] ?? null;
                        $payload['customer_phone'] = $payload['customer_info']['phone'] ?? null;
                        $payload['customer_email'] = $payload['customer_info']['email'] ?? null;
                    }
                    
                    $batchOrders[] = $payload;
                    $eventIdMap[$payload['order_code']] = $event['id'];
                }
            }
            
            if (empty($batchOrders)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No valid orders to sync',
                    'synced' => 0
                ]);
                return;
            }
            
            // Send batch request to Central
            $result = $this->syncOrdersBatch($batchOrders);
            
            // Update outbox events status based on result
            $synced = 0;
            $failed = 0;
            $details = [];
            
            foreach ($result['orders'] as $orderResult) {
                $orderCode = $orderResult['order_code'];
                $eventId = $eventIdMap[$orderCode] ?? null;
                
                if (!$eventId) continue;
                
                if ($orderResult['success']) {
                    $synced++;
                    $this->db->execute(
                        "UPDATE outbox_events SET status = 'SENT', updated_at = NOW() WHERE id = ?",
                        [$eventId]
                    );
                    
                    // Update stock at Central for each item
                    if (isset($orderResult['items'])) {
                        foreach ($orderResult['items'] as $item) {
                            try {
                                $this->updateCentralStock($item['product_id'], $item['qty']);
                            } catch (Exception $e) {
                                error_log("Failed to update Central stock for product {$item['product_id']}: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    $failed++;
                    $this->db->execute(
                        "UPDATE outbox_events 
                         SET status = 'FAILED', 
                             retry_count = retry_count + 1, 
                             last_error = ?,
                             updated_at = NOW() 
                         WHERE id = ?",
                        [$orderResult['error'] ?? 'Unknown error', $eventId]
                    );
                }
                
                $details[] = [
                    'event_id' => $eventId,
                    'order_code' => $orderCode,
                    'success' => $orderResult['success'],
                    'error' => $orderResult['error'] ?? null
                ];
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Synced {$synced} orders in batch, {$failed} failed",
                'synced' => $synced,
                'failed' => $failed,
                'batch_size' => count($batchOrders),
                'details' => $details
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Sync order to Central
     */
    private function syncOrder($event) {
        $payload = json_decode($event['payload_json'], true);
        
        // Get order details from local DB
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE order_code = ?",
            [$payload['order_code']]
        );
        
        if (!$order) {
            return [
                'success' => false,
                'event_id' => $event['id'],
                'order_code' => $payload['order_code'],
                'error' => 'Order not found in local DB'
            ];
        }
        
        // Get order items
        $items = $this->db->fetchAll(
            "SELECT * FROM order_item WHERE order_id = ?",
            [$order['id']]
        );
        
        // Call Central API to ingest order
        try {
            $centralUrl = rtrim($this->config['CENTRAL_API_URL'] ?? 'https://cps.duyet.dev/api', '/');
            $apiKey = $this->config['CENTRAL_API_KEY'] ?? '';
            
            $response = $this->callCentralAPI(
                "{$centralUrl}/sync/order",
                'POST',
                [
                    'order_code' => $order['order_code'],
                    'branch_code' => $order['branch_code'],
                    // Central expects 'total' not 'total_amount'
                    'total' => (int)$order['total'],
                    'status' => $order['status'],
                    'created_at' => $order['created_at'],
                    'items' => $items,
                    'customer_info' => json_decode($order['json_ext'], true)
                ],
                $apiKey
            );
            
            // Update stock at Central for each item
            foreach ($items as $item) {
                try {
                    $this->updateCentralStock($item['product_id'], $item['qty']);
                } catch (Exception $e) {
                    error_log("Failed to update Central stock for product {$item['product_id']}: " . $e->getMessage());
                    // Continue anyway - stock sync is secondary
                }
            }
            
            return [
                'success' => true,
                'event_id' => $event['id'],
                'order_code' => $order['order_code'],
                'central_response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'event_id' => $event['id'],
                'order_code' => $order['order_code'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync orders in batch to Central (ONE API CALL)
     */
    private function syncOrdersBatch($orders) {
        $centralUrl = rtrim($this->config['CENTRAL_API_URL'] ?? 'https://cps.duyet.dev/api', '/');
        $apiKey = $this->config['CENTRAL_API_KEY'] ?? '';
        
        try {
            $response = $this->callCentralAPI(
                "{$centralUrl}/sync/orders/batch",
                'POST',
                ['orders' => $orders],
                $apiKey
            );
            
            return $response;
        } catch (Exception $e) {
            error_log("Batch sync failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update stock at Central
     */
    private function updateCentralStock($productId, $qtyDeducted) {
        $centralUrl = rtrim($this->config['CENTRAL_API_URL'] ?? 'https://cps.duyet.dev/api', '/');
        $apiKey = $this->config['CENTRAL_API_KEY'] ?? '';
        
        // Get current stock from Central
        $currentStock = $this->callCentralAPI(
            "{$centralUrl}/stock/{$productId}/{$this->branchCode}",
            'GET',
            null,
            $apiKey
        );
        
        $newStock = max(0, ($currentStock['stock'] ?? 0) - $qtyDeducted);
        
        // Update stock at Central
        return $this->callCentralAPI(
            "{$centralUrl}/stock/update",
            'POST',
            [
                'product_id' => $productId,
                'branch_code' => $this->branchCode,
                'qty' => $newStock
            ],
            $apiKey
        );
    }
    
    /**
     * Call Central API
     */
    private function callCentralAPI($url, $method = 'GET', $data = null, $apiKey = '') {
        $ch = curl_init($url);
        
        $headers = ['Content-Type: application/json'];
        if (!empty($apiKey)) {
            $headers[] = "X-API-Key: {$apiKey}";
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $decoded['message'] ?? $decoded['error'] ?? 'Unknown error';
            throw new Exception("Central API Error ({$httpCode}): {$errorMsg}");
        }
        
        if (!$decoded && $response !== '') {
            throw new Exception("Invalid JSON response from Central");
        }
        
        return $decoded ?? [];
    }
    
    /**
     * GET /outbox/status
     * Get outbox status
     */
    public function status() {
        header('Content-Type: application/json');
        
        try {
            $stats = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed
                 FROM outbox_events"
            );
            
            $recentEvents = $this->db->fetchAll(
                "SELECT id, event_type, status, retry_count, created_at, updated_at
                 FROM outbox_events
                 ORDER BY created_at DESC
                 LIMIT 20"
            );
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'recent_events' => $recentEvents
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

