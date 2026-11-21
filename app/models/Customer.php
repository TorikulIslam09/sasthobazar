<?php
declare(strict_types=1);

/**
 * Admin view of customers: registered accounts (users.user_type = customer) and guest buyers
 * (orders without an account, grouped by mobile number).
 */
final class Customer
{
    public const STATUSES = ['active', 'inactive', 'banned'];
    public const SORTS = ['newest', 'orders', 'spent', 'last_order', 'name'];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** @return array{0: string, 1: array} WHERE + params for the registered list */
    private static function where(array $f): array
    {
        $w = ["u.user_type = 'customer'", 'u.deleted_at IS NULL'];
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            $w[] = '(u.name LIKE :q1 OR u.phone LIKE :q2 OR u.email LIKE :q3)';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (in_array($f['status'] ?? '', self::STATUSES, true)) {
            $w[] = 'u.status = :st';
            $p['st'] = $f['status'];
        }
        if (($f['from'] ?? '') !== '') {
            $w[] = 'u.created_at >= :from';
            $p['from'] = $f['from'] . ' 00:00:00';
        }
        if (($f['to'] ?? '') !== '') {
            $w[] = 'u.created_at <= :to';
            $p['to'] = $f['to'] . ' 23:59:59';
        }
        if (($f['buyers'] ?? '') === '1') {
            $w[] = "EXISTS (SELECT 1 FROM orders o2 WHERE o2.user_id = u.id AND o2.status <> 'cancelled')";
        } elseif (($f['buyers'] ?? '') === '0') {
            $w[] = "NOT EXISTS (SELECT 1 FROM orders o2 WHERE o2.user_id = u.id AND o2.status <> 'cancelled')";
        }
        return [implode(' AND ', $w), $p];
    }

    public static function count(array $f): int
    {
        [$w, $p] = self::where($f);
        return (int) self::db()->fetchColumn("SELECT COUNT(*) FROM users u WHERE {$w}", $p);
    }

    public static function list(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::where($f);
        $order = [
            'newest'     => 'u.id DESC',
            'orders'     => 'orders DESC, u.id DESC',
            'spent'      => 'spent DESC, u.id DESC',
            'last_order' => 'last_order IS NULL, last_order DESC',
            'name'       => 'u.name ASC',
        ][$f['sort'] ?? 'newest'] ?? 'u.id DESC';
        return self::db()->fetchAll(
            "SELECT u.id, u.name, u.phone, u.email, u.status, u.created_at, u.last_login_at, u.phone_verified_at, u.email_verified_at,
                    COALESCE(s.orders, 0) AS orders, COALESCE(s.spent, 0) AS spent, s.last_order
               FROM users u
          LEFT JOIN (SELECT user_id, COUNT(*) AS orders, SUM(grand_total - refunded_amount) AS spent, MAX(placed_at) AS last_order
                       FROM orders WHERE user_id IS NOT NULL AND status <> 'cancelled' GROUP BY user_id) s ON s.user_id = u.id
              WHERE {$w} ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    /** Counts for the status tabs + totals. */
    public static function summary(): array
    {
        $db = self::db();
        $by = array_column($db->fetchAll("SELECT status, COUNT(*) AS n FROM users WHERE user_type = 'customer' AND deleted_at IS NULL GROUP BY status"), 'n', 'status');
        return [
            'statuses'  => $by,
            'total'     => array_sum($by),
            'new_month' => (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type = 'customer' AND deleted_at IS NULL AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'guests'    => (int) $db->fetchColumn("SELECT COUNT(DISTINCT customer_phone) FROM orders WHERE user_id IS NULL AND customer_phone IS NOT NULL"),
        ];
    }

    /** Guest buyers (no account): one row per mobile number. */
    public static function guests(string $q, int $limit, int $offset): array
    {
        [$w, $p] = self::guestWhere($q);
        return self::db()->fetchAll(
            "SELECT customer_phone AS phone, MAX(customer_name) AS name, MAX(customer_email) AS email, COUNT(*) AS orders,
                    SUM(CASE WHEN status <> 'cancelled' THEN grand_total - refunded_amount ELSE 0 END) AS spent,
                    MAX(placed_at) AS last_order, MAX(contact_verified) AS verified
               FROM orders WHERE {$w} GROUP BY customer_phone ORDER BY last_order DESC LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    public static function countGuests(string $q): int
    {
        [$w, $p] = self::guestWhere($q);
        return (int) self::db()->fetchColumn("SELECT COUNT(DISTINCT customer_phone) FROM orders WHERE {$w}", $p);
    }

    private static function guestWhere(string $q): array
    {
        $w = 'user_id IS NULL AND customer_phone IS NOT NULL';
        $p = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $w .= ' AND (customer_phone LIKE :q1 OR customer_name LIKE :q2 OR customer_email LIKE :q3)';
            $p = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        return [$w, $p];
    }

    public static function find(int $id): ?array
    {
        return self::db()->fetch("SELECT * FROM users WHERE id = :id AND user_type = 'customer' AND deleted_at IS NULL", ['id' => $id]);
    }

    /** Everything shown on the customer page. */
    public static function details(int $id): array
    {
        $db = self::db();
        return [
            'stats'     => $db->fetch(
                "SELECT COUNT(*) AS orders, SUM(status = 'delivered') AS delivered, SUM(status = 'cancelled') AS cancelled,
                        COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN grand_total - refunded_amount END), 0) AS spent,
                        COALESCE(SUM(refunded_amount), 0) AS refunded, MIN(placed_at) AS first_order, MAX(placed_at) AS last_order
                   FROM orders WHERE user_id = :u",
                ['u' => $id]
            ),
            'orders'    => $db->fetchAll('SELECT id, order_no, status, payment_method, payment_status, grand_total, placed_at FROM orders WHERE user_id = :u ORDER BY id DESC LIMIT 20', ['u' => $id]),
            'addresses' => $db->fetchAll('SELECT * FROM addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC', ['u' => $id]),
            'rx'        => $db->fetchAll('SELECT id, group_code, request_type, status, original_name, created_at FROM prescriptions WHERE user_id = :u ORDER BY id DESC LIMIT 10', ['u' => $id]),
            'tickets'   => $db->fetchAll('SELECT id, ticket_no, subject, status, created_at FROM support_tickets WHERE user_id = :u ORDER BY id DESC LIMIT 10', ['u' => $id]),
            'wishlist'  => (int) $db->fetchColumn('SELECT COUNT(*) FROM wishlists WHERE user_id = :u', ['u' => $id]),
            'reviews'   => (int) $db->fetchColumn('SELECT COUNT(*) FROM reviews WHERE user_id = :u', ['u' => $id]),
        ];
    }

    public static function openOrders(int $id): int
    {
        return (int) self::db()->fetchColumn(
            "SELECT COUNT(*) FROM orders WHERE user_id = :u AND status NOT IN ('delivered','cancelled','returned','failed')",
            ['u' => $id]
        );
    }
}
