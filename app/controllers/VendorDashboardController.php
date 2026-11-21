<?php
declare(strict_types=1);

/** Vendor panel home: orders, sales, commission, net earning (today / 7 days / 30 days), wallet, stock alerts. */
class VendorDashboardController extends VendorBaseController
{
    public function index(): void
    {
        $v = $this->vendor();
        $vid = (int) $v['id'];
        $data = ['title' => __('dashboard'), 'vendor' => $v];

        if (in_array($v['status'], ['approved', 'suspended'], true)) {
            Wallet::releaseDue($vid);
            $periods = [];
            foreach (['today' => date('Y-m-d 00:00:00'), 'week' => date('Y-m-d 00:00:00', strtotime('-6 days')), 'month' => date('Y-m-d 00:00:00', strtotime('-29 days'))] as $k => $from) {
                $placed = $this->db->fetch(
                    "SELECT COUNT(*) AS orders, COALESCE(SUM(CASE WHEN s.status <> 'cancelled' THEN s.subtotal - s.discount_total END), 0) AS sales
                       FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id
                      WHERE s.vendor_id = :v AND o.placed_at >= :f",
                    ['v' => $vid, 'f' => $from]
                );
                $booked = $this->db->fetch(
                    "SELECT COALESCE(SUM(commission_amount), 0) AS commission, COALESCE(SUM(vendor_earning), 0) AS earning
                       FROM order_vendor_splits WHERE vendor_id = :v AND delivered_at >= :f AND settlement_status IN ('pending','eligible','settled')",
                    ['v' => $vid, 'f' => $from]
                );
                $periods[$k] = $placed + $booked;
            }
            $data['periods'] = $periods;
            $data['wallet'] = Wallet::forVendor($vid);
            $data['toProcess'] = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM order_vendor_splits WHERE vendor_id = :v AND status IN ('confirmed','processing','packed')", ['v' => $vid]);
            $data['waiting'] = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM order_vendor_splits WHERE vendor_id = :v AND status IN ('pending','prescription_review')", ['v' => $vid]);
            $data['returns'] = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM refunds r JOIN order_vendor_splits s ON s.id = r.split_id WHERE s.vendor_id = :v AND r.status = 'requested'", ['v' => $vid]);
            $data['lowStock'] = $this->db->fetchAll(
                "SELECT id, name_en, name_bn, stock_qty, low_stock_threshold FROM products
                  WHERE vendor_id = :v AND deleted_at IS NULL AND status = 'active' AND track_stock = 1 AND stock_qty <= low_stock_threshold
               ORDER BY stock_qty, name_en LIMIT 8",
                ['v' => $vid]
            );
            $data['nearExpiry'] = $this->db->fetchAll(
                "SELECT b.batch_no, b.expiry_date, b.quantity_available, p.name_en, p.name_bn FROM inventory_batches b JOIN products p ON p.id = b.product_id
                  WHERE p.vendor_id = :v AND b.status = 'active' AND b.quantity_available > 0 AND b.expiry_date IS NOT NULL
                    AND b.expiry_date <= (CURDATE() + INTERVAL :d DAY)
               ORDER BY b.expiry_date LIMIT 8",
                ['v' => $vid, 'd' => (int) setting('expiry_alert_days', 90)]
            );
            $data['recent'] = $this->db->fetchAll(
                'SELECT s.id, s.sub_order_no, s.status, s.subtotal, s.discount_total, o.placed_at, o.shipping_name
                   FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id WHERE s.vendor_id = :v ORDER BY s.id DESC LIMIT 6',
                ['v' => $vid]
            );
            [$rate, $scope] = Commission::rateFor(['vendor_id' => $vid, 'commission_rate' => null, 'category_id' => null]);
            $data['rate'] = $rate;
            $data['rateScope'] = $scope;
        }
        $this->view('vendor/dashboard', $data, 'vendor');
    }
}
