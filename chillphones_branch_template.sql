-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 09, 2025 at 06:12 PM
-- Server version: 10.11.6-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

USE chillphones_branch_{{BRANCH}};
--
-- Database: `chillphones_branch_{{BRANCH}}`
--

-- --------------------------------------------------------
--
-- Stored Procedures
--
-- --------------------------------------------------------

DELIMITER $$
--
-- Procedure: sp_create_order (ACID Transaction for Order Creation)
--
DROP PROCEDURE IF EXISTS `sp_create_order`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_order` (
    IN `p_order_code` VARCHAR(40), 
    IN `p_branch_code` CHAR(5), 
    IN `p_customer_name` VARCHAR(255), 
    IN `p_customer_phone` VARCHAR(20), 
    IN `p_customer_email` VARCHAR(255), 
    IN `p_customer_address` TEXT, 
    IN `p_order_note` TEXT, 
    IN `p_items_json` JSON, 
    IN `p_total_amount` INT
) sp:BEGIN
    DECLARE v_order_id BIGINT;
    DECLARE v_item_count INT DEFAULT 0;
    DECLARE v_idx INT DEFAULT 0;
    DECLARE v_product_id BIGINT;
    DECLARE v_qty INT;
    DECLARE v_price INT;
    DECLARE v_stock INT;
    DECLARE v_product_name VARCHAR(255);
    
    -- Handler must be at the beginning of BEGIN block
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'ERROR' AS status, 'Transaction failed - check stock or data constraints' AS message;
    END;
    
    -- Set strict mode to ensure warnings become errors
    SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
    
    -- IDEMPOTENCY CHECK
    IF EXISTS (SELECT 1 FROM orders WHERE order_code = p_order_code) THEN
        SELECT 'DUPLICATE' AS status, p_order_code AS order_code, id AS order_id 
        FROM orders WHERE order_code = p_order_code;
        LEAVE sp;
    END IF;
    
    -- START TRANSACTION
    START TRANSACTION;
    
    -- 1. CREATE ORDER
    INSERT INTO orders(order_code, branch_code, total, status, json_ext, created_at)
    VALUES (
        p_order_code, 
        p_branch_code, 
        p_total_amount, 
        'NEW', 
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
    
    -- 2. PROCESS ITEMS (ATOMICITY + ISOLATION)
    SET v_item_count = JSON_LENGTH(p_items_json);
    WHILE v_idx < v_item_count DO
        -- Extract item data from JSON
        SET v_product_id = JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_idx, '].product_id')));
        SET v_qty = JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_idx, '].qty')));
        SET v_price = JSON_UNQUOTE(JSON_EXTRACT(p_items_json, CONCAT('$[', v_idx, '].price')));
        
        -- 2.1. CHECK STOCK (ISOLATION - FOR UPDATE LOCK)
        SELECT inv.qty, pr.name INTO v_stock, v_product_name
        FROM branch_inventory inv 
        LEFT JOIN products_replica pr ON pr.id = inv.product_id
        WHERE inv.product_id = v_product_id 
        FOR UPDATE;
        
        -- Validate stock exists
        IF v_stock IS NULL THEN 
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PRODUCT_NOT_IN_INVENTORY'; 
        END IF;
        
        -- Check sufficient stock
        IF v_stock < v_qty THEN
            SET @err_detail = CONCAT(
                'INSUFFICIENT_STOCK: Product "', 
                COALESCE(v_product_name, v_product_id), 
                '" only has ', v_stock, 
                ' in stock, but ', v_qty, ' requested'
            );
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @err_detail;
        END IF;
        
        -- 2.2. CREATE ORDER ITEM
        INSERT INTO order_item(order_id, product_id, qty, unit_price)
        VALUES (v_order_id, v_product_id, v_qty, v_price);
        
        -- 2.3. DEDUCT STOCK (ATOMICITY)
        UPDATE branch_inventory
        SET qty = qty - v_qty, updated_at = NOW()
        WHERE product_id = v_product_id;
        
        SET v_idx = v_idx + 1;
    END WHILE;
    
    -- 3. UPDATE ORDER STATUS TO PAID
    UPDATE orders
    SET status = 'PAID'
    WHERE id = v_order_id;
    
    -- 4. COMMIT (DURABILITY)
    COMMIT;
    
    -- RETURN SUCCESS
    SELECT 
        'SUCCESS' AS status,
        p_order_code AS order_code,
        v_order_id AS order_id,
        'Order created successfully (Real-time sync mode)' AS message;
END$$

--
-- Procedure: sp_set_stock (Set Branch Stock)
--
DROP PROCEDURE IF EXISTS `sp_set_stock`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_set_stock` (
    IN `p_product_id` BIGINT, 
    IN `p_qty` INT
) BEGIN
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

