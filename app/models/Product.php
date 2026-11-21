<?php
declare(strict_types=1);

/**
 * Products (+ product_variants, product_images).
 *
 * Price fields (DB column => admin label):
 *   mrp        => "Price (MRP)"      regular price, required
 *   price      => "Sale price"       what the customer pays; equals mrp when there is no sale
 *   cost_price => "Purchase price"   internal, for margin reports
 *
 * Deleting: a product that was never ordered is removed for good (images too); an ordered product is
 * soft-deleted (deleted_at) so order history keeps working, and its slug/SKU are released for reuse.
 */
class Product extends Model
{
    protected static string $table = 'products';

    public const TYPES = ['medicine', 'otc', 'device', 'general'];
    public const STATUSES = ['draft', 'pending', 'active', 'inactive', 'rejected'];
    /** Statuses only users with products.approve may set */
    public const APPROVAL_STATUSES = ['active', 'rejected'];
    public const UNITS = ['pcs', 'strip', 'box', 'bottle', 'tube', 'pack', 'sachet', 'vial', 'ampoule', 'jar', 'can', 'pair', 'ml', 'l', 'g', 'kg'];
    public const DOSAGE_FORMS = ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Cream', 'Ointment', 'Gel', 'Drops', 'Inhaler', 'Suppository', 'Powder', 'Solution', 'Spray', 'Lotion'];
    public const MAX_IMAGES = 10;
    public const MAX_VARIANTS = 30;

    public const SORTS = [
        'newest'     => 'p.id DESC',
        'updated'    => 'COALESCE(p.updated_at, p.created_at) DESC',
        'name'       => 'p.name_en ASC',
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'stock'      => 'p.stock_qty ASC',
    ];

    public static function label(array $p): string
    {
        return tr($p['name_bn'] ?? null, $p['name_en'] ?? '');
    }

