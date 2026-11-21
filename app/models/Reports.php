<?php
declare(strict_types=1);

/**
 * Report definitions shared by the report pages and the CSV / Excel / PDF export.
 *
 * Each report returns:
 *   title, subtitle, cards => [[label, value, type]], tables => [[title, columns => [[key, label, type]], rows, totals]],
 *   chart (optional) => [type, labels, datasets => [[label, data, kind]]], notes => [string]
 *
 * Basis:
 *   sales / products / vendors / customers / VAT: orders by order date, cancelled and failed orders excluded
 *   commission: seller sub-orders by delivery date (that is when commission is booked)
 *   delivery: shipments by booking date;  COD: shipments by delivery date, settlements by receive date
 *   stock / expiry: current stock (no date range)
 */
final class Reports
{
    /** key => [permission group, icon, filters] */
    public const LIST = [
        'sales'      => ['reports', 'bi-graph-up-arrow', ['range', 'group_by']],
        'products'   => ['reports', 'bi-capsule', ['range', 'category', 'seller']],
        'vendors'    => ['reports', 'bi-shop', ['range']],
        'commission' => ['reports', 'bi-percent', ['range', 'vendor']],
        'stock'      => ['reports', 'bi-box-seam', ['category', 'seller', 'stock_state']],
        'expiry'     => ['reports', 'bi-calendar-x', ['days', 'seller']],
        'customers'  => ['reports', 'bi-people', ['range']],
        'delivery'   => ['reports', 'bi-truck', ['range', 'courier']],
        'ledger'     => ['accounting', 'bi-journal-text', ['range', 'vendor']],
        'payouts'    => ['accounting', 'bi-cash-coin', ['range', 'vendor', 'payout_status']],
        'cod'        => ['accounting', 'bi-cash-stack', ['range', 'courier']],
        'refunds'    => ['accounting', 'bi-arrow-counterclockwise', ['range', 'refund_status']],
        'vat'        => ['accounting', 'bi-receipt-cutoff', ['range']],
        'entries'    => ['accounting', 'bi-journal-plus', ['range', 'entry_type', 'entry_category']],
    ];
    public const VALID = "o.status NOT IN ('cancelled','failed')";

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /* ---------------- filters ---------------- */

    /** Filters from the query string (validated). */
    public static function filters(): array
    {
        $ok = static fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $from = $ok(Helpers::str('from')) ? Helpers::str('from') : date('Y-m-01');
        $to = $ok(Helpers::str('to')) ? Helpers::str('to') : date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $int = static fn(string $k) => ctype_digit(Helpers::str($k)) ? (int) Helpers::str($k) : null;
        $pick = static fn(string $k, array $allowed) => in_array(Helpers::str($k), $allowed, true) ? Helpers::str($k) : '';
        $seller = Helpers::str('seller');
        return [
            'from'           => $from,
            'to'             => $to,
            'group_by'       => $pick('group_by', ['day', 'week', 'month']),
            'category'       => $int('category'),
            'seller'         => $seller === 'inhouse' ? 'inhouse' : (ctype_digit($seller) ? $seller : ''),
            'vendor'         => $int('vendor'),
            'courier'        => Helpers::str('courier') === 'own' ? 'own' : ($int('courier') !== null ? (string) $int('courier') : ''),
            'stock_state'    => $pick('stock_state', ['low', 'out']),
            'days'           => in_array($int('days'), [30, 60, 90, 180, 365], true) ? $int('days') : (int) setting('expiry_alert_days', 90),
            'payout_status'  => $pick('payout_status', ['requested', 'approved', 'paid', 'rejected']),
            'refund_status'  => $pick('refund_status', ['requested', 'approved', 'processed', 'rejected']),
            'entry_type'     => $pick('entry_type', ['income', 'expense']),
            'entry_category' => $int('entry_category'),
        ];
    }

    /** Date range presets for the filter bar: key => [from, to]. */
    public static function presets(): array
    {
        $t = date('Y-m-d');
        return [
            'today'      => [$t, $t],
            'yesterday'  => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            'last_7'     => [date('Y-m-d', strtotime('-6 days')), $t],
            'last_30'    => [date('Y-m-d', strtotime('-29 days')), $t],
            'this_month' => [date('Y-m-01'), $t],
            'last_month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
            'this_year'  => [date('Y-01-01'), $t],
        ];
    }

    private static function range(array $f): array
    {
        return [$f['from'] . ' 00:00:00', $f['to'] . ' 23:59:59'];
    }

    private static function subtitle(array $f, bool $range = true): string
    {
        return $range
            ? __('rp_range', ['from' => dt($f['from'], 'd M Y'), 'to' => dt($f['to'], 'd M Y')])
            : __('rp_as_of', ['date' => dt(date('Y-m-d H:i:s'), 'd M Y, h:i A')]);
    }

    /** Sum the given keys of rows into a totals row. */
    private static function totals(array $rows, array $keys, array $base = []): array
    {
        $t = $base;
        foreach ($keys as $k) {
            $t[$k] = array_sum(array_map(static fn($r) => (float) ($r[$k] ?? 0), $rows));
        }
        return $t;
    }