-- --------------------------------------------------------

--
-- Table structure for table `branch_inventory`
--

CREATE TABLE `branch_inventory` (
  `product_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branch_price_override`
--

CREATE TABLE `branch_price_override` (
  `product_id` bigint(20) NOT NULL,
  `branch_code` char(5) NOT NULL,
  `price` int(11) DEFAULT NULL,
  `promo_price` int(11) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `branch_price_override`
--
DELIMITER $$
CREATE TRIGGER `tg_bpo_force_branch_ins` BEFORE INSERT ON `branch_price_override` FOR EACH ROW BEGIN
  SET NEW.branch_code = '{{BRANCH}}';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tg_bpo_force_branch_upd` BEFORE UPDATE ON `branch_price_override` FOR EACH ROW BEGIN
  SET NEW.branch_code = '{{BRANCH}}';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `branch_seq`
--

CREATE TABLE `branch_seq` (
  `branch_code` char(5) NOT NULL,
  `next_val` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `id` bigint(20) NOT NULL,
  `email` varchar(160) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_replica`
--

CREATE TABLE `employee_replica` (
  `id` bigint(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('ADMIN','MANAGER','STAFF') DEFAULT 'STAFF',
  `branch_code` char(5) DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_replica`
-- Insert default admin account for branch login
--

INSERT INTO `employee_replica` (`id`, `name`, `email`, `password_hash`, `role`, `branch_code`, `enabled`, `updated_at`) VALUES
(1, 'CPS Admin', 'cps_admin@duyet.dev', '$2y$10$68.UbUSy3UrTg2Av4hlUcuSbt1K2mn3ik/nJZu2CEtUeWISy3fNce', 'ADMIN', '{{BRANCH}}', 1, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `email` = VALUES(`email`),
  `password_hash` = VALUES(`password_hash`),
  `role` = VALUES(`role`),
  `branch_code` = VALUES(`branch_code`),
  `enabled` = VALUES(`enabled`),
  `updated_at` = CURRENT_TIMESTAMP;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `product_id` bigint(20) NOT NULL,
  `branch_code` char(5) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `reserved` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `inventory`
--
DELIMITER $$
CREATE TRIGGER `tg_inventory_force_branch` BEFORE INSERT ON `inventory` FOR EACH ROW BEGIN
  SET NEW.branch_code = '{{BRANCH}}';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tg_inventory_force_branch_upd` BEFORE UPDATE ON `inventory` FOR EACH ROW BEGIN
  SET NEW.branch_code = '{{BRANCH}}';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) NOT NULL,
  `order_code` varchar(40) DEFAULT NULL,
  `branch_code` char(5) NOT NULL,
  `total` int(11) NOT NULL,
  `json_ext` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`json_ext`)),
  `status` enum('NEW','PAID','CANCELLED','FULFILLED') DEFAULT 'NEW',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `tg_outbox_after_order` AFTER INSERT ON `orders` FOR EACH ROW BEGIN
  INSERT INTO outbox_events(event_type, payload_json, status)
  VALUES(
    'ORDER_CREATED',
    JSON_OBJECT(
      'order_code', NEW.order_code,
      'branch_code', '{{BRANCH}}',
      'total', NEW.total,
      'created_at', NEW.created_at
    ),
    'PENDING'
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_item`
--

CREATE TABLE `order_item` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outbox_events`
--

CREATE TABLE `outbox_events` (
  `id` bigint(20) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `status` enum('PENDING','SENT','ERROR') DEFAULT 'PENDING',
  `retry_count` int(11) DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products_replica`
--

CREATE TABLE `products_replica` (
  `id` bigint(20) NOT NULL,
  `sku` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `brand_name` varchar(120) NOT NULL,
  `price` int(11) NOT NULL,
  `msrp` int(11) DEFAULT NULL,
  `promo_price` int(11) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
  `ext_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ext_json`)),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `products_replica`
--
DELIMITER $$
CREATE TRIGGER `prevent_products_replica_delete` BEFORE DELETE ON `products_replica` FOR EACH ROW BEGIN
    -- Allow deletes from Central sync
    IF @central_sync IS NULL OR @central_sync != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Branch cannot DELETE products_replica';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `prevent_products_replica_update` BEFORE UPDATE ON `products_replica` FOR EACH ROW BEGIN
    -- Allow updates from Central sync
    IF @central_sync IS NULL OR @central_sync != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Branch cannot UPDATE products_replica';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_inventory_overview`
-- (See below for the actual view)
--
CREATE TABLE `v_inventory_overview` (
`product_id` bigint(20)
,`sku` varchar(64)
,`name` varchar(255)
,`brand_name` varchar(120)
,`price` int(11)
,`qty` int(11)
,`reserved` int(11)
,`available` bigint(12)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pos_catalog`
-- (See below for the actual view)
--
CREATE TABLE `v_pos_catalog` (
`product_id` bigint(20)
,`sku` varchar(64)
,`name` varchar(255)
,`brand_name` varchar(120)
,`status` enum('ACTIVE','INACTIVE')
,`central_price` int(11)
,`central_promo_price` int(11)
,`qty` int(11)
,`reserved` int(11)
,`effective_price` int(11)
);

-- --------------------------------------------------------

--
-- Structure for view `v_inventory_overview`
--
DROP TABLE IF EXISTS `v_inventory_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_inventory_overview`  AS SELECT `pr`.`id` AS `product_id`, `pr`.`sku` AS `sku`, `pr`.`name` AS `name`, `pr`.`brand_name` AS `brand_name`, `pr`.`price` AS `price`, `i`.`qty` AS `qty`, `i`.`reserved` AS `reserved`, `i`.`qty`- `i`.`reserved` AS `available` FROM (`products_replica` `pr` left join `inventory` `i` on(`i`.`product_id` = `pr`.`id` and `i`.`branch_code` = '{{BRANCH}}')) ;

-- --------------------------------------------------------

--
-- Structure for view `v_pos_catalog`
--
DROP TABLE IF EXISTS `v_pos_catalog`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pos_catalog`  AS SELECT `pr`.`id` AS `product_id`, `pr`.`sku` AS `sku`, `pr`.`name` AS `name`, `pr`.`brand_name` AS `brand_name`, `pr`.`status` AS `status`, `pr`.`price` AS `central_price`, `pr`.`promo_price` AS `central_promo_price`, `i`.`qty` AS `qty`, `i`.`reserved` AS `reserved`, coalesce(case when `bpo`.`product_id` is not null and (`bpo`.`starts_at` is null or `bpo`.`starts_at` <= current_timestamp()) and (`bpo`.`ends_at` is null or `bpo`.`ends_at` >= current_timestamp()) then `bpo`.`promo_price` end,`pr`.`promo_price`,case when `bpo`.`product_id` is not null and (`bpo`.`starts_at` is null or `bpo`.`starts_at` <= current_timestamp()) and (`bpo`.`ends_at` is null or `bpo`.`ends_at` >= current_timestamp()) then `bpo`.`price` end,`pr`.`price`) AS `effective_price` FROM ((`products_replica` `pr` left join `inventory` `i` on(`i`.`product_id` = `pr`.`id` and `i`.`branch_code` = '{{BRANCH}}')) left join `branch_price_override` `bpo` on(`bpo`.`product_id` = `pr`.`id` and `bpo`.`branch_code` = '{{BRANCH}}')) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branch_inventory`
--
ALTER TABLE `branch_inventory`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `branch_price_override`
--
ALTER TABLE `branch_price_override`
  ADD PRIMARY KEY (`product_id`,`branch_code`);

--
-- Indexes for table `branch_seq`
--
ALTER TABLE `branch_seq`
  ADD PRIMARY KEY (`branch_code`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `employee_replica`
--
ALTER TABLE `employee_replica`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_emp_email` (`email`),
  ADD KEY `idx_emp_branch` (`branch_code`),
  ADD KEY `idx_emp_enabled` (`enabled`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`product_id`,`branch_code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `idx_orders_created` (`created_at`),
  ADD KEY `idx_orders_status` (`status`);

--
-- Indexes for table `order_item`
--
ALTER TABLE `order_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_order` (`order_id`),
  ADD KEY `idx_order_item_product` (`product_id`);

--
-- Indexes for table `outbox_events`
--
ALTER TABLE `outbox_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_outbox_status` (`status`,`created_at`);

--
-- Indexes for table `products_replica`
--
ALTER TABLE `products_replica`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rep_sku` (`sku`),
  ADD KEY `idx_products_replica_updated` (`updated_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_item`
--
ALTER TABLE `order_item`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `outbox_events`
--
ALTER TABLE `outbox_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `branch_inventory`
--
ALTER TABLE `branch_inventory`
  ADD CONSTRAINT `fk_bi_product` FOREIGN KEY (`product_id`) REFERENCES `products_replica` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `branch_price_override`
--
ALTER TABLE `branch_price_override`
  ADD CONSTRAINT `fk_bpo_product` FOREIGN KEY (`product_id`) REFERENCES `products_replica` (`id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products_replica` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_item`
--
ALTER TABLE `order_item`
  ADD CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products_replica` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
