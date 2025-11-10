<?php
require_once __DIR__ . '/DB.php';
class EmployeeReplica {
    private DB $db; private string $branchCode;
    public function __construct(array $env){$this->db=DB::get($env); $this->branchCode = strtoupper($env['APP_BRANCH_CODE'] ?? 'HN');} 
    public function upsertMany(array $employees){
        // Check if we need to update password or not
        $sqlWithPassword = "INSERT INTO employee_replica (id, name, email, password_hash, role, branch_code, enabled, updated_at)
                VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), password_hash=VALUES(password_hash), role=VALUES(role), branch_code=VALUES(branch_code), enabled=VALUES(enabled), updated_at=VALUES(updated_at)";
        
        $sqlWithoutPassword = "INSERT INTO employee_replica (id, name, email, password_hash, role, branch_code, enabled, updated_at)
                VALUES (?,?,?,COALESCE(VALUES(password_hash), ''),?,?,?,?)
                ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), role=VALUES(role), branch_code=VALUES(branch_code), enabled=VALUES(enabled), updated_at=VALUES(updated_at)";
        
        $pdo=$this->db->pdo();
        
        foreach($employees as $e){
            if (!empty($e['branch_code']) && strtoupper($e['branch_code']) !== $this->branchCode) { continue; }
            
            // Use appropriate SQL based on whether password is provided
            $hasPassword = !empty($e['password_hash']);
            $sql = $hasPassword ? $sqlWithPassword : $sqlWithoutPassword;
            $st = $pdo->prepare($sql);
            
            $st->execute([
                $e['id'], 
                $e['name']??'', 
                $e['email']??'', 
                $e['password_hash']??'',
                $e['role']??'STAFF', 
                $this->branchCode, 
                isset($e['enabled'])?(int)$e['enabled']:1, 
                $e['updated_at']??date('Y-m-d H:i:s')
            ]);
        }
    }
    public function findByUsername(string $u){ return $this->db->fetchOne("SELECT * FROM employee_replica WHERE email=? AND enabled=1",[$u]); }
}
