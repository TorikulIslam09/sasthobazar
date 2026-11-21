<?php
/**
 * Migrator - applies database/migrations/* once each, in filename order.
 *   *.sql  : plain SQL statements
 *   *.php  : must `return function (PDO $pdo): void { ... };`
 * Applied migrations are recorded in the `migrations` table.
 *
 * Run: C:\xampp\php\php.exe database\migrate.php      (the installer also runs them)
 */
declare(strict_types=1);

final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir)
    {
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT UNSIGNED NOT NULL,
                `ran_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migrations_name` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return string[] file names not yet applied */
    public function pending(): array
    {
        $this->ensureTable();
        $done = $this->pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = array_merge(glob($this->dir . '/*.sql') ?: [], glob($this->dir . '/*.php') ?: []);
        $names = array_map('basename', $files);
        sort($names, SORT_STRING);
        return array_values(array_diff($names, $done));
    }

    /** @return string[] applied file names */
    public function run(?callable $log = null): array
    {
        $pending = $this->pending();
        if (!$pending) {
            return [];
        }
        $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
        $applied = [];
        foreach ($pending as $name) {
            $file = $this->dir . '/' . $name;
            $log && $log("Migrating: {$name}");
            if (str_ends_with($name, '.sql')) {
                SqlImporter::runFile($this->pdo, $file);
            } else {
                $fn = require $file;
                if (!is_callable($fn)) {
                    throw new RuntimeException("Migration {$name} must return a callable");
                }
                $fn($this->pdo);
            }
            $stmt = $this->pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
            $stmt->execute([$name, $batch]);
            $applied[] = $name;
            $log && $log("Migrated:  {$name}");
        }
        return $applied;
    }
}
