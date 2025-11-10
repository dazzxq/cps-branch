# 🏢 Branch Setup Guide - Chillphones Multi-Branch System

Hướng dẫn deploy chi nhánh mới cho hệ thống Chillphones sử dụng **template động**.

---

## 📋 Tổng quan

Hệ thống được thiết kế để dễ dàng triển khai cho nhiều chi nhánh (HN, SG, DN, CT, HP, ...) chỉ bằng cách:
1. **Copy template files**
2. **Chạy 2 scripts** để tự động generate config và import database
3. **Done!** ✨

**Không cần sửa code tay nữa!**

---

## 🗂️ Template Files

Trong thư mục `cps/` bạn có các file template:

```
cps/
├── chillphones_branch_template.sql    # SQL template với placeholder ___BRANCH__
├── import_branch_schema.sh            # Script tự động import DB cho branch
├── gen_branch_config.sh               # Script tự động tạo config.php cho branch
└── BRANCH_SETUP_GUIDE.md             # File này
```

---

## 🚀 Hướng dẫn Setup Chi Nhánh Mới

### Bước 1: Chuẩn bị thư mục cho chi nhánh

Giả sử bạn muốn setup chi nhánh **SG** (Singapore):

```bash
# Trên VPS
cd /var/www
mkdir sg-cps
cd sg-cps

# Clone hoặc copy code từ template branch
# Có thể copy từ hn-cps hoặc pull từ Git
cp -r /path/to/hn-cps/* .

# Copy các template files từ thư mục cps
cp /path/to/cps/chillphones_branch_template.sql .
cp /path/to/cps/import_branch_schema.sh .
cp /path/to/cps/gen_branch_config.sh .
```

---

### Bước 2: Import Database Schema

Chạy script import với branch code **SG**:

```bash
cd /var/www/sg-cps
./import_branch_schema.sh SG
```

**Hoặc** nếu đặt tên thư mục đúng với mã chi nhánh (vd: `/var/www/sg`), script sẽ tự detect:

```bash
cd /var/www/sg
./import_branch_schema.sh    # Tự động detect BRANCH=SG từ tên thư mục
```

Script sẽ:
- ✅ Thay thế `___BRANCH__` → `SG` trong SQL
- ✅ Tạo database: `chillphones_branch_SG`
- ✅ Grant quyền cho user `cps_admin`
- ✅ Import tất cả tables, triggers, views với branch code đúng

**Output:**
```
============================================
Branch Database Schema Import Script
============================================
Branch Code: SG
Database: chillphones_branch_SG
Template: chillphones_branch_template.sql
============================================

⚠️  This will CREATE/REPLACE database 'chillphones_branch_SG'. Continue? (y/N): y

➡️  Replacing placeholder ___BRANCH__ with SG...
➡️  Importing schema to database: chillphones_branch_SG...

✅ SUCCESS: Schema imported successfully for database 'chillphones_branch_SG'
```

---

### Bước 3: Import Stored Procedures (nếu có)

```bash
mysql -u cps_admin -p chillphones_branch_SG < branch_stored_procedures.sql
```

**Lưu ý:** Nếu file `branch_stored_procedures.sql` có hardcode branch code, bạn cần apply cùng pattern:
```bash
sed "s/___BRANCH__/SG/g" branch_stored_procedures.sql | mysql -u cps_admin -p chillphones_branch_SG
```

---

### Bước 4: Generate Config File

Chạy script generate config:

```bash
./gen_branch_config.sh SG your-api-key-here
```

**Hoặc** nếu ở thư mục đúng tên:

```bash
./gen_branch_config.sh    # Tự động detect BRANCH=SG và dùng default API key
```

Script sẽ tạo file `config.php`:

```php
<?php
return [
    'APP_NAME' => 'Chillphones SG',
    'APP_BRANCH_CODE' => 'SG',
    'APP_ENV' => 'production',
    'APP_URL' => 'http://localhost:9001',
    'APP_TIMEZONE' => 'Asia/Ho_Chi_Minh',
    
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'chillphones_branch_SG',
    'DB_USERNAME' => 'cps_admin',
    'DB_PASSWORD' => '123456789',
    
    'CENTRAL_API_URL' => 'https://cps.duyet.dev/api',
    'CENTRAL_API_KEY' => 'your-api-key-here',
    
    'LOG_CHANNEL' => 'stack',
    'LOG_LEVEL' => 'info',
];
```

