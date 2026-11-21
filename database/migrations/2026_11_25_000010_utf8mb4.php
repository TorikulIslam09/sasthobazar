<?php
/**
 * Make sure the database, every table and every text column use utf8mb4 / utf8mb4_unicode_ci
 * (Bangla text needs utf8mb4). Nothing changes on a correct database; on a server where the database or a
 * table was created as latin1 / utf8 (for example by a phpMyAdmin import with the wrong defaults) it converts them.
 * JSON columns stay utf8mb4_bin (that is how MariaDB / MySQL store JSON).
 * Converting the structure does NOT bring back text already stored as "????" - run database/repair_text.php.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db = $pdo->query('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()')->fetch(PDO::FETCH_NUM);
    if ($db && ($db[0] !== 'utf8mb4' || $db[1] !== 'utf8mb4_unicode_ci')) {
        $pdo->exec('ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    // tables whose default or any text column is not utf8mb4 (JSON columns with utf8mb4_bin are fine)
    $tables = $pdo->query(
        "SELECT DISTINCT t.TABLE_NAME FROM information_schema.TABLES t
           LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.CHARACTER_SET_NAME IS NOT NULL
          WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
            AND (t.TABLE_COLLATION <> 'utf8mb4_unicode_ci' OR (c.CHARACTER_SET_NAME <> 'utf8mb4'))"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        return;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $t) {
            // remember JSON-style (utf8mb4_bin) columns so they can be put back after CONVERT
            $bin = $pdo->query(
                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($t) . " AND COLLATION_NAME = 'utf8mb4_bin'"
            )->fetchAll(PDO::FETCH_ASSOC);
            $pdo->exec("ALTER TABLE `{$t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            foreach ($bin as $c) {
                $pdo->exec("ALTER TABLE `{$t}` MODIFY `{$c['COLUMN_NAME']}` {$c['COLUMN_TYPE']} CHARACTER SET utf8mb4 COLLATE utf8mb4_bin "
                    . ($c['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL'));
            }
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
};
