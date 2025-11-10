<?php
error_reporting(E_ALL); ini_set('display_errors',1);

// Load config from config.php
$config = require __DIR__ . '/../config.php';
if (!empty($config['APP_TIMEZONE'])) date_default_timezone_set($config['APP_TIMEZONE']);

$router = require __DIR__ . '/../routes.php';
$router->run();
?>