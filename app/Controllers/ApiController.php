<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Models/ProductReplica.php';
require_once __DIR__ . '/../Models/EmployeeReplica.php';
require_once __DIR__ . '/../Models/DB.php';

class ApiController {
    private array $env;
    public function __construct() {
        $this->env = require __DIR__ . '/../../config.php';
        date_default_timezone_set($this->env['APP_TIMEZONE'] ?? 'Asia/Ho_Chi_Minh');
        header('Content-Type: application/json');
    }
    
    private function verifyApiKey() {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        
        // Fallback to $_SERVER for nginx
        if (empty($apiKey)) {
            $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        }
        
        // For now, just check it matches our config
        $expectedKey = $this->env['CENTRAL_API_KEY'] ?? '';
        if ($apiKey !== $expectedKey) {
            http_response_code(403);
            echo json_encode(['success'=>false,'error'=>'Forbidden']);
            exit();
        }
    }

    private function ok($data) { echo json_encode(['success'=>true,'data'=>$data]); }

    public function ping() { $this->ok(['branch'=> $this->env['APP_BRANCH_CODE'] ?? 'UNKNOWN', 'time'=>date('c')]); }

    public function catalog() {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $model = new ProductReplica($this->env);
        $this->ok($model->getCatalog($limit,$offset));
    }
    
    /**
     * POST /api/upsert/products
     * Receive products from Central and upsert into local replica
     */
    public function upsertProducts() {
        $this->verifyApiKey();
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
            exit();
        }
        
        try {
            $model = new ProductReplica($this->env);
            $model->upsertMany($input);
            
            // Update branch_inventory with stock from Central
            foreach($input as $product) {
                if (isset($product['id']) && isset($product['stock'])) {
                    $model->setBranchStock((int)$product['id'], (int)$product['stock']);
                }
            }
            
            echo json_encode(['success'=>true,'message'=>'Upserted '.count($input).' products']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }
    
    /**
     * POST /api/upsert/employees
     * Receive employees from Central and upsert into local replica
     */
    public function upsertEmployees() {
        $this->verifyApiKey();
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            http_response_code(400);
            echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
            exit();
        }
        
        try {
            $model = new EmployeeReplica($this->env);
            $model->upsertMany($input);
            echo json_encode(['success'=>true,'message'=>'Upserted '.count($input).' employees']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }
    
    /**
     * DELETE /api/delete/product/{id}
     * Delete a product from local replica
     */
    public function deleteProduct($id) {
        $this->verifyApiKey();
        
        try {
            $db = DB::get($this->env);
            // Delete from products_replica
            $db->execute("DELETE FROM products_replica WHERE id = ?", [(int)$id]);
            // Delete from branch_inventory
            $db->execute("DELETE FROM branch_inventory WHERE product_id = ?", [(int)$id]);
            
            echo json_encode(['success'=>true,'message'=>'Product deleted']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }
    
    /**
     * DELETE /api/delete/employee/{id}
     * Delete an employee from local replica
     */
    public function deleteEmployee($id) {
        $this->verifyApiKey();
        
        try {
            require_once __DIR__ . '/../Models/EmployeeReplica.php';
            $db = DB::get($this->env);
            // Correct table name is employee_replica (singular)
            $db->execute("DELETE FROM employee_replica WHERE id = ?", [(int)$id]);
            
            echo json_encode(['success'=>true,'message'=>'Employee deleted']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    /**
     * GET /api/price/{product_id}
     * Trả về effective price cho 1 sản phẩm tại chi nhánh hiện tại
     */
    public function getPriceByProduct($productId) {
        try {
            $pid = (int)$productId;
            if ($pid <= 0) {
                http_response_code(400);
                echo json_encode(['success'=>false,'message'=>'Product ID required']);
                return;
            }

            $db = DB::get($this->env);
            $branch = $this->env['APP_BRANCH_CODE'] ?? 'UNKNOWN';
            $row = $db->fetchOne(
                "SELECT pr.id AS product_id,
                        pr.price AS central_price,
                        pr.promo_price AS central_promo_price,
                        bpo.price AS override_price,
                        bpo.promo_price AS override_promo_price,
                        CASE
                          WHEN bpo.product_id IS NOT NULL
                               AND (bpo.starts_at IS NULL OR bpo.starts_at <= NOW())
                               AND (bpo.ends_at IS NULL OR bpo.ends_at >= NOW())
                            THEN COALESCE(bpo.promo_price, bpo.price)
                          ELSE COALESCE(pr.promo_price, pr.price)
                        END AS effective_price
                 FROM products_replica pr
                 LEFT JOIN branch_price_override bpo ON bpo.product_id = pr.id AND bpo.branch_code = ?
                 WHERE pr.id = ?",
                [$branch, $pid]
            );

            if (!$row) {
                http_response_code(404);
                echo json_encode(['success'=>false,'message'=>'Product not found']);
                return;
            }

            echo json_encode([
                'success' => true,
                'product_id' => $pid,
                'branch_code' => $branch,
                'effective_price' => (int)$row['effective_price'],
                'central_price' => (int)$row['central_price'],
                'central_promo_price' => isset($row['central_promo_price']) ? (int)$row['central_promo_price'] : null,
                'override_price' => isset($row['override_price']) ? (int)$row['override_price'] : null,
                'override_promo_price' => isset($row['override_promo_price']) ? (int)$row['override_promo_price'] : null
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    /**
     * GET /api/stock/{product_id}
     * Trả về stock thực tế từ branch_inventory (source of truth)
     * No API key required - public endpoint for Storefront
     */
    public function getStockByProduct($productId) {
        try {
            $pid = (int)$productId;
            if ($pid <= 0) {
                http_response_code(400);
                echo json_encode(['success'=>false,'message'=>'Product ID required']);
                return;
            }

            $db = DB::get($this->env);
            $branch = $this->env['APP_BRANCH_CODE'] ?? 'UNKNOWN';
            
            // Query from branch_inventory - this is the real-time stock after order deductions
            $row = $db->fetchOne(
                "SELECT product_id, qty, updated_at 
                 FROM branch_inventory 
                 WHERE product_id = ?",
                [$pid]
            );

            if (!$row) {
                // Product exists but not in inventory = 0 stock
                echo json_encode([
                    'success' => true,
                    'product_id' => $pid,
                    'branch_code' => $branch,
                    'stock' => 0,
                    'updated_at' => null
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'product_id' => $pid,
                'branch_code' => $branch,
                'stock' => (int)$row['qty'],
                'updated_at' => $row['updated_at']
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
    }
}
