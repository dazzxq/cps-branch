-- =====================================================
-- BRANCH STORED PROCEDURES - Chillphones
-- Database: chillphones_branch_HN (áp dụng cho mọi branch)
-- Purpose: ACID + Transaction + Stock Management
-- =====================================================

-- Drop existing procedures nếu có
DROP PROCEDURE IF EXISTS sp_create_order;
DROP PROCEDURE IF EXISTS sp_set_stock;

-- =====================================================
-- SP1: Tạo đơn hàng với kiểm tra stock (ACID)
-- =====================================================
DELIMITER $$

CREATE PROCEDURE sp_create_order(
    IN p_order_code VARCHAR(40),
    IN p_branch_code CHAR(5),
    IN p_customer_name VARCHAR(255),
    IN p_customer_phone VARCHAR(20),
    IN p_customer_email VARCHAR(255),
    IN p_customer_address TEXT,
    IN p_order_note TEXT,
    IN p_items_json JSON,
    IN p_total_amount INT
)
sp:BEGIN
    -- ====================
    -- DECLARE ALL VARIABLES FIRST (MySQL/MariaDB requirement)
    -- ====================
    DECLARE v_order_id BIGINT;
    DECLARE v_item_count INT DEFAULT 0;
    DECLARE v_idx INT DEFAULT 0;
    DECLARE v_product_id BIGINT;
    DECLARE v_qty INT;
    DECLARE v_price INT;
    DECLARE v_stock INT;
    DECLARE v_product_name VARCHAR(255);
    
    -- ====================
    -- ERROR HANDLER (phải sau DECLARE variables, trước logic)
    -- ====================
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'ERROR' AS status, 'Transaction failed - check stock or data constraints' AS message;
    END;
    
    -- Set SQL mode để warnings thành errors (critical cho ACID!)
    SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
    
    -- ====================
    -- IDEMPOTENCY CHECK
    -- ====================
    IF EXISTS (SELECT 1 FROM orders WHERE order_code = p_order_code) THEN
        SELECT 
            'DUPLICATE' AS status, 
            p_order_code AS order_code,
            id AS order_id
        FROM orders 
        WHERE order_code = p_order_code;
        LEAVE sp;
    END IF;
    
    -- ====================
    -- START TRANSACTION (ACID begins here!)
    -- ====================
    START TRANSACTION;
    
    -- ====================
    -- 1. TẠO ORDER HEADER
    -- ====================
    INSERT INTO orders(
        order_code, 
        branch_code, 
        total, 
        status, 
        json_ext, 
        created_at
    )
    VALUES (
        p_order_code,
        p_branch_code,
        p_total_amount,
        'NEW',  -- Đảm bảo giá trị này khớp với ENUM trong bảng orders
        JSON_OBJECT(
            'name', p_customer_name,
            'phone', p_customer_phone,
            'email', p_customer_email,
            'address', p_customer_address,
            'note', p_order_note,
            'source', 'storefront',
            'created_at', NOW()
        ),
        NOW()
    );
    
    SET v_order_id = LAST_INSERT_ID();
    
    -- ====================
    -- 2. XỬ LÝ TỪNG ITEM
    -- ====================
    SET v_item_count = JSON_LENGTH(p_items_json);
    
    WHILE v_idx < v_item_count DO
        -- Extract item data từ JSON
        SET v_product_id = JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_idx, '].product_id')));
        SET v_qty = JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_idx, '].qty')));
        SET v_price = JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_idx, '].price')));
        
        -- ====================
        -- 2.1. LOCK STOCK (ISOLATION - Ngăn race condition)
        -- ====================
        SELECT 
            inv.qty,
            pr.name
        INTO 
            v_stock,
            v_product_name
        FROM branch_inventory inv
        LEFT JOIN products_replica pr ON pr.id = inv.product_id
        WHERE inv.product_id = v_product_id
        FOR UPDATE;  -- 🔒 CRITICAL: Lock row để tránh oversell
        
        -- ====================
        -- 2.2. VALIDATE STOCK (CONSISTENCY)
        -- ====================
        IF v_stock IS NULL THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'PRODUCT_NOT_IN_INVENTORY';
        END IF;
        
        IF v_stock < v_qty THEN
            SET @err_detail = CONCAT('INSUFFICIENT_STOCK: Product "', 
                COALESCE(v_product_name, v_product_id), 
                '" only has ', v_stock, ' in stock, but ', v_qty, ' requested');
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = @err_detail;
        END IF;
        
        -- ====================
        -- 2.3. TẠO ORDER ITEM
        -- ====================
        INSERT INTO order_item(order_id, product_id, qty, unit_price)
        VALUES (v_order_id, v_product_id, v_qty, v_price);
        
        -- ====================
        -- 2.4. TRỪ STOCK (ATOMICITY)
        -- ====================
        UPDATE branch_inventory
        SET 
            qty = qty - v_qty,
            updated_at = NOW()
        WHERE product_id = v_product_id;
        
        SET v_idx = v_idx + 1;
    END WHILE;
    
    -- ====================
    -- 3. CẬP NHẬT STATUS ORDER
    -- ====================
    UPDATE orders
    SET status = 'PAID'  -- Sử dụng giá trị có trong ENUM: NEW, PAID, CANCELLED, FULFILLED
    WHERE id = v_order_id;
    
    -- ====================
    -- 4. THÊM VÀO OUTBOX (để sync sang Central)
    -- ====================
    -- Check nếu chưa có outbox event cho order này (tránh duplicate)
    IF NOT EXISTS (
        SELECT 1 FROM outbox_events 
        WHERE event_type = 'ORDER_CREATED' 
        AND JSON_EXTRACT(payload_json, '$.order_code') = p_order_code
    ) THEN
        INSERT INTO outbox_events(event_type, payload_json, status, created_at)
        VALUES (
            'ORDER_CREATED',
            JSON_OBJECT(
                'order_code', p_order_code,
                'order_id', v_order_id,
                'branch_code', p_branch_code,
                'total', p_total_amount,
                'created_at', NOW(),
                'status', 'PAID',
                'customer_name', p_customer_name,
                'customer_phone', p_customer_phone,
                'customer_email', p_customer_email,
                'customer_address', p_customer_address,
                'customer_note', p_order_note,
                'items', p_items_json,
                'customer_info', JSON_OBJECT(
                    'name', p_customer_name,
                    'phone', p_customer_phone,
                    'email', p_customer_email,
                    'address', p_customer_address,
                    'note', p_order_note
                )
            ),
            'PENDING',
            NOW()
        );
    END IF;
    
    -- ====================
    -- COMMIT (DURABILITY - Data bền vững!)
    -- ====================
    COMMIT;
    
    -- Return success
    SELECT 
        'SUCCESS' AS status,
        p_order_code AS order_code,
        v_order_id AS order_id,
        'Order created and stock updated' AS message;
