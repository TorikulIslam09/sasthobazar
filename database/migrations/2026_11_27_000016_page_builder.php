<?php
/**
 * Page builder: custom sections on any storefront page.
 *  - page_sections: page_key (home | all | products | product | offers | cart | prescription | page:{slug}),
 *    position (top | after_slider | after_categories | bottom), type, titles, settings / style JSON, schedule, order, on/off
 *  - settings: customer_cancel_until (orders), footer order-tracking bar (appearance)
 *  - permission module "page_builder" (view / create / edit / delete)
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_sections (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        page_key VARCHAR(200) NOT NULL,
        position VARCHAR(30) NOT NULL DEFAULT 'bottom',
        type VARCHAR(30) NOT NULL,
        admin_label VARCHAR(120) NULL,
        title_en VARCHAR(200) NULL,
        title_bn VARCHAR(200) NULL,
        settings MEDIUMTEXT NULL COMMENT 'JSON, fields depend on the type',
        style TEXT NULL COMMENT 'JSON: bg, color, padding, width, show_on',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        KEY idx_sections_page (page_key, position, is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $st = $pdo->prepare('INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES (?, ?, ?, ?, ?, 0)');
    foreach ([['order', 'customer_cancel_until', 'confirmed', 'string'], ['appearance', 'footer_track_enabled', '1', 'bool'],
              ['appearance', 'footer_track_title_en', '', 'string'], ['appearance', 'footer_track_title_bn', '', 'string']] as [$g, $k, $v, $t]) {
        $st->execute([$g, $k, $v, $t, $k]);
    }

    Permission::sync($pdo, false);
};