    /** Not-deleted product or null. */
    public static function findLive(int $id): ?array
    {
        return static::db()->fetch('SELECT * FROM products WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    /* ---------------- List / search ---------------- */

    /**
     * Filters: q, category_id (includes sub-categories), brand_id, vendor ('' | 'inhouse' | id),
     *          status, type, rx ('1'|'0'), stock ('low'|'out')
     */
    private static function filterSql(array $f): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $where[] = '(p.name_en LIKE :q1 OR p.name_bn LIKE :q2 OR p.generic_name LIKE :q3 OR p.sku LIKE :q4 OR p.barcode = :q5)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $f['q']];
        }
        if (!empty($f['category_id'])) {
            $ids = Category::descendantIds((int) $f['category_id']);
            $ph = [];
            foreach ($ids as $i => $cid) {
                $ph[] = ':c' . $i;
                $params['c' . $i] = $cid;
            }
            $where[] = 'p.category_id IN (' . implode(',', $ph) . ')';
        }
        if (!empty($f['brand_id'])) {
            $where[] = 'p.brand_id = :brand';
            $params['brand'] = (int) $f['brand_id'];
        }
        if (($f['vendor'] ?? '') === 'inhouse') {
            $where[] = 'p.vendor_id IS NULL';
        } elseif (ctype_digit((string) ($f['vendor'] ?? ''))) {
            $where[] = 'p.vendor_id = :vendor';
            $params['vendor'] = (int) $f['vendor'];
        }
        if (in_array($f['status'] ?? '', self::STATUSES, true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $f['status'];
        }
        if (in_array($f['type'] ?? '', self::TYPES, true)) {
            $where[] = 'p.product_type = :type';
            $params['type'] = $f['type'];
        }
        if (in_array($f['rx'] ?? '', ['0', '1'], true)) {
            $where[] = 'p.requires_prescription = :rx';
            $params['rx'] = (int) $f['rx'];
        }
        if (($f['stock'] ?? '') === 'out') {
            $where[] = 'p.track_stock = 1 AND p.stock_qty <= 0';
        } elseif (($f['stock'] ?? '') === 'low') {
            $where[] = 'p.track_stock = 1 AND p.stock_qty > 0 AND p.stock_qty <= p.low_stock_threshold';
        }
        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    public static function countFiltered(array $f): int
    {
        [$w, $p] = self::filterSql($f);
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM products p' . $w, $p);
    }

    public static function filtered(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::filterSql($f);
        $order = self::SORTS[$f['sort'] ?? ''] ?? self::SORTS['newest'];
        return static::db()->fetchAll(
            'SELECT p.*, c.name_en AS category_name_en, c.name_bn AS category_name_bn,
                    b.name AS brand_name, b.name_bn AS brand_name_bn, b.slug AS brand_slug,
                    v.shop_name AS vendor_name, v.shop_name_bn AS vendor_name_bn, v.slug AS vendor_slug,
                    c.slug AS category_slug,
                    (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image,
                    (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variants_count
               FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN brands b ON b.id = p.brand_id
          LEFT JOIN vendors v ON v.id = p.vendor_id' . $w . '
           ORDER BY ' . $order . ', p.id DESC
              LIMIT :lim OFFSET :off',
            $p + ['lim' => $limit, 'off' => $offset]
        );
    }

    /** Counts per status for the list tabs (non-deleted). */
    public static function statusCounts(): array
    {
        $rows = static::db()->fetchAll('SELECT status, COUNT(*) n FROM products WHERE deleted_at IS NULL GROUP BY status');
        return array_map('intval', array_column($rows, 'n', 'status'));
    }

    /* ---------------- Uniqueness ---------------- */

    public static function slugExists(string $slug, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM products WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $exceptId]);
    }

    /** Slug source: name + strength, unless the name already contains the strength ("Napa 500 mg Tablet"). */
    public static function slugBase(string $name, ?string $strength): string
    {
        $strength = trim((string) $strength);
        return ($strength === '' || str_contains(mb_strtolower($name), mb_strtolower($strength))) ? $name : $name . ' ' . $strength;
    }

    public static function uniqueSlug(string $text, int $exceptId = 0): string
    {
        $base = mb_substr(Helpers::slugify($text), 0, 260);
        $slug = $base;
        $i = 2;
        while (self::slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /** SKU is unique per seller (vendor_id NULL = in-house; NULL-safe compare, the DB index can't enforce that case). */
    public static function skuExists(string $sku, ?int $vendorId, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn(
            'SELECT 1 FROM products WHERE sku = :sku AND vendor_id <=> :v AND id <> :id',
            ['sku' => $sku, 'v' => $vendorId, 'id' => $exceptId]
        );
    }

    public static function findBySku(string $sku, ?int $vendorId): ?array
    {
        return static::db()->fetch(
            'SELECT * FROM products WHERE sku = :sku AND vendor_id <=> :v AND deleted_at IS NULL LIMIT 1',
            ['sku' => $sku, 'v' => $vendorId]
        );
    }

    /* ---------------- Variants ---------------- */

    public static function variants(int $productId): array
    {
        return static::db()->fetchAll('SELECT * FROM product_variants WHERE product_id = :p ORDER BY is_default DESC, id', ['p' => $productId]);
    }

    /**
     * Replace the product's variants with $rows (already validated).
     * Row with 'id' => update that variant; without => insert. Variants missing from $rows are deleted,
     * unless they have stock batches or order history - those are deactivated instead.
     * @return array{added:int, updated:int, removed:int, deactivated:int}
     */
    public static function syncVariants(int $productId, array $rows): array
    {
        $db = static::db();
        $existing = array_column(self::variants($productId), null, 'id');
        $keep = [];
        $stats = ['added' => 0, 'updated' => 0, 'removed' => 0, 'deactivated' => 0];

        $hasDefault = false;
        foreach ($rows as $r) {
            $hasDefault = $hasDefault || !empty($r['is_default']);
        }

        foreach (array_values($rows) as $i => $r) {
            $data = [
                'name_en'         => $r['name_en'],
                'name_bn'         => $r['name_bn'] ?: null,
                'sku'             => $r['sku'] ?: null,
                'barcode'         => $r['barcode'] ?: null,
                'unit_multiplier' => (int) ($r['unit_multiplier'] ?: 1),
                'mrp'             => $r['mrp'],
                'price'           => $r['price'] !== '' ? $r['price'] : $r['mrp'],
                'cost_price'      => $r['cost_price'] !== '' ? $r['cost_price'] : null,
                'is_default'      => ($hasDefault ? (int) !empty($r['is_default']) : (int) ($i === 0)),
                'is_active'       => (int) !empty($r['is_active']),
            ];
            $id = (int) ($r['id'] ?? 0);
            if ($id && isset($existing[$id])) {
                $db->update('product_variants', $data, 'id = :id AND product_id = :p', ['id' => $id, 'p' => $productId]);
                $keep[] = $id;
                $stats['updated']++;
            } else {
                $keep[] = $db->insert('product_variants', $data + ['product_id' => $productId]);
                $stats['added']++;
            }
        }

        foreach ($existing as $id => $v) {
            if (in_array((int) $id, $keep, true)) {
                continue;
            }
            $used = $db->fetchColumn(
                'SELECT (SELECT COUNT(*) FROM inventory_batches WHERE variant_id = :a) + (SELECT COUNT(*) FROM order_items WHERE variant_id = :b)',
                ['a' => $id, 'b' => $id]
            );
            if ((int) $used > 0) {
                $db->update('product_variants', ['is_active' => 0, 'is_default' => 0], 'id = :id', ['id' => $id]);
                $stats['deactivated']++;
            } else {
                $db->delete('product_variants', 'id = :id', ['id' => $id]);
                $stats['removed']++;
            }
        }

        $db->update('products', ['has_variants' => (int) ($keep !== [])], 'id = :id', ['id' => $productId]);
        if (array_filter($stats)) {
            Audit::log('products.variants', 'products', $productId, null, $stats);
        }
        return $stats;
    }

    /* ---------------- Images ---------------- */

    public static function images(int $productId): array
    {
        return static::db()->fetchAll('SELECT * FROM product_images WHERE product_id = :p ORDER BY is_primary DESC, sort_order, id', ['p' => $productId]);
    }

    public static function imageCount(int $productId): int
    {
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM product_images WHERE product_id = :p', ['p' => $productId]);
    }

    /** Make sure exactly one image is primary (the chosen one, else the first by sort order). */
    public static function normalizePrimaryImage(int $productId, ?int $primaryId = null): void
    {
        $db = static::db();
        $ids = array_map('intval', array_column(
            $db->fetchAll('SELECT id FROM product_images WHERE product_id = :p ORDER BY is_primary DESC, sort_order, id', ['p' => $productId]),
            'id'
        ));
        if (!$ids) {
            return;
        }
        $primaryId = ($primaryId && in_array($primaryId, $ids, true)) ? $primaryId : $ids[0];
        $db->query('UPDATE product_images SET is_primary = (id = :id) WHERE product_id = :p', ['id' => $primaryId, 'p' => $productId]);
    }

    /* ---------------- Delete ---------------- */

    /** @return string 'deleted' (gone for good) or 'archived' (soft-deleted because it has orders) */
    public static function remove(int $id): string
    {
        $db = static::db();
        $product = self::find($id);
        if (!$product) {
            return 'deleted';
        }
        $ordered = (int) $db->fetchColumn('SELECT COUNT(*) FROM order_items WHERE product_id = :p', ['p' => $id]) > 0;

        if ($ordered) {
            $db->update('products', [
                'deleted_at' => date('Y-m-d H:i:s'),
                'status'     => 'inactive',
                'slug'       => mb_substr($product['slug'], 0, 250) . '-del' . $id,
                'sku'        => null, // free the SKU for reuse
            ], 'id = :id', ['id' => $id]);
            Audit::log('products.delete', 'products', $id, ['name_en' => $product['name_en'], 'sku' => $product['sku'], 'slug' => $product['slug']], ['soft_deleted' => true]);
            return 'archived';
        }

        $paths = array_column(self::images($id), 'path');
        self::deleteById($id); // images/variants/batches cascade; audited by Model
        foreach ($paths as $p) {
            Upload::delete($p);
        }
        return 'deleted';
    }
}
