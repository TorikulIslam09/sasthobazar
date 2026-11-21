<?php
/**
 * Database - PDO singleton with small query helpers.
 * All queries use prepared statements. Identifiers passed to insert/update are whitelisted by regex.
 */
declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $txDepth = 0;

    private function __construct(array $c)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], (int) ($c['port'] ?? 3306), $c['database'], $c['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+06:00'");
    }

    private function __clone() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(config('db'));
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Prepare + execute. Integers are bound as PARAM_INT (needed for LIMIT/OFFSET). */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $name = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k);
            $type = match (true) {
                is_int($v)  => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                $v === null => PDO::PARAM_NULL,
                default     => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $v, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** insert('users', ['name' => 'x']) => new id */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::ident($table),
            implode(', ', array_map([self::class, 'ident'], $cols)),
            implode(', ', array_map(static fn($c) => ':' . $c, $cols))
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** update('users', ['name'=>'x'], 'id = :id', ['id'=>5]) => affected rows */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = self::ident($col) . ' = :set_' . $col;
            $params['set_' . $col] = $val;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', self::ident($table), implode(', ', $set), $where);
        return $this->query($sql, array_merge($params, $whereParams))->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM %s WHERE %s', self::ident($table), $where), $params)->rowCount();
    }

    /* ---------------- Transactions (nesting-safe) ---------------- */

    public function beginTransaction(): void
    {
        if ($this->txDepth === 0) {
            $this->pdo->beginTransaction();
        }
        $this->txDepth++;
    }

    public function commit(): void
    {
        $this->txDepth = max(0, $this->txDepth - 1);
        if ($this->txDepth === 0 && $this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        $this->txDepth = 0;
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Run $fn inside a transaction. Commits on success, rolls back and rethrows on any error.
     * Use for every order / stock / wallet update:
     *   $db->transaction(function (Database $db) { ... SELECT ... FOR UPDATE ... });
     */
    public function transaction(callable $fn): mixed
    {
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Invalid SQL identifier: ' . $name);
        }
        return '`' . $name . '`';
    }
}