    private static function pct(float $part, float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    /** "seller" filter -> SQL on a vendor_id column. */
    private static function sellerSql(array $f, string $col, array &$p): string
    {
        if ($f['seller'] === 'inhouse') {
            return " AND {$col} IS NULL";
        }
        if ($f['seller'] !== '') {
            $p['seller'] = (int) $f['seller'];
            return " AND {$col} = :seller";
        }
        return '';
    }

    public static function build(string $key, array $f): array
    {
        $r = match ($key) {
            'sales'      => self::sales($f),
            'products'   => self::products($f),
            'vendors'    => self::vendors($f),
            'commission' => self::commission($f),
            'stock'      => self::stock($f),
            'expiry'     => self::expiry($f),
            'customers'  => self::customers($f),
            'delivery'   => self::delivery($f),
            'ledger'     => self::ledger($f),
            'payouts'    => self::payouts($f),
            'cod'        => self::cod($f),
            'refunds'    => self::refunds($f),
            'vat'        => self::vat($f),
            'entries'    => self::entries($f),
        };
        return $r + ['title' => __('rp_' . $key), 'cards' => [], 'notes' => [], 'chart' => null];
    }

    /* ======================================================================
     * Reports
     * ==================================================================== */

    private static function sales(array $f): array
    {
        [$from, $to] = self::range($f);
        $days = (int) ((strtotime($f['to']) - strtotime($f['from'])) / 86400) + 1;
        $group = $f['group_by'] ?: ($days <= 62 ? 'day' : 'month');
        $period = match ($group) {
            'week'  => "DATE(o.placed_at - INTERVAL WEEKDAY(o.placed_at) DAY)",
            'month' => "DATE_FORMAT(o.placed_at, '%Y-%m')",
            default => 'DATE(o.placed_at)',
        };
        $db = self::db();
        $rows = array_column($db->fetchAll(
            "SELECT {$period} AS period, COUNT(*) AS orders, SUM(o.subtotal) AS gross, SUM(o.discount_total) AS discounts,
                    SUM(o.shipping_total + o.cod_charge) AS delivery, SUM(o.vat_total) AS vat, SUM(o.grand_total) AS total,
                    SUM(o.refunded_amount) AS refunds, SUM(o.grand_total - o.refunded_amount) AS net
               FROM orders o WHERE " . self::VALID . " AND o.placed_at BETWEEN :f AND :t GROUP BY period ORDER BY period",
            ['f' => $from, 't' => $to]
        ), null, 'period');
        $items = array_column($db->fetchAll(
            "SELECT {$period} AS period, SUM(oi.quantity) AS items FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE " . self::VALID . " AND oi.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t GROUP BY period",
            ['f' => $from, 't' => $to]
        ), 'items', 'period');

        // one row per period (empty days too)
        $keys = [];
        if ($group === 'day') {
            for ($d = strtotime($f['from']); $d <= strtotime($f['to']); $d += 86400) {
                $keys[] = date('Y-m-d', $d);
            }
        } else {
            $keys = array_keys($rows);
        }
        $out = [];
        foreach ($keys as $k) {
            $r = $rows[$k] ?? ['orders' => 0, 'gross' => 0, 'discounts' => 0, 'delivery' => 0, 'vat' => 0, 'total' => 0, 'refunds' => 0, 'net' => 0];
            $label = match ($group) {
                'month' => dt($k . '-01', 'M Y'),
                'week'  => __('rp_week_of', ['date' => dt($k, 'd M')]),
                default => dt($k, 'd M Y'),
            };
            $out[] = ['period' => $label, 'items' => (int) ($items[$k] ?? 0)] + array_map('floatval', array_diff_key($r, ['period' => 1]));
        }
        $sum = ['period' => __('total')] + self::totals($out, ['orders', 'items', 'gross', 'discounts', 'delivery', 'vat', 'total', 'refunds', 'net']);

        $byMethod = $db->fetchAll(
            "SELECT o.payment_method AS method, COUNT(*) AS orders, SUM(o.grand_total) AS total, SUM(o.grand_total - o.refunded_amount) AS net
               FROM orders o WHERE " . self::VALID . " AND o.placed_at BETWEEN :f AND :t GROUP BY o.payment_method ORDER BY total DESC",
            ['f' => $from, 't' => $to]
        );
        foreach ($byMethod as &$m) {
            $m['method'] = __('pm_' . $m['method']);
            $m['share'] = self::pct((float) $m['net'], (float) $sum['net']);
        }
        unset($m);
        $byStatus = $db->fetchAll(
            'SELECT o.status, COUNT(*) AS orders, SUM(o.grand_total) AS total FROM orders o WHERE o.placed_at BETWEEN :f AND :t GROUP BY o.status ORDER BY orders DESC',
            ['f' => $from, 't' => $to]
        );
        foreach ($byStatus as &$s) {
            $s['status'] = __('os_' . $s['status']);
        }
        unset($s);

        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [
                [__('rp_net_sales'), $sum['net'], 'money'],
                [__('orders'), $sum['orders'], 'int'],
                [__('dash_avg_order'), $sum['orders'] > 0 ? $sum['net'] / $sum['orders'] : 0, 'money'],
                [__('rp_items_sold'), $sum['items'], 'int'],
                [__('rp_discounts'), $sum['discounts'], 'money'],
                [__('rp_refunds'), $sum['refunds'], 'money'],
            ],
            'tables'   => [
                ['title' => __('rp_sales_by_' . $group), 'columns' => [
                    ['period', __('rp_period'), 'text'], ['orders', __('orders'), 'int'], ['items', __('items'), 'int'],
                    ['gross', __('rp_gross'), 'money'], ['discounts', __('rp_discounts'), 'money'], ['delivery', __('rp_delivery_cod'), 'money'],
                    ['vat', Tax::label(), 'money'], ['total', __('rp_order_total'), 'money'], ['refunds', __('rp_refunds'), 'money'], ['net', __('rp_net_sales'), 'money'],
                ], 'rows' => $out, 'totals' => $sum],
                ['title' => __('rp_by_payment'), 'columns' => [
                    ['method', __('payment_method'), 'text'], ['orders', __('orders'), 'int'], ['total', __('rp_order_total'), 'money'],
                    ['net', __('rp_net_sales'), 'money'], ['share', __('rp_share'), 'pct'],
                ], 'rows' => $byMethod, 'totals' => ['method' => __('total')] + self::totals($byMethod, ['orders', 'total', 'net'])],
                ['title' => __('rp_by_status'), 'columns' => [
                    ['status', __('status'), 'text'], ['orders', __('orders'), 'int'], ['total', __('rp_order_total'), 'money'],
                ], 'rows' => $byStatus, 'totals' => null],
            ],
            'chart'    => ['labels' => array_column($out, 'period'), 'datasets' => [
                ['label' => __('rp_net_sales'), 'data' => array_map(static fn($r) => round($r['net'], 2), $out), 'kind' => 'bar'],
                ['label' => __('orders'), 'data' => array_column($out, 'orders'), 'kind' => 'line'],
            ]],
            'notes'    => [__('rp_note_order_date')],
        ];
    }

