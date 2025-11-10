<?php

require_once __DIR__ . '/../Models/DB.php';

/**
 * AcidDemoController - Interactive ACID Demo UI
 * Mục đích: Demo ACID principles cho giảng viên
 */
class AcidDemoController {
    private $db;
    private $branchCode;
    private $config;
    
    public function __construct() {
        $this->db = DB::getInstance();
        $this->config = require __DIR__ . '/../../config.php';
        $this->branchCode = $this->config['APP_BRANCH_CODE'] ?? 'HN';
    }
    
    /**
     * GET /acid-demo
     * Trang chủ ACID Demo
     */
    public function index() {
        // Lấy thống kê hiện tại
        $stats = $this->getStats();
        
        // Lấy danh sách sản phẩm cho dropdown
        $products = $this->getProducts();
        
        $env = $this->config;
        $viewFile = __DIR__ . '/../Views/admin/acid_demo.php';
        require __DIR__ . '/../Views/admin/layout.php';
    }
    
    /**
     * GET /acid-demo/products
     * Get list of products for dropdown
     */
    public function getProductsList() {
        header('Content-Type: application/json');
        echo json_encode($this->getProducts());
    }
    
    /**
     * Helper: Get products with stock
     */
    private function getProducts() {
        try {
            $products = $this->db->fetchAll(
                "SELECT 
                    pr.id,
                    pr.name,
                    pr.sku,
                    COALESCE(bi.qty, 0) as stock
                 FROM products_replica pr
                 LEFT JOIN branch_inventory bi ON bi.product_id = pr.id
                 WHERE pr.status = 'ACTIVE'
                 ORDER BY 
                    CASE WHEN bi.qty IS NOT NULL THEN 0 ELSE 1 END,
                    bi.qty DESC,
                    pr.name ASC
                 LIMIT 50"
            );
            
            return $products;
        } catch (Exception $e) {
            error_log("Failed to get products: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * POST /acid-demo/test-atomicity
     * Demo Atomicity - Transaction rollback
     */
    public function testAtomicity() {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = (int)($input['product_id'] ?? 1);
            $qty = (int)($input['qty'] ?? 999);
            
            // Generate order code
            $timestamp = date('YmdHis');
            $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $orderCode = "DEMO-{$this->branchCode}-{$timestamp}{$random}";
            
            // Prepare items JSON
            $itemsJson = json_encode([
                ['product_id' => $productId, 'qty' => $qty, 'price' => 1000000]
            ]);
            
            // Get stock trước khi gọi SP
            $stockBefore = $this->db->fetchOne(
                "SELECT product_id, qty FROM branch_inventory WHERE product_id = ?",
                [$productId]
            );
            
            $sqlExecuted = [
                "-- Step 1: Lock and read stock",
                "SELECT qty FROM branch_inventory WHERE product_id = {$productId} FOR UPDATE;",
                "",
                "-- Step 2: Check stock (Consistency)",
                "IF stock < {$qty} THEN SIGNAL 'INSUFFICIENT_STOCK'",
                "",
                "-- Step 3: Create order",
                "INSERT INTO orders(...) VALUES(...)",
                "",
                "-- Step 4: Create order items",
                "INSERT INTO order_item(...) VALUES(...)",
                "",
                "-- Step 5: Deduct stock",
                "UPDATE branch_inventory SET qty = qty - {$qty} WHERE product_id = {$productId}",
                "",
                "-- Step 6: Add to outbox",
                "INSERT INTO outbox_events(...) VALUES(...)",
                "",
                "-- If all OK: COMMIT, else: ROLLBACK"
            ];
            
            // Call stored procedure
            try {
                $pdo = $this->db->getPdo();
                $stmt = $pdo->prepare("CALL sp_create_order(?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $orderCode,
                    $this->branchCode,
                    'Demo ACID Test',
                    '0123456789',
                    'demo@test.com',
                    'Test Address',
                    'Demo Atomicity',
                    $itemsJson,
                    1000000 * $qty
                ]);
                
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Close cursor để tránh "pending result sets"
                $stmt->closeCursor();
                
                // Get stock sau khi gọi SP
                $stockAfter = $this->db->fetchOne(
                    "SELECT product_id, qty FROM branch_inventory WHERE product_id = ?",
                    [$productId]
                );
                
                echo json_encode([
                    'success' => ($result['status'] === 'SUCCESS'),
                    'status' => $result['status'],
                    'message' => $result['message'] ?? 'Transaction completed',
                    'order_code' => $orderCode,
                    'stock_before' => (int)($stockBefore['qty'] ?? 0),
                    'stock_after' => (int)($stockAfter['qty'] ?? 0),
                    'sql_executed' => $sqlExecuted,
                    'explanation' => $result['status'] === 'SUCCESS' 
                        ? "✅ ATOMICITY: Tất cả bước đều thành công → COMMIT" 
                        : "✅ ATOMICITY: Có bước fail (stock không đủ) → ROLLBACK toàn bộ"
                ]);
                
            } catch (PDOException $e) {
                // Close any pending cursors
                if (isset($stmt)) {
                    $stmt->closeCursor();
                }
                
                // Transaction rollback
                $stockAfter = $this->db->fetchOne(
                    "SELECT product_id, qty FROM branch_inventory WHERE product_id = ?",
                    [$productId]
                );
                
                echo json_encode([
                    'success' => false,
                    'status' => 'ROLLBACK',
                    'message' => $e->getMessage(),
                    'order_code' => $orderCode,
                    'stock_before' => (int)($stockBefore['qty'] ?? 0),
                    'stock_after' => (int)($stockAfter['qty'] ?? 0),
                    'sql_executed' => $sqlExecuted,
                    'explanation' => "✅ ATOMICITY: Stock không đủ → Stored Procedure tự động ROLLBACK. Không có order rác, stock không đổi!"
                ]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * POST /acid-demo/test-isolation
     * Demo Isolation - Race condition với FOR UPDATE
     */
    public function testIsolation() {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = (int)($input['product_id'] ?? 1);
            
            // Simulate concurrent access
            $sqlExecuted = [
                "-- TERMINAL 1:",
                "START TRANSACTION;",
                "SELECT qty FROM branch_inventory WHERE product_id = {$productId} FOR UPDATE; -- 🔒 LOCK",
                "-- Terminal 1 giữ lock 5 giây...",
                "UPDATE branch_inventory SET qty = qty - 1 WHERE product_id = {$productId};",
                "COMMIT; -- 🔓 UNLOCK",
                "",
                "-- TERMINAL 2 (chạy song song):",
                "START TRANSACTION;",
                "SELECT qty FROM branch_inventory WHERE product_id = {$productId} FOR UPDATE;",
                "-- ⏳ BLOCKED! Phải chờ Terminal 1 COMMIT",
                "-- Sau khi T1 xong, T2 mới đọc được stock đã trừ",
                "UPDATE branch_inventory SET qty = qty - 1 WHERE product_id = {$productId};",
                "COMMIT;",
                "",
                "-- KẾT QUẢ: Chỉ 1 transaction thành công nếu stock = 1"
            ];
            
            $stock = $this->db->fetchOne(
                "SELECT product_id, qty FROM branch_inventory WHERE product_id = ?",
                [$productId]
            );
            
            echo json_encode([
                'success' => true,
                'product_id' => $productId,
                'current_stock' => (int)($stock['qty'] ?? 0),
                'sql_executed' => $sqlExecuted,
                'explanation' => "✅ ISOLATION: `SELECT ... FOR UPDATE` khóa dòng stock. Transaction thứ 2 phải CHỜ transaction thứ 1 xong. Ngăn race condition/oversell!"
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
     * POST /acid-demo/reset-data
     * Reset test data
     */
    public function resetData() {
        header('Content-Type: application/json');
        
        try {
            // Reset inventory
            $this->db->execute(
                "INSERT INTO branch_inventory(product_id, qty) VALUES (1, 10), (15, 5), (999, 1)
                 ON DUPLICATE KEY UPDATE qty = VALUES(qty)"
            );
            
            // Clear demo orders
            $this->db->execute(
                "DELETE FROM order_item WHERE order_id IN 
                 (SELECT id FROM orders WHERE order_code LIKE 'DEMO-%')"
            );
            $this->db->execute("DELETE FROM orders WHERE order_code LIKE 'DEMO-%'");
            $this->db->execute("DELETE FROM outbox_events WHERE payload_json LIKE '%DEMO-%'");
            
            echo json_encode([
                'success' => true,
                'message' => 'Reset data thành công',
                'inventory_reset' => [
                    'product_id_1' => 10,
                    'product_id_15' => 5,
                    'product_id_999' => 1
                ],
                'orders_cleared' => 'All DEMO-* orders'
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
     * GET /acid-demo/stats
     * Get current statistics
     */
    public function stats() {
        header('Content-Type: application/json');
        echo json_encode($this->getStats());
    }
    
    /**
     * Helper: Get statistics
     */
    private function getStats() {
        try {
            $inventory = $this->db->fetchAll(
                "SELECT bi.product_id, COALESCE(pr.name, CONCAT('Product #', bi.product_id)) as product_name, bi.qty, bi.updated_at
                 FROM branch_inventory bi
                 LEFT JOIN products_replica pr ON pr.id = bi.product_id
                 ORDER BY bi.product_id
                 LIMIT 10"
            );
            
            $recentOrders = $this->db->fetchAll(
                "SELECT order_code, status, total, created_at
                 FROM orders
                 WHERE order_code LIKE 'DEMO-%'
                 ORDER BY created_at DESC
                 LIMIT 5"
            );
            
            $outboxPending = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM outbox_events WHERE status = 'PENDING'"
            );
            
            return [
                'inventory' => $inventory,
                'recent_orders' => $recentOrders,
                'outbox_pending' => (int)($outboxPending['count'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("Failed to get stats: " . $e->getMessage());
            return [
                'inventory' => [],
                'recent_orders' => [],
                'outbox_pending' => 0
            ];
        }
    }
}

