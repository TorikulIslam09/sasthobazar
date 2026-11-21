<?php
/**
 * Step 6 - Cart & ordering
 *  - orders.status / order_vendor_splits.status: + 'prescription_review' (Rx orders wait for a pharmacist)
 *  - carts: index for guest carts (session token) + variant
 *  - settings: manual mobile-banking numbers (bKash / Nagad / Rocket), rocket_enabled
 *  - permissions synced; orders / prescriptions / refunds / coupons / settings granted to the matching staff roles
 * Every step checks information_schema first, so the migration can be re-run safely.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $q = static function (string $sql, array $p = []) use ($pdo) {
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st;
    };
    $columnType = static fn(string $t, string $c): string => (string) $q(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]
    )->fetchColumn();
    $hasIndex = static fn(string $t, string $i): bool => (int) $q(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?', [$t, $i]
    )->fetchColumn() > 0;

    $statuses = "ENUM('pending','prescription_review','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','failed')";
    foreach (['orders', 'order_vendor_splits'] as $table) {
        if (!str_contains($columnType($table, 'status'), 'prescription_review')) {
            $pdo->exec("ALTER TABLE `{$table}` MODIFY `status` {$statuses} NOT NULL DEFAULT 'pending'");
        }
    }

    if (!$hasIndex('carts', 'idx_carts_session_product')) {
        $pdo->exec('ALTER TABLE `carts` ADD INDEX `idx_carts_session_product` (`session_id`, `product_id`)');
    }
    if (!$hasIndex('order_status_history', 'idx_osh_order_id')) {
        $pdo->exec('ALTER TABLE `order_status_history` ADD INDEX `idx_osh_order_id` (`order_id`, `id`)');
    }

    $pdo->exec("INSERT IGNORE INTO `settings` (`group`,`key`,`value`,`type`,`label`,`is_public`) VALUES
        ('payment','rocket_enabled','0','bool','Rocket (manual TrxID) enabled',1),
        ('payment','bkash_number','','string','bKash number customers send money to',1),
        ('payment','nagad_number','','string','Nagad number customers send money to',1),
        ('payment','rocket_number','','string','Rocket number customers send money to',1),
        ('payment','bkash_account_type','personal','string','bKash account type (personal / merchant / agent)',1),
        ('payment','nagad_account_type','personal','string','Nagad account type',1),
        ('payment','rocket_account_type','personal','string','Rocket account type',1)");

    Permission::sync($pdo, false);
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE (r.slug IN ('super-admin', 'manager') AND p.module IN ('orders', 'prescriptions', 'refunds', 'coupons', 'settings'))
                    OR (r.slug = 'order-manager' AND (p.module = 'orders' OR p.`key` IN ('prescriptions.view', 'refunds.view', 'refunds.create', 'coupons.view')))
                    OR (r.slug = 'pharmacist' AND (p.module = 'prescriptions' OR p.`key` IN ('orders.view')))
                    OR (r.slug = 'accountant' AND (p.module = 'refunds' OR p.`key` IN ('orders.view', 'orders.export', 'coupons.view')))
                    OR (r.slug = 'support-agent' AND p.`key` IN ('orders.view', 'refunds.view', 'refunds.create'))");
};
