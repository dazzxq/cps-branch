#!/bin/bash
BRANCH=$(basename "$PWD" | tr '[:lower:]' '[:upper:]')
cat > config.php <<EOF
<?php
return [
    'APP_NAME' => 'Chillphones $BRANCH',
    'APP_BRANCH_CODE' => '$BRANCH',
    'APP_ENV' => 'production',
    'APP_URL' => 'http://localhost:9001',
    'APP_TIMEZONE' => 'Asia/Ho_Chi_Minh',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'chillphones_branch_$BRANCH',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '123456789',
    'CENTRAL_API_URL' => 'https://cps.duyet.dev/api',
    'CENTRAL_API_KEY' => 'dqIwQOnB9nlijX4avTx4z78jFhMCQVeCcv2z9Edu',
    'LOG_CHANNEL' => 'stack',
    'LOG_LEVEL' => 'info',
];
EOF
echo "✅ config.php generated for branch $BRANCH"