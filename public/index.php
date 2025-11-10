<?php
error_reporting(E_ALL); ini_set('display_errors',1);
require_once __DIR__ . '/../app/Helpers/env.php';
$env = loadEnvIntoGlobals(__DIR__ . '/../.env'); if(!$env) $env = loadEnvIntoGlobals(__DIR__ . '/../ENV_EXAMPLE.txt');
if (!empty($env['APP_TIMEZONE'])) date_default_timezone_set($env['APP_TIMEZONE']);
$router = require __DIR__ . '/../routes.php';
$router->run();
?>