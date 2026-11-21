<?php
declare(strict_types=1);

/**
 * Storefront catalog queries (customer side).
 *
 * A product is VISIBLE when: status = active, not deleted, its vendor (if any) is approved and not deleted,
 * and its category (if any) is active.
 *
 * Price shown to customers ("effective price"):
 *   - variant products use their default active variant (else the cheapest active one)
 *   - a running flash sale item for that product/variant lowers the price (never raises it)
 *   - MRP is the struck-through regular price; discount % = (MRP - price) / MRP
 */
class Storefront
{
    public const PER_PAGE = 24;

    /** sort key => ORDER BY (aliases come from CARD_SELECT) */
    public const SORTS = [
        'relevance'  => null, // built from the search words
        'popular'    => 'p.sold_count DESC, p.rating_count DESC, p.views DESC, p.id DESC',
        'newest'     => 'p.id DESC',
        'price_asc'  => 'eff_price ASC, p.id DESC',
        'price_desc' => 'eff_price DESC, p.id DESC',
        'discount'   => 'off_ratio DESC, p.sold_count DESC, p.id DESC',
        'rating'     => 'p.rating_avg DESC, p.rating_count DESC, p.id DESC',
    ];

    private const BASE_PRICE = 'COALESCE(dv.price, p.price)';
    private const MRP = 'GREATEST(COALESCE(dv.mrp, p.mrp), COALESCE(dv.price, p.price))';

    private static int $paramNo = 0;

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** Unique PDO parameter name (native prepares do not allow re-using a name). */
    private static function p(): string
    {
        return 'sf' . ++self::$paramNo;
    }

    private static function priceExpr(): string
    {
        $base = self::BASE_PRICE;
        return "LEAST(COALESCE(fi.sale_price, {$base}), {$base})";
    }

    /** FROM + joins shared by every product query. */
    private static function from(): string
    {
        return "FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
           LEFT JOIN brands b     ON b.id = p.brand_id
           LEFT JOIN vendors v    ON v.id = p.vendor_id
           LEFT JOIN product_variants dv ON p.has_variants = 1 AND dv.id = (
                SELECT v2.id FROM product_variants v2
                 WHERE v2.product_id = p.id AND v2.is_active = 1
              ORDER BY v2.is_default DESC, v2.price ASC, v2.id ASC LIMIT 1)
           LEFT JOIN flash_sale_items fi ON fi.id = (
                SELECT f2.id FROM flash_sale_items f2 JOIN flash_sales fs2 ON fs2.id = f2.flash_sale_id
                 WHERE f2.product_id = p.id AND (f2.variant_id IS NULL OR f2.variant_id = dv.id)
                   AND fs2.is_active = 1 AND fs2.starts_at <= NOW() AND fs2.ends_at > NOW()
                   AND (f2.quantity_limit IS NULL OR f2.sold_qty < f2.quantity_limit)
              ORDER BY f2.sale_price ASC LIMIT 1)";
    }

    private static function visibleSql(): string
    {
        return "p.status = 'active' AND p.deleted_at IS NULL
            AND (p.vendor_id IS NULL OR (v.status = 'approved' AND v.deleted_at IS NULL))
            AND (p.category_id IS NULL OR c.is_active = 1)";
    }

    private static function cardSelect(): string
    {
        $price = self::priceExpr();
        $mrp = self::MRP;
        $base = self::BASE_PRICE;
        return "SELECT p.id, p.slug, p.name_en, p.name_bn, p.generic_name, p.strength, p.dosage_form, p.pack_size, p.unit,
                    p.requires_prescription, p.has_variants, p.track_stock, p.stock_qty, p.low_stock_threshold,
                    p.rating_avg, p.rating_count, p.vendor_id, p.is_featured, p.category_id, p.brand_id,
                    {$base} AS base_price,
                    b.name AS brand_name, b.name_bn AS brand_name_bn,
                    dv.name_en AS variant_en, dv.name_bn AS variant_bn,
                    (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id
                      ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image,
                    {$price} AS eff_price,
                    {$mrp} AS eff_mrp,
                    (fi.sale_price IS NOT NULL AND fi.sale_price < {$base}) AS is_flash,
                    CASE WHEN {$mrp} > 0 THEN ({$mrp} - {$price}) / {$mrp} ELSE 0 END AS off_ratio";
    }

    /** Search words (max 6), each 1-50 chars. */
    public static function words(string $q): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');
        if ($q === '') {
            return [];
        }
        $words = array_slice(array_unique(explode(' ', mb_strtolower($q))), 0, 6);
        return array_values(array_filter(array_map(static fn($w) => mb_substr($w, 0, 50), $words), static fn($w) => $w !== ''));
    }

