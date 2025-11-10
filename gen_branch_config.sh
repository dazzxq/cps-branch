#!/bin/bash
# ============================================
# Generate config.php with dynamic BRANCH code
# Usage: 
#   ./gen_branch_config.sh [BRANCH_CODE] [API_KEY]
# 
# Example:
#   ./gen_branch_config.sh HN dqIwQOnB9nlijX4avTx4z78jFhMCQVeCcv2z9Edu
#   ./gen_branch_config.sh SG abc123xyz456
#
# If no BRANCH_CODE provided, auto-detect from current directory name
# /var/www/hn -> HN
# /var/www/sg -> SG
# ============================================

# Get branch code from argument or auto-detect from directory name
if [ -n "$1" ]; then
    BRANCH=$(echo "$1" | tr '[:lower:]' '[:upper:]')
else
    BRANCH=$(basename "$PWD" | tr '[:lower:]' '[:upper:]')
fi

# Get API key from argument or use default
if [ -n "$2" ]; then
    API_KEY="$2"
else
    API_KEY="dqIwQOnB9nlijX4avTx4z78jFhMCQVeCcv2z9Edu"
fi

echo "============================================"
echo "Branch Config Generator"
echo "============================================"
echo "Branch Code: $BRANCH"
echo "Database: chillphones_branch_$BRANCH"
echo "API Key: ${API_KEY:0:10}..."
echo "============================================"
echo ""

# Generate config.php
cat > config.php <<EOF
<?php

/**
 * Branch $BRANCH Configuration
 * Auto-generated config - customize as needed
 */

return [
    // ============ App =============
    'APP_NAME' => 'Chillphones $BRANCH',
    'APP_BRANCH_CODE' => '$BRANCH',
    'APP_ENV' => 'production',
    'APP_URL' => 'http://localhost:9001',
    'APP_TIMEZONE' => 'Asia/Ho_Chi_Minh',
    
    // ============ Database ============
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'chillphones_branch_$BRANCH',
    'DB_USERNAME' => 'cps_admin',
    'DB_PASSWORD' => '123456789',
    
    // ============ Central API ============
    'CENTRAL_API_URL' => 'https://cps.duyet.dev/api',
    'CENTRAL_API_KEY' => '$API_KEY',
    
    // ============ Logs ============
    'LOG_CHANNEL' => 'stack',
    'LOG_LEVEL' => 'info',
];

EOF

if [ $? -eq 0 ]; then
    echo "✅ SUCCESS: config.php generated for branch $BRANCH"
    echo ""
    echo "Next steps:"
    echo "  1. Review and customize config.php if needed"
    echo "  2. Update DB_PASSWORD if using different credentials"
    echo "  3. Update APP_URL with the correct domain"
    echo "  4. Verify CENTRAL_API_KEY is correct for this branch"
    echo ""
else
    echo "❌ ERROR: Failed to generate config.php"
    exit 1
fi