---

### Bước 5: Customize Config (nếu cần)

Sửa file `config.php` để match với môi trường thực tế:

```bash
vim config.php
```

Các thông số có thể cần sửa:
- `APP_URL` → domain thực tế của chi nhánh
- `DB_PASSWORD` → password MySQL thực tế
- `CENTRAL_API_KEY` → key riêng cho branch này (nếu khác default)

---

### Bước 6: Test & Verify

**Test database connection:**

```bash
php -r "
\$config = require 'config.php';
\$pdo = new PDO(
    'mysql:host='.\$config['DB_HOST'].';dbname='.\$config['DB_DATABASE'],
    \$config['DB_USERNAME'],
    \$config['DB_PASSWORD']
);
echo '✅ Database connection OK for branch: '.\$config['APP_BRANCH_CODE'].PHP_EOL;
"
```

**Test API ping:**

```bash
curl http://localhost:9001/api/ping
# Expected: {"success":true,"data":{"branch":"SG","time":"2025-11-10T..."}}
```

**Test web login:**

```bash
# Open in browser: http://localhost/login
# Email: cps_admin@duyet.dev
# Password: admin123
```

**Verify admin account in database:**

```bash
mysql -u cps_admin -p123456789 chillphones_branch_SG -e "
SELECT id, name, email, role, branch_code, enabled 
FROM employee_replica 
WHERE email = 'cps_admin@duyet.dev';
"
```

Expected output:
```
+----+-----------+----------------------+-------+-------------+---------+
| id | name      | email                | role  | branch_code | enabled |
+----+-----------+----------------------+-------+-------------+---------+
|  1 | CPS Admin | cps_admin@duyet.dev  | ADMIN | SG          |       1 |
+----+-----------+----------------------+-------+-------------+---------+
```

**Verify triggers:**

```bash
mysql -u cps_admin -p123456789 chillphones_branch_SG -e "
SHOW TRIGGERS LIKE 'branch_price_override';
SHOW CREATE TRIGGER tg_bpo_force_branch_ins;
"
```

Trigger phải có: `SET NEW.branch_code = 'SG';`

---

## 📊 Kiến trúc Placeholder System

### SQL Template (`chillphones_branch_template.sql`)

Template sử dụng placeholder `{{BRANCH}}` ở các vị trí:

1. **Database name:**
```sql
CREATE DATABASE IF NOT EXISTS chillphones_branch_{{BRANCH}}
USE chillphones_branch_{{BRANCH}};
```

2. **Triggers:**
```sql
CREATE TRIGGER tg_bpo_force_branch_ins ... BEGIN
  SET NEW.branch_code = '{{BRANCH}}';
END
```

3. **Views:**
```sql
CREATE VIEW v_pos_catalog AS 
SELECT ... WHERE i.branch_code = '{{BRANCH}}'
```

### Script Flow

```
┌─────────────────────────────┐
│  Template Files (cps/)      │
│  - SQL template             │
│  - Scripts                  │
└──────────┬──────────────────┘
           │
           ├─► Copy to branch directory (/var/www/sg-cps/)
           │
           ├─► Run import_branch_schema.sh SG
           │   └─► sed 's/{{BRANCH}}/SG/g' | mysql
           │       └─► Create chillphones_branch_SG
           │           └─► Import tables, triggers, views with SG
           │
           └─► Run gen_branch_config.sh SG
               └─► Generate config.php with:
                   - APP_BRANCH_CODE = 'SG'
                   - DB_DATABASE = 'chillphones_branch_SG'
                   - All code auto-uses this config
```

---

## 🎯 Ưu điểm của Template System

