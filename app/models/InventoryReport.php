<?php
declare(strict_types=1);

/**
 * Read-only inventory queries: dashboard figures, alert lists, batch list, ledger,
 * stock valuation and vendor-wise stock.
 *
 * Valuation per batch:
 *   cost value = quantity_available x batch cost (fallback: variant cost, product purchase price, 0)
 *   sale value = quantity_available x current selling price (variant price for variant batches)
 * Only active batches count as stock. "Expired" = active batch whose expiry date has passed (not yet written off).
 */
final class InventoryReport
{
    /** SQL snippets reused by several queries */
    private const COST = 'COALESCE(b.cost_price, v.cost_price, p.cost_price, 0)';
    private const SALE = 'COALESCE(v.price, p.price)';

    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function lowStockDefault(): int
    {
        return max(0, (int) setting('low_stock_threshold', 10));
    }

    public static function expiryDays(): int
    {
        return max(1, (int) setting('expiry_alert_days', 90));
    }

    /** vendor filter: '' = all, 'inhouse' = vendor_id IS NULL, id = that vendor. Returns [sql, params] on alias $a. */
    private static function vendorSql(string $vendor, string $a = 'p'): array
    {
        if ($vendor === 'inhouse') {
            return [" AND {$a}.vendor_id IS NULL", []];
        }
        if (ctype_digit($vendor) && $vendor !== '') {
            return [" AND {$a}.vendor_id = :vendor", ['vendor' => (int) $vendor]];
        }
        return ['', []];
    }

    /* ======================= Dashboard ======================= */

