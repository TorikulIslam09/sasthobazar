<?php
declare(strict_types=1);

/** Read-side helpers for orders: lists, filters, details, labels. */
final class OrderView
{
    public const BADGES = [
        'pending'             => 'warning',
        'prescription_review' => 'info',
        'confirmed'           => 'primary',
        'processing'          => 'primary',
        'packed'              => 'primary',
        'shipped'             => 'secondary',
        'out_for_delivery'    => 'secondary',
        'delivered'           => 'success',
        'cancelled'           => 'danger',
        'returned'            => 'dark',
        'failed'              => 'danger',
    ];
    public const PAY_BADGES = [
        'unpaid' => 'warning', 'partial' => 'info', 'paid' => 'success', 'refunded' => 'dark',
        'partially_refunded' => 'secondary', 'failed' => 'danger',
    ];
    public const PER_PAGE = 25;

    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function badge(string $status): string
    {
        return '<span class="badge text-bg-' . (self::BADGES[$status] ?? 'secondary') . '">' . e(__('os_' . $status)) . '</span>';
    }

    public static function payBadge(string $status): string
    {
        return '<span class="badge text-bg-' . (self::PAY_BADGES[$status] ?? 'secondary') . '">' . e(__('ps_' . $status)) . '</span>';
    }

    /* ---------------- Customer ---------------- */

    public static function countForUser(int $userId, string $status = ''): int
    {
        [$w, $p] = self::userWhere($userId, $status);
        return (int) self::db()->fetchColumn("SELECT COUNT(*) FROM orders o WHERE {$w}", $p);
    }