✅ **Zero manual editing** - Không cần sửa SQL/code tay
✅ **Consistent** - Mọi branch dùng cùng 1 template, không lỗi typo
✅ **Fast deployment** - Setup 1 branch mới chỉ mất < 5 phút
✅ **Easy maintenance** - Update template 1 lần, apply cho all branches
✅ **Git-friendly** - Template không chứa thông tin nhạy cảm

---

## 🔄 Quy trình Update Schema cho Tất cả Branches

Khi có thay đổi schema (thêm bảng, sửa trigger...):

1. **Update template SQL:**
```bash
vim cps/chillphones_branch_template.sql
# Sửa schema, nhớ dùng ___BRANCH__ cho branch-specific logic
```

2. **Apply cho từng branch:**
```bash
cd /var/www/hn-cps && ./import_branch_schema.sh HN
cd /var/www/sg-cps && ./import_branch_schema.sh SG
cd /var/www/dn-cps && ./import_branch_schema.sh DN
# ...
```

3. **Hoặc dùng loop:**
```bash
for branch in HN SG DN CT HP; do
    cd /var/www/${branch,,}-cps
    ./import_branch_schema.sh $branch
done
```

---

## 🐛 Troubleshooting

### Lỗi: "Template file not found"

**Cause:** Script không tìm thấy `chillphones_branch_template.sql`

**Fix:** Copy file template vào thư mục branch:
```bash
cp /path/to/cps/chillphones_branch_template.sql .
```

### Lỗi: "Access denied for user 'cps_admin'"

**Cause:** User chưa tồn tại hoặc sai password

**Fix:** Tạo user và grant quyền:
```bash
mysql -u root -p <<EOF
CREATE USER IF NOT EXISTS 'cps_admin'@'localhost' IDENTIFIED BY '123456789';
GRANT ALL PRIVILEGES ON chillphones_branch_*.* TO 'cps_admin'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### Database đã tồn tại với schema cũ

**Fix:** Drop database cũ trước khi import:
```bash
mysql -u cps_admin -p -e "DROP DATABASE IF EXISTS chillphones_branch_SG;"
./import_branch_schema.sh SG
```

### Trigger vẫn dùng branch code cũ

**Cause:** Import không thành công hoàn toàn

**Fix:** Drop triggers và re-import:
```bash
mysql -u cps_admin -p chillphones_branch_SG <<EOF
DROP TRIGGER IF EXISTS tg_bpo_force_branch_ins;
DROP TRIGGER IF EXISTS tg_bpo_force_branch_upd;
DROP TRIGGER IF EXISTS tg_inventory_force_branch;
DROP TRIGGER IF EXISTS tg_inventory_force_branch_upd;
DROP TRIGGER IF EXISTS tg_outbox_after_order;
EOF

./import_branch_schema.sh SG
```

---

## 📚 Reference

**Branch Codes đang dùng:**
- `HN` - Hà Nội
- `SG` - Sài Gòn
- `DN` - Đà Nẵng
- `CT` - Cần Thơ
- `HP` - Hải Phòng

**MySQL Users:**
- `cps_admin` - User cho branch databases
- `root` - Admin user (chỉ dùng cho setup)

**File Structure:**
```
/var/www/
├── hn-cps/           # Branch HN
│   ├── config.php    # APP_BRANCH_CODE = 'HN'
│   ├── app/
│   └── public/
├── sg-cps/           # Branch SG
│   ├── config.php    # APP_BRANCH_CODE = 'SG'
│   ├── app/
│   └── public/
└── cps/              # Central + Templates
    ├── chillphones_branch_template.sql
    ├── import_branch_schema.sh
    └── gen_branch_config.sh
```

---

## 🎓 Tóm tắt Commands

```bash
# Setup branch mới (ví dụ: SG)
cd /var/www/sg-cps
./import_branch_schema.sh SG              # Import database
./gen_branch_config.sh SG your-api-key    # Generate config
vim config.php                             # Customize if needed

# Verify
mysql -u cps_admin -p -e "SHOW TABLES FROM chillphones_branch_SG;"
php -r "\$c=require 'config.php'; echo \$c['APP_BRANCH_CODE'];"
curl http://localhost:9001/api/ping
```

---

**✅ Done!** Chi nhánh mới đã sẵn sàng hoạt động!

