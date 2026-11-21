<?php
/**
 * Run pending database migrations (database/migrations/*).
 *
 *   cd C:\xampp\htdocs\sasthobazar
 *   C:\xampp\php\php.exe database\migrate.php            run pending migrations
 *   C:\xampp\php\php.exe database\migrate.php --status   list pending migrations
 *
 * Command line only. Safe to run many times (each migration runs once).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/core/bootstrap.php';

$migrator = new Migrator(Database::getInstance()->pdo(), __DIR__ . '/migrations');

if (in_array('--status', $argv, true)) {
    $pending = $migrator->pending();
    echo $pending ? "Pending:\n  " . implode("\n  ", $pending) . "\n" : "Nothing to migrate.\n";
    exit(0);
}

$applied = $migrator->run(static fn(string $msg) => print($msg . PHP_EOL));
echo $applied ? 'Done: ' . count($applied) . " migration(s) applied.\n" : "Nothing to migrate.\n";
