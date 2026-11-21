<?php
/**
 * Step 3 - Catalog management
 *  - products: usage_instructions, meta_keywords
 *  - brands:   description, website, meta_title, meta_description
 *  - search indexes for the admin product list (sku, updated)
 * Safe to re-run: every change checks information_schema first.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $q = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $q->execute([$table, $column]);
        return (int) $q->fetchColumn() > 0;
    };
    $hasIndex = static function (string $table, string $index) use ($pdo): bool {
        $q = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $q->execute([$table, $index]);
        return (int) $q->fetchColumn() > 0;
    };

    $columns = [
        ['products', 'usage_instructions', 'TEXT NULL COMMENT "dosage & administration" AFTER `indications`'],
        ['products', 'meta_keywords',      'VARCHAR(255) NULL AFTER `meta_description`'],
        ['brands',   'description',        'TEXT NULL AFTER `logo`'],
        ['brands',   'website',            'VARCHAR(255) NULL AFTER `country`'],
        ['brands',   'meta_title',         'VARCHAR(255) NULL AFTER `is_active`'],
        ['brands',   'meta_description',   'VARCHAR(500) NULL AFTER `meta_title`'],
    ];
    foreach ($columns as [$table, $column, $definition]) {
        if (!$hasColumn($table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    if (!$hasIndex('products', 'idx_products_sku')) {
        $pdo->exec('ALTER TABLE `products` ADD INDEX `idx_products_sku` (`sku`)');
    }
    if (!$hasIndex('products', 'idx_products_deleted_updated')) {
        $pdo->exec('ALTER TABLE `products` ADD INDEX `idx_products_deleted_updated` (`deleted_at`, `updated_at`)');
    }
};
