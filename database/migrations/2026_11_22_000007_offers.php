<?php
/**
 * Step 9 - Offer management
 *  - offers: kind (price / bogo / first_order / free_shipping), buy/get quantities, min order, who bears the cost
 *    (funded_by + vendor_share_percent), approval workflow for vendor requests (status, requested_by, review)
 *  - coupons / flash_sales: vendor_share_percent (+ funded_by for flash sales)
 *  - order_items: offer_discount (price-offer / flash saving), vendor_funded, platform_funded, promo_info
 *  - orders: promo_discount (buy-X-get-Y + first-order offer)
 *  - banners: Bangla title / subtitle, button text, popup delay
 *  - coupons WELCOME10 and FLAT50 (kept if they already exist)
 *  - permissions synced; offers / flash sales / banners / coupons granted
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

    $cols = [
        ['offers', 'kind', "ENUM('price','bogo','first_order','free_shipping') NOT NULL DEFAULT 'price' AFTER `description`"],
        ['offers', 'buy_qty', 'SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `max_discount`'],
        ['offers', 'get_qty', 'SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `buy_qty`'],
        ['offers', 'min_order_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `get_qty`'],
        ['offers', 'funded_by', "ENUM('platform','vendor','shared') NOT NULL DEFAULT 'platform' AFTER `vendor_id`"],
        ['offers', 'vendor_share_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER `funded_by`'],
        ['offers', 'status', "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER `is_active`"],
        ['offers', 'requested_by', 'BIGINT UNSIGNED NULL AFTER `status`'],
        ['offers', 'reviewed_by', 'BIGINT UNSIGNED NULL AFTER `requested_by`'],
        ['offers', 'reviewed_at', 'DATETIME NULL AFTER `reviewed_by`'],
        ['offers', 'review_note', 'VARCHAR(255) NULL AFTER `reviewed_at`'],
        ['coupons', 'vendor_share_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER `funded_by`'],
        ['flash_sales', 'funded_by', "ENUM('platform','vendor','shared') NOT NULL DEFAULT 'platform' AFTER `banner`"],
        ['flash_sales', 'vendor_share_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER `funded_by`'],
        ['order_items', 'offer_discount', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'flash / price-offer saving already inside unit_price' AFTER `discount_amount`"],
        ['order_items', 'vendor_funded', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'part of all discounts borne by the vendor' AFTER `offer_discount`"],
        ['order_items', 'platform_funded', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'part borne by the platform' AFTER `vendor_funded`"],
        ['order_items', 'promo_info', 'JSON NULL AFTER `platform_funded`'],
        ['orders', 'promo_discount', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'buy-X-get-Y + first-order offer' AFTER `coupon_discount`"],
        ['banners', 'title_bn', 'VARCHAR(200) NULL AFTER `title`'],
        ['banners', 'subtitle_bn', 'VARCHAR(255) NULL AFTER `subtitle`'],
        ['banners', 'button_text', 'VARCHAR(60) NULL AFTER `link_url`'],
        ['banners', 'popup_delay', 'SMALLINT UNSIGNED NOT NULL DEFAULT 3 AFTER `button_text`'],
    ];
    foreach ($cols as [$t, $c, $def]) {
        if (!$hasColumn($t, $c)) {
            $pdo->exec("ALTER TABLE `{$t}` ADD COLUMN `{$c}` {$def}");
        }
    }

    // coupons the shop wants to keep
    $pdo->exec("INSERT IGNORE INTO coupons (code, description, type, value, max_discount, min_order_amount, usage_limit_per_user, first_order_only, funded_by, applies_to, is_active)
                VALUES ('WELCOME10', '10% off your first order (up to ৳150)', 'percent', 10, 150, 0, 1, 1, 'platform', 'all', 1),
                       ('FLAT50', '৳50 off on orders of ৳500 or more', 'fixed', 50, NULL, 500, 3, 0, 'platform', 'all', 1)");

    Permission::sync($pdo, false);
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE (r.slug IN ('super-admin', 'manager') AND p.module IN ('offers', 'flash_sales', 'banners', 'coupons'))
                    OR (r.slug = 'content-editor' AND p.module IN ('banners'))
                    OR (r.slug = 'order-manager' AND p.`key` IN ('offers.view', 'flash_sales.view'))");
};
