<?php
/**
 * Step 8 - Bangladesh shipping
 *  - shipping_zones.zone_type (city / suburb / outside) + sort_order; default names
 *  - shipping_rates.service (standard / express)
 *  - orders.delivery_option (standard / express / pickup)
 *  - shipments: delivery option, zone/rate, origin & destination, customer fee, COD charge + COD status/settlement,
 *    failed attempts, extra statuses (failed, returning, on_hold), mock flag
 *  - shipment_events (status history from API / webhook / staff), cod_settlements
 *  - couriers: eCourier added, "Own delivery man" name, webhook tokens
 *  - settings: warehouse district, store pickup, courier mock mode
 *  - permissions synced; shipping / couriers / shipments granted
 * Safe to re-run.
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
    $hasTable = static fn(string $t): bool => (int) $q(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$t]
    )->fetchColumn() > 0;
    $columnType = static fn(string $t, string $c): string => (string) $q(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]
    )->fetchColumn();
    $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    // ---- zones & rates ----
    if (!$hasColumn('shipping_zones', 'zone_type')) {
        $pdo->exec("ALTER TABLE `shipping_zones` ADD COLUMN `zone_type` ENUM('city','suburb','outside') NOT NULL DEFAULT 'outside' AFTER `name_bn`,
                    ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `zone_type`");
        $pdo->exec("UPDATE shipping_zones SET zone_type = 'city', sort_order = 1, name = 'Inside Dhaka City', name_bn = 'ঢাকা সিটির ভিতরে' WHERE id = 1");
        $pdo->exec("UPDATE shipping_zones SET zone_type = 'suburb', sort_order = 2, name = 'Dhaka Sub-area', name_bn = 'ঢাকার উপশহর' WHERE id = 2");
        $pdo->exec("UPDATE shipping_zones SET zone_type = 'outside', sort_order = 3 WHERE id = 3");
    }
    if (!$hasColumn('shipping_rates', 'service')) {
        $pdo->exec("ALTER TABLE `shipping_rates` ADD COLUMN `service` ENUM('standard','express') NOT NULL DEFAULT 'standard' AFTER `zone_id`");
        $pdo->exec("UPDATE shipping_rates SET service = 'express' WHERE name LIKE 'Express%'");
    }

    // ---- orders ----
    if (!$hasColumn('orders', 'delivery_option')) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `delivery_option` ENUM('standard','express','pickup') NOT NULL DEFAULT 'standard' AFTER `shipping_rate_id`");
    }

    // ---- shipments ----
    $statuses = "ENUM('pending','booked','picked_up','in_transit','out_for_delivery','on_hold','failed','delivered','partial_delivered','returning','returned','cancelled','lost')";
    if (!str_contains($columnType('shipments', 'status'), 'returning')) {
        $pdo->exec("ALTER TABLE `shipments` MODIFY `status` {$statuses} NOT NULL DEFAULT 'pending'");
    }
    $cols = [
        ['delivery_option', "ENUM('standard','express','pickup') NOT NULL DEFAULT 'standard' AFTER `courier_id`"],
        ['zone_id', 'INT UNSIGNED NULL AFTER `delivery_option`'],
        ['rate_id', 'INT UNSIGNED NULL AFTER `zone_id`'],
        ['origin_district_id', 'INT UNSIGNED NULL AFTER `rate_id`'],
        ['dest_district_id', 'INT UNSIGNED NULL AFTER `origin_district_id`'],
        ['shipping_fee', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'charged to the customer' AFTER `cod_amount`"],
        ['cod_charge', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `shipping_fee`'],
        ['cod_status', "ENUM('none','pending','collected','settled') NOT NULL DEFAULT 'none' AFTER `collected_amount`"],
        ['cod_settlement_id', 'BIGINT UNSIGNED NULL AFTER `cod_status`'],
        ['failed_attempts', 'TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`'],
        ['rider_user_id', 'BIGINT UNSIGNED NULL AFTER `rider_phone`'],
        ['is_mock', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `api_response`'],
        ['returned_at', 'DATETIME NULL AFTER `delivered_at`'],
        ['last_synced_at', 'DATETIME NULL AFTER `returned_at`'],
    ];
    foreach ($cols as [$c, $def]) {
        if (!$hasColumn('shipments', $c)) {
            $pdo->exec("ALTER TABLE `shipments` ADD COLUMN `{$c}` {$def}");
        }
    }
    if (!(int) $q("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND INDEX_NAME = 'idx_shipments_cod'")->fetchColumn()) {
        $pdo->exec('ALTER TABLE `shipments` ADD INDEX `idx_shipments_cod` (`courier_id`, `cod_status`), ADD INDEX `idx_shipments_consignment` (`consignment_id`)');
    }

    if (!$hasTable('shipment_events')) {
        $pdo->exec("CREATE TABLE `shipment_events` (
            `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `shipment_id` BIGINT UNSIGNED NOT NULL,
            `status`      VARCHAR(30) NOT NULL,
            `note`        VARCHAR(255) NULL,
            `source`      ENUM('system','api','webhook','staff','vendor') NOT NULL DEFAULT 'system',
            `raw`         JSON NULL,
            `created_by`  BIGINT UNSIGNED NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_shipment_events` (`shipment_id`, `id`),
            CONSTRAINT `fk_shipment_events_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
        ) {$engine}");
    }
    if (!$hasTable('cod_settlements')) {
        $pdo->exec("CREATE TABLE `cod_settlements` (
            `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `courier_id`      INT UNSIGNED NOT NULL,
            `reference_no`    VARCHAR(100) NOT NULL,
            `shipments_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `amount_expected` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `amount_received` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `courier_fees`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `note`            VARCHAR(255) NULL,
            `received_at`     DATE NOT NULL,
            `created_by`      BIGINT UNSIGNED NULL,
            `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_cod_settlements_courier` (`courier_id`, `received_at`),
            CONSTRAINT `fk_cod_settlements_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`) ON DELETE RESTRICT
        ) {$engine}");
    }

    // ---- couriers ----
    $pdo->exec("UPDATE couriers SET name = 'Own delivery man' WHERE code = 'self' AND name = 'Self Delivery'");
    $pdo->exec("INSERT IGNORE INTO couriers (name, code, api_base_url, tracking_url_template, is_active)
                VALUES ('eCourier', 'ecourier', 'https://backoffice.ecourier.com.bd/api', 'https://ecourier.com.bd/track/?tracking={tracking_no}', 0)");
    $pdo->exec("UPDATE couriers SET api_base_url = 'https://api.paperfly.com.bd' WHERE code = 'paperfly' AND (api_base_url IS NULL OR api_base_url = '')");
    foreach ($q("SELECT id, api_config FROM couriers")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $cfg = json_decode((string) $c['api_config'], true) ?: [];
        if (empty($cfg['webhook_token'])) {
            $cfg['webhook_token'] = bin2hex(random_bytes(20));
            $cfg += ['mock' => true];
            $q('UPDATE couriers SET api_config = ? WHERE id = ?', [json_encode($cfg), $c['id']]);
        }
    }

    // ---- settings ----
    $pdo->exec("INSERT IGNORE INTO `settings` (`group`,`key`,`value`,`type`,`label`,`is_public`) VALUES
        ('shipping','warehouse_district_id','147','int','District of our own warehouse (in-house stock ships from here)',0),
        ('shipping','store_pickup_enabled','1','bool','Customers may collect in-house orders from the store',1),
        ('shipping','store_pickup_address','','string','Store pickup address shown at checkout',1),
        ('shipping','courier_mock_mode','1','bool','Use mock courier responses (no real API calls)',0)");

    Permission::sync($pdo, false);
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE (r.slug IN ('super-admin', 'manager') AND p.module IN ('shipping', 'couriers', 'shipments'))
                    OR (r.slug = 'order-manager' AND (p.module = 'shipments' OR p.`key` IN ('couriers.view', 'shipping.view')))
                    OR (r.slug = 'accountant' AND p.`key` IN ('shipments.view', 'shipments.export', 'shipments.edit'))");
};
