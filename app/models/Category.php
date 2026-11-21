<?php
declare(strict_types=1);

/**
 * Multi-level categories (parent_id tree, any depth).
 * The whole table is small, so tree helpers load it once per request and build the tree in PHP.
 */
class Category extends Model
{
    protected static string $table = 'categories';

    private static ?array $cache = null;

    /** Active top-level categories, ordered. */
    public static function topLevel(): array
    {
        return static::db()->fetchAll(
            'SELECT id, name_en, name_bn, slug, icon, requires_prescription
               FROM categories WHERE parent_id IS NULL AND is_active = 1
           ORDER BY sort_order, id'
        );
    }

    /** Localised name for the current UI language. */
    public static function label(array $c): string
    {
        return lang() === 'bn' && !empty($c['name_bn']) ? $c['name_bn'] : $c['name_en'];
    }

    /** All categories with product / child counts, keyed by id. */
    public static function allById(bool $fresh = false): array
    {
        if (self::$cache !== null && !$fresh) {
            return self::$cache;
        }
        $rows = static::db()->fetchAll(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.deleted_at IS NULL) AS products_count,
                    (SELECT COUNT(*) FROM categories ch WHERE ch.parent_id = c.id) AS children_count
               FROM categories c
           ORDER BY c.sort_order, c.name_en'
        );
        return self::$cache = array_column($rows, null, 'id');
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Depth-first flat list: each row gets 'depth' (0 = top) and 'path' ("Medicine › OTC").
     * Orphans (parent missing) are shown at the top level.
     */
    public static function tree(): array
    {
        $all = self::allById();
        $children = [];
        foreach ($all as $id => $c) {
            $parent = ($c['parent_id'] !== null && isset($all[$c['parent_id']])) ? (int) $c['parent_id'] : 0;
            $children[$parent][] = $id;
        }
        $out = [];
        $walk = static function (int $parent, int $depth, array $trail) use (&$walk, &$out, $children, $all): void {
            foreach ($children[$parent] ?? [] as $id) {
                if ($depth > 20) {
                    return; // corrupted data guard
                }
                $row = $all[$id];
                $row['depth'] = $depth;
                $row['path'] = implode(' › ', [...$trail, self::label($row)]);
                $out[] = $row;
                $walk((int) $id, $depth + 1, [...$trail, self::label($row)]);
            }
        };
        $walk(0, 0, []);
        return $out;
    }

    /** @return int[] the category id plus all its descendants */
    public static function descendantIds(int $id): array
    {
        $byParent = [];
        foreach (self::allById() as $c) {
            if ($c['parent_id'] !== null) {
                $byParent[(int) $c['parent_id']][] = (int) $c['id'];
            }
        }
        $ids = [$id];
        $queue = [$id];
        while ($queue) {
            $cur = array_shift($queue);
            foreach ($byParent[$cur] ?? [] as $child) {
                if (!in_array($child, $ids, true)) {
                    $ids[] = $child;
                    $queue[] = $child;
                }
            }
        }
        return $ids;
    }

    /** "Medicine › OTC Medicine" for a category id. */
    public static function pathOf(?int $id): string
    {
        if (!$id) {
            return '';
        }
        foreach (self::tree() as $row) {
            if ((int) $row['id'] === $id) {
                return $row['path'];
            }
        }
        return '';
    }

    public static function slugExists(string $slug, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM categories WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $exceptId]);
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

    /** Lookup by slug or exact (case-insensitive) English/Bangla name - used by CSV import. */
    public static function findByRef(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (ctype_digit($ref) && ($c = self::allById()[(int) $ref] ?? null)) {
            return $c;
        }
        foreach (self::allById() as $c) {
            if ($c['slug'] === $ref
                || mb_strtolower($c['name_en']) === mb_strtolower($ref)
                || ($c['name_bn'] !== null && $c['name_bn'] === $ref)) {
                return $c;
            }
        }
        return null;
    }
}
