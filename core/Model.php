<?php
/**
 * Base model - simple table gateway. Subclasses set $table.
 * create / updateById / deleteById are written to the audit log automatically
 * (set `protected static bool $audited = false;` in a subclass to opt out).
 */
declare(strict_types=1);

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $audited = true;

    protected static function db(): Database
    {
        return Database::getInstance();
    }

    public static function find(int $id): ?array
    {
        return static::db()->fetch(
            sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', Database::ident(static::$table), Database::ident(static::$primaryKey)),
            ['id' => $id]
        );
    }

    /** where(['status' => 'active'], 'name ASC', 20) */
    public static function where(array $conditions = [], string $orderBy = '', int $limit = 0): array
    {
        [$whereSql, $params] = self::buildWhere($conditions);
        $sql = 'SELECT * FROM ' . Database::ident(static::$table) . $whereSql;
        if ($orderBy !== '' && preg_match('/^[A-Za-z0-9_`., ]+$/', $orderBy)) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        return static::db()->fetchAll($sql, $params);
    }

    public static function count(array $conditions = []): int
    {
        [$whereSql, $params] = self::buildWhere($conditions);
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM ' . Database::ident(static::$table) . $whereSql, $params);
    }

    private static function buildWhere(array $conditions): array
    {
        if (!$conditions) {
            return ['', []];
        }
        $parts = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            if ($val === null) {
                $parts[] = Database::ident($col) . ' IS NULL';
            } else {
                $parts[] = Database::ident($col) . ' = :w_' . $col;
                $params['w_' . $col] = $val;
            }
        }
        return [' WHERE ' . implode(' AND ', $parts), $params];
    }

    public static function create(array $data): int
    {
        $id = static::db()->insert(static::$table, $data);
        if (static::$audited) {
            Audit::log(static::$table . '.create', static::$table, $id, null, $data);
        }
        return $id;
    }

    public static function updateById(int $id, array $data): int
    {
        $old = static::$audited ? static::find($id) : null;
        $n = static::db()->update(static::$table, $data, Database::ident(static::$primaryKey) . ' = :pk', ['pk' => $id]);
        if (static::$audited && $old) {
            [$o, $nw] = Audit::diff($old, $data);
            if ($nw) {
                Audit::log(static::$table . '.update', static::$table, $id, $o, $nw);
            }
        }
        return $n;
    }

    public static function deleteById(int $id): int
    {
        $old = static::$audited ? static::find($id) : null;
        $n = static::db()->delete(static::$table, Database::ident(static::$primaryKey) . ' = :pk', ['pk' => $id]);
        if (static::$audited && $old && $n > 0) {
            Audit::log(static::$table . '.delete', static::$table, $id, $old, null);
        }
        return $n;
    }
}
