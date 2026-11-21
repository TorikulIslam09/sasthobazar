<?php
declare(strict_types=1);

/** Vendor: own products, own sub-orders (status: processing / shipped / cancel), packing slip, returns. */
class VendorOrderController extends VendorBaseController
{
    private function split(array $v, string $id): array
    {
        $s = $this->db->fetch(
            'SELECT s.*, o.order_no, o.placed_at, o.status AS order_status, o.payment_method, o.payment_status, o.shipping_name, o.shipping_phone,
                    o.shipping_address, o.division_id, o.district_id, o.upazila_id, o.postcode, o.customer_note, o.requires_prescription,
                    o.id AS order_id, o.delivery_option, sh.status AS shipment_status, sh.tracking_no, sh.is_mock, sh.rider_name, sh.rider_phone,
                    c.name AS courier_name
               FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id
          LEFT JOIN shipments sh ON sh.id = (SELECT MAX(id) FROM shipments WHERE split_id = s.id)
          LEFT JOIN couriers c ON c.id = sh.courier_id
              WHERE s.id = :id AND s.vendor_id = :v',
            ['id' => (int) $id, 'v' => (int) $v['id']]
        );
        if (!$s) {
            $this->notFound();
        }
        return $s;
    }

    public function index(): void
    {
        $v = $this->activeVendor();
        $status = in_array(Helpers::str('status'), Order::STATUSES, true) ? Helpers::str('status') : '';
        $q = mb_substr(Helpers::str('q'), 0, 50);
        $w = 's.vendor_id = :v';
        $p = ['v' => (int) $v['id']];
        if ($status !== '') {
            $w .= ' AND s.status = :s';
            $p['s'] = $status;
        }
        if ($q !== '') {
            $w .= ' AND (s.sub_order_no LIKE :q OR o.shipping_phone LIKE :q2)';
            $p['q'] = '%' . addcslashes($q, '%_\\') . '%';
            $p['q2'] = $p['q'];
        }
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id WHERE {$w}", $p), 20);
        $rows = $this->db->fetchAll(
            "SELECT s.*, o.order_no, o.placed_at, o.shipping_name, o.payment_method,
                    (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE split_id = s.id) AS items
               FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id
              WHERE {$w} ORDER BY s.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            $p
        );
        $counts = array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM order_vendor_splits WHERE vendor_id = :v GROUP BY status', ['v' => (int) $v['id']]), 'n', 'status');
        $this->view('vendor/orders', ['title' => __('my_orders'), 'vendor' => $v, 'rows' => $rows, 'pager' => $pager, 'status' => $status, 'q' => $q, 'counts' => $counts], 'vendor');
    }

    public function show(string $id): void
    {
        $v = $this->activeVendor();
        $s = $this->split($v, $id);
        $this->view('vendor/order', [
            'title'   => __('sub_order') . ' ' . $s['sub_order_no'],
            'vendor'  => $v,
            's'       => $s,
            'items'   => $this->db->fetchAll('SELECT * FROM order_items WHERE split_id = :s ORDER BY id', ['s' => (int) $s['id']]),
            'history' => $this->db->fetchAll(
                'SELECT h.*, u.name AS user_name FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by
                  WHERE h.order_id = :o AND (h.split_id = :s OR (h.split_id IS NULL AND h.from_status <> h.to_status)) ORDER BY h.id',
                ['o' => (int) $s['order_id'], 's' => (int) $s['id']]
            ),
            'area'    => OrderView::area($s),
            'next'    => $v['status'] === 'approved' ? $this->vendorNext($s['status']) : [],
        ], 'vendor');
    }

    private function vendorNext(string $status): array
    {
        $next = Order::VENDOR_NEXT[$status] ?? [];
        return setting('vendor_can_cancel', true) ? $next : array_values(array_diff($next, ['cancelled']));
    }

    public function status(string $id): void
    {
        $v = $this->activeVendor();
        $this->requireApproved($v);
        $s = $this->split($v, $id);
        try {
            Order::changeSplitStatus((int) $s['id'], Helpers::str('status'), mb_substr(Helpers::str('note'), 0, 255) ?: null, true, (int) Auth::id(), (int) $v['id']);
            flash('success', __('order_status_saved', ['status' => __('os_' . Helpers::str('status'))]));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        redirect('vendor-panel/orders/' . (int) $s['id']);
    }

    public function slip(string $id): void
    {
        $v = $this->activeVendor();
        $s = $this->split($v, $id);
        $this->view('vendor/slip', [
            'title'  => __('packing_slip') . ' ' . $s['sub_order_no'],
            'vendor' => $v,
            's'      => $s,
            'items'  => $this->db->fetchAll("SELECT * FROM order_items WHERE split_id = :s AND status <> 'cancelled' ORDER BY id", ['s' => (int) $s['id']]),
            'area'   => OrderView::area($s),
        ], 'print');
    }

    public function returns(): void
    {
        $v = $this->activeVendor();
        $rows = $this->db->fetchAll(
            "SELECT r.*, s.sub_order_no, s.id AS split_id, oi.product_name, oi.variant_name
               FROM refunds r JOIN order_vendor_splits s ON s.id = r.split_id LEFT JOIN order_items oi ON oi.id = r.order_item_id
              WHERE s.vendor_id = :v ORDER BY r.id DESC LIMIT 200",
            ['v' => (int) $v['id']]
        );
        $this->view('vendor/returns', ['title' => __('returns_refunds'), 'vendor' => $v, 'rows' => $rows], 'vendor');
    }

    public function products(): void
    {
        $v = $this->activeVendor();
        $q = mb_substr(Helpers::str('q'), 0, 100);
        $stock = Helpers::str('stock');
        $w = 'p.vendor_id = :v AND p.deleted_at IS NULL';
        $p = ['v' => (int) $v['id']];
        if ($q !== '') {
            $w .= ' AND (p.name_en LIKE :q OR p.name_bn LIKE :q2 OR p.sku = :q3 OR p.generic_name LIKE :q4)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $p += ['q' => $like, 'q2' => $like, 'q3' => $q, 'q4' => $like];
        }
        if ($stock === 'low') {
            $w .= ' AND p.track_stock = 1 AND p.stock_qty <= p.low_stock_threshold';
        }
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM products p WHERE {$w}", $p), 25);
        $rows = $this->db->fetchAll(
            "SELECT p.id, p.name_en, p.name_bn, p.slug, p.sku, p.generic_name, p.strength, p.mrp, p.price, p.stock_qty, p.low_stock_threshold,
                    p.track_stock, p.status, p.sold_count, p.commission_rate, p.category_id, p.vendor_id, c.name_en AS cat_en, c.name_bn AS cat_bn,
                    (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order LIMIT 1) AS image
               FROM products p LEFT JOIN categories c ON c.id = p.category_id
              WHERE {$w} ORDER BY p.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            $p
        );
        $this->view('vendor/products', ['title' => __('my_products'), 'vendor' => $v, 'rows' => $rows, 'pager' => $pager, 'q' => $q, 'stock' => $stock], 'vendor');
    }
}