END$$

DELIMITER ;

-- =====================================================
-- SP2: Set stock (đồng bộ từ Central)
-- =====================================================
DELIMITER $$

CREATE PROCEDURE sp_set_stock(
    IN p_product_id BIGINT,
    IN p_qty INT
)
BEGIN
    START TRANSACTION;
    
    -- Validate input
    IF p_qty < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'INVALID_QTY: Stock cannot be negative';
    END IF;
    
    -- Upsert stock
    INSERT INTO branch_inventory(product_id, qty, updated_at)
    VALUES (p_product_id, p_qty, NOW())
    ON DUPLICATE KEY UPDATE 
        qty = VALUES(qty),
        updated_at = VALUES(updated_at);
    
    COMMIT;
    
    SELECT 
        'SUCCESS' AS status,
        p_product_id AS product_id,
        p_qty AS qty,
        'Stock updated' AS message;
END$$

DELIMITER ;

-- =====================================================
-- MIGRATION: Tạo bảng outbox_events nếu chưa có
-- =====================================================
CREATE TABLE IF NOT EXISTS outbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',  -- Changed from ENUM to VARCHAR for flexibility
    retry_count INT DEFAULT 0,
    last_error TEXT NULL,  -- Renamed from error_message to match controller
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MIGRATION: Tạo bảng branch_inventory nếu chưa có
-- =====================================================
CREATE TABLE IF NOT EXISTS branch_inventory (
    product_id BIGINT PRIMARY KEY,
    qty INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (qty >= 0)  -- Consistency: Stock không được âm
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TEST CASES (Comment out khi production)
-- =====================================================
/*
-- Test 1: Tạo order thành công
CALL sp_create_order(
    'TEST-001',
    'HN',
    'Nguyen Van A',
    '0123456789',
    'test@email.com',
    '123 Hanoi Street',
    'Test order note',
    '[{"product_id":1,"qty":1,"price":1000000}]',
    1000000
);

-- Test 2: Tạo order trùng (idempotency)
CALL sp_create_order(
    'TEST-001',
    'HN',
    'Nguyen Van A',
    '0123456789',
    'test@email.com',
    '123 Hanoi Street',
    'Test order note',
    '[{"product_id":1,"qty":1,"price":1000000}]',
    1000000
);
-- Expected: status='DUPLICATE'

-- Test 3: Tạo order với stock không đủ
INSERT INTO branch_inventory(product_id, qty) VALUES (999, 1);
CALL sp_create_order(
    'TEST-002',
    'HN',
    'Tran Van B',
    '0987654321',
    'test2@email.com',
    '456 Hanoi Street',
    'Test oversell',
    '[{"product_id":999,"qty":10,"price":1000000}]',
    10000000
);
-- Expected: ERROR 'INSUFFICIENT_STOCK'

-- Test 4: Set stock
CALL sp_set_stock(1, 100);
SELECT * FROM branch_inventory WHERE product_id = 1;
*/

-- =====================================================
-- END OF FILE
-- =====================================================