    private static function like(string $s): string
    {
        return '%' . addcslashes($s, '%_\\') . '%';
    }

    /**
     * WHERE clause for the filters.
     * $f keys: q, category_ids[], brand_ids[], vendor_ids[] (0 = sold by SasthoBazar), min_price, max_price,
     *          in_stock, featured, discounted, flash_sale (id), ids[], exclude_ids[], generic [name, strength]
     * $skip:   filter keys to ignore (used for facet counts)
     */
    private static function where(array $f, array $skip = []): array
    {
        $w = [self::visibleSql()];
        $params = [];
        $use = static fn(string $k) => !in_array($k, $skip, true) && isset($f[$k]) && !in_array($f[$k], ['', [], false], true);

        if ($use('q')) {
            foreach (self::words((string) $f['q']) as $word) {
                $or = [];
                foreach (['p.name_en', 'p.name_bn', 'p.generic_name', 'p.strength', 'b.name', 'b.name_bn'] as $col) {
                    $k = self::p();
                    $or[] = "{$col} LIKE :{$k}";
                    $params[$k] = self::like($word);
                }
                $k1 = self::p();
                $k2 = self::p();
                $or[] = "p.sku = :{$k1}";
                $or[] = "p.barcode = :{$k2}";
                $params[$k1] = $word;
                $params[$k2] = $word;
                $w[] = '(' . implode(' OR ', $or) . ')';
            }
        }
        foreach (['category_ids' => 'p.category_id', 'brand_ids' => 'p.brand_id', 'ids' => 'p.id'] as $key => $col) {
            if ($use($key)) {
                $in = [];
                foreach (array_slice(array_map('intval', (array) $f[$key]), 0, 500) as $id) {
                    $k = self::p();
                    $in[] = ':' . $k;
                    $params[$k] = $id;
                }
                $w[] = $in ? "{$col} IN (" . implode(',', $in) . ')' : '0';
            }
        }
        if ($use('ids')) { // only these products (offers page)
            $ids = array_slice(array_values(array_unique(array_map('intval', (array) $f['ids']))), 0, 200);
            $w[] = $ids ? 'p.id IN (' . implode(',', $ids) . ')' : '0';
        }
        if ($use('exclude_ids')) {
            foreach (array_map('intval', (array) $f['exclude_ids']) as $id) {
                $k = self::p();
                $w[] = "p.id <> :{$k}";
                $params[$k] = $id;
            }
        }
        if ($use('vendor_ids')) {
            $ids = array_map('intval', (array) $f['vendor_ids']);
            $parts = [];
            if (in_array(0, $ids, true)) {
                $parts[] = 'p.vendor_id IS NULL';
            }
            $real = array_values(array_filter($ids, static fn($i) => $i > 0));
            if ($real) {
                $in = [];
                foreach ($real as $id) {
                    $k = self::p();
                    $in[] = ':' . $k;
                    $params[$k] = $id;
                }
                $parts[] = 'p.vendor_id IN (' . implode(',', $in) . ')';
            }
            $w[] = $parts ? '(' . implode(' OR ', $parts) . ')' : '0';
        }
        $price = self::priceExpr();
        if ($use('min_price')) {
            $k = self::p();
            $w[] = "{$price} >= :{$k}";
            $params[$k] = (string) (float) $f['min_price'];
        }
        if ($use('max_price')) {
            $k = self::p();
            $w[] = "{$price} <= :{$k}";
            $params[$k] = (string) (float) $f['max_price'];
        }
        if ($use('in_stock')) {
            $w[] = '(p.track_stock = 0 OR p.stock_qty > 0)';
        }
        if ($use('featured')) {
            $w[] = 'p.is_featured = 1';
        }
        if ($use('discounted')) {
            $w[] = $price . ' < ' . self::MRP;
        }
        if ($use('flash_sale')) {
            $k = self::p();
            $w[] = "EXISTS (SELECT 1 FROM flash_sale_items x WHERE x.flash_sale_id = :{$k} AND x.product_id = p.id)";
            $params[$k] = (int) $f['flash_sale'];
        }
        if ($use('generic')) {
            [$name, $strength] = $f['generic'];
            $k1 = self::p();
            $w[] = "p.generic_name = :{$k1}";
            $params[$k1] = $name;
            if ($strength !== null && $strength !== '') {
                $k2 = self::p();
                $w[] = "p.strength = :{$k2}";
                $params[$k2] = $strength;
            }
        }
        return [implode(' AND ', $w), $params];
    }

