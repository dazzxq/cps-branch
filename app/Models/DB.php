<?php
require_once __DIR__ . '/../Helpers/env.php';
class DB {
    private static ?DB $inst=null; 
    private PDO $pdo;
    
    private function __construct(array $env = []){
        // Load config from config.php if not provided
        if (empty($env)) {
            $env = require __DIR__ . '/../../config.php';
        }
        
        // Use credentials from config
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3306';
        $dbname = $env['DB_DATABASE'] ?? 'chillphones_branch_HN';
        $user = $env['DB_USERNAME'] ?? 'root';
        $pass = $env['DB_PASSWORD'] ?? '';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
        try {
            $this->pdo=new PDO($dsn,$user,$pass,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false
            ]);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function get(array $env):DB{ 
        if(!self::$inst) self::$inst=new DB($env); 
        return self::$inst; 
    }
    
    public static function getInstance():DB{ 
        if(!self::$inst) self::$inst=new DB([]); 
        return self::$inst; 
    }
    public function pdo():PDO{return $this->pdo;}
    public function query($sql,$p=[]){$st=$this->pdo->prepare($sql);$st->execute($p);return $st;}
    public function fetchAll($sql,$p=[]){return $this->query($sql,$p)->fetchAll();}
    public function fetchOne($sql,$p=[]){return $this->query($sql,$p)->fetch();}
    public function execute($sql,$p=[]){return $this->query($sql,$p)->rowCount();}
    public function insert($sql,$p=[]){$this->query($sql,$p);return $this->pdo->lastInsertId();}
    public function beginTransaction(){return $this->pdo->beginTransaction();}
    public function commit(){return $this->pdo->commit();}
    public function rollback(){return $this->pdo->rollBack();}
    
    // Expose PDO instance for stored procedures
    public function getPdo(){return $this->pdo;}
}
