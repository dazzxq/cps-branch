<?php
require_once __DIR__ . '/../Models/ProductReplica.php';
require_once __DIR__ . '/../Models/EmployeeReplica.php';
require_once __DIR__ . '/../Models/DB.php';

class BranchUIController {
    private array $env; private ProductReplica $products; private EmployeeReplica $employees; private DB $db;
    public function __construct(){ 
        // Load from config.php instead of .env
        $this->env = require __DIR__ . '/../../config.php';
        $this->products=new ProductReplica($this->env); 
        $this->employees=new EmployeeReplica($this->env); 
        $this->db=DB::get($this->env); 
        if(session_status()===PHP_SESSION_NONE) session_start(); 
    }
    private function requireAuth(){ if(!isset($_SESSION['user_id'])){ header('Location:/login'); exit; } }
    public function products(){
        $this->requireAuth();
        $list = $this->products->getCatalog(200,0);
        $env = $this->env;
        $viewFile = __DIR__ . '/../Views/admin/products.php';
        require __DIR__ . '/../Views/admin/layout.php';
    }
    public function orders(){
        $this->requireAuth();
        $orders = $this->db->fetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT 200");
        $env = $this->env;
        $viewFile = __DIR__ . '/../Views/admin/orders.php';
        require __DIR__ . '/../Views/admin/layout.php';
    }
    public function employees(){
        $this->requireAuth();
        // Sort newest to oldest by updated_at (employee_replica has no created_at)
        $emps = $this->db->fetchAll("SELECT id,name,email,role,branch_code,enabled,updated_at FROM employee_replica ORDER BY id DESC");
        $env = $this->env;
        $viewFile = __DIR__ . '/../Views/admin/employees.php';
        require __DIR__ . '/../Views/admin/layout.php';
    }
    public function syncProducts(){
        $this->requireAuth(); header('Content-Type: application/json');
        try {
            // pull from Central and upsert locally
            require_once __DIR__ . '/SyncController.php';
            $sync = new SyncController();
            // Call internal method to reuse logic
            // Hack: capture output
            ob_start();
            $sync->pullProducts();
            $out = ob_get_clean();
            echo $out ?: json_encode(['success'=>true]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }
    public function syncEmployees(){
        $this->requireAuth(); header('Content-Type: application/json');
        try {
            require_once __DIR__ . '/SyncController.php';
            $sync = new SyncController();
            ob_start();
            $sync->pullEmployees();
            $out = ob_get_clean();
            echo $out ?: json_encode(['success'=>true]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
    }

    // POST /products/{id}/override  JSON: {price, promo_price, starts_at, ends_at}
    public function updatePriceOverride($id){
        $this->requireAuth(); header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) throw new Exception('Invalid JSON');
            $price = isset($input['price']) && $input['price'] !== '' ? (int)$input['price'] : null;
            $promo = isset($input['promo_price']) && $input['promo_price'] !== '' ? (int)$input['promo_price'] : null;
            $starts = !empty($input['starts_at']) ? $input['starts_at'] : null;
            $ends   = !empty($input['ends_at']) ? $input['ends_at'] : null;
            // Upsert into branch_price_override (using branch_code from config)
            $branchCode = $this->env['APP_BRANCH_CODE'];
            $sql = "INSERT INTO branch_price_override (product_id, branch_code, price, promo_price, starts_at, ends_at) VALUES (?,?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE price=VALUES(price), promo_price=VALUES(promo_price), starts_at=VALUES(starts_at), ends_at=VALUES(ends_at)";
            $this->db->execute($sql, [$id, $branchCode, $price, $promo, $starts, $ends]);
            echo json_encode(['success'=>true]);
        } catch(Exception $e){ http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    }

    // POST /products/{id}/stock  JSON: {qty, reserved}
    public function updateStock($id){
        $this->requireAuth(); header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) throw new Exception('Invalid JSON');
            $qty = isset($input['qty']) ? (int)$input['qty'] : 0;
            $reserved = isset($input['reserved']) ? (int)$input['reserved'] : 0;
            $branchCode = $this->env['APP_BRANCH_CODE'];
            $sql = "INSERT INTO inventory (product_id, branch_code, qty, reserved) VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE qty=VALUES(qty), reserved=VALUES(reserved)";
            $this->db->execute($sql, [$id, $branchCode, $qty, $reserved]);
            echo json_encode(['success'=>true]);
        } catch(Exception $e){ http_response_code(400); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    }
}