    /** ORDER BY for search relevance: name starts with the phrase > name contains it > generic match > rest. */
    private static function relevanceOrder(string $q, array &$params): string
    {
        $phrase = mb_strtolower(trim($q));
        if ($phrase === '') {
            return self::SORTS['popular'];
        }
        $k = [self::p(), self::p(), self::p(), self::p(), self::p()];
        $params[$k[0]] = addcslashes($phrase, '%_\\') . '%';
        $params[$k[1]] = addcslashes($phrase, '%_\\') . '%';
        $params[$k[2]] = self::like($phrase);
        $params[$k[3]] = self::like($phrase);
        $params[$k[4]] = addcslashes($phrase, '%_\\') . '%';
        return "CASE WHEN p.name_en LIKE :{$k[0]} OR p.name_bn LIKE :{$k[1]} THEN 0
                     WHEN p.name_en LIKE :{$k[2]} OR p.name_bn LIKE :{$k[3]} THEN 1
                     WHEN p.generic_name LIKE :{$k[4]} THEN 2 ELSE 3 END, " . self::SORTS['popular'];
    }

    public static function count(array $f): int
    {
        [$where, $params] = self::where($f);
        return (int) self::db()->fetchColumn('SELECT COUNT(*) ' . self::from() . ' WHERE ' . $where, $params);
    }

    /** Product cards for a filter. */
    public static function cards(array $f, string $sort = 'popular', int $limit = 12, int $offset = 0): array
    {
        [$where, $params] = self::where($f);
        if ($sort === 'relevance') {
            $order = self::relevanceOrder((string) ($f['q'] ?? ''), $params);
        } else {
            $order = self::SORTS[$sort] ?? self::SORTS['popular'];
        }
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        return self::applyOffers(self::db()->fetchAll(
            self::cardSelect() . ' ' . self::from() . " WHERE {$where} ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}",
            $params
        ));
    }

    /**
     * Running price offers / buy-X-get-Y on card rows: lower eff_price when an offer beats the flash price,
     * and set offer_label / bogo_label for the badges.
     */
    public static function applyOffers(array $rows): array
    {
        $act = Promo::active();
        if (!$act['price'] && !$act['bogo']) {
            return $rows;
        }
        foreach ($rows as &$r) {
            $base = (float) $r['base_price'];
            $r['offer_label'] = $r['bogo_label'] = null;
            $best = Promo::bestPrice($r, $base);
            if ($best && $best['price'] < (float) $r['eff_price']) {
                $mrp = (float) $r['eff_mrp'];
                $r['eff_price'] = $best['price'];
                $r['is_flash'] = 0;
                $r['off_ratio'] = $mrp > 0 ? ($mrp - $best['price']) / $mrp : 0;
                $r['offer_label'] = Promo::label($best['offer']);
            } elseif ((float) $r['eff_price'] >= $base && ($b = Promo::bogo($r))) {
                $r['bogo_label'] = Promo::label($b);
            }
        }
        unset($r);
        return $rows;
    }

