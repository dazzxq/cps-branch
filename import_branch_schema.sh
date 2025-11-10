#!/bin/bash
# ============================================
# Import branch schema with dynamic BRANCH code
# Usage: 
#   ./import_branch_schema.sh [BRANCH_CODE]
# 
# Example:
#   ./import_branch_schema.sh HN
#   ./import_branch_schema.sh SG
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

DB_NAME="chillphones_branch_${BRANCH}"
MYSQL_USER="cps_admin"
MYSQL_PASS="123456789"
MYSQL_HOST="localhost"
SCHEMA_TEMPLATE="chillphones_branch_template.sql"

echo "============================================"
echo "Branch Database Schema Import Script"
echo "============================================"
echo "Branch Code: $BRANCH"
echo "Database: $DB_NAME"
echo "MySQL User: $MYSQL_USER"
echo "Template: $SCHEMA_TEMPLATE"
echo "============================================"
echo "Default Admin Account:"
echo "  Email: cps_admin@duyet.dev"
echo "  Password: admin123"
echo "  Role: ADMIN"
echo "============================================"
echo ""

# Check if template file exists
if [ ! -f "$SCHEMA_TEMPLATE" ]; then
    echo "❌ ERROR: Template file '$SCHEMA_TEMPLATE' not found!"
    echo "   Please make sure the file exists in the current directory."
    exit 1
fi

# Confirm before proceeding
read -p "⚠️  This will CREATE/REPLACE database '$DB_NAME'. Continue? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Import cancelled."
    exit 0
fi

echo ""
echo "➡️  Replacing placeholder {{BRANCH}} with $BRANCH..."
echo "➡️  Importing schema to database: $DB_NAME..."
echo ""

# Replace {{BRANCH}} placeholder with actual branch code and pipe to mysql
sed "s/{{BRANCH}}/${BRANCH}/g" "$SCHEMA_TEMPLATE" | mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" 2>&1

# Check if import was successful
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ SUCCESS: Schema imported successfully for database '$DB_NAME'"
    echo ""
    echo "📋 Summary:"
    echo "  ✅ Database: $DB_NAME created"
    echo "  ✅ Tables: Created with branch code '$BRANCH'"
    echo "  ✅ Stored Procedures: sp_create_order, sp_set_stock"
    echo "  ✅ Triggers: Configured for branch '$BRANCH'"
    echo "  ✅ Views: Configured for branch '$BRANCH'"
    echo "  ✅ Admin Account: cps_admin@duyet.dev (password: admin123)"
    echo ""
    echo "Next steps:"
    echo "  1. Verify database: mysql -u $MYSQL_USER -p123456789 -e 'SHOW TABLES FROM $DB_NAME;'"
    echo "  2. Verify procedures: mysql -u $MYSQL_USER -p123456789 -e 'SHOW PROCEDURE STATUS WHERE Db=\"$DB_NAME\";'"
    echo "  3. Generate config: ./gen_branch_config.sh $BRANCH"
    echo "  4. Test login: http://localhost/login (cps_admin@duyet.dev / admin123)"
    echo ""
else
    echo ""
    echo "❌ ERROR: Schema import failed for database '$DB_NAME'"
    echo "   Check MySQL credentials and permissions."
    echo ""
    exit 1
fi

