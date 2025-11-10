<?php

/**
 * Branch HN Configuration
 * Hardcoded config - not using .env anymore
 */

return [
    // ============ App =============
    'APP_NAME' => 'Chillphones ',
    'APP_BRANCH_CODE' => '',
    'APP_ENV' => 'production',
    'APP_URL' => 'http://localhost:9001',
    'APP_TIMEZONE' => 'Asia/Ho_Chi_Minh',
    
    // ============ Database ============
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'chillphones_branch_',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
    
    // ============ Central API ============
    'CENTRAL_API_URL' => 'https://cps.duyet.dev/api',
    'CENTRAL_API_KEY' => '',
    
    // ============ Logs ============
    'LOG_CHANNEL' => 'stack',
    'LOG_LEVEL' => 'info',
];