    /* ---------------- Facets for the listing sidebar ---------------- */

    /** Brands available for the current filters (ignoring the brand filter itself), with product counts. */
    public static function brandFacet(array $f): array
    {
        [$where, $params] = self::where($f, ['brand_ids']);
        return self::db()->fetchAll(
            'SELECT b.id, b.name, b.name_bn, b.slug, COUNT(*) AS n ' . self::from()
            . " WHERE {$where} AND b.id IS NOT NULL AND b.is_active = 1
                GROUP BY b.id, b.name, b.name_bn, b.slug ORDER BY n DESC, b.name LIMIT 60",
            $params
        );
    }

    /** Sellers available for the current filters; id 0 = sold by SasthoBazar. */
    public static function vendorFacet(array $f): array
    {
        [$where, $params] = self::where($f, ['vendor_ids']);
        return self::db()->fetchAll(
            'SELECT COALESCE(v.id, 0) AS id, v.shop_name, v.shop_name_bn, v.slug, COUNT(*) AS n ' . self::from()
            . " WHERE {$where} GROUP BY COALESCE(v.id, 0), v.shop_name, v.shop_name_bn, v.slug ORDER BY n DESC LIMIT 40",
            $params
        );
    }

    /** [min, max] effective price for the current filters (ignoring the price filter). */
    public static function priceRange(array $f): array
    {
        [$where, $params] = self::where($f, ['min_price', 'max_price']);
        $price = self::priceExpr();
        $row = self::db()->fetch("SELECT MIN({$price}) AS lo, MAX({$price}) AS hi " . self::from() . " WHERE {$where}", $params);
        return [(float) ($row['lo'] ?? 0), (float) ($row['hi'] ?? 0)];
    }

    /* ---------------- Search suggestions (AJAX) ---------------- */

    public static function suggest(string $q): array
    {
        $products = self::cards(['q' => $q], 'relevance', 8);
        $like = self::like(mb_strtolower(trim($q)));
        $cats = self::db()->fetchAll(
            'SELECT id, name_en, name_bn, slug FROM categories
              WHERE is_active = 1 AND (name_en LIKE :a OR name_bn LIKE :b) ORDER BY parent_id IS NOT NULL, sort_order LIMIT 3',
            ['a' => $like, 'b' => $like]
        );
        $brands = self::db()->fetchAll(
            'SELECT id, name, name_bn, slug FROM brands
              WHERE is_active = 1 AND (name LIKE :a OR name_bn LIKE :b) ORDER BY is_featured DESC, name LIMIT 3',
            ['a' => $like, 'b' => $like]
        );
        return [$products, $cats, $brands];
    }

    /* ---------------- Home page blocks ---------------- */