    public static function forUser(int $userId, string $status, int $limit, int $offset): array
    {
        [$w, $p] = self::userWhere($userId, $status);
        return self::db()->fetchAll(
            "SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS items,
                    (SELECT product_name FROM order_items WHERE order_id = o.id ORDER BY id LIMIT 1) AS first_item
               FROM orders o WHERE {$w} ORDER BY o.id DESC LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    private static function userWhere(int $userId, string $status): array
    {
        $w = 'o.user_id = :u';
        $p = ['u' => $userId];
        if ($status === 'open') {
            $w .= " AND o.status IN ('" . implode("','", Order::OPEN) . "')";
        } elseif (in_array($status, Order::STATUSES, true)) {
            $w .= ' AND o.status = :s';
            $p['s'] = $status;
        }
        return [$w, $p];
    }

    /* ---------------- Admin list ---------------- */

    /**
     * Filters: status, q (order no / phone / name / TrxID), payment_method, payment_status, from, to, rx (1)
     */
    public static function filterSql(array $f): array
    {
        $w = ['1 = 1'];
        $p = [];
        if (($f['status'] ?? '') !== '' && in_array($f['status'], Order::STATUSES, true)) {
            $w[] = 'o.status = :st';
            $p['st'] = $f['status'];
        }
        if (($f['q'] ?? '') !== '') {
            $q = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(o.order_no LIKE :q1 OR o.customer_phone LIKE :q2 OR o.shipping_phone LIKE :q3 OR o.customer_name LIKE :q4
                     OR EXISTS (SELECT 1 FROM payments py WHERE py.order_id = o.id AND py.transaction_id = :q5))';
            $p += ['q1' => $q, 'q2' => $q, 'q3' => $q, 'q4' => $q, 'q5' => strtoupper($f['q'])];
        }
        if (($f['payment_method'] ?? '') !== '' && in_array($f['payment_method'], Order::PAYMENT_METHODS, true)) {
            $w[] = 'o.payment_method = :pm';
            $p['pm'] = $f['payment_method'];
        }
        if (($f['payment_status'] ?? '') !== '' && isset(self::PAY_BADGES[$f['payment_status']])) {
            $w[] = 'o.payment_status = :ps';
            $p['ps'] = $f['payment_status'];
        }
        if (($f['from'] ?? '') !== '') {
            $w[] = 'o.placed_at >= :from';
            $p['from'] = $f['from'] . ' 00:00:00';
        }
        if (($f['to'] ?? '') !== '') {
            $w[] = 'o.placed_at <= :to';
            $p['to'] = $f['to'] . ' 23:59:59';
        }
        if (!empty($f['rx'])) {
            $w[] = 'o.requires_prescription = 1';
        }
        if (!empty($f['unverified'])) {
            $w[] = "o.status <> 'cancelled' AND EXISTS (SELECT 1 FROM payments py2 WHERE py2.order_id = o.id AND py2.status = 'pending' AND py2.gateway IS NOT NULL)";
        }
        if (!empty($f['return'])) {
            $w[] = "EXISTS (SELECT 1 FROM refunds rf WHERE rf.order_id = o.id AND rf.status IN ('requested','approved'))";
        }
        return [implode(' AND ', $w), $p];
    }

    public static function countFiltered(array $f): int
    {
        [$w, $p] = self::filterSql($f);
        return (int) self::db()->fetchColumn("SELECT COUNT(*) FROM orders o WHERE {$w}", $p);
    }

    public static function filtered(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::filterSql($f);
        return self::db()->fetchAll(
            "SELECT o.*, d.name_en AS district_en, d.name_bn AS district_bn,
                    (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS items,
                    (SELECT COUNT(*) FROM refunds rf WHERE rf.order_id = o.id AND rf.status = 'requested') AS open_returns,
                    (SELECT COUNT(*) FROM payments py WHERE py.order_id = o.id AND py.status = 'pending' AND py.gateway IS NOT NULL) AS unverified
               FROM orders o LEFT JOIN locations d ON d.id = o.district_id
              WHERE {$w} ORDER BY o.id DESC LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    /** status => count (ignoring the status filter). */
    public static function statusCounts(array $f): array
    {
        $f['status'] = '';
        [$w, $p] = self::filterSql($f);
        $rows = self::db()->fetchAll("SELECT o.status, COUNT(*) AS n FROM orders o WHERE {$w} GROUP BY o.status", $p);
        return array_column($rows, 'n', 'status');
    }

    /* ---------------- Detail ---------------- */

    public static function items(int $orderId): array
    {
        return self::db()->fetchAll(
            'SELECT oi.*, p.slug, p.name_bn, v.name_bn AS variant_bn, vd.shop_name, vd.shop_name_bn,
                    (SELECT pi.path FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image
               FROM order_items oi
          LEFT JOIN products p ON p.id = oi.product_id
          LEFT JOIN product_variants v ON v.id = oi.variant_id
          LEFT JOIN vendors vd ON vd.id = oi.vendor_id
              WHERE oi.order_id = :o ORDER BY oi.id',
            ['o' => $orderId]
        );
    }

    /** Product name for display (Bangla name when the UI is Bangla and the product still exists). */
    public static function itemName(array $it): string
    {
        $name = tr($it['name_bn'] ?? null, $it['product_name']);
        $variant = tr($it['variant_bn'] ?? null, $it['variant_name'] ?? null);
        return $variant !== '' ? $name . ' — ' . $variant : $name;
    }

    public static function history(int $orderId, bool $customerOnly = false): array
    {
        return self::db()->fetchAll(
            'SELECT h.*, u.name AS user_name, u.user_type, sp.sub_order_no FROM order_status_history h
          LEFT JOIN users u ON u.id = h.changed_by LEFT JOIN order_vendor_splits sp ON sp.id = h.split_id
              WHERE h.order_id = :o' . ($customerOnly ? ' AND (h.from_status IS NULL OR h.from_status <> h.to_status OR h.notify_customer = 1)' : '') . '
           ORDER BY h.id',
            ['o' => $orderId]
        );
    }

    /** Sub-orders with seller name and item count. */
    public static function splits(int $orderId): array
    {
        return self::db()->fetchAll(
            'SELECT s.*, v.shop_name, v.shop_name_bn, v.phone AS vendor_phone,
                    (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE split_id = s.id) AS items,
                    sh.id AS shipment_id, sh.status AS shipment_status, sh.tracking_no, sh.delivery_option, sh.shipping_fee, sh.is_mock,
                    c.name AS courier_name, c.tracking_url_template
               FROM order_vendor_splits s LEFT JOIN vendors v ON v.id = s.vendor_id
          LEFT JOIN shipments sh ON sh.id = (SELECT MAX(id) FROM shipments WHERE split_id = s.id)
          LEFT JOIN couriers c ON c.id = sh.courier_id
              WHERE s.order_id = :o ORDER BY s.id',
            ['o' => $orderId]
        );
    }

    public static function payments(int $orderId): array
    {
        return self::db()->fetchAll(
            'SELECT py.*, u.name AS received_by_name FROM payments py LEFT JOIN users u ON u.id = py.received_by WHERE py.order_id = :o ORDER BY py.id',
            ['o' => $orderId]
        );
    }

    public static function refunds(int $orderId): array
    {
        return self::db()->fetchAll(
            'SELECT rf.*, oi.product_name, oi.variant_name FROM refunds rf LEFT JOIN order_items oi ON oi.id = rf.order_item_id
              WHERE rf.order_id = :o ORDER BY rf.id',
            ['o' => $orderId]
        );
    }

    public static function prescription(?int $id): ?array
    {
        return $id ? self::db()->fetch(
            'SELECT pr.*, u.name AS reviewer FROM prescriptions pr LEFT JOIN users u ON u.id = pr.reviewed_by WHERE pr.id = :id',
            ['id' => $id]
        ) : null;
    }

    /** "Upazila, District, Division" for an order. */
    public static function area(array $o): string
    {
        $ids = array_filter([(int) $o['upazila_id'], (int) $o['district_id'], (int) $o['division_id']]);
        if (!$ids) {
            return '';
        }
        $rows = array_column(self::db()->fetchAll(
            'SELECT id, name_en, name_bn FROM locations WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')'
        ), null, 'id');
        $out = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $out[] = tr($rows[$id]['name_bn'], $rows[$id]['name_en']);
            }
        }
        return implode(', ', $out);
    }

    /**
     * Timeline steps for tracking: [status, label, time|null, state(done|current|todo)].
     * Cancelled / returned orders end with that step.
     */
    public static function timeline(array $o, array $history): array
    {
        $at = [];
        foreach ($history as $h) {
            if ($h['split_id'] === null && $h['from_status'] !== $h['to_status'] && !isset($at[$h['to_status']])) {
                $at[$h['to_status']] = $h['created_at'];
            }
        }
        $flow = Order::FLOW;
        if (!$o['requires_prescription']) {
            $flow = array_values(array_diff($flow, ['prescription_review']));
        }
        $current = $o['status'];
        $end = in_array($current, ['cancelled', 'returned', 'failed'], true);
        $reached = $end ? array_keys($at) : [];
        $idx = array_search($current, $flow, true);
        $steps = [];
        foreach ($flow as $i => $s) {
            if ($end) {
                $state = in_array($s, $reached, true) ? 'done' : 'todo';
                if ($state === 'todo') {
                    continue; // only show what really happened before the cancellation / return
                }
            } else {
                $state = $idx === false ? 'todo' : ($i < $idx ? 'done' : ($i === $idx ? 'current' : 'todo'));
            }
            $steps[] = [$s, __('os_' . $s), $at[$s] ?? null, $state];
        }
        if ($end) {
            $steps[] = [$current, __('os_' . $current), $at[$current] ?? $o['cancelled_at'] ?? null, 'end'];
        }
        return $steps;
    }
}
