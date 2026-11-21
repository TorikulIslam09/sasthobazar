<?php
declare(strict_types=1);

class Vendor extends Model
{
    protected static string $table = 'vendors';

    public static function label(array $v): string
    {
        return tr($v['shop_name_bn'] ?? null, $v['shop_name'] ?? '');
    }

    /** Vendors that may own products (not deleted), approved first. */
    public static function options(): array
    {
        return static::db()->fetchAll(
            "SELECT id, shop_name, shop_name_bn, slug, status FROM vendors
              WHERE deleted_at IS NULL ORDER BY status = 'approved' DESC, shop_name"
        );
    }

    public static function existsActive(int $id): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM vendors WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    /** Lookup by id or slug - used by CSV import. */
    public static function findByRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        return static::db()->fetch(
            'SELECT * FROM vendors WHERE deleted_at IS NULL AND (slug = :s OR id = :id) LIMIT 1',
            ['s' => $ref, 'id' => ctype_digit($ref) ? (int) $ref : 0]
        );
    }
}
