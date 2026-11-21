<?php
declare(strict_types=1);

/**
 * Admin dashboard: what needs attention, sales, recent orders, top products, stock alerts.
 * "View Dashboard" (dashboard.view) is the master switch - without it staff see a welcome screen with no figures;
 * with it, every block still needs the matching module permission.
 */
class AdminDashboardController extends Controller
{
    public function index(): void
    {
        $db = $this->db;
        $dash = can('dashboard.view');
        $canOrders = $dash && can('orders.view');
        $canStock = $dash && can('inventory.view');
        $data = [
            'title'     => __('dashboard'),
            'canOrders' => $canOrders,
            'canStock'  => $canStock,
            'canRx'     => $dash && can('prescriptions.view'),
            'canRefund' => $dash && can('refunds.view'),
            'canDash'   => $dash,
        ];

        // ---- Needs attention ----
        $attention = [];
        if ($canOrders) {
            $attention['pending_orders'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
            $attention['to_ship'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','processing','packed')");
            $attention['unverified_payments'] = (int) $db->fetchColumn(
                "SELECT COUNT(DISTINCT p.order_id) FROM payments p JOIN orders o ON o.id = p.order_id
                  WHERE p.status = 'pending' AND p.gateway IS NOT NULL AND o.status <> 'cancelled'"
            );
        }
        if ($data['canRx'] || $canOrders) {
            $attention['rx_review'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'prescription_review'");
        }
        if ($data['canRefund']) {
            $attention['return_requests'] = (int) $db->fetchColumn("SELECT COUNT(DISTINCT order_id) FROM refunds WHERE status = 'requested'");
            $attention['refunds_to_pay'] = (int) $db->fetchColumn("SELECT COUNT(DISTINCT order_id) FROM refunds WHERE status = 'approved'");
        }
        if ($canStock) {
            $attention['low_stock'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 'active' AND track_stock = 1
                    AND stock_qty > 0 AND stock_qty <= low_stock_threshold"
            );
            $attention['out_of_stock'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 'active' AND track_stock = 1 AND stock_qty <= 0"
            );
            $attention['near_expiry'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM inventory_batches WHERE status = 'active' AND quantity_available > 0
                    AND expiry_date IS NOT NULL AND expiry_date <= (CURDATE() + INTERVAL :d DAY)",
                ['d' => (int) setting('expiry_alert_days', 90)]
            );
        }
        $data['attention'] = $attention;

        // ---- Sales & orders ----
        if ($canOrders) {
            $valid = "status NOT IN ('cancelled','failed')";
            $sum = static fn(string $from) => $db->fetch(
                "SELECT COUNT(*) AS n, COALESCE(SUM(grand_total - refunded_amount), 0) AS total FROM orders WHERE {$valid} AND placed_at >= :f",
                ['f' => $from]
            );
            // last month up to the same day of the month (fair comparison with this month so far)
            $lastStart = date('Y-m-01 00:00:00', strtotime('first day of last month'));
            $lastEnd = date('Y-m-d 23:59:59', min(strtotime(date('Y-m-t', strtotime($lastStart))), strtotime($lastStart . ' +' . ((int) date('j') - 1) . ' days')));
            $data['sales'] = [
                'today' => $sum(date('Y-m-d 00:00:00')),
                'month' => $sum(date('Y-m-01 00:00:00')),
                'last'  => $db->fetch(
                    "SELECT COUNT(*) AS n, COALESCE(SUM(grand_total - refunded_amount), 0) AS total FROM orders WHERE {$valid} AND placed_at BETWEEN :f AND :t",
                    ['f' => $lastStart, 't' => $lastEnd]
                ),
                'yesterday' => $db->fetch(
                    "SELECT COUNT(*) AS n, COALESCE(SUM(grand_total - refunded_amount), 0) AS total FROM orders WHERE {$valid} AND placed_at BETWEEN :f AND :t",
                    ['f' => date('Y-m-d 00:00:00', strtotime('-1 day')), 't' => date('Y-m-d 23:59:59', strtotime('-1 day'))]
                ),
            ];
            $data['avgOrder'] = $data['sales']['month']['n'] > 0 ? $data['sales']['month']['total'] / $data['sales']['month']['n'] : 0;

            // last 30 days, one bar per day
            $rows = array_column($db->fetchAll(
                "SELECT DATE(placed_at) AS d, COUNT(*) AS n, COALESCE(SUM(grand_total - refunded_amount), 0) AS total
                   FROM orders WHERE {$valid} AND placed_at >= :f GROUP BY DATE(placed_at)",
                ['f' => date('Y-m-d 00:00:00', strtotime('-29 days'))]
            ), null, 'd');
            $chart = [];
            for ($i = 29; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $chart[] = ['date' => $d, 'n' => (int) ($rows[$d]['n'] ?? 0), 'total' => (float) ($rows[$d]['total'] ?? 0)];
            }
            $data['chart'] = $chart;

            $data['statusCounts'] = array_column($db->fetchAll('SELECT status, COUNT(*) AS n FROM orders GROUP BY status'), 'n', 'status');
            $data['recent'] = $db->fetchAll(
                'SELECT id, order_no, customer_name, customer_phone, grand_total, status, payment_method, payment_status, requires_prescription, placed_at
                   FROM orders ORDER BY id DESC LIMIT 8'
            );
            $data['topProducts'] = $db->fetchAll(
                "SELECT oi.product_id, MAX(oi.product_name) AS name, SUM(oi.quantity - oi.returned_qty) AS qty, SUM(oi.line_total) AS revenue
                   FROM order_items oi JOIN orders o ON o.id = oi.order_id
                  WHERE o.status NOT IN ('cancelled','failed') AND o.placed_at >= :f AND oi.status <> 'cancelled'
               GROUP BY oi.product_id ORDER BY qty DESC LIMIT 5",
                ['f' => date('Y-m-d 00:00:00', strtotime('-29 days'))]
            );
        }

        // ---- Commission income (commission ledger: booked on delivery, reversals deducted) ----
        if ($dash && (can('commissions.view') || can('accounting.view') || can('reports.view'))) {
            $comm = static fn(string $from) => -(float) $db->fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM vendor_wallet_transactions WHERE type = 'commission' AND created_at >= :f", ['f' => $from]
            );
            $data['commission'] = ['today' => $comm(date('Y-m-d 00:00:00')), 'month' => $comm(date('Y-m-01 00:00:00'))];
        }

        // ---- Catalog / people ----
        $data['totals'] = [
            'total_products'  => (int) $db->fetchColumn("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 'active'"),
            'total_customers' => (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type = 'customer' AND deleted_at IS NULL"),
            'new_customers'   => (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type = 'customer' AND deleted_at IS NULL AND created_at >= :f", ['f' => date('Y-m-01 00:00:00')]),
            'new_today'       => (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE user_type = 'customer' AND deleted_at IS NULL AND created_at >= :f", ['f' => date('Y-m-d 00:00:00')]),
            'total_vendors'   => (int) $db->fetchColumn("SELECT COUNT(*) FROM vendors WHERE deleted_at IS NULL AND status = 'approved'"),
        ];
        if ($canStock) {
            $data['lowStock'] = $db->fetchAll(
                "SELECT id, name_en, name_bn, stock_qty, low_stock_threshold FROM products
                  WHERE deleted_at IS NULL AND status = 'active' AND track_stock = 1 AND stock_qty <= low_stock_threshold
               ORDER BY stock_qty ASC, name_en LIMIT 6"
            );
            $data['expiring'] = $db->fetchAll(
                "SELECT b.id, b.batch_no, b.expiry_date, b.quantity_available, p.id AS product_id, p.name_en, p.name_bn,
                        DATEDIFF(b.expiry_date, CURDATE()) AS days_left
                   FROM inventory_batches b JOIN products p ON p.id = b.product_id
                  WHERE b.status = 'active' AND b.quantity_available > 0 AND b.expiry_date IS NOT NULL
                    AND b.expiry_date <= (CURDATE() + INTERVAL :d DAY)
               ORDER BY b.expiry_date LIMIT 6",
                ['d' => (int) setting('expiry_alert_days', 90)]
            );
        }

        $this->view('admin/dashboard', $data, 'admin');
    }
}
