<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Models/DB.php';

class SyncController {
    private array $env; private DB $db;
    public function __construct(){ 
        $this->env = require __DIR__ . '/../../config.php';
        $this->db = DB::get($this->env); 
        if(session_status() === PHP_SESSION_NONE) session_start(); 
    }
    private function requireAuth(){ if(!isset($_SESSION['user_id'])){ header('Content-Type: application/json'); http_response_code(401); echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; } }
    private function requestCentral(string $path){
        // Load config values from $this->env (already loaded from config.php in constructor)
        $base = $this->env['CENTRAL_API_URL'];
        $apiKey = $this->env['CENTRAL_API_KEY'];
        $branchCode = $this->env['APP_BRANCH_CODE'];
        
        // Normalize base and path
        $base = rtrim($base, '/');
        $path = '/' . ltrim($path, '/');
        // Append branch code as query for Central
        $url = $base . $path . (strpos($path,'?')!==false ? '&' : '?') . 'branch=' . urlencode($branchCode);
        
        // Prepare headers
        $headers = [
            'X-API-Key: ' . $apiKey,
            'X-Branch-Code: ' . $branchCode
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Enhanced error with full response body for debugging
        if ($res === false || $code >= 400) {
            $errorMsg = 'Central error: '.$code.' | cURL error: '.curl_error($ch).' | Response body: '.$res;
            throw new Exception($errorMsg);
        }
        return json_decode($res, true);
    }
    public function pullEmployees(){ 
        $this->requireAuth(); 
        header('Content-Type: application/json'); 
        try{ 
            $json=$this->requestCentral('/employees'); 
            if(!($json['success']??false)) throw new Exception('Central returned error'); 
            $payload=$json['data']??[]; 
            require_once __DIR__ . '/../Models/EmployeeReplica.php'; 
            $m=new EmployeeReplica($this->env); 
            $m->upsertMany($payload);
            echo json_encode(['success'=>true,'message'=>'Pulled employees: '.count($payload)]);
        } catch(Exception $e){ 
            http_response_code(500); 
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]); 
        } 
    }
    
    public function pullProducts(){ 
        $this->requireAuth(); 
        header('Content-Type: application/json'); 
        
        try{ 
            $json=$this->requestCentral('/products'); 
            if(!($json['success']??false)) throw new Exception('Central returned error'); 
            $payload=$json['data']??[]; 
            
            // DEBUG: Log first 3 products để check stock field
            $debugSample = array_slice($payload, 0, 3);
            $debugInfo = [
                'branch_code' => $this->env['APP_BRANCH_CODE'],
                'total_products' => count($payload),
                'sample_products' => array_map(function($p) {
                    return [
                        'id' => $p['id'] ?? null,
                        'name' => $p['name'] ?? null,
                        'has_stock_field' => isset($p['stock']),
                        'stock_value' => $p['stock'] ?? 'FIELD_NOT_EXIST'
                    ];
                }, $debugSample)
            ];
            error_log("🔍 SYNC DEBUG: " . json_encode($debugInfo, JSON_PRETTY_PRINT));
            
            require_once __DIR__ . '/../Models/ProductReplica.php'; 
            $m=new ProductReplica($this->env); 
            $m->upsertMany($payload);
            
            // Update branch_inventory with stock from Central
            $stockUpdated = 0;
            $stockMissing = 0;
            foreach($payload as $product) {
                if (isset($product['id']) && isset($product['stock'])) {
                    $m->setBranchStock((int)$product['id'], (int)$product['stock']);
                    $stockUpdated++;
                    
                    // DEBUG: Log first 3 stock updates
                    if ($stockUpdated <= 3) {
                        error_log("🔍 Stock Update: product_id={$product['id']}, stock={$product['stock']}");
                    }
                } else {
                    $stockMissing++;
                }
            }
            
            error_log("🔍 Stock Summary: Updated={$stockUpdated}, Missing={$stockMissing}");
            
            echo json_encode([
                'success'=>true,
                'message'=>'Pulled products: '.count($payload),
                'debug' => [
                    'stock_updated' => $stockUpdated,
                    'stock_missing' => $stockMissing,
                    'sample' => $debugSample
                ]
            ]);
        } catch(Exception $e){ 
            http_response_code(500); 
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]); 
        } 
    }
}