    private static function products(array $f): array
    {
        [$from, $to] = self::range($f);
        $p = ['f' => $from, 't' => $to];
        $where = '';
        if ($f['category']) {
            $ids = array_map('intval', Category::descendantIds($f['category']));
            $where .= ' AND pr.category_id IN (' . ($ids ? implode(',', $ids) : '0') . ')';
        }
        $where .= self::sellerSql($f, 'oi.vendor_id', $p);
        $rows = self::db()->fetchAll(
            "SELECT oi.product_id, MAX(oi.product_name) AS name, MAX(oi.sku) AS sku, MAX(COALESCE(v.shop_name, '')) AS seller,
                    MAX(oi.vendor_id IS NULL) AS in_house,
                    COUNT(DISTINCT oi.order_id) AS orders, SUM(oi.quantity) AS qty, SUM(oi.returned_qty) AS returned,
                    SUM((oi.line_total - oi.discount_amount) * (oi.quantity - oi.returned_qty) / oi.quantity) AS revenue,
                    SUM(CASE WHEN oi.vendor_id IS NULL THEN COALESCE(oi.unit_cost, 0) * (oi.quantity - oi.returned_qty) ELSE 0 END) AS cost,
                    SUM(oi.vendor_id IS NULL AND oi.unit_cost IS NULL) AS cost_missing,
                    SUM(CASE WHEN oi.vendor_id IS NULL THEN 0 ELSE oi.commission_amount END) AS commission
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
          LEFT JOIN products pr ON pr.id = oi.product_id LEFT JOIN vendors v ON v.id = oi.vendor_id
              WHERE " . self::VALID . " AND oi.status <> 'cancelled' AND o.placed_at BETWEEN :f AND :t {$where}
           GROUP BY oi.product_id ORDER BY revenue DESC LIMIT 1000",
            $p
        );
        foreach ($rows as &$r) {
            $r['seller'] = $r['seller'] !== '' ? $r['seller'] : __('app_name');
            if ((int) $r['in_house']) {
                $r['cost'] = (int) $r['cost_missing'] > 0 ? null : (float) $r['cost'];
                $r['profit'] = $r['cost'] === null ? null : (float) $r['revenue'] - $r['cost'];
            } else {
                $r['cost'] = null;
                $r['profit'] = (float) $r['commission'];
            }
            $r['margin'] = $r['profit'] === null ? null : self::pct((float) $r['profit'], (float) $r['revenue']);
            $r['net_qty'] = (int) $r['qty'] - (int) $r['returned'];
        }
        unset($r);
        $t = ['name' => __('total')] + self::totals($rows, ['orders', 'qty', 'returned', 'net_qty', 'revenue', 'cost', 'profit']);
        $t['orders'] = null;
        $t['margin'] = self::pct((float) $t['profit'], (float) $t['revenue']);
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [
                [__('rp_products_sold'), count($rows), 'int'], [__('rp_units_sold'), $t['net_qty'], 'int'],
                [__('rp_revenue'), $t['revenue'], 'money'], [__('rp_platform_profit'), $t['profit'], 'money'],
            ],
            'tables'   => [['title' => '', 'columns' => [
                ['name', __('product'), 'text'], ['sku', 'SKU', 'text'], ['seller', __('seller'), 'text'], ['orders', __('orders'), 'int'],
                ['qty', __('rp_qty_sold'), 'int'], ['returned', __('rp_returned'), 'int'], ['revenue', __('rp_revenue'), 'money'],
                ['cost', __('rp_cost'), 'money'], ['profit', __('rp_platform_profit'), 'money'], ['margin', __('rp_margin'), 'pct'],
            ], 'rows' => $rows, 'totals' => $t]],
            'notes'    => [__('rp_note_products')],
        ];
    }

    private static function vendors(array $f): array
    {
        [$from, $to] = self::range($f);
        $db = self::db();
        $rows = $db->fetchAll(
            "SELECT v.shop_name AS seller, v.status AS vendor_status,
                    COUNT(s.id) AS orders, SUM(s.status = 'delivered') AS delivered, SUM(s.status = 'cancelled') AS cancelled,
                    SUM(s.status = 'returned') AS returned,
                    COALESCE(SUM(CASE WHEN s.status <> 'cancelled' THEN s.subtotal - s.discount_total END), 0) AS sales,
                    COALESCE(SUM(CASE WHEN s.settlement_status IN ('pending','eligible','settled') THEN s.commission_amount END), 0) AS commission,
                    COALESCE(SUM(CASE WHEN s.settlement_status IN ('pending','eligible','settled') THEN s.vendor_earning END), 0) AS earning,
                    COALESCE((SELECT SUM(r.amount) FROM refunds r JOIN order_vendor_splits s2 ON s2.id = r.split_id JOIN orders o2 ON o2.id = s2.order_id
                               WHERE s2.vendor_id = v.id AND r.status IN ('approved','processed') AND o2.placed_at BETWEEN :f2 AND :t2), 0) AS refunds
               FROM vendors v JOIN order_vendor_splits s ON s.vendor_id = v.id
               JOIN orders o ON o.id = s.order_id AND o.placed_at BETWEEN :f AND :t
           GROUP BY v.id, v.shop_name, v.status ORDER BY sales DESC",
            ['f' => $from, 't' => $to, 'f2' => $from, 't2' => $to]
        );
        $inHouse = $db->fetch(
            "SELECT COUNT(*) AS orders, SUM(s.status = 'delivered') AS delivered, SUM(s.status = 'cancelled') AS cancelled, SUM(s.status = 'returned') AS returned,
                    COALESCE(SUM(CASE WHEN s.status <> 'cancelled' THEN s.subtotal - s.discount_total END), 0) AS sales
               FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id WHERE s.vendor_id IS NULL AND o.placed_at BETWEEN :f AND :t",
            ['f' => $from, 't' => $to]
        );
        foreach ($rows as &$r) {
            $r['vendor_status'] = __('vs_' . $r['vendor_status']);
        }
        unset($r);
        if ((int) $inHouse['orders'] > 0) {
            array_unshift($rows, ['seller' => __('app_name') . ' (' . __('in_house') . ')', 'vendor_status' => '—', 'commission' => null, 'earning' => null, 'refunds' => null] + $inHouse);
        }
        foreach ($rows as &$r) {
            $done = (int) $r['delivered'] + (int) $r['returned'];
            $r['rate'] = self::pct((float) $r['delivered'], (float) $done);
        }
        unset($r);
        $t = ['seller' => __('total')] + self::totals($rows, ['orders', 'delivered', 'cancelled', 'returned', 'sales', 'commission', 'earning', 'refunds']);
        $t['rate'] = self::pct((float) $t['delivered'], (float) $t['delivered'] + (float) $t['returned']);
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [
                [__('v_sales'), $t['sales'], 'money'], [__('commission_earned'), $t['commission'], 'money'],
                [__('vendor_earning'), $t['earning'], 'money'], [__('rp_sub_orders'), $t['orders'], 'int'],
            ],
            'tables'   => [['title' => '', 'columns' => [
                ['seller', __('seller'), 'text'], ['vendor_status', __('status'), 'text'], ['orders', __('rp_sub_orders'), 'int'],
                ['delivered', __('os_delivered'), 'int'], ['cancelled', __('os_cancelled'), 'int'], ['returned', __('os_returned'), 'int'],
                ['rate', __('rp_delivery_rate'), 'pct'], ['sales', __('v_sales'), 'money'], ['commission', __('commission_earned'), 'money'],
                ['earning', __('vendor_earning'), 'money'], ['refunds', __('rp_refunds'), 'money'],
            ], 'rows' => $rows, 'totals' => $t]],
            'chart'    => ['type' => 'bar', 'labels' => array_slice(array_column($rows, 'seller'), 0, 10), 'datasets' => [
                ['label' => __('v_sales'), 'data' => array_slice(array_map(static fn($r) => round((float) $r['sales'], 2), $rows), 0, 10), 'kind' => 'bar'],
            ]],
            'notes'    => [__('rp_note_vendors')],
        ];
    }

    private static function commission(array $f): array
    {
        [$from, $to] = self::range($f);
        $p = ['f' => $from, 't' => $to];
        $w = '';
        if ($f['vendor']) {
            $w = ' AND s.vendor_id = :v';
            $p['v'] = $f['vendor'];
        }
        $rows = self::db()->fetchAll(
            "SELECT s.delivered_at, o.order_no, s.sub_order_no, v.shop_name AS seller,
                    COALESCE((SELECT SUM(c.base_amount) FROM commissions c WHERE c.split_id = s.id), 0) AS base,
                    s.commission_amount AS commission, s.vendor_earning AS earning, s.settlement_status
               FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id JOIN vendors v ON v.id = s.vendor_id
              WHERE s.settlement_status IN ('pending','eligible','settled') AND s.delivered_at BETWEEN :f AND :t {$w}
           ORDER BY s.delivered_at, s.id",
            $p
        );
        $by = [];
        foreach ($rows as &$r) {
            $r['rate'] = self::pct((float) $r['commission'], (float) $r['base']);
            $r['settlement_status'] = __('settle_' . $r['settlement_status']);
            $b = &$by[$r['seller']];
            $b ??= ['seller' => $r['seller'], 'orders' => 0, 'base' => 0.0, 'commission' => 0.0, 'earning' => 0.0];
            $b['orders']++;
            $b['base'] += (float) $r['base'];
            $b['commission'] += (float) $r['commission'];
            $b['earning'] += (float) $r['earning'];
            unset($b);
        }
        unset($r);
        $by = array_values($by);
        usort($by, static fn($a, $b) => $b['commission'] <=> $a['commission']);
        foreach ($by as &$b) {
            $b['rate'] = self::pct($b['commission'], $b['base']);
        }
        unset($b);
        $t = self::totals($rows, ['base', 'commission', 'earning']);
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [
                [__('rp_commission_income'), $t['commission'], 'money'], [__('rp_commission_base'), $t['base'], 'money'],
                [__('rp_avg_rate'), self::pct($t['commission'], $t['base']), 'pct'], [__('rp_sub_orders'), count($rows), 'int'],
            ],
            'tables'   => [
                ['title' => __('rp_by_seller'), 'columns' => [
                    ['seller', __('seller'), 'text'], ['orders', __('rp_sub_orders'), 'int'], ['base', __('rp_commission_base'), 'money'],
                    ['rate', __('rp_avg_rate'), 'pct'], ['commission', __('commission'), 'money'], ['earning', __('vendor_earning'), 'money'],
                ], 'rows' => $by, 'totals' => ['seller' => __('total')] + self::totals($by, ['orders', 'base', 'commission', 'earning'])],
                ['title' => __('rp_commission_detail'), 'columns' => [
                    ['delivered_at', __('rp_delivered_on'), 'date'], ['order_no', __('order'), 'text'], ['sub_order_no', __('sub_order'), 'text'],
                    ['seller', __('seller'), 'text'], ['base', __('rp_commission_base'), 'money'], ['rate', __('rp_rate'), 'pct'],
                    ['commission', __('commission'), 'money'], ['earning', __('vendor_earning'), 'money'], ['settlement_status', __('status'), 'text'],
                ], 'rows' => $rows, 'totals' => ['delivered_at' => null, 'order_no' => __('total')] + $t],
            ],
            'notes'    => [__('rp_note_commission')],
        ];
    }

    private static function stock(array $f): array
    {
        $p = [];
        $w = '';
        if ($f['category']) {
            $ids = array_map('intval', Category::descendantIds($f['category']));
            $w .= ' AND p.category_id IN (' . ($ids ? implode(',', $ids) : '0') . ')';
        }
        $w .= self::sellerSql($f, 'p.vendor_id', $p);
        if ($f['stock_state'] === 'low') {
            $w .= ' AND p.stock_qty > 0 AND p.stock_qty <= p.low_stock_threshold';
        } elseif ($f['stock_state'] === 'out') {
            $w .= ' AND p.stock_qty <= 0';
        }
        $rows = self::db()->fetchAll(
            "SELECT p.name_en AS name, p.sku, COALESCE(c.name_en, '') AS category, COALESCE(v.shop_name, '') AS seller,
                    p.stock_qty AS stock, p.low_stock_threshold AS threshold, p.price,
                    COALESCE((SELECT SUM(b.quantity_available * COALESCE(b.cost_price, 0)) FROM inventory_batches b
                               WHERE b.product_id = p.id AND b.status = 'active'), 0) AS cost_value
               FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN vendors v ON v.id = p.vendor_id
              WHERE p.deleted_at IS NULL AND p.track_stock = 1 {$w}
           ORDER BY p.stock_qty <= 0 DESC, p.stock_qty <= p.low_stock_threshold DESC, p.name_en LIMIT 3000",
            $p
        );
        $low = $out = 0;
        foreach ($rows as &$r) {
            $r['seller'] = $r['seller'] !== '' ? $r['seller'] : __('app_name');
            $r['retail_value'] = max(0, (int) $r['stock']) * (float) $r['price'];
            $state = (int) $r['stock'] <= 0 ? 'out' : ((int) $r['stock'] <= (int) $r['threshold'] ? 'low' : 'ok');
            $low += $state === 'low' ? 1 : 0;
            $out += $state === 'out' ? 1 : 0;
            $r['state'] = __('rp_stock_' . $state);
        }
        unset($r);
        $t = ['name' => __('total')] + self::totals($rows, ['stock', 'cost_value', 'retail_value']);
        return [
            'subtitle' => self::subtitle($f, false),
            'cards'    => [
                [__('products'), count($rows), 'int'], [__('rp_units_in_stock'), $t['stock'], 'int'],
                [__('rp_value_cost'), $t['cost_value'], 'money'], [__('rp_value_retail'), $t['retail_value'], 'money'],
                [__('rp_stock_low'), $low, 'int'], [__('rp_stock_out'), $out, 'int'],
            ],
            'tables'   => [['title' => '', 'columns' => [
                ['name', __('product'), 'text'], ['sku', 'SKU', 'text'], ['category', __('category'), 'text'], ['seller', __('seller'), 'text'],
                ['stock', __('rp_stock'), 'int'], ['threshold', __('rp_reorder_level'), 'int'], ['price', __('price'), 'money'],
                ['cost_value', __('rp_value_cost'), 'money'], ['retail_value', __('rp_value_retail'), 'money'], ['state', __('status'), 'text'],
            ], 'rows' => $rows, 'totals' => $t]],
            'notes'    => [__('rp_note_stock')],
        ];
    }

    private static function expiry(array $f): array
    {
        $p = ['d' => $f['days']];
        $w = self::sellerSql($f, 'b.vendor_id', $p);
        $rows = self::db()->fetchAll(
            "SELECT p.name_en AS name, p.sku, b.batch_no, b.expiry_date, DATEDIFF(b.expiry_date, CURDATE()) AS days_left,
                    b.quantity_available AS qty, b.cost_price AS cost, b.quantity_available * COALESCE(b.cost_price, 0) AS value,
                    COALESCE(v.shop_name, '') AS seller, COALESCE(b.supplier_name, '') AS supplier
               FROM inventory_batches b JOIN products p ON p.id = b.product_id LEFT JOIN vendors v ON v.id = b.vendor_id
              WHERE b.status = 'active' AND b.quantity_available > 0 AND b.expiry_date IS NOT NULL
                AND b.expiry_date <= (CURDATE() + INTERVAL :d DAY) {$w}
           ORDER BY b.expiry_date, p.name_en LIMIT 3000",
            $p
        );
        $expired = 0;
        $expValue = 0.0;
        foreach ($rows as &$r) {
            $r['seller'] = $r['seller'] !== '' ? $r['seller'] : __('app_name');
            $isExp = (int) $r['days_left'] < 0;
            $expired += $isExp ? 1 : 0;
            $expValue += $isExp ? (float) $r['value'] : 0;
            $r['state'] = $isExp ? __('rp_expired') : __('rp_expiring');
        }
        unset($r);
        $t = ['name' => __('total')] + self::totals($rows, ['qty', 'value']);
        return [
            'subtitle' => __('rp_expiry_within', ['n' => num($f['days'])]) . ' · ' . self::subtitle($f, false),
            'cards'    => [
                [__('rp_batches'), count($rows), 'int'], [__('rp_expired'), $expired, 'int'],
                [__('rp_units'), $t['qty'], 'int'], [__('rp_value_at_risk'), $t['value'], 'money'], [__('rp_expired_value'), $expValue, 'money'],
            ],
            'tables'   => [['title' => '', 'columns' => [
                ['name', __('product'), 'text'], ['sku', 'SKU', 'text'], ['batch_no', __('batch_no'), 'text'], ['expiry_date', __('expiry_date'), 'date'],
                ['days_left', __('rp_days_left'), 'int'], ['qty', __('rp_units'), 'int'], ['cost', __('rp_unit_cost'), 'money'],
                ['value', __('rp_value_cost'), 'money'], ['seller', __('seller'), 'text'], ['supplier', __('supplier'), 'text'], ['state', __('status'), 'text'],
            ], 'rows' => $rows, 'totals' => $t]],
        ];
    }

    private static function customers(array $f): array
    {
        [$from, $to] = self::range($f);
        $db = self::db();
        $rows = $db->fetchAll(
            "SELECT u.id, u.name, u.phone, u.created_at AS registered, COUNT(o.id) AS orders,
                    SUM(o.grand_total - o.refunded_amount) AS spent, MAX(o.placed_at) AS last_order,
                    (SELECT MIN(o2.placed_at) FROM orders o2 WHERE o2.user_id = u.id AND o2.status NOT IN ('cancelled','failed')) AS first_order
               FROM users u JOIN orders o ON o.user_id = u.id
              WHERE u.user_type = 'customer' AND " . self::VALID . " AND o.placed_at BETWEEN :f AND :t
           GROUP BY u.id, u.name, u.phone, u.created_at ORDER BY spent DESC LIMIT 2000",
            ['f' => $from, 't' => $to]
        );
        $new = $repeat = 0;
        foreach ($rows as &$r) {
            $isNew = $r['first_order'] !== null && $r['first_order'] >= $from;
            $new += $isNew ? 1 : 0;
            $repeat += (int) $r['orders'] > 1 ? 1 : 0;
            $r['type'] = $isNew ? __('rp_new_buyer') : __('rp_returning');
            $r['aov'] = (int) $r['orders'] > 0 ? (float) $r['spent'] / (int) $r['orders'] : 0;
        }
        unset($r);
        $registered = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type = 'customer' AND deleted_at IS NULL AND created_at BETWEEN :f AND :t", ['f' => $from, 't' => $to]);
        $t = ['name' => __('total')] + self::totals($rows, ['orders', 'spent']);
        $t['aov'] = $t['orders'] > 0 ? $t['spent'] / $t['orders'] : 0;
        $daily = $db->fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS n FROM users WHERE user_type = 'customer' AND deleted_at IS NULL AND created_at BETWEEN :f AND :t GROUP BY DATE(created_at) ORDER BY d",
            ['f' => $from, 't' => $to]
        );
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [
                [__('rp_new_registrations'), $registered, 'int'], [__('rp_buyers'), count($rows), 'int'], [__('rp_new_buyers'), $new, 'int'],
                [__('rp_returning_buyers'), count($rows) - $new, 'int'], [__('rp_repeat_rate'), self::pct($repeat, count($rows)), 'pct'],
                [__('rp_avg_spend'), count($rows) ? $t['spent'] / count($rows) : 0, 'money'],
            ],
            'tables'   => [['title' => '', 'columns' => [
                ['name', __('customer'), 'text'], ['phone', __('phone'), 'text'], ['orders', __('orders'), 'int'], ['spent', __('rp_spent'), 'money'],
                ['aov', __('dash_avg_order'), 'money'], ['first_order', __('rp_first_order'), 'date'], ['last_order', __('rp_last_order'), 'date'],
                ['registered', __('rp_registered'), 'date'], ['type', __('type'), 'text'],
            ], 'rows' => $rows, 'totals' => $t]],
            'chart'    => $daily ? ['labels' => array_map(static fn($d) => dt($d['d'], 'd M'), $daily), 'datasets' => [
                ['label' => __('rp_new_registrations'), 'data' => array_map('intval', array_column($daily, 'n')), 'kind' => 'bar'],
            ]] : null,
        ];
    }

    private static function delivery(array $f): array
    {
        [$from, $to] = self::range($f);
        $p = ['f' => $from, 't' => $to];
        $w = '';
        if ($f['courier'] === 'own') {
            $w = ' AND sh.courier_id IS NULL';
        } elseif ($f['courier'] !== '') {
            $w = ' AND sh.courier_id = :c';
            $p['c'] = (int) $f['courier'];
        }
        $agg = "COUNT(*) AS total,
                SUM(sh.status IN ('delivered','partial_delivered')) AS delivered,
                SUM(sh.status IN ('returned','returning')) AS returned,
                SUM(sh.status = 'failed') AS failed,
                SUM(sh.status = 'lost') AS lost,
                SUM(sh.status = 'cancelled') AS cancelled,
                SUM(sh.status IN ('pending','booked','picked_up','in_transit','out_for_delivery','on_hold')) AS in_progress,
                SUM(sh.failed_attempts) AS attempts,
                AVG(CASE WHEN sh.delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, COALESCE(sh.shipped_at, sh.booked_at, sh.created_at), sh.delivered_at) END) / 24 AS avg_days";
        $base = "FROM shipments sh LEFT JOIN couriers c ON c.id = sh.courier_id LEFT JOIN shipping_zones z ON z.id = sh.zone_id
                 WHERE COALESCE(sh.booked_at, sh.created_at) BETWEEN :f AND :t {$w}";
        $db = self::db();
        $byCourier = $db->fetchAll("SELECT COALESCE(c.name, IF(sh.delivery_option = 'pickup', 'pickup', 'own')) AS name, {$agg} {$base} GROUP BY name ORDER BY total DESC", $p);
        $byZone = $db->fetchAll("SELECT COALESCE(z.name, '—') AS name, {$agg} {$base} GROUP BY name ORDER BY total DESC", $p);
        $fix = static function (array &$rows) {
            foreach ($rows as &$r) {
                if ($r['name'] === 'own') {
                    $r['name'] = __('own_delivery');
                } elseif ($r['name'] === 'pickup') {
                    $r['name'] = __('dopt_pickup');
                }
                $done = (int) $r['delivered'] + (int) $r['returned'] + (int) $r['failed'] + (int) $r['lost'];
                $r['rate'] = self::pct((float) $r['delivered'], (float) $done);
                $r['avg_days'] = $r['avg_days'] !== null ? round((float) $r['avg_days'], 1) : null;
            }
        };
        $fix($byCourier);
        $fix($byZone);
        $sumKeys = ['total', 'delivered', 'returned', 'failed', 'lost', 'cancelled', 'in_progress', 'attempts'];
        $t = ['name' => __('total')] + self::totals($byCourier, $sumKeys);
        $t['rate'] = self::pct((float) $t['delivered'], (float) ($t['delivered'] + $t['returned'] + $t['failed'] + $t['lost']));
        $avg = $db->fetchColumn("SELECT AVG(CASE WHEN sh.delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, COALESCE(sh.shipped_at, sh.booked_at, sh.created_at), sh.delivered_at) END) / 24 {$base}", $p);
        $t['avg_days'] = $avg !== null && $avg !== false ? round((float) $avg, 1) : null;
        $cols = static fn(string $first) => [
            ['name', $first, 'text'], ['total', __('rp_shipments'), 'int'], ['delivered', __('os_delivered'), 'int'], ['returned', __('os_returned'), 'int'],
            ['failed', __('sh_failed'), 'int'], ['lost', __('sh_lost'), 'int'], ['cancelled', __('os_cancelled'), 'int'], ['in_progress', __('rp_in_progress'), 'int'],
            ['rate', __('rp_success_rate'), 'pct'], ['avg_days', __('rp_avg_days'), 'num'], ['attempts', __('rp_failed_attempts'), 'int'],
        ];
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [
                [__('rp_shipments'), $t['total'], 'int'], [__('os_delivered'), $t['delivered'], 'int'], [__('rp_success_rate'), $t['rate'], 'pct'],
                [__('os_returned'), $t['returned'], 'int'], [__('rp_in_progress'), $t['in_progress'], 'int'], [__('rp_avg_days'), $t['avg_days'], 'num'],
            ],
            'tables'   => [
                ['title' => __('rp_by_courier'), 'columns' => $cols(__('courier')), 'rows' => $byCourier, 'totals' => $t],
                ['title' => __('rp_by_zone'), 'columns' => $cols(__('zone')), 'rows' => $byZone, 'totals' => null],
            ],
            'notes'    => [__('rp_note_delivery')],
        ];
    }

    /* ---------------- accounting registers ---------------- */

    private static function ledger(array $f): array
    {
        [$from, $to] = self::range($f);
        $p = ['f' => $from, 't' => $to];
        $w = '';
        if ($f['vendor']) {
            $w = ' AND t.vendor_id = :v';
            $p['v'] = $f['vendor'];
        }
        $rows = self::db()->fetchAll(
            "SELECT t.created_at, v.shop_name AS seller, COALESCE(s.sub_order_no, '') AS ref, COALESCE(t.note, '') AS note, t.amount
               FROM vendor_wallet_transactions t JOIN vendors v ON v.id = t.vendor_id
          LEFT JOIN order_vendor_splits s ON t.reference_type = 'split' AND s.id = t.reference_id
              WHERE t.type = 'commission' AND t.created_at BETWEEN :f AND :t {$w} ORDER BY t.created_at, t.id",
            $p
        );
        $bal = 0.0;
        foreach ($rows as &$r) {
            $a = (float) $r['amount'];
            $r['earned'] = $a < 0 ? -$a : null;
            $r['reversed'] = $a > 0 ? $a : null;
            $bal += -$a;
            $r['balance'] = $bal;
        }
        unset($r);
        $t = ['created_at' => null, 'seller' => __('total')] + self::totals($rows, ['earned', 'reversed']);
        $t['balance'] = $bal;
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [[__('rp_commission_earned'), $t['earned'], 'money'], [__('rp_commission_reversed'), $t['reversed'], 'money'], [__('rp_net_commission'), $bal, 'money']],
            'tables'   => [['title' => '', 'columns' => [
                ['created_at', __('date'), 'datetime'], ['seller', __('seller'), 'text'], ['ref', __('sub_order'), 'text'], ['note', __('description'), 'text'],
                ['earned', __('rp_earned'), 'money'], ['reversed', __('rp_reversed'), 'money'], ['balance', __('rp_running_total'), 'money'],
            ], 'rows' => $rows, 'totals' => $t]],
            'notes'    => [__('rp_note_ledger')],
        ];
    }

    private static function payouts(array $f): array
    {
        [$from, $to] = self::range($f);
        $p = ['f' => $from, 't' => $to];
        $w = '';
        if ($f['vendor']) {
            $w .= ' AND vp.vendor_id = :v';
            $p['v'] = $f['vendor'];
        }
        if ($f['payout_status'] !== '') {
            $w .= ' AND vp.status = :st';
            $p['st'] = $f['payout_status'];
        }
        $rows = self::db()->fetchAll(
            "SELECT vp.id, vp.requested_at, v.shop_name AS seller, vp.amount, vp.fee, vp.amount - vp.fee AS net, vp.method, vp.status,
                    COALESCE(vp.reference_no, '') AS reference, vp.processed_at
               FROM vendor_payouts vp JOIN vendors v ON v.id = vp.vendor_id
              WHERE vp.requested_at BETWEEN :f AND :t {$w} ORDER BY vp.requested_at DESC",
            $p
        );
        $by = [];
        foreach ($rows as &$r) {
            $r['no'] = '#' . $r['id'];
            $r['method'] = __('mfs_' . $r['method']);
            $by[$r['status']] ??= ['status' => __('po_st_' . $r['status']), 'count' => 0, 'amount' => 0.0];
            $by[$r['status']]['count']++;
            $by[$r['status']]['amount'] += (float) $r['amount'];
            $r['status'] = __('po_st_' . $r['status']);
        }
        unset($r);
        $paid = (float) self::db()->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM vendor_payouts WHERE status = 'paid' AND processed_at BETWEEN :f AND :t", ['f' => $from, 't' => $to]);
        $open = (float) self::db()->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM vendor_payouts WHERE status IN ('requested','approved')");
        $t = ['no' => __('total')] + self::totals($rows, ['amount', 'fee', 'net']);
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [[__('rp_paid_in_period'), $paid, 'money'], [__('rp_open_payouts'), $open, 'money'], [__('rp_fees'), $t['fee'], 'money']],
            'tables'   => [
                ['title' => __('rp_by_status'), 'columns' => [['status', __('status'), 'text'], ['count', __('rp_count'), 'int'], ['amount', __('amount'), 'money']],
                 'rows' => array_values($by), 'totals' => null],
                ['title' => __('rp_payout_list'), 'columns' => [
                    ['no', '#', 'text'], ['requested_at', __('rp_requested_on'), 'date'], ['seller', __('seller'), 'text'], ['amount', __('amount'), 'money'],
                    ['fee', __('rp_fee'), 'money'], ['net', __('rp_net_paid'), 'money'], ['method', __('payout_method'), 'text'], ['status', __('status'), 'text'],
                    ['reference', __('reference'), 'text'], ['processed_at', __('rp_processed_on'), 'date'],
                ], 'rows' => $rows, 'totals' => $t],
            ],
        ];
    }

    private static function cod(array $f): array
    {
        [$from, $to] = self::range($f);
        $p = ['f' => $from, 't' => $to];
        $w = '';
        $w2 = '';
        $p2 = ['f' => $from, 't' => $to];
        if ($f['courier'] === 'own') {
            $w = ' AND sh.courier_id IS NULL';
            $w2 = ' AND cs.courier_id IS NULL';
        } elseif ($f['courier'] !== '') {
            $w = ' AND sh.courier_id = :c';
            $w2 = ' AND cs.courier_id = :c';
            $p['c'] = $p2['c'] = (int) $f['courier'];
        }
        $db = self::db();
        $rows = $db->fetchAll(
            "SELECT COALESCE(c.name, 'own') AS courier, COUNT(*) AS shipments, SUM(sh.cod_amount) AS to_collect, SUM(sh.collected_amount) AS collected,
                    SUM(CASE WHEN sh.cod_status = 'settled' THEN sh.collected_amount ELSE 0 END) AS settled,
                    SUM(CASE WHEN sh.cod_status = 'collected' THEN sh.collected_amount ELSE 0 END) AS held,
                    SUM(CASE WHEN sh.cod_status = 'pending' THEN sh.cod_amount ELSE 0 END) AS not_collected,
                    SUM(sh.delivery_charge) AS courier_fees
               FROM shipments sh LEFT JOIN couriers c ON c.id = sh.courier_id
              WHERE sh.cod_amount > 0 AND sh.delivered_at BETWEEN :f AND :t {$w}
           GROUP BY courier ORDER BY to_collect DESC",
            $p
        );
        foreach ($rows as &$r) {
            $r['courier'] = $r['courier'] === 'own' ? __('own_delivery') : $r['courier'];
            $r['short'] = max(0, (float) $r['to_collect'] - (float) $r['collected'] - (float) $r['not_collected']);
        }
        unset($r);
        $settlements = $db->fetchAll(
            "SELECT cs.received_at, COALESCE(c.name, '') AS courier, cs.reference_no, cs.shipments_count, cs.amount_expected, cs.amount_received, cs.courier_fees,
                    cs.amount_expected - cs.amount_received - cs.courier_fees AS difference
               FROM cod_settlements cs LEFT JOIN couriers c ON c.id = cs.courier_id
              WHERE cs.received_at BETWEEN :f AND :t {$w2} ORDER BY cs.received_at DESC",
            $p2
        );
        $t = ['courier' => __('total')] + self::totals($rows, ['shipments', 'to_collect', 'collected', 'settled', 'held', 'not_collected', 'short', 'courier_fees']);
        $heldNow = (float) $db->fetchColumn("SELECT COALESCE(SUM(collected_amount), 0) FROM shipments WHERE cod_status = 'collected'");
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [
                [__('rp_cod_collected'), $t['collected'], 'money'], [__('rp_cod_settled'), $t['settled'], 'money'],
                [__('rp_cod_held_now'), $heldNow, 'money'], [__('rp_courier_fees'), $t['courier_fees'], 'money'],
            ],
            'tables'   => [
                ['title' => __('rp_by_courier'), 'columns' => [
                    ['courier', __('courier'), 'text'], ['shipments', __('rp_shipments'), 'int'], ['to_collect', __('rp_cod_to_collect'), 'money'],
                    ['collected', __('rp_cod_collected'), 'money'], ['settled', __('rp_cod_settled'), 'money'], ['held', __('rp_cod_held'), 'money'],
                    ['not_collected', __('rp_cod_not_collected'), 'money'], ['short', __('rp_cod_short'), 'money'], ['courier_fees', __('rp_courier_fees'), 'money'],
                ], 'rows' => $rows, 'totals' => $t],
                ['title' => __('rp_settlements'), 'columns' => [
                    ['received_at', __('rp_received_on'), 'date'], ['courier', __('courier'), 'text'], ['reference_no', __('reference'), 'text'],
                    ['shipments_count', __('rp_shipments'), 'int'], ['amount_expected', __('rp_expected'), 'money'], ['amount_received', __('rp_received'), 'money'],
                    ['courier_fees', __('rp_courier_fees'), 'money'], ['difference', __('rp_difference'), 'money'],
                ], 'rows' => $settlements, 'totals' => ['received_at' => null, 'courier' => __('total')] + self::totals($settlements, ['shipments_count', 'amount_expected', 'amount_received', 'courier_fees', 'difference'])],
            ],
            'notes'    => [__('rp_note_cod')],
        ];
    }

    private static function refunds(array $f): array
    {
        [$from, $to] = self::range($f);
        $p = ['f' => $from, 't' => $to];
        $w = '';
        if ($f['refund_status'] !== '') {
            $w = ' AND r.status = :st';
            $p['st'] = $f['refund_status'];
        }
        $rows = self::db()->fetchAll(
            "SELECT r.refund_no, r.created_at, o.order_no, COALESCE(oi.product_name, '—') AS item, r.quantity, r.amount, r.method, r.status,
                    COALESCE(r.reason, '') AS reason, COALESCE(r.reference_no, '') AS reference, r.processed_at
               FROM refunds r JOIN orders o ON o.id = r.order_id LEFT JOIN order_items oi ON oi.id = r.order_item_id
              WHERE r.created_at BETWEEN :f AND :t {$w} ORDER BY r.created_at DESC",
            $p
        );
        $by = [];
        foreach ($rows as &$r) {
            $by[$r['status']] ??= ['status' => __('rf_' . $r['status']), 'count' => 0, 'amount' => 0.0];
            $by[$r['status']]['count']++;
            $by[$r['status']]['amount'] += (float) $r['amount'];
            $r['status'] = __('rf_' . $r['status']);
            $r['method'] = $r['method'] ? __('rm_' . $r['method']) : '';
        }
        unset($r);
        $paid = (float) self::db()->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'processed' AND processed_at BETWEEN :f AND :t", ['f' => $from, 't' => $to]);
        $toPay = (float) self::db()->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'approved'");
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [[__('rp_refunded_in_period'), $paid, 'money'], [__('rp_refunds_to_pay'), $toPay, 'money'], [__('rp_refund_requests'), count($rows), 'int']],
            'tables'   => [
                ['title' => __('rp_by_status'), 'columns' => [['status', __('status'), 'text'], ['count', __('rp_count'), 'int'], ['amount', __('amount'), 'money']],
                 'rows' => array_values($by), 'totals' => null],
                ['title' => __('rp_refund_list'), 'columns' => [
                    ['refund_no', __('rp_refund_no'), 'text'], ['created_at', __('date'), 'date'], ['order_no', __('order'), 'text'], ['item', __('product'), 'text'],
                    ['quantity', __('quantity'), 'int'], ['amount', __('amount'), 'money'], ['method', __('rp_method'), 'text'], ['status', __('status'), 'text'],
                    ['reason', __('reason'), 'text'], ['reference', __('reference'), 'text'], ['processed_at', __('rp_processed_on'), 'date'],
                ], 'rows' => $rows, 'totals' => ['refund_no' => __('total')] + self::totals($rows, ['quantity', 'amount'])],
            ],
        ];
    }

    private static function vat(array $f): array
    {
        [$from, $to] = self::range($f);
        $kept = '(oi.quantity - oi.returned_qty) / oi.quantity';
        $db = self::db();
        $rows = $db->fetchAll(
            "SELECT DATE_FORMAT(o.placed_at, '%Y-%m') AS month, oi.vat_rate AS rate, (oi.vendor_id IS NULL) AS in_house,
                    COUNT(DISTINCT o.id) AS orders,
                    SUM(((oi.line_total - oi.discount_amount) - IF(oi.vat_in_price = 1, oi.vat_amount, 0)) * {$kept}) AS taxable,
                    SUM(oi.vat_amount * {$kept}) AS vat
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE " . self::VALID . " AND oi.status <> 'cancelled' AND oi.vat_amount > 0 AND o.placed_at BETWEEN :f AND :t
           GROUP BY month, oi.vat_rate, in_house ORDER BY month, rate",
            ['f' => $from, 't' => $to]
        );
        $platform = 0.0;
        foreach ($rows as &$r) {
            $platform += (int) $r['in_house'] ? (float) $r['vat'] : 0;
            $r['month'] = dt($r['month'] . '-01', 'M Y');
            $r['seller'] = (int) $r['in_house'] ? __('app_name') . ' (' . __('in_house') . ')' : __('rp_sellers');
        }
        unset($r);
        $bySeller = $db->fetchAll(
            "SELECT COALESCE(v.shop_name, '') AS seller, v.bin_no AS bin,
                    SUM(((oi.line_total - oi.discount_amount) - IF(oi.vat_in_price = 1, oi.vat_amount, 0)) * {$kept}) AS taxable,
                    SUM(oi.vat_amount * {$kept}) AS vat
               FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN vendors v ON v.id = oi.vendor_id
              WHERE " . self::VALID . " AND oi.status <> 'cancelled' AND oi.vat_amount > 0 AND o.placed_at BETWEEN :f AND :t
           GROUP BY oi.vendor_id, v.shop_name, v.bin_no ORDER BY vat DESC",
            ['f' => $from, 't' => $to]
        );
        foreach ($bySeller as &$s) {
            $s['seller'] = $s['seller'] !== '' ? $s['seller'] : __('app_name') . ' (' . __('in_house') . ')';
            $s['bin'] = (string) ($s['bin'] ?? '');
        }
        unset($s);
        $t = ['month' => __('total')] + self::totals($rows, ['taxable', 'vat']);
        return [
            'subtitle' => self::subtitle($f) . (Tax::regNo() !== '' ? ' · ' . __('vat_reg_no') . ': ' . Tax::regNo() : ''),
            'cards'    => [
                [__('rp_vat_collected'), $t['vat'], 'money'], [__('rp_taxable_value'), $t['taxable'], 'money'],
                [__('rp_vat_payable_platform'), $platform, 'money'], [__('rp_vat_sellers'), $t['vat'] - $platform, 'money'],
            ],
            'tables'   => [
                ['title' => __('rp_vat_by_month'), 'columns' => [
                    ['month', __('rp_month'), 'text'], ['rate', __('rp_rate'), 'pct'], ['seller', __('seller'), 'text'], ['orders', __('orders'), 'int'],
                    ['taxable', __('rp_taxable_value'), 'money'], ['vat', Tax::label(), 'money'],
                ], 'rows' => $rows, 'totals' => $t],
                ['title' => __('rp_by_seller'), 'columns' => [
                    ['seller', __('seller'), 'text'], ['bin', __('vat_reg_no'), 'text'], ['taxable', __('rp_taxable_value'), 'money'], ['vat', Tax::label(), 'money'],
                ], 'rows' => $bySeller, 'totals' => ['seller' => __('total')] + self::totals($bySeller, ['taxable', 'vat'])],
            ],
            'notes'    => [__('rp_note_vat')],
        ];
    }

    private static function entries(array $f): array
    {
        [$from, $to] = [$f['from'], $f['to']];
        $p = ['f' => $from, 't' => $to];
        $w = '';
        if ($f['entry_type'] !== '') {
            $w .= ' AND e.type = :ty';
            $p['ty'] = $f['entry_type'];
        }
        if ($f['entry_category']) {
            $w .= ' AND e.category_id = :c';
            $p['c'] = $f['entry_category'];
        }
        $rows = self::db()->fetchAll(
            "SELECT e.id, e.entry_no, e.entry_date, e.type, c.name_en, c.name_bn, e.description, e.payment_method, e.reference, e.amount
               FROM account_entries e JOIN account_categories c ON c.id = e.category_id
              WHERE e.deleted_at IS NULL AND e.entry_date BETWEEN :f AND :t {$w} ORDER BY e.entry_date DESC, e.id DESC",
            $p
        );
        $by = [];
        foreach ($rows as &$r) {
            $r['category'] = tr($r['name_bn'], $r['name_en']);
            $r['income'] = $r['type'] === 'income' ? (float) $r['amount'] : null;
            $r['expense'] = $r['type'] === 'expense' ? (float) $r['amount'] : null;
            $r['type_label'] = __('acc_' . $r['type']);
            $r['method'] = __('acc_pm_' . $r['payment_method']);
            $k = $r['type'] . '|' . $r['category'];
            $by[$k] ??= ['type' => $r['type_label'], 'category' => $r['category'], 'count' => 0, 'amount' => 0.0];
            $by[$k]['count']++;
            $by[$k]['amount'] += (float) $r['amount'];
        }
        unset($r);
        $t = ['entry_no' => __('total')] + self::totals($rows, ['income', 'expense']);
        return [
            'subtitle' => self::subtitle($f),
            'cards'    => [[__('acc_total_income'), $t['income'], 'money'], [__('acc_total_expense'), $t['expense'], 'money'], [__('acc_net'), $t['income'] - $t['expense'], 'money']],
            'tables'   => [
                ['title' => __('rp_by_category'), 'columns' => [['type', __('type'), 'text'], ['category', __('category'), 'text'], ['count', __('rp_count'), 'int'], ['amount', __('amount'), 'money']],
                 'rows' => array_values($by), 'totals' => null],
                ['title' => __('acc_entries'), 'columns' => [
                    ['entry_date', __('date'), 'date'], ['entry_no', __('acc_entry_no'), 'text'], ['type_label', __('type'), 'text'], ['category', __('category'), 'text'],
                    ['description', __('description'), 'text'], ['method', __('rp_method'), 'text'], ['reference', __('reference'), 'text'],
                    ['income', __('acc_income'), 'money'], ['expense', __('acc_expense'), 'money'],
                ], 'rows' => $rows, 'totals' => $t],
            ],
        ];
    }
}
