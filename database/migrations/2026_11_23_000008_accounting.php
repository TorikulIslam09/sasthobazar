<?php
/**
 * Step 10 - Reports & accounting
 *  - VAT: order_items.vat_rate / vat_amount / vat_in_price, order_vendor_splits.vat_total,
 *    settings vat_inclusive, vat_label, vat_reg_no (BIN); vat_enabled / vat_rate move to the "tax" group
 *  - account_categories + account_entries (manual income / expense entries)
 *  - permissions: new module "accounting"; reports + accounting granted to super-admin, manager, accountant
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

    foreach ([
        ['order_items', 'vat_rate', "DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `promo_info`"],
        ['order_items', 'vat_amount', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `vat_rate`"],
        ['order_items', 'vat_in_price', "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = VAT included in line_total, 0 = added on top' AFTER `vat_amount`"],
        ['order_vendor_splits', 'vat_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `discount_total`"],
    ] as [$t, $c, $def]) {
        if (!$hasColumn($t, $c)) {
            $pdo->exec("ALTER TABLE `{$t}` ADD COLUMN `{$c}` {$def}");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS account_categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        type ENUM('income','expense') NOT NULL,
        name_en VARCHAR(100) NOT NULL,
        name_bn VARCHAR(100) NULL,
        slug VARCHAR(120) NOT NULL,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_account_categories_slug (slug),
        KEY idx_account_categories_type (type, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS account_entries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entry_no VARCHAR(30) NOT NULL,
        type ENUM('income','expense') NOT NULL,
        category_id INT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        entry_date DATE NOT NULL,
        payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
        reference VARCHAR(100) NULL,
        description VARCHAR(500) NULL,
        created_by BIGINT UNSIGNED NULL,
        updated_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_account_entries_no (entry_no),
        KEY idx_account_entries_date (type, entry_date),
        KEY idx_account_entries_cat (category_id),
        CONSTRAINT fk_account_entries_cat FOREIGN KEY (category_id) REFERENCES account_categories (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cats = [
        ['expense', 'Rent', 'ভাড়া', 'rent'],
        ['expense', 'Salaries & wages', 'বেতন ও মজুরি', 'salaries'],
        ['expense', 'Utilities (electricity, internet)', 'ইউটিলিটি (বিদ্যুৎ, ইন্টারনেট)', 'utilities'],
        ['expense', 'Marketing & ads', 'মার্কেটিং ও বিজ্ঞাপন', 'marketing'],
        ['expense', 'Packaging', 'প্যাকেজিং', 'packaging'],
        ['expense', 'Transport & own delivery', 'পরিবহন ও নিজস্ব ডেলিভারি', 'transport'],
        ['expense', 'Software & hosting', 'সফটওয়্যার ও হোস্টিং', 'software'],
        ['expense', 'Office & supplies', 'অফিস ও সরঞ্জাম', 'office'],
        ['expense', 'Bank & mobile banking charges', 'ব্যাংক ও মোবাইল ব্যাংকিং চার্জ', 'bank-charges'],
        ['expense', 'Taxes & licences', 'কর ও লাইসেন্স', 'taxes'],
        ['expense', 'Other expense', 'অন্যান্য খরচ', 'other-expense'],
        ['income', 'Seller registration / listing fees', 'বিক্রেতা নিবন্ধন / লিস্টিং ফি', 'seller-fees'],
        ['income', 'Advertising income', 'বিজ্ঞাপন আয়', 'ad-income'],
        ['income', 'Interest income', 'সুদ আয়', 'interest'],
        ['income', 'Scrap / other sales', 'স্ক্র্যাপ / অন্যান্য বিক্রি', 'scrap'],
        ['income', 'Other income', 'অন্যান্য আয়', 'other-income'],
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO account_categories (type, name_en, name_bn, slug, is_system, sort_order) VALUES (?, ?, ?, ?, 1, ?)');
    foreach ($cats as $i => $c) {
        $ins->execute([$c[0], $c[1], $c[2], $c[3], $i]);
    }

    // VAT / tax settings
    $pdo->exec("UPDATE settings SET `group` = 'tax' WHERE `key` IN ('vat_enabled', 'vat_rate')");
    foreach ([
        ['vat_enabled', '0', 'bool'], ['vat_rate', '0.00', 'decimal'], ['vat_inclusive', '1', 'bool'],
        ['vat_label', 'VAT', 'string'], ['vat_reg_no', '', 'string'],
    ] as [$k, $v, $type]) {
        $q("INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES ('tax', ?, ?, ?, ?, 0)", [$k, $v, $type, $k]);
    }

    Permission::sync($pdo, false);
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE (r.slug IN ('super-admin', 'manager') AND p.module IN ('accounting', 'reports'))
                    OR (r.slug = 'accountant' AND (p.module = 'reports' OR (p.module = 'accounting' AND p.action <> 'configure')))");
};
