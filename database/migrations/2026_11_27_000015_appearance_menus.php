<?php
/**
 * Appearance: editable menus + header settings.
 *  - menu_items: links for header (bar under the search), mobile (phone menu), footer_1 (Quick links), footer_2 (Information).
 *    Seeded with the links the site had built in, so nothing changes until an admin edits them.
 *  - settings (group appearance): announcement bar, search placeholder, header buttons, brand colours, footer column titles.
 *  - permission module "appearance" (view / edit) - synced from config/modules.php.
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        location ENUM('header','mobile','footer_1','footer_2') NOT NULL,
        label_en VARCHAR(100) NOT NULL,
        label_bn VARCHAR(100) NULL,
        url VARCHAR(255) NOT NULL,
        icon VARCHAR(50) NULL,
        new_tab TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        KEY idx_menu_location (location, is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!(int) $pdo->query('SELECT COUNT(*) FROM menu_items')->fetchColumn()) {
        $seed = [
            // location, en, bn, url, icon
            ['header', 'Home', 'হোম', '/', null],
            ['header', 'All products', 'সব পণ্য', 'products', null],
            ['mobile', 'All products', 'সব পণ্য', 'products', 'bi-grid-3x3-gap'],
            ['mobile', "Today's offers", 'আজকের অফার', 'offers', 'bi-lightning-charge'],
            ['mobile', 'Upload prescription', 'প্রেসক্রিপশন আপলোড', 'prescription', 'bi-file-earmark-medical'],
            ['footer_1', 'All products', 'সব পণ্য', 'products', null],
            ['footer_1', "Today's offers", 'আজকের অফার', 'offers', null],
            ['footer_1', 'New arrivals', 'নতুন পণ্য', 'products?sort=newest', null],
            ['footer_1', 'Track order', 'অর্ডার ট্র্যাক', 'track', null],
            ['footer_1', 'Upload prescription', 'প্রেসক্রিপশন আপলোড', 'prescription', null],
            ['footer_2', 'FAQ', 'সাধারণ প্রশ্ন', 'faq', null],
            ['footer_2', 'Health tips', 'স্বাস্থ্য টিপস', 'blog', null],
        ];
        $ins = $pdo->prepare('INSERT INTO menu_items (location, label_en, label_bn, url, icon, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        $sort = [];
        foreach ($seed as [$loc, $en, $bn, $u, $icon]) {
            $sort[$loc] = ($sort[$loc] ?? 0) + 10;
            $ins->execute([$loc, $en, $bn, $u, $icon, $sort[$loc]]);
        }
    }

    $settings = [
        ['announce_enabled', '1', 'bool'], ['announce_text_en', '', 'string'], ['announce_text_bn', '', 'string'], ['announce_link', '', 'string'],
        ['announce_show_hotline', '1', 'bool'], ['announce_show_lang', '1', 'bool'],
        ['search_placeholder_en', '', 'string'], ['search_placeholder_bn', '', 'string'],
        ['header_show_rx', '1', 'bool'], ['header_show_offers', '1', 'bool'], ['header_offers_label_en', '', 'string'], ['header_offers_label_bn', '', 'string'],
        ['header_offers_link', 'offers', 'string'], ['header_show_wishlist', '1', 'bool'],
        ['header_show_categories', '1', 'bool'], ['header_category_count', '8', 'int'],
        ['theme_brand', '#0e7673', 'string'], ['theme_brand_dark', '#0a5a58', 'string'], ['theme_accent', '#f0663a', 'string'],
        ['footer_1_title_en', '', 'string'], ['footer_1_title_bn', '', 'string'], ['footer_2_title_en', '', 'string'], ['footer_2_title_bn', '', 'string'],
        ['footer_show_categories', '1', 'bool'], ['footer_copyright_en', '', 'string'], ['footer_copyright_bn', '', 'string'],
    ];
    $st = $pdo->prepare("INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES ('appearance', ?, ?, ?, ?, 0)");
    foreach ($settings as [$k, $v, $t]) {
        $st->execute([$k, $v, $t, $k]);
    }

    Permission::sync($pdo, false);
};
