<?php
require_once __DIR__ . '/DB.php';
class ProductReplica {
    private DB $db; public function __construct(array $env){$this->db=DB::get($env);} 
    public function getCatalog(int $limit=100,int $offset=0){
        // Join with branch_inventory and branch_price_override
        // Priority: branch_price_override > products_replica
        $sql = "SELECT 
                    pr.id as product_id,
                    pr.*,
                    COALESCE(bi.qty, 0) as qty,
                    COALESCE(bi.qty, 0) as stock,
                    0 as reserved,
                    -- Use override price if exists, otherwise use replica price
                    COALESCE(bpo.price, pr.price) as price,
                    COALESCE(bpo.promo_price, pr.promo_price) as promo_price,
                    -- Effective price: promo_price > price (after override applied)
                    COALESCE(
                        COALESCE(bpo.promo_price, pr.promo_price), 
                        COALESCE(bpo.price, pr.price)
                    ) as effective_price
                FROM products_replica pr
                LEFT JOIN branch_inventory bi ON bi.product_id = pr.id
                LEFT JOIN branch_price_override bpo ON bpo.product_id = pr.id
                WHERE pr.status = 'ACTIVE'
                ORDER BY pr.name ASC 
                LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$limit, $offset]);
    }
    public function upsertMany(array $products){
        try {
            // Set session variable to bypass trigger if exists
            $this->db->execute("SET @central_sync = 1");
            
            // Use ON DUPLICATE KEY UPDATE to update existing products
            $sql = "INSERT INTO products_replica (id, sku, name, brand_name, price, promo_price, status, msrp, ext_json, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE 
                        sku = VALUES(sku),
                        name = VALUES(name),
                        brand_name = VALUES(brand_name),
                        price = VALUES(price),
                        promo_price = VALUES(promo_price),
                        status = VALUES(status),
                        msrp = VALUES(msrp),
                        ext_json = VALUES(ext_json),
                        updated_at = VALUES(updated_at)";
            $st = $this->db->pdo()->prepare($sql);
            
            foreach($products as $p){
                try {
                    $st->execute([
                        $p['id'], $p['sku']??'', $p['name']??'', $p['brand_name']??'',
                        isset($p['price'])?(int)$p['price']:0, $p['promo_price']??null, $p['status']??'ACTIVE', $p['msrp']??null,
                        isset($p['ext_json']) ? json_encode($p['ext_json']) : null,
                        $p['updated_at']??date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $ex) {
                    // Log individual product error but continue with others
                    error_log("Failed to upsert product {$p['id']}: " . $ex->getMessage());
                    throw $ex; // Re-throw to see in API response
                }
            }
            
            // Unset session variable
            $this->db->execute("SET @central_sync = NULL");
        } catch (Exception $e) {
            // Always clean up session variable
            try { $this->db->execute("SET @central_sync = NULL"); } catch (Exception $ignored) {}
            throw $e;
        }
    }
    public function setBranchStock(int $productId, int $qty){
        $this->db->execute(
            "INSERT INTO branch_inventory (product_id, qty) VALUES (?,?) ON DUPLICATE KEY UPDATE qty=VALUES(qty)",
            [$productId, $qty]
        );
    }
}
