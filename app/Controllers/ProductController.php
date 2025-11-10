<?php
require_once __DIR__ . '/../Models/ProductReplica.php';

class ProductController {
    private array $env; private ProductReplica $model;
    public function __construct(){
        // Load from config.php instead of .env
        $this->env = require __DIR__ . '/../../config.php';
        $this->model = new ProductReplica($this->env);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }
    private function requireAuth(){ if (!isset($_SESSION['user_id'])) { header('Location:/login'); exit; } }
    public function index(){ $this->requireAuth(); $products=$this->model->getCatalog(100,0); $env=$this->env; require __DIR__ . '/../Views/catalog.php'; }
}
