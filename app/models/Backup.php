<?php
declare(strict_types=1);

/**
 * Database backup / restore (Settings → Backup), plain PHP + PDO - no mysqldump needed.
 *
 * Backups are gzip-compressed SQL files in storage/backups (not reachable from the web; storage/.htaccess denies all):
 *   -- SasthoBazar database backup ... then for each table DROP TABLE IF EXISTS / CREATE TABLE / INSERT batches.
 * Restore accepts a .sql or .sql.gz made here (the first line must be the SasthoBazar header). A safety backup of
 * the current database is taken first; if any statement fails, that safety backup is restored automatically.
 * Uploaded images (public/uploads) and private files (storage/private) are not part of the database backup.
 */
final class Backup
{
    public const HEADER = '-- SasthoBazar database backup';
    private const SKIP_DATA = ['rate_limits'];

    public static function dir(): string
    {
        $dir = ROOT_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }
        return $dir;
    }

    /** @return array<int, array{name: string, size: int, time: int}> newest first */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.sql.gz') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, static fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    /** Absolute path of a backup by file name (only names made here are accepted), or null. */
    public static function path(string $name): ?string
    {
        if (!preg_match('/^[a-z0-9-]+\.sql\.gz$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name;
        return is_file($p) ? $p : null;
    }

    /** Write a full backup; returns the file name. */
    public static function create(string $label = 'manual'): string
    {
        @set_time_limit(0);
        $pdo = Database::getInstance()->pdo();
        $name = 'sasthobazar-' . date('Ymd-His') . '-' . preg_replace('/[^a-z0-9]/', '', strtolower($label)) . '-' . bin2hex(random_bytes(3)) . '.sql.gz';
        $file = self::dir() . '/' . $name;
        $gz = gzopen($file, 'wb6');
        if (!$gz) {
            throw new RuntimeException('Cannot write the backup file');
        }
        try {
            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            gzwrite($gz, self::HEADER . "\n-- Created: " . date('Y-m-d H:i:s') . "\n-- Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn()
                . "\n-- Tables: " . count($tables) . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
            foreach ($tables as [$table]) {
                $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM)[1];
                gzwrite($gz, "-- Table `{$table}`\nDROP TABLE IF EXISTS `{$table}`;\n{$create};\n");
                if (in_array($table, self::SKIP_DATA, true)) {
                    gzwrite($gz, "\n");
                    continue;
                }
                $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`', PDO::FETCH_ASSOC);
                $batch = [];
                $cols = null;
                while ($row = $stmt->fetch()) {
                    $cols ??= '(`' . implode('`, `', array_keys($row)) . '`)';
                    $batch[] = '(' . implode(', ', array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                    if (count($batch) >= 200) {
                        gzwrite($gz, "INSERT INTO `{$table}` {$cols} VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    gzwrite($gz, "INSERT INTO `{$table}` {$cols} VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n-- End of backup\n");
        } catch (Throwable $e) {
            gzclose($gz);
            @unlink($file);
            throw $e;
        }
        gzclose($gz);
        return $name;
    }

    /** Read a backup / uploaded file (.sql or .sql.gz) and check the header. */
    public static function read(string $file): string
    {
        $raw = (string) file_get_contents($file);
        if (str_starts_with($raw, "\x1f\x8b")) {
            $raw = (string) @gzdecode($raw);
        }
        if (!str_starts_with(ltrim($raw, "\xEF\xBB\xBF"), self::HEADER)) {
            throw new RuntimeException(__('backup_not_ours'));
        }
        return $raw;
    }

    /**
     * Replace the database with a backup. Takes a safety backup first and puts it back if anything fails.
     * @return array{statements: int, safety: string}
     */
    public static function restore(string $file): array
    {
        @set_time_limit(0);
        $sql = self::read($file);
        $safety = self::create('before-restore');
        $pdo = Database::getInstance()->pdo();
        try {
            $n = self::run($pdo, $sql);
        } catch (Throwable $e) {
            try {
                self::run($pdo, self::read(self::dir() . '/' . $safety));
            } catch (Throwable $e2) {
                throw new RuntimeException(__('backup_restore_failed_hard', ['file' => $safety]) . ' ' . $e2->getMessage(), 0, $e);
            }
            throw new RuntimeException(__('backup_restore_rolled_back') . ' ' . $e->getMessage(), 0, $e);
        }
        Helpers::clearSettingsCache();
        return ['statements' => $n, 'safety' => $safety];
    }

    private static function run(PDO $pdo, string $sql): int
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $n = 0;
        try {
            foreach (SqlImporter::split($sql) as $stmt) {
                $pdo->exec($stmt);
                $n++;
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        return $n;
    }

    public static function delete(string $name): bool
    {
        $p = self::path($name);
        return $p !== null && @unlink($p);
    }
}
