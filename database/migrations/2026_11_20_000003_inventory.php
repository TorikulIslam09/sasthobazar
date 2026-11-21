<?php
/**
 * Step 4 - Inventory, suppliers, purchase orders, GRN
 *  - stock_movements.type: + stock_in, stock_out, recall
 *  - inventory_batches: + supplier_id, grn_id; unique batch per product/variant/owner
 *  - suppliers, purchase_orders, purchase_order_items, grns, grn_items
 *  - permissions synced from config/modules.php (inventory.configure, suppliers.*, purchase_orders.*)
 *    and granted to Super Admin, Manager and Inventory Manager
 * Every step checks information_schema first, so the migration can be re-run safely.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $q = static function (string $sql, array $p = []) use ($pdo) {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st;
    };
    $hasColumn = static fn(string $t, string $c): bool => (int) $q(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]
    )->fetchColumn() > 0;
    $hasIndex = static fn(string $t, string $i): bool => (int) $q(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?', [$t, $i]
    )->fetchColumn() > 0;
    $hasFk = static fn(string $t, string $fk): bool => (int) $q(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?', [$t, $fk]
    )->fetchColumn() > 0;

    $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    /* ---------- stock movement types ---------- */
    $pdo->exec("ALTER TABLE `stock_movements` MODIFY `type`
        ENUM('purchase','stock_in','sale','return','stock_out','adjustment','damage','expired','recall','transfer','cancel') NOT NULL");

    /* ---------- suppliers ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS `suppliers` (
        `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`            VARCHAR(150) NOT NULL,
        `contact_person`  VARCHAR(150) NULL,
        `phone`           VARCHAR(20)  NULL,
        `email`           VARCHAR(191) NULL,
        `address`         VARCHAR(255) NULL,
        `drug_license_no` VARCHAR(100) NULL,
        `tin_no`          VARCHAR(50)  NULL,
        `payment_terms`   VARCHAR(100) NULL COMMENT 'e.g. Cash, 30 days credit',
        `notes`           TEXT NULL,
        `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
        `created_by`      BIGINT UNSIGNED NULL,
        `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_suppliers_name` (`name`),
        KEY `idx_suppliers_active` (`is_active`),
        CONSTRAINT `fk_suppliers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) {$engine}");

    /* ---------- purchase orders ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS `purchase_orders` (
        `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `po_no`          VARCHAR(30) NOT NULL,
        `supplier_id`    INT UNSIGNED NOT NULL,
        `vendor_id`      BIGINT UNSIGNED NULL COMMENT 'stock owner; NULL = in-house',
        `status`         ENUM('draft','ordered','partial','received','closed','cancelled') NOT NULL DEFAULT 'draft',
        `order_date`     DATE NOT NULL,
        `expected_date`  DATE NULL,
        `subtotal`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `discount`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `tax`            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total`          DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `notes`          TEXT NULL,
        `ordered_at`     DATETIME NULL,
        `closed_at`      DATETIME NULL,
        `created_by`     BIGINT UNSIGNED NULL,
        `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_po_no` (`po_no`),
        KEY `idx_po_supplier_status` (`supplier_id`, `status`),
        KEY `idx_po_status_date` (`status`, `order_date`),
        CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_po_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
        CONSTRAINT `chk_po_amounts` CHECK (`subtotal` >= 0 AND `discount` >= 0 AND `tax` >= 0 AND `total` >= 0)
    ) {$engine}");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `purchase_order_items` (
        `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `purchase_order_id` BIGINT UNSIGNED NOT NULL,
        `product_id`        BIGINT UNSIGNED NOT NULL,
        `variant_id`        BIGINT UNSIGNED NULL,
        `quantity_ordered`  INT UNSIGNED NOT NULL,
        `quantity_received` INT UNSIGNED NOT NULL DEFAULT 0,
        `unit_cost`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `line_total`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (`id`),
        KEY `idx_poi_po` (`purchase_order_id`),
        KEY `idx_poi_product` (`product_id`),
        CONSTRAINT `fk_poi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_poi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_poi_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `chk_poi_qty` CHECK (`quantity_ordered` > 0 AND `quantity_received` <= `quantity_ordered`)
    ) {$engine}");

    /* ---------- goods received notes ---------- */
    $pdo->exec("CREATE TABLE IF NOT EXISTS `grns` (
        `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `grn_no`            VARCHAR(30) NOT NULL,
        `purchase_order_id` BIGINT UNSIGNED NOT NULL,
        `supplier_id`       INT UNSIGNED NOT NULL,
        `invoice_no`        VARCHAR(100) NULL COMMENT 'supplier invoice / challan',
        `received_date`     DATE NOT NULL,
        `total_quantity`    INT UNSIGNED NOT NULL DEFAULT 0,
        `total_cost`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `notes`             VARCHAR(500) NULL,
        `received_by`       BIGINT UNSIGNED NULL,
        `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_grn_no` (`grn_no`),
        KEY `idx_grn_po` (`purchase_order_id`),
        KEY `idx_grn_supplier_date` (`supplier_id`, `received_date`),
        CONSTRAINT `fk_grn_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_grn_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_grn_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) {$engine}");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `grn_items` (
        `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `grn_id`           BIGINT UNSIGNED NOT NULL,
        `po_item_id`       BIGINT UNSIGNED NOT NULL,
        `product_id`       BIGINT UNSIGNED NOT NULL,
        `variant_id`       BIGINT UNSIGNED NULL,
        `batch_id`         BIGINT UNSIGNED NULL,
        `batch_no`         VARCHAR(100) NOT NULL,
        `manufacture_date` DATE NULL,
        `expiry_date`      DATE NULL,
        `quantity`         INT UNSIGNED NOT NULL,
        `unit_cost`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `line_total`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (`id`),
        KEY `idx_grn_items_grn` (`grn_id`),
        KEY `idx_grn_items_po_item` (`po_item_id`),
        CONSTRAINT `fk_grn_items_grn` FOREIGN KEY (`grn_id`) REFERENCES `grns` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_grn_items_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_grn_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
        CONSTRAINT `fk_grn_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
        CONSTRAINT `chk_grn_items_qty` CHECK (`quantity` > 0)
    ) {$engine}");

    /* ---------- batches: supplier + GRN link, one row per batch number ---------- */
    if (!$hasColumn('inventory_batches', 'supplier_id')) {
        $pdo->exec('ALTER TABLE `inventory_batches` ADD COLUMN `supplier_id` INT UNSIGNED NULL AFTER `cost_price`');
    }
    if (!$hasColumn('inventory_batches', 'grn_id')) {
        $pdo->exec('ALTER TABLE `inventory_batches` ADD COLUMN `grn_id` BIGINT UNSIGNED NULL AFTER `invoice_no`');
    }
    if (!$hasColumn('inventory_batches', 'variant_key')) {
        // NULL-safe uniqueness helper: MySQL unique keys ignore NULLs, so use 0 for "no variant"
        $pdo->exec('ALTER TABLE `inventory_batches` ADD COLUMN `variant_key` BIGINT UNSIGNED AS (IFNULL(`variant_id`, 0)) PERSISTENT AFTER `variant_id`');
    }
    if (!$hasFk('inventory_batches', 'fk_batches_supplier')) {
        $pdo->exec('ALTER TABLE `inventory_batches` ADD CONSTRAINT `fk_batches_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL');
    }
    if (!$hasFk('inventory_batches', 'fk_batches_grn')) {
        $pdo->exec('ALTER TABLE `inventory_batches` ADD CONSTRAINT `fk_batches_grn` FOREIGN KEY (`grn_id`) REFERENCES `grns` (`id`) ON DELETE SET NULL');
    }
    if (!$hasIndex('inventory_batches', 'uq_batches_product_batch')) {
        $pdo->exec('ALTER TABLE `inventory_batches` ADD UNIQUE KEY `uq_batches_product_batch` (`product_id`, `variant_key`, `batch_no`)');
    }
    if (!$hasIndex('inventory_batches', 'idx_batches_status_expiry')) {
        $pdo->exec('ALTER TABLE `inventory_batches` ADD INDEX `idx_batches_status_expiry` (`status`, `expiry_date`)');
    }
    if (!$hasIndex('stock_movements', 'idx_stock_mov_type_date')) {
        $pdo->exec('ALTER TABLE `stock_movements` ADD INDEX `idx_stock_mov_type_date` (`type`, `created_at`)');
    }

    /* ---------- settings ---------- */
    $pdo->exec("INSERT IGNORE INTO `settings` (`group`,`key`,`value`,`type`,`label`,`is_public`) VALUES
        ('inventory','low_stock_threshold','10','int','Default low-stock alert level',0),
        ('inventory','expiry_alert_days','90','int','Warn when batch expires within (days)',0),
        ('inventory','block_expired_stock_in','1','bool','Refuse stock-in of already expired batches',0)");

    /* ---------- permissions ---------- */
    Permission::sync($pdo, false);
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE (r.slug IN ('super-admin', 'manager') AND p.module IN ('inventory', 'suppliers', 'purchase_orders'))
                    OR (r.slug = 'inventory-manager' AND p.module IN ('inventory', 'suppliers', 'purchase_orders'))
                    OR (r.slug = 'accountant' AND p.`key` IN ('inventory.view', 'inventory.export', 'purchase_orders.view', 'purchase_orders.export', 'suppliers.view'))");
};