    /** Active banners for a position, in the current language. */
    public static function banners(string $position = 'home_slider', int $limit = 8): array
    {
        return self::db()->fetchAll(
            "SELECT * FROM banners
              WHERE position = :pos AND is_active = 1 AND lang IN ('all', :lang)
                AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at > NOW())
           ORDER BY sort_order, id LIMIT " . max(1, $limit),
            ['pos' => $position, 'lang' => lang()]
        );
    }

    /** The running flash sale that ends first (null when none). */
    public static function activeFlashSale(): ?array
    {
        return self::db()->fetch(
            'SELECT * FROM flash_sales WHERE is_active = 1 AND starts_at <= NOW() AND ends_at > NOW() ORDER BY ends_at ASC, id LIMIT 1'
        );
    }

    /** product_id => [sold, limit] for a flash sale (limit null = unlimited). */
    public static function flashProgress(int $saleId): array
    {
        $rows = self::db()->fetchAll(
            'SELECT product_id, SUM(sold_qty) AS sold,
                    CASE WHEN SUM(quantity_limit IS NULL) > 0 THEN NULL ELSE SUM(quantity_limit) END AS lim
               FROM flash_sale_items WHERE flash_sale_id = :s GROUP BY product_id',
            ['s' => $saleId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['product_id']] = [(int) $r['sold'], $r['lim'] === null ? null : (int) $r['lim']];
        }
        return $out;
    }

    /** Active brands that have visible products; featured first. */
    public static function popularBrands(int $limit = 12): array
    {
        return self::db()->fetchAll(
            'SELECT b.id, b.name, b.name_bn, b.slug, b.logo, b.is_featured, COUNT(*) AS n ' . self::from()
            . ' WHERE ' . self::visibleSql() . ' AND b.id IS NOT NULL AND b.is_active = 1
              GROUP BY b.id, b.name, b.name_bn, b.slug, b.logo, b.is_featured
              ORDER BY b.is_featured DESC, n DESC, b.name LIMIT ' . max(1, $limit)
        );
    }

    /** Top-level active categories with image/icon for the home grid. */
    public static function homeCategories(int $limit = 16): array
    {
        return self::db()->fetchAll(
            'SELECT id, name_en, name_bn, slug, icon, image FROM categories
              WHERE parent_id IS NULL AND is_active = 1 ORDER BY is_featured DESC, sort_order, id LIMIT ' . max(1, $limit)
        );
    }

    /* ---------------- Product page ---------------- */

    /** Visible product by slug with brand / vendor / category columns, or null. */
    public static function productBySlug(string $slug): ?array
    {
        return self::db()->fetch(
            'SELECT p.*, b.name AS brand_name, b.name_bn AS brand_name_bn, b.slug AS brand_slug,
                    v.shop_name, v.shop_name_bn, v.slug AS vendor_slug, v.logo AS vendor_logo,
                    c.name_en AS category_en, c.name_bn AS category_bn, c.slug AS category_slug
               FROM products p
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN brands b     ON b.id = p.brand_id
          LEFT JOIN vendors v    ON v.id = p.vendor_id
              WHERE p.slug = :s AND ' . self::visibleSql() . ' LIMIT 1',
            ['s' => $slug]
        );
    }

    public static function activeVariants(int $productId): array
    {
        return self::db()->fetchAll(
            'SELECT * FROM product_variants WHERE product_id = :p AND is_active = 1 ORDER BY is_default DESC, price, id',
            ['p' => $productId]
        );
    }

    /**
     * Running flash sale items of a product, keyed by variant id ('' = whole product).
     * @return array<string, array{id: int, sale_price: float, ends_at: string, sold: int, limit: ?int, per_order: ?int, sale_id: int, funded_by: string, share: float}>
     */
    public static function productFlash(int $productId): array
    {
        $rows = self::db()->fetchAll(
            'SELECT fi.id, fi.variant_id, fi.sale_price, fi.sold_qty, fi.quantity_limit, fi.per_user_limit,
                    fs.id AS sale_id, fs.ends_at, fs.funded_by, fs.vendor_share_percent
               FROM flash_sale_items fi JOIN flash_sales fs ON fs.id = fi.flash_sale_id
              WHERE fi.product_id = :p AND fs.is_active = 1 AND fs.starts_at <= NOW() AND fs.ends_at > NOW()
                AND (fi.quantity_limit IS NULL OR fi.sold_qty < fi.quantity_limit)
           ORDER BY fi.sale_price DESC',
            ['p' => $productId]
        );
        $out = [];
        foreach ($rows as $r) { // cheapest last wins
            $out[(string) ($r['variant_id'] ?? '')] = [
                'id'         => (int) $r['id'],
                'sale_price' => (float) $r['sale_price'],
                'ends_at'    => $r['ends_at'],
                'sold'       => (int) $r['sold_qty'],
                'limit'      => $r['quantity_limit'] === null ? null : (int) $r['quantity_limit'],
                'per_order'  => $r['per_user_limit'] === null ? null : (int) $r['per_user_limit'],
                'sale_id'    => (int) $r['sale_id'],
                'funded_by'  => $r['funded_by'],
                'share'      => (float) $r['vendor_share_percent'],
            ];
        }
        return $out;
    }

    /**
     * Customer price for a product or variant row ($row: price, mrp).
     * $product (id / product_id, category_id, brand_id, vendor_id) enables price offers and buy-X-get-Y.
     * Lowest of: regular price, flash sale price, best price offer. Buy-X-get-Y only at the regular price.
     * @return array{price: float, mrp: float, base: float, off: int, flash: ?array, offer: ?array, promo: ?array, bogo: ?array}
     *   promo = the discount inside the unit price: {type: flash|offer, id, funded_by, share, label}
     */
    public static function priceFor(array $row, array $flash, ?int $variantId = null, ?array $product = null): array
    {
        $base = (float) $row['price'];
        $mrp = max((float) $row['mrp'], $base);
        $f = ($variantId !== null ? ($flash[(string) $variantId] ?? null) : null) ?? ($flash[''] ?? null);
        $price = $base;
        $promo = $offer = $bogo = null;
        if ($f && $f['sale_price'] < $base) {
            $price = $f['sale_price'];
            $promo = ['type' => 'flash', 'id' => $f['id'], 'sale_id' => $f['sale_id'] ?? null, 'funded_by' => $f['funded_by'] ?? 'platform',
                      'share' => $f['share'] ?? 0, 'label' => __('flash_sale')];
        }
        if ($product !== null) {
            $best = Promo::bestPrice($product, $base);
            if ($best && $best['price'] < $price) {
                $price = $best['price'];
                $offer = $best['offer'];
                $promo = ['type' => 'offer', 'id' => (int) $offer['id'], 'funded_by' => $offer['funded_by'],
                          'share' => (float) $offer['vendor_share_percent'], 'label' => Promo::label($offer),
                          'ptype' => $offer['type'], 'value' => (float) $offer['value']];
            }
            if ($promo === null) {
                $bogo = Promo::bogo($product);
            }
        }
        return [
            'price' => $price,
            'mrp'   => $mrp,
            'base'  => $base,
            'off'   => $mrp > 0 ? (int) floor(($mrp - $price) / $mrp * 100) : 0,
            'flash' => ($promo['type'] ?? '') === 'flash' ? $f : null,
            'offer' => $offer,
            'promo' => $promo,
            'bogo'  => $bogo,
        ];
    }

    /** Record one view per product per session (does not touch updated_at). */
    public static function countView(int $productId): void
    {
        $seen = $_SESSION['_viewed'] ?? [];
        if (isset($seen[$productId])) {
            return;
        }
        $seen[$productId] = 1;
        $_SESSION['_viewed'] = array_slice($seen, -200, null, true);
        self::db()->query('UPDATE products SET views = views + 1, updated_at = updated_at WHERE id = :id', ['id' => $productId]);
    }

    /* ---------------- Small display helpers ---------------- */

    public static function url(array $p): string
    {
        return url('product/' . rawurlencode((string) $p['slug']));
    }

    public static function name(array $p): string
    {
        return tr($p['name_bn'] ?? null, $p['name_en'] ?? '');
    }

    /** "Paracetamol 500 mg · Tablet" style sub-line. */
    public static function subline(array $p): string
    {
        $parts = array_filter([
            trim(($p['generic_name'] ?? '') . ' ' . ($p['strength'] ?? '')),
            $p['dosage_form'] ?? '',
        ], static fn($x) => $x !== '' && $x !== null);
        return implode(' · ', $parts);
    }

    /** Discount percent for a card row. */
    public static function offPercent(array $p): int
    {
        return (int) floor(((float) ($p['off_ratio'] ?? 0)) * 100);
    }

    public static function inStock(array $p): bool
    {
        return !(int) $p['track_stock'] || (int) $p['stock_qty'] > 0;
    }

    /** Parse "?brand[]=1&brand[]=2" or "?brand=1,2" into ints. */
    public static function intList(mixed $v, bool $allowZero = false): array
    {
        if (is_string($v)) {
            $v = explode(',', $v);
        }
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $x) {
            if (is_scalar($x) && preg_match('/^\d{1,10}$/', (string) $x) && ((int) $x > 0 || $allowZero)) {
                $out[] = (int) $x;
            }
        }
        return array_values(array_unique(array_slice($out, 0, 50)));
    }
}
