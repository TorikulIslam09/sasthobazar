<?php
declare(strict_types=1);

/** Brands and pharmaceutical companies (is_manufacturer = 1). */
class Brand extends Model
{
    protected static string $table = 'brands';

    public static function label(array $b): string
    {
        return tr($b['name_bn'] ?? null, $b['name'] ?? '');
    }

    /** [sql, params] for the admin list filters: q, type (manufacturer|brand), status (active|inactive). */
    private static function filterSql(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(b.name LIKE :q1 OR b.name_bn LIKE :q2 OR b.slug LIKE :q3 OR b.country LIKE :q4)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (($f['type'] ?? '') === 'manufacturer') {
            $where[] = 'b.is_manufacturer = 1';
        } elseif (($f['type'] ?? '') === 'brand') {
            $where[] = 'b.is_manufacturer = 0';
        }
        if (($f['status'] ?? '') === 'active') {
            $where[] = 'b.is_active = 1';
        } elseif (($f['status'] ?? '') === 'inactive') {
            $where[] = 'b.is_active = 0';
        }
        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    public static function countFiltered(array $f): int
    {
        [$w, $p] = self::filterSql($f);
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM brands b' . $w, $p);
    }

    public static function filtered(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::filterSql($f);
        return static::db()->fetchAll(
            'SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.deleted_at IS NULL) AS products_count
               FROM brands b' . $w . '
           ORDER BY b.name LIMIT :lim OFFSET :off',
            $p + ['lim' => $limit, 'off' => $offset]
        );
    }

    /** id, name, name_bn for dropdowns (active first). */
    public static function options(): array
    {
        return static::db()->fetchAll('SELECT id, name, name_bn, is_active FROM brands ORDER BY is_active DESC, name');
    }

    public static function productsCount(int $id): int
    {
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM products WHERE brand_id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    public static function slugExists(string $slug, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM brands WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $exceptId]);
    }

    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM brands WHERE name = :n AND id <> :id', ['n' => $name, 'id' => $exceptId]);
    }

    public static function uniqueSlug(string $text, int $exceptId = 0): string
    {
        $base = mb_substr(Helpers::slugify($text), 0, 170);
        $slug = $base;
        $i = 2;
        while (self::slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /** Lookup by id, slug or exact name (case-insensitive) - used by CSV import. */
    public static function findByRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        return static::db()->fetch(
            'SELECT * FROM brands WHERE slug = :s OR LOWER(name) = LOWER(:n) OR name_bn = :b ORDER BY (slug = :s2) DESC LIMIT 1',
            ['s' => $ref, 'n' => $ref, 'b' => $ref, 's2' => $ref]
        );
    }
}
