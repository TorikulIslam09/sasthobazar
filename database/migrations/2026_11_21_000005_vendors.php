<?php
/**
 * Step 7 - Multi-vendor
 *  - order_vendor_splits: delivered_at, cancelled_at, release_at (earning becomes withdrawable), tracking_note
 *  - order_items: commission_scope (product / category / vendor / global)
 *  - vendors: suspension_reason, pickup_phone
 *  - vendor_wallet_transactions.balance_after = available + pending after the entry (documented, no change)
 *  - settings: payout_hold_days / payout_min_amount already exist; vendor_can_cancel added
 *  - permissions synced; vendors / payouts / commissions / reports granted to the matching staff roles
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
    $hasIndex = static fn(string $t, string $i): bool => (int) $q(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?', [$t, $i]
    )->fetchColumn() > 0;

    $columns = [
        ['order_vendor_splits', 'delivered_at', 'DATETIME NULL AFTER `settled_at`'],
        ['order_vendor_splits', 'cancelled_at', 'DATETIME NULL AFTER `delivered_at`'],
        ['order_vendor_splits', 'release_at', 'DATETIME NULL COMMENT "earning becomes withdrawable" AFTER `cancelled_at`'],
        ['order_vendor_splits', 'tracking_note', 'VARCHAR(255) NULL AFTER `vendor_note`'],
        ['order_items', 'commission_scope', "VARCHAR(20) NULL COMMENT 'product / category / vendor / global' AFTER `commission_type`"],
        ['vendors', 'suspension_reason', 'VARCHAR(255) NULL AFTER `rejection_reason`'],
        ['vendors', 'pickup_phone', 'VARCHAR(20) NULL AFTER `address`'],
    ];
    foreach ($columns as [$t, $c, $def]) {
        if (!$hasColumn($t, $c)) {
            $pdo->exec("ALTER TABLE `{$t}` ADD COLUMN `{$c}` {$def}");
        }
    }
    if (!$hasIndex('order_vendor_splits', 'idx_splits_release')) {
        $pdo->exec('ALTER TABLE `order_vendor_splits` ADD INDEX `idx_splits_release` (`vendor_id`, `settlement_status`, `release_at`)');
    }
    if (!$hasIndex('order_vendor_splits', 'idx_splits_delivered')) {
        $pdo->exec('ALTER TABLE `order_vendor_splits` ADD INDEX `idx_splits_delivered` (`vendor_id`, `delivered_at`)');
    }
    if (!$hasIndex('vendor_wallet_transactions', 'idx_vwt_vendor_id')) {
        $pdo->exec('ALTER TABLE `vendor_wallet_transactions` ADD INDEX `idx_vwt_vendor_id` (`vendor_id`, `id`)');
    }

    $pdo->exec("INSERT IGNORE INTO `settings` (`group`,`key`,`value`,`type`,`label`,`is_public`) VALUES
        ('vendor','vendor_can_cancel','1','bool','Vendors may cancel their confirmed sub-orders',0)");

    Permission::sync($pdo, false);
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE (r.slug IN ('super-admin', 'manager') AND p.module IN ('vendors', 'vendor_payouts', 'commissions', 'reports'))
                    OR (r.slug = 'accountant' AND (p.module IN ('vendor_payouts', 'reports') OR p.`key` IN ('vendors.view', 'commissions.view', 'commissions.export')))
                    OR (r.slug = 'order-manager' AND p.`key` IN ('vendors.view', 'reports.view'))");
};
