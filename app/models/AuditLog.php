<?php
declare(strict_types=1);

class AuditLog extends Model
{
    protected static string $table = 'audit_logs';
    protected static bool $audited = false;

    private static function filterWhere(array $f): array
    {
        $where = ['1=1'];
        $p = [];
        if (!empty($f['user_id'])) {
            $where[] = 'a.user_id = :uid';
            $p['uid'] = (int) $f['user_id'];
        }
        if (($f['action'] ?? '') !== '') {
            $where[] = 'a.action LIKE :action';
            $p['action'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['action']) . '%';
        }
        if (($f['entity_type'] ?? '') !== '') {
            $where[] = 'a.entity_type = :etype';
            $p['etype'] = $f['entity_type'];
        }
        if (($f['entity_id'] ?? '') !== '' && ctype_digit((string) $f['entity_id'])) {
            $where[] = 'a.entity_id = :eid';
            $p['eid'] = (int) $f['entity_id'];
        }
        if (($f['ip'] ?? '') !== '') {
            $where[] = 'a.ip_address = :ip';
            $p['ip'] = $f['ip'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['from'] ?? ''))) {
            $where[] = 'a.created_at >= :from';
            $p['from'] = $f['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['to'] ?? ''))) {
            $where[] = 'a.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
            $p['to'] = $f['to'];
        }
        return [implode(' AND ', $where), $p];
    }

    public static function countFiltered(array $f): int
    {
        [$w, $p] = self::filterWhere($f);
        return (int) static::db()->fetchColumn("SELECT COUNT(*) FROM audit_logs a WHERE {$w}", $p);
    }

    public static function filtered(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::filterWhere($f);
        return static::db()->fetchAll(
            "SELECT a.*, u.name AS user_name, u.email AS user_email
               FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
              WHERE {$w}
           ORDER BY a.id DESC
              LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    /** Generator for CSV export (memory-friendly). */
    public static function iterate(array $f, int $max = 50000): Generator
    {
        [$w, $p] = self::filterWhere($f);
        $stmt = static::db()->query(
            "SELECT a.id, a.created_at, u.name AS user_name, a.user_id, a.action, a.entity_type, a.entity_id,
                    a.ip_address, a.old_values, a.new_values, a.user_agent
               FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
              WHERE {$w} ORDER BY a.id DESC LIMIT {$max}",
            $p
        );
        while ($r = $stmt->fetch()) {
            yield $r;
        }
    }

    public static function findWithUser(int $id): ?array
    {
        return static::db()->fetch(
            'SELECT a.*, u.name AS user_name, u.email AS user_email
               FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.id = :id',
            ['id' => $id]
        );
    }

    public static function entityTypes(): array
    {
        return array_column(static::db()->fetchAll(
            'SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type'
        ), 'entity_type');
    }

    public static function usersWithLogs(): array
    {
        return static::db()->fetchAll(
            'SELECT DISTINCT u.id, u.name FROM audit_logs a JOIN users u ON u.id = a.user_id ORDER BY u.name'
        );
    }
}