    public static function summary(string $vendor = ''): array
    {
        $db = self::db();
        [$vs, $vp] = self::vendorSql($vendor);
        $days = self::expiryDays();

        $stock = $db->fetch(
            'SELECT COALESCE(SUM(b.quantity_available), 0) AS units,
                    COALESCE(SUM(b.quantity_available * ' . self::COST . '), 0) AS cost_value,
                    COALESCE(SUM(b.quantity_available * ' . self::SALE . '), 0) AS sale_value,
                    COUNT(DISTINCT b.product_id) AS products_in_stock
               FROM inventory_batches b
               JOIN products p ON p.id = b.product_id AND p.deleted_at IS NULL
          LEFT JOIN product_variants v ON v.id = b.variant_id
              WHERE b.status = \'active\' AND b.quantity_available > 0' . $vs,
            $vp
        );
        [$vsm, $vpm] = self::vendorSql($vendor, 'm');
        $flow = $db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN m.type IN ('purchase','stock_in') THEN m.quantity END), 0) AS received,
                    COALESCE(-SUM(CASE WHEN m.type = 'sale' THEN m.quantity END), 0) + COALESCE(-SUM(CASE WHEN m.type IN ('return','cancel') THEN m.quantity END), 0) AS sold,
                    COALESCE(-SUM(CASE WHEN m.type IN ('damage','expired','recall') THEN m.quantity END), 0) AS lost,
                    COALESCE(-SUM(CASE WHEN m.type = 'sale' AND m.created_at >= :d30 THEN m.quantity END), 0) AS sold_30d
               FROM stock_movements m WHERE 1=1" . $vsm,
            $vpm + ['d30' => date('Y-m-d H:i:s', strtotime('-30 days'))]
        );
        $counts = $db->fetch(
            "SELECT SUM(p.stock_qty <= 0) AS out_of_stock,
                    SUM(p.stock_qty > 0 AND p.stock_qty <= p.low_stock_threshold) AS low_stock
               FROM products p WHERE p.deleted_at IS NULL AND p.track_stock = 1 AND p.status IN ('active','pending','draft')" . $vs,
            $vp
        );
        $exp = $db->fetch(
            "SELECT SUM(b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)) AS near_expiry,
                    SUM(b.expiry_date < CURDATE()) AS expired,
                    COALESCE(SUM(CASE WHEN b.expiry_date < CURDATE() THEN b.quantity_available * " . self::COST . " END), 0) AS expired_value
               FROM inventory_batches b JOIN products p ON p.id = b.product_id AND p.deleted_at IS NULL
          LEFT JOIN product_variants v ON v.id = b.variant_id
              WHERE b.status = 'active' AND b.quantity_available > 0 AND b.expiry_date IS NOT NULL" . $vs,
            $vp + ['days' => $days]
        );
        return [
            'units'             => (int) $stock['units'],
            'cost_value'        => (float) $stock['cost_value'],
            'sale_value'        => (float) $stock['sale_value'],
            'products_in_stock' => (int) $stock['products_in_stock'],
            'received'          => (int) $flow['received'],
            'sold'              => (int) $flow['sold'],
            'sold_30d'          => (int) $flow['sold_30d'],
            'lost'              => (int) $flow['lost'],
            'out_of_stock'      => (int) $counts['out_of_stock'],
            'low_stock'         => (int) $counts['low_stock'],
            'near_expiry'       => (int) $exp['near_expiry'],
            'expired'           => (int) $exp['expired'],
            'expired_value'     => (float) $exp['expired_value'],
        ];
    }

    /** Products with no stock (stock tracked, not archived). */
    public static function outOfStock(string $vendor, int $limit, int $offset = 0): array
    {
        [$vs, $vp] = self::vendorSql($vendor);
        return self::db()->fetchAll(
            "SELECT p.id, p.name_en, p.name_bn, p.sku, p.strength, p.status, p.stock_qty, p.low_stock_threshold,
                    (SELECT MAX(m.created_at) FROM stock_movements m WHERE m.product_id = p.id AND m.quantity < 0) AS last_out
               FROM products p
              WHERE p.deleted_at IS NULL AND p.track_stock = 1 AND p.stock_qty <= 0 AND p.status IN ('active','pending','draft')" . $vs . '
           ORDER BY p.status = \'active\' DESC, p.name_en LIMIT :lim OFFSET :off',
            $vp + ['lim' => $limit, 'off' => $offset]
        );
    }

    /** Products at or below their own low-stock threshold (or $threshold when given). */
    public static function lowStock(string $vendor, ?int $threshold, int $limit, int $offset = 0): array
    {
        [$vs, $vp] = self::vendorSql($vendor);
        $cond = $threshold === null ? 'p.stock_qty <= p.low_stock_threshold' : 'p.stock_qty <= :thr';
        $params = $vp + ['lim' => $limit, 'off' => $offset] + ($threshold === null ? [] : ['thr' => $threshold]);
        return self::db()->fetchAll(
            "SELECT p.id, p.name_en, p.name_bn, p.sku, p.strength, p.stock_qty, p.low_stock_threshold,
                    (SELECT COALESCE(-SUM(m.quantity), 0) FROM stock_movements m WHERE m.product_id = p.id AND m.type = 'sale' AND m.created_at >= :d30) AS sold_30d
               FROM products p
              WHERE p.deleted_at IS NULL AND p.track_stock = 1 AND p.stock_qty > 0 AND {$cond}
                AND p.status IN ('active','pending','draft')" . $vs . '
           ORDER BY p.stock_qty / GREATEST(p.low_stock_threshold, 1), p.name_en LIMIT :lim OFFSET :off',
            $params + ['d30' => date('Y-m-d H:i:s', strtotime('-30 days'))]
        );
    }

    /** Active batches with stock expiring within $days (expired ones too when $includeExpired). */
    public static function nearExpiry(string $vendor, int $days, bool $includeExpired, int $limit, int $offset = 0): array
    {
        [$vs, $vp] = self::vendorSql($vendor);
        return self::db()->fetchAll(
            'SELECT b.id, b.batch_no, b.expiry_date, b.quantity_available, DATEDIFF(b.expiry_date, CURDATE()) AS days_left,
                    b.quantity_available * ' . self::COST . ' AS cost_value,
                    p.id AS product_id, p.name_en, p.name_bn, p.strength, v.name_en AS variant_name
               FROM inventory_batches b
               JOIN products p ON p.id = b.product_id AND p.deleted_at IS NULL
          LEFT JOIN product_variants v ON v.id = b.variant_id
              WHERE b.status = \'active\' AND b.quantity_available > 0 AND b.expiry_date IS NOT NULL
                AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)'
                . ($includeExpired ? '' : ' AND b.expiry_date >= CURDATE()') . $vs . '
           ORDER BY b.expiry_date, p.name_en LIMIT :lim OFFSET :off',
            $vp + ['days' => $days, 'lim' => $limit, 'off' => $offset]
        );
    }

    /* ======================= Batches ======================= */

    /** Filters: q (product/sku/batch), status, expiry (expired|near|none), vendor, product_id */
    private static function batchFilterSql(array $f): array
    {
        $w = ['p.deleted_at IS NULL'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(p.name_en LIKE :q1 OR p.name_bn LIKE :q2 OR p.sku LIKE :q3 OR b.batch_no LIKE :q4 OR p.generic_name LIKE :q5)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }
        if (in_array($f['status'] ?? '', ['active', 'depleted', 'expired', 'recalled'], true)) {
            $w[] = 'b.status = :status';
            $params['status'] = $f['status'];
        } elseif (($f['status'] ?? '') === 'in_stock') {
            $w[] = "b.status = 'active' AND b.quantity_available > 0";
        }
        if (($f['expiry'] ?? '') === 'expired') {
            $w[] = 'b.expiry_date < CURDATE()';
        } elseif (($f['expiry'] ?? '') === 'near') {
            $w[] = 'b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)';
            $params['days'] = self::expiryDays();
        } elseif (($f['expiry'] ?? '') === 'none') {
            $w[] = 'b.expiry_date IS NULL';
        }
        if (!empty($f['product_id'])) {
            $w[] = 'b.product_id = :pid';
            $params['pid'] = (int) $f['product_id'];
        }
        if (!empty($f['supplier_id'])) {
            $w[] = 'b.supplier_id = :sup';
            $params['sup'] = (int) $f['supplier_id'];
        }
        [$vs, $vp] = self::vendorSql($f['vendor'] ?? '', 'b');
        return [' WHERE ' . implode(' AND ', $w) . $vs, $params + $vp];
    }

    public static function countBatches(array $f): int
    {
        [$w, $p] = self::batchFilterSql($f);
        return (int) self::db()->fetchColumn('SELECT COUNT(*) FROM inventory_batches b JOIN products p ON p.id = b.product_id' . $w, $p);
    }

    public static function batches(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::batchFilterSql($f);
        $order = match ($f['sort'] ?? '') {
            'qty'      => 'b.quantity_available DESC',
            'received' => 'b.received_at DESC',
            default    => 'b.expiry_date IS NULL, b.expiry_date, b.id',
        };
        return self::db()->fetchAll(
            'SELECT b.*, DATEDIFF(b.expiry_date, CURDATE()) AS days_left, ' . self::COST . ' AS unit_cost_eff,
                    p.name_en, p.name_bn, p.sku, p.strength, v.name_en AS variant_name,
                    s.name AS supplier, vd.shop_name AS vendor_name
               FROM inventory_batches b
               JOIN products p ON p.id = b.product_id
          LEFT JOIN product_variants v ON v.id = b.variant_id
          LEFT JOIN suppliers s ON s.id = b.supplier_id
          LEFT JOIN vendors vd ON vd.id = b.vendor_id' . $w . '
           ORDER BY ' . $order . ' LIMIT :lim OFFSET :off',
            $p + ['lim' => $limit, 'off' => $offset]
        );
    }

    public static function batch(int $id): ?array
    {
        return self::db()->fetch(
            'SELECT b.*, DATEDIFF(b.expiry_date, CURDATE()) AS days_left, p.name_en, p.name_bn, p.sku, p.strength, p.product_type,
                    p.price AS product_price, v.name_en AS variant_name, s.name AS supplier, u.name AS received_by_name,
                    g.grn_no, g.purchase_order_id
               FROM inventory_batches b
               JOIN products p ON p.id = b.product_id
          LEFT JOIN product_variants v ON v.id = b.variant_id
          LEFT JOIN suppliers s ON s.id = b.supplier_id
          LEFT JOIN users u ON u.id = b.received_by
          LEFT JOIN grns g ON g.id = b.grn_id
              WHERE b.id = :id',
            ['id' => $id]
        );
    }

    /** Batches of one product/variant for pickers (JSON). */
    public static function batchesFor(int $productId, ?int $variantId): array
    {
        return self::db()->fetchAll(
            "SELECT id, batch_no, expiry_date, quantity_available, status, (expiry_date < CURDATE()) AS is_expired
               FROM inventory_batches
              WHERE product_id = :p AND variant_key = :v AND quantity_available > 0 AND status IN ('active','expired')
           ORDER BY expiry_date IS NULL, expiry_date, id",
            ['p' => $productId, 'v' => (int) $variantId]
        );
    }

    /* ======================= Ledger ======================= */

    /** Filters: q, type, product_id, batch_id, vendor, from, to */
    private static function movementFilterSql(array $f): array
    {
        $w = ['1=1'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(p.name_en LIKE :q1 OR p.sku LIKE :q2 OR b.batch_no LIKE :q3 OR m.note LIKE :q4)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (($f['type'] ?? '') !== '') {
            $w[] = 'm.type = :type';
            $params['type'] = $f['type'];
        }
        if (!empty($f['product_id'])) {
            $w[] = 'm.product_id = :pid';
            $params['pid'] = (int) $f['product_id'];
        }
        if (!empty($f['batch_id'])) {
            $w[] = 'm.batch_id = :bid';
            $params['bid'] = (int) $f['batch_id'];
        }
        if (($f['from'] ?? '') !== '') {
            $w[] = 'm.created_at >= :from';
            $params['from'] = $f['from'] . ' 00:00:00';
        }
        if (($f['to'] ?? '') !== '') {
            $w[] = 'm.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
            $params['to'] = $f['to'];
        }
        [$vs, $vp] = self::vendorSql($f['vendor'] ?? '', 'm');
        return [' WHERE ' . implode(' AND ', $w) . $vs, $params + $vp];
    }

    public static function countMovements(array $f): int
    {
        [$w, $p] = self::movementFilterSql($f);
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN inventory_batches b ON b.id = m.batch_id' . $w, $p
        );
    }

    public static function movements(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::movementFilterSql($f);
        return self::db()->fetchAll(
            'SELECT m.*, p.name_en, p.name_bn, p.sku, p.strength, v.name_en AS variant_name, b.batch_no, u.name AS user_name
               FROM stock_movements m
               JOIN products p ON p.id = m.product_id
          LEFT JOIN product_variants v ON v.id = m.variant_id
          LEFT JOIN inventory_batches b ON b.id = m.batch_id
          LEFT JOIN users u ON u.id = m.created_by' . $w . '
           ORDER BY m.id DESC LIMIT :lim OFFSET :off',
            $p + ['lim' => $limit, 'off' => $offset]
        );
    }

    /* ======================= Valuation ======================= */

    /** Filters: q, category_id (incl. sub-categories), vendor, expired ('only'|'exclude'|'') */
    private static function valuationFilterSql(array $f): array
    {
        $w = ["b.status = 'active'", 'b.quantity_available > 0', 'p.deleted_at IS NULL'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(p.name_en LIKE :q1 OR p.sku LIKE :q2 OR p.generic_name LIKE :q3)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (!empty($f['category_id'])) {
            $ph = [];
            foreach (Category::descendantIds((int) $f['category_id']) as $i => $cid) {
                $ph[] = ':c' . $i;
                $params['c' . $i] = $cid;
            }
            $w[] = 'p.category_id IN (' . implode(',', $ph) . ')';
        }
        if (($f['expired'] ?? '') === 'exclude') {
            $w[] = '(b.expiry_date IS NULL OR b.expiry_date >= CURDATE())';
        }
        [$vs, $vp] = self::vendorSql($f['vendor'] ?? '');
        return [' WHERE ' . implode(' AND ', $w) . $vs, $params + $vp];
    }

    private const VALUATION_SELECT = '
        SELECT p.id, p.name_en, p.name_bn, p.sku, p.strength, p.vendor_id, c.name_en AS category,
               SUM(b.quantity_available) AS qty,
               SUM(b.quantity_available * COALESCE(b.cost_price, v.cost_price, p.cost_price, 0)) AS cost_value,
               SUM(b.quantity_available * COALESCE(v.price, p.price)) AS sale_value,
               SUM(CASE WHEN COALESCE(b.cost_price, v.cost_price, p.cost_price) IS NULL THEN b.quantity_available ELSE 0 END) AS qty_no_cost,
               SUM(CASE WHEN b.expiry_date < CURDATE() THEN b.quantity_available ELSE 0 END) AS qty_expired
          FROM inventory_batches b
          JOIN products p ON p.id = b.product_id
     LEFT JOIN product_variants v ON v.id = b.variant_id
     LEFT JOIN categories c ON c.id = p.category_id';

    public static function countValuation(array $f): int
    {
        [$w, $p] = self::valuationFilterSql($f);
        return (int) self::db()->fetchColumn('SELECT COUNT(DISTINCT p.id) FROM inventory_batches b JOIN products p ON p.id = b.product_id' . $w, $p);
    }

    public static function valuation(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::valuationFilterSql($f);
        $order = match ($f['sort'] ?? '') {
            'margin' => '(sale_value - cost_value) DESC',
            'qty'    => 'qty DESC',
            'name'   => 'p.name_en',
            default  => 'cost_value DESC',
        };
        return self::db()->fetchAll(self::VALUATION_SELECT . $w . ' GROUP BY p.id ORDER BY ' . $order . ', p.id LIMIT :lim OFFSET :off',
            $p + ['lim' => $limit, 'off' => $offset]);
    }

    public static function valuationTotals(array $f): array
    {
        [$w, $p] = self::valuationFilterSql($f);
        $t = self::db()->fetch(
            'SELECT COALESCE(SUM(b.quantity_available), 0) AS qty,
                    COALESCE(SUM(b.quantity_available * ' . self::COST . '), 0) AS cost_value,
                    COALESCE(SUM(b.quantity_available * ' . self::SALE . '), 0) AS sale_value,
                    COALESCE(SUM(CASE WHEN COALESCE(b.cost_price, v.cost_price, p.cost_price) IS NULL THEN b.quantity_available ELSE 0 END), 0) AS qty_no_cost
               FROM inventory_batches b JOIN products p ON p.id = b.product_id LEFT JOIN product_variants v ON v.id = b.variant_id' . $w,
            $p
        );
        return array_map('floatval', $t);
    }

    /* ======================= Vendor-wise ======================= */

    /** One row per stock owner (in-house + each vendor with products). */
    public static function vendorWise(): array
    {
        $days = self::expiryDays();
        return self::db()->fetchAll(
            "SELECT o.vendor_id, COALESCE(vd.shop_name, '') AS vendor_name, vd.shop_name_bn AS vendor_name_bn, vd.status AS vendor_status,
                    o.products, o.tracked, o.out_of_stock, o.low_stock,
                    COALESCE(s.qty, 0) AS qty, COALESCE(s.cost_value, 0) AS cost_value, COALESCE(s.sale_value, 0) AS sale_value,
                    COALESCE(s.batches, 0) AS batches, COALESCE(s.near_expiry_qty, 0) AS near_expiry_qty, COALESCE(s.expired_qty, 0) AS expired_qty
               FROM (SELECT p.vendor_id, COUNT(*) AS products, SUM(p.track_stock = 1) AS tracked,
                            SUM(p.track_stock = 1 AND p.stock_qty <= 0) AS out_of_stock,
                            SUM(p.track_stock = 1 AND p.stock_qty > 0 AND p.stock_qty <= p.low_stock_threshold) AS low_stock
                       FROM products p WHERE p.deleted_at IS NULL GROUP BY p.vendor_id) o
          LEFT JOIN (SELECT p.vendor_id, SUM(b.quantity_available) AS qty, COUNT(*) AS batches,
                            SUM(b.quantity_available * " . self::COST . ") AS cost_value,
                            SUM(b.quantity_available * " . self::SALE . ") AS sale_value,
                            SUM(CASE WHEN b.expiry_date >= CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY) THEN b.quantity_available ELSE 0 END) AS near_expiry_qty,
                            SUM(CASE WHEN b.expiry_date < CURDATE() THEN b.quantity_available ELSE 0 END) AS expired_qty
                       FROM inventory_batches b JOIN products p ON p.id = b.product_id AND p.deleted_at IS NULL
                  LEFT JOIN product_variants v ON v.id = b.variant_id
                      WHERE b.status = 'active' AND b.quantity_available > 0
                   GROUP BY p.vendor_id) s ON s.vendor_id <=> o.vendor_id
          LEFT JOIN vendors vd ON vd.id = o.vendor_id
           ORDER BY o.vendor_id IS NOT NULL, cost_value DESC",
            ['days' => $days]
        );
    }

    /* ======================= Product picker ======================= */

    /** Live search for stock-in/out and PO forms. */
    public static function searchProducts(string $q, int $limit = 15): array
    {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $rows = self::db()->fetchAll(
            'SELECT p.id, p.name_en, p.name_bn, p.sku, p.strength, p.product_type, p.stock_qty, p.cost_price, p.price, p.vendor_id,
                    vd.shop_name AS vendor_name
               FROM products p LEFT JOIN vendors vd ON vd.id = p.vendor_id
              WHERE p.deleted_at IS NULL AND (p.name_en LIKE :q1 OR p.name_bn LIKE :q2 OR p.sku LIKE :q3 OR p.generic_name LIKE :q4 OR p.barcode = :q5)
           ORDER BY (p.sku = :q6) DESC, p.name_en LIMIT :lim',
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $q, 'q6' => $q, 'lim' => $limit]
        );
        if (!$rows) {
            return [];
        }
        $ids = array_column($rows, 'id');
        $variants = self::db()->fetchAll(
            'SELECT id, product_id, name_en, sku, stock_qty, cost_price, price FROM product_variants
              WHERE is_active = 1 AND product_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY is_default DESC, id'
        );
        $byProduct = [];
        foreach ($variants as $v) {
            $byProduct[$v['product_id']][] = $v;
        }
        foreach ($rows as &$r) {
            $r['variants'] = $byProduct[$r['id']] ?? [];
            $r['label'] = trim($r['name_en'] . ' ' . ($r['strength'] && !str_contains($r['name_en'], (string) $r['strength']) ? $r['strength'] : ''));
        }
        return $rows;
    }
}
