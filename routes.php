<?php
require_once __DIR__ . '/app/Router.php';

$router = new Router();

// Auth
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// FE
$router->get('/', 'BranchUIController@products');
$router->get('/products', 'BranchUIController@products');
$router->get('/orders', 'BranchUIController@orders');
$router->get('/employees', 'BranchUIController@employees');
$router->post('/sync/products', 'BranchUIController@syncProducts');
$router->post('/sync/employees', 'BranchUIController@syncEmployees');
$router->post('/products/{id}/override', 'BranchUIController@updatePriceOverride');
$router->post('/products/{id}/stock', 'BranchUIController@updateStock');

// API
$router->get('/api/ping', 'ApiController@ping');
$router->get('/api/catalog', 'ApiController@catalog');
$router->post('/api/upsert/employees', 'ApiController@upsertEmployees');
$router->post('/api/upsert/products', 'ApiController@upsertProducts');
$router->post('/api/set-stock', 'ApiController@setStock');
$router->post('/api/delete/product/{id}', 'ApiController@deleteProduct');
$router->post('/api/delete/employee/{id}', 'ApiController@deleteEmployee');

// ACID Demo UI
$router->get('/acid-demo', 'AcidDemoController@index');
$router->get('/acid-demo/products', 'AcidDemoController@getProductsList');
$router->post('/acid-demo/test-atomicity', 'AcidDemoController@testAtomicity');
$router->post('/acid-demo/test-isolation', 'AcidDemoController@testIsolation');
$router->post('/acid-demo/reset-data', 'AcidDemoController@resetData');
$router->get('/acid-demo/stats', 'AcidDemoController@stats');

// Outbox Sync
$router->post('/outbox/sync', 'OutboxController@sync');
$router->get('/outbox/status', 'OutboxController@status');

// Orders API (from Storefront - no auth required)
$router->post('/api/orders/create', 'OrderController@create');
$router->post('/api/orders/create-sp', 'OrderController@createWithStoredProcedure'); // ACID Demo version
$router->get('/api/orders/{order_code}', 'OrderController@getByCode');

// Pricing API
$router->get('/api/price/{product_id}', 'ApiController@getPriceByProduct');

// Stock API (public - no auth, for Storefront)
$router->get('/api/stock/{product_id}', 'ApiController@getStockByProduct');

// Internal sync pull from Central (manual)
$router->post('/sync/pull/employees', 'SyncController@pullEmployees');
$router->post('/sync/pull/products', 'SyncController@pullProducts');

return $router;
