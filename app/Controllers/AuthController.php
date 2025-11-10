<?php
require_once __DIR__ . '/../Models/EmployeeReplica.php';

class AuthController {
    private array $env; private EmployeeReplica $model;
    public function __construct(){
        // Load from config.php instead of .env
        $this->env = require __DIR__ . '/../../config.php';
        $this->model = new EmployeeReplica($this->env);
        if (session_status() === PHP_SESSION_NONE) session_start();
    }
    public function showLogin(){ require __DIR__ . '/../Views/login.php'; }
    public function login(){
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){ header('Location: /login'); exit; }
        $u = $_POST['username'] ?? ''; $p = $_POST['password'] ?? '';
        $user = $this->model->findByUsername($u);
        if (!$user || !password_verify($p, $user['password_hash'])){
            $_SESSION['flash_error'] = 'Sai tài khoản hoặc mật khẩu'; header('Location:/login'); exit;
        }
        // Store user info in session (use 'name' and 'email' from employee_replica table)
        $_SESSION['user_id'] = $user['id']; 
        $_SESSION['user_name'] = $user['name']; 
        $_SESSION['user_email'] = $user['email']; 
        $_SESSION['role'] = $user['role'];
        
        // Debug: Check what was stored
        error_log("Login success - Stored in session: user_name=" . ($user['name'] ?? 'NULL') . ", email=" . ($user['email'] ?? 'NULL'));
        
        header('Location: /');
    }
    public function logout(){ session_destroy(); header('Location:/login'); }
}
