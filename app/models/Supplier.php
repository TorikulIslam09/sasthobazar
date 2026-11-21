<?php
declare(strict_types=1);

/** Suppliers (pharma distributors / wholesalers we buy stock from). */
class Supplier extends Model
{
    protected static string $table = 'suppliers';

    private static function filterSql(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(s.name LIKE :q1 OR s.contact_person LIKE :q2 OR s.phone LIKE :q3 OR s.email LIKE :q4)';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (($f['status'] ?? '') === 'active') {
            $w[] = 's.is_active = 1';
        } elseif (($f['status'] ?? '') === 'inactive') {
            $w[] = 's.is_active = 0';
        }
        return [' WHERE ' . implode(' AND ', $w), $p];
    }

    public static function countFiltered(array $f): int
    {
        [$w, $p] = self::filterSql($f);
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM suppliers s' . $w, $p);
    }

    public static function filtered(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::filterSql($f);
        return static::db()->fetchAll(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS po_count,
                    (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.status IN ('ordered','partial')) AS open_po_count,
                    (SELECT COALESCE(SUM(g.total_cost), 0) FROM grns g WHERE g.supplier_id = s.id) AS received_value,
                    (SELECT MAX(g.received_date) FROM grns g WHERE g.supplier_id = s.id) AS last_received
               FROM suppliers s" . $w . ' ORDER BY s.is_active DESC, s.name LIMIT :lim OFFSET :off',
            $p + ['lim' => $limit, 'off' => $offset]
        );
    }

    public static function options(bool $activeOnly = true): array
    {
        return static::db()->fetchAll('SELECT id, name, is_active FROM suppliers' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name');
    }

    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM suppliers WHERE name = :n AND id <> :id', ['n' => $name, 'id' => $exceptId]);
    }

    /** Purchase orders / batches pointing at this supplier (delete is blocked when > 0). */
    public static function usage(int $id): array
    {
        $db = static::db();
        return [
            'pos'     => (int) $db->fetchColumn('SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = :s', ['s' => $id]),
            'batches' => (int) $db->fetchColumn('SELECT COUNT(*) FROM inventory_batches WHERE supplier_id = :s', ['s' => $id]),
        ];
    }
}
