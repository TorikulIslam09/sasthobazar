<?php
/**
 * Restore Bengali text that was saved as "?????" (lost when text went through a non-UTF-8 connection, terminal or import).
 *
 *   C:\xampp\php\php.exe database\repair_text.php            show what would be restored (nothing is changed)
 *   C:\xampp\php\php.exe database\repair_text.php --apply    restore it
 *
 * Text saved as "?" is gone - the original letters are not in the database any more. This tool takes the
 * original values from database/seed.sql (settings, roles, categories, pages, shipping zones, couriers, locations)
 * and puts them back ONLY where the current value contains "??". Anything else (products, shops, customer data
 * typed in later) is listed at the end and has to be typed in again (UTF-8 is fine now).
 *
 * How: every INSERT of a seeded table is run into a TEMPORARY copy of that table, then matching rows are compared.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('SKIP_INSTALL_CHECK', true);
require dirname(__DIR__) . '/core/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$pdo = Database::getInstance()->pdo();
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

// seeded table => column(s) that identify the same row in the live table
$keys = [
    'settings'       => ['key'],
    'roles'          => ['slug'],
    'categories'     => ['slug'],
    'pages'          => ['slug'],
    'shipping_zones' => ['id'],
    'couriers'       => ['code'],
    'locations'      => ['id'],
    'permissions'    => ['key'],
];
$isBroken = static fn(?string $v): bool => $v !== null && str_contains($v, '??');

$seed = (string) file_get_contents(ROOT_PATH . '/database/seed.sql');
if (!mb_check_encoding($seed, 'UTF-8') || str_contains($seed, '??')) {
    fwrite(STDERR, "database/seed.sql itself is not clean UTF-8 - restore it from the original project files first.\n");
    exit(1);
}

$restored = 0;
$found = 0;
foreach (SqlImporter::split($seed) as $stmt) {
    if (!preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i', $stmt, $m) || !isset($keys[$m[1]])) {
        continue;
    }
    $table = $m[1];
    $tmp = 'tmp_seed_' . $table;
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS `{$tmp}`");
    $pdo->exec("CREATE TEMPORARY TABLE `{$tmp}` LIKE `{$table}`");
    // temporary copy: no foreign keys / unique clashes with the real data
    $sql = preg_replace('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?' . $table . '`?/i', "INSERT IGNORE INTO `{$tmp}`", $stmt, 1);
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        echo "skip {$table}: " . $e->getMessage() . "\n";
        continue;
    }
    $textCols = $pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table)
        . " AND DATA_TYPE IN ('varchar','char','text','tinytext','mediumtext','longtext') AND COLLATION_NAME <> 'utf8mb4_bin'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $on = implode(' AND ', array_map(static fn($k) => "l.`{$k}` = s.`{$k}`", $keys[$table]));
    foreach ($textCols as $col) {
        $rows = $pdo->query(
            "SELECT " . implode(', ', array_map(static fn($k) => "l.`{$k}` AS k_{$k}", $keys[$table])) . ", l.`{$col}` AS now_v, s.`{$col}` AS seed_v
               FROM `{$table}` l JOIN `{$tmp}` s ON {$on} WHERE l.`{$col}` LIKE '%??%'"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $found++;
            $id = implode('/', array_map(static fn($k) => $r['k_' . $k], $keys[$table]));
            if ($isBroken($r['seed_v'])) {
                echo "  {$table}.{$col} [{$id}]: no clean original in seed.sql\n";
                continue;
            }
            echo ($apply ? 'restored ' : 'would restore ') . "{$table}.{$col} [{$id}]: \"" . mb_substr((string) $r['now_v'], 0, 30) . '" -> "' . mb_substr((string) $r['seed_v'], 0, 60) . "\"\n";
            if ($apply) {
                $where = implode(' AND ', array_map(static fn($k) => "`{$k}` = ?", $keys[$table]));
                $st = $pdo->prepare("UPDATE `{$table}` SET `{$col}` = ? WHERE {$where} AND `{$col}` LIKE '%??%'");
                $st->execute(array_merge([$r['seed_v']], array_map(static fn($k) => $r['k_' . $k], $keys[$table])));
                $restored += $st->rowCount();
            }
        }
    }
}

// everything else that still contains "??" (cannot be restored automatically)
echo "\nOther text that contains \"??\" (type it in again; JSON / log columns ignored):\n";
$left = 0;
$cols = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND DATA_TYPE IN ('varchar','char','text','tinytext','mediumtext','longtext') AND COLLATION_NAME <> 'utf8mb4_bin'
        AND TABLE_NAME NOT IN ('audit_logs','message_logs','migrations','rate_limits')"
)->fetchAll(PDO::FETCH_NUM);
foreach ($cols as [$t, $c]) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}` WHERE `{$c}` LIKE '%???%'")->fetchColumn();
    if ($n > 0) {
        $left += $n;
        echo "  {$t}.{$c}: {$n} row(s)\n";
    }
}
if ($left === 0) {
    echo "  none\n";
}
Helpers::clearSettingsCache();
echo "\n" . ($apply ? "Restored {$restored} value(s)." : "Found {$found} restorable value(s). Run again with --apply to restore them.") . "\n";
