<?php
declare(strict_types=1);

/** Shipments: list, book courier / own rider (single + bulk), sync, manual status, labels, manifest. */
class AdminShipmentController extends Controller
{
    private function filters(): array
    {
        $date = static fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', Helpers::str($k)) ? Helpers::str($k) : '';
        return [
            'status'  => in_array(Helpers::str('status'), array_merge(Shipment::STATUSES, ['open', 'ready']), true) ? Helpers::str('status') : '',
            'courier' => (int) Helpers::str('courier'),
            'cod'     => array_key_exists(Helpers::str('cod'), Shipment::COD_BADGES) ? Helpers::str('cod') : '',
            'q'       => mb_substr(Helpers::str('q'), 0, 60),
            'from'    => $date('from'),
            'to'      => $date('to'),
        ];
    }

    private function where(array $f): array
    {
        $w = ['1 = 1'];
        $p = [];
        if ($f['status'] === 'ready') { // ready to book: sub-order confirmed/processing, not booked yet
            $w[] = "sh.status IN ('pending','cancelled') AND sh.delivery_option <> 'pickup' AND s.status IN ('confirmed','processing','packed')";
        } elseif ($f['status'] === 'open') {
            $w[] = "sh.status IN ('booked','picked_up','in_transit','out_for_delivery','on_hold','failed','returning')";
        } elseif ($f['status'] !== '') {
            $w[] = 'sh.status = :st';
            $p['st'] = $f['status'];
        }
        if ($f['courier']) {
            $w[] = 'sh.courier_id = :c';
            $p['c'] = $f['courier'];
        }
        if ($f['cod'] !== '') {
            $w[] = 'sh.cod_status = :cod';
            $p['cod'] = $f['cod'];
        }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(sh.tracking_no LIKE :q1 OR o.order_no LIKE :q2 OR s.sub_order_no LIKE :q3 OR o.shipping_phone LIKE :q4)';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if ($f['from'] !== '') {
            $w[] = 'sh.created_at >= :from';
            $p['from'] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $w[] = 'sh.created_at <= :to';
            $p['to'] = $f['to'] . ' 23:59:59';
        }
        return [implode(' AND ', $w), $p];
    }

    private const FROM = 'FROM shipments sh JOIN orders o ON o.id = sh.order_id LEFT JOIN order_vendor_splits s ON s.id = sh.split_id
                          LEFT JOIN vendors v ON v.id = s.vendor_id LEFT JOIN couriers c ON c.id = sh.courier_id LEFT JOIN locations d ON d.id = o.district_id';

    public function index(): void
    {
        $f = $this->filters();
        [$w, $p] = $this->where($f);
        $pager = new Paginator((int) $this->db->fetchColumn('SELECT COUNT(*) ' . self::FROM . " WHERE {$w}", $p), 30);
        $rows = $this->db->fetchAll(
            'SELECT sh.*, o.order_no, o.shipping_name, o.shipping_phone, s.sub_order_no, s.status AS split_status, v.shop_name, c.name AS courier_name,
                    d.name_en AS district_en, d.name_bn AS district_bn ' . self::FROM . " WHERE {$w} ORDER BY sh.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            $p
        );
        $counts = array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM shipments GROUP BY status'), 'n', 'status');
        $counts['ready'] = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM shipments sh JOIN order_vendor_splits s ON s.id = sh.split_id
              WHERE sh.status IN ('pending','cancelled') AND sh.delivery_option <> 'pickup' AND s.status IN ('confirmed','processing','packed')"
        );
        $this->view('admin/shipments/index', [
            'title'    => __('shipments'),
            'rows'     => $rows,
            'pager'    => $pager,
            'filters'  => $f,
            'counts'   => $counts,
            'couriers' => Courier::all(),
        ], 'admin');
    }

    public function show(string $id): void
    {
        $sh = Shipment::detail((int) $id);
        if (!$sh) {
            $this->notFound();
        }
        $this->view('admin/shipments/show', [
            'title'    => __('shipment') . ' #' . $sh['id'],
            'sh'       => $sh,
            'events'   => $this->db->fetchAll('SELECT e.*, u.name AS user_name FROM shipment_events e LEFT JOIN users u ON u.id = e.created_by WHERE e.shipment_id = :s ORDER BY e.id DESC', ['s' => (int) $id]),
            'couriers' => Courier::all(true),
            'items'    => $this->db->fetchAll("SELECT product_name, variant_name, quantity FROM order_items WHERE split_id = :s AND status <> 'cancelled'", ['s' => (int) $sh['split_id']]),
            'area'     => OrderView::area($sh),
            'riders'   => $this->db->fetchAll("SELECT id, name, phone FROM users WHERE user_type IN ('employee','super_admin') AND status = 'active' AND deleted_at IS NULL AND phone IS NOT NULL ORDER BY name LIMIT 100"),
        ], 'admin');
    }

    public function book(string $id): void
    {
        try {
            $rider = ['name' => Helpers::str('rider_name'), 'phone' => Helpers::str('rider_phone'), 'user_id' => (int) Helpers::str('rider_user_id') ?: null];
            if ($rider['user_id'] && ($u = $this->db->fetch('SELECT name, phone FROM users WHERE id = :id', ['id' => $rider['user_id']]))) {
                $rider['name'] = $rider['name'] ?: $u['name'];
                $rider['phone'] = $rider['phone'] ?: (string) $u['phone'];
            }
            $sh = Shipment::book((int) $id, Helpers::str('courier'), $rider, (int) Auth::id());
            flash('success', __('shipment_booked', ['tracking' => $sh['tracking_no']]));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        back();
    }

    /** Bulk: book ticked shipments with one courier, or print labels / manifest. */
    public function bulk(): void
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))))), 0, 100);
        $action = Helpers::str('bulk_action');
        if (!$ids) {
            flash('warning', __('bulk_none'));
            back();
        }
        if ($action === 'labels' || $action === 'manifest') {
            redirect('admin/shipments/' . $action . '?ids=' . implode(',', $ids));
        }
        if (!str_starts_with($action, 'book:')) {
            flash('error', __('v_in'));
            back();
        }
        $this->authorize('shipments.create');
        $code = substr($action, 5);
        if ($code === 'self') {
            flash('error', __('rider_bulk_not_allowed'));
            back();
        }
        $ok = 0;
        $fail = [];
        foreach ($ids as $id) {
            try {
                Shipment::book($id, $code, [], (int) Auth::id());
                $ok++;
            } catch (OrderException $e) {
                $fail[] = '#' . $id . ': ' . $e->getMessage();
            }
        }
        if ($ok) {
            flash('success', __('shipments_booked_n', ['n' => num($ok)]));
        }
        foreach (array_slice($fail, 0, 8) as $m) {
            flash('error', $m);
        }
        back();
    }

    public function cancel(string $id): void
    {
        try {
            Shipment::cancelBooking((int) $id, (int) Auth::id());
            flash('success', __('sh_booking_cancelled'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        back();
    }

    public function sync(string $id): void
    {
        try {
            $st = Shipment::sync((int) $id, (int) Auth::id());
            flash($st ? 'success' : 'info', $st ? __('sh_synced', ['status' => __('sh_' . $st)]) : __('sh_no_change'));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        back();
    }

    /** Sync every open shipment of API couriers (button on the list; can also run from cron). */
    public function syncAll(): void
    {
        $ids = array_column($this->db->fetchAll(
            "SELECT sh.id FROM shipments sh JOIN couriers c ON c.id = sh.courier_id
              WHERE c.code <> 'self' AND sh.status IN ('booked','picked_up','in_transit','out_for_delivery','on_hold','failed','returning')
           ORDER BY sh.last_synced_at IS NOT NULL, sh.last_synced_at LIMIT 50"
        ), 'id');
        $changed = 0;
        foreach ($ids as $id) {
            try {
                if (Shipment::sync((int) $id, (int) Auth::id())) {
                    $changed++;
                }
            } catch (Throwable $e) {
                error_log('[shipment sync] #' . $id . ': ' . $e->getMessage());
            }
        }
        flash('success', __('sh_synced_n', ['n' => num(count($ids)), 'c' => num($changed)]));
        back();
    }

    public function status(string $id): void
    {
        $to = Helpers::str('status');
        if (!in_array($to, Shipment::MANUAL, true)) {
            flash('error', __('v_in'));
            back();
        }
        try {
            $changed = Shipment::updateStatus((int) $id, $to, mb_substr(Helpers::str('note'), 0, 255) ?: null, 'staff', null, (int) Auth::id());
            flash($changed ? 'success' : 'info', $changed ? __('sh_status_saved', ['status' => __('sh_' . $to)]) : __('sh_no_change'));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        back();
    }

    /** Store pickup: customer collected the parcel at the counter. */
    public function pickedUp(string $id): void
    {
        $sh = Shipment::detail((int) $id);
        if (!$sh || $sh['delivery_option'] !== 'pickup') {
            $this->notFound();
        }
        // walk the sub-order through the remaining steps: confirmed > processing > shipped > delivered
        $steps = match ($sh['split_status']) {
            'confirmed'            => ['processing', 'shipped', 'delivered'],
            'processing', 'packed' => ['shipped', 'delivered'],
            'shipped'              => ['delivered'],
            default                => [],
        };
        if (!$steps) {
            flash('error', __('shipment_split_not_ready'));
            back();
        }
        try {
            foreach ($steps as $to) {
                Order::changeSplitStatus((int) $sh['split_id'], $to, __($to === 'delivered' ? 'pickup_collected' : 'pickup_ready'), $to === 'delivered', (int) Auth::id());
            }
            flash('success', __('pickup_collected'));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        back();
    }

    private function many(): array
    {
        $ids = Storefront::intList(Helpers::str('ids'));
        $out = [];
        foreach (array_slice($ids, 0, 100) as $id) {
            if ($sh = Shipment::detail($id)) {
                $sh['area'] = OrderView::area($sh);
                $out[] = $sh;
            }
        }
        if (!$out) {
            $this->notFound();
        }
        return $out;
    }

    public function labels(): void
    {
        $this->view('admin/shipments/labels', ['title' => __('shipping_labels'), 'list' => $this->many()], 'print');
    }

    public function manifest(): void
    {
        $list = $this->many();
        $this->view('admin/shipments/manifest', ['title' => __('manifest'), 'list' => $list, 'date' => date('Y-m-d')], 'print');
    }

    public function export(): void
    {
        $f = $this->filters();
        [$w, $p] = $this->where($f);
        $rows = $this->db->fetchAll(
            'SELECT sh.*, o.order_no, o.shipping_name, o.shipping_phone, s.sub_order_no, v.shop_name, c.name AS courier_name, d.name_en AS district_en ' . self::FROM . " WHERE {$w} ORDER BY sh.id DESC LIMIT 5000",
            $p
        );
        Helpers::csvDownload('shipments-' . date('Ymd-His') . '.csv',
            ['ID', 'Created', 'Order', 'Sub-order', 'Seller', 'Courier', 'Tracking', 'Status', 'Option', 'Customer', 'Phone', 'District', 'Weight g', 'Customer fee', 'COD amount', 'COD status', 'Courier charge'],
            (static function () use ($rows) {
                foreach ($rows as $r) {
                    yield [$r['id'], $r['created_at'], $r['order_no'], $r['sub_order_no'], $r['shop_name'] ?? 'In-house', $r['courier_name'], $r['tracking_no'], $r['status'],
                        $r['delivery_option'], $r['shipping_name'], $r['shipping_phone'], $r['district_en'], $r['weight_grams'], $r['shipping_fee'], $r['cod_amount'], $r['cod_status'], $r['delivery_charge']];
                }
            })()
        );
    }
}
