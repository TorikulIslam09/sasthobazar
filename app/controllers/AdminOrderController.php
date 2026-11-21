<?php
declare(strict_types=1);

/** Admin order management: list with filters, bulk status / print, detail actions, invoice, CSV export. */
class AdminOrderController extends Controller
{
    private function filters(): array
    {
        $date = static fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', Helpers::str($k)) ? Helpers::str($k) : '';
        return [
            'status'         => in_array(Helpers::str('status'), Order::STATUSES, true) ? Helpers::str('status') : '',
            'q'              => mb_substr(Helpers::str('q'), 0, 100),
            'payment_method' => in_array(Helpers::str('payment_method'), Order::PAYMENT_METHODS, true) ? Helpers::str('payment_method') : '',
            'payment_status' => isset(OrderView::PAY_BADGES[Helpers::str('payment_status')]) ? Helpers::str('payment_status') : '',
            'from'           => $date('from'),
            'to'             => $date('to'),
            'rx'             => Helpers::str('rx') === '1' ? '1' : '',
            'return'         => Helpers::str('return') === '1' ? '1' : '',
            'unverified'     => Helpers::str('unverified') === '1' ? '1' : '',
        ];
    }

    public function index(): void
    {
        $f = $this->filters();
        $pager = new Paginator(OrderView::countFiltered($f), OrderView::PER_PAGE);
        $this->view('admin/orders/index', [
            'title'   => __('orders'),
            'filters' => $f,
            'orders'  => OrderView::filtered($f, $pager->perPage, $pager->offset()),
            'pager'   => $pager,
            'counts'  => OrderView::statusCounts($f),
        ], 'admin');
    }

    public function show(string $id): void
    {
        $o = Order::find((int) $id);
        if (!$o) {
            $this->notFound();
        }
        $history = OrderView::history((int) $o['id']);
        $this->view('admin/orders/show', [
            'title'        => __('order') . ' ' . $o['order_no'],
            'o'            => $o,
            'items'        => OrderView::items((int) $o['id']),
            'history'      => $history,
            'timeline'     => OrderView::timeline($o, $history),
            'payments'     => OrderView::payments((int) $o['id']),
            'refunds'      => OrderView::refunds((int) $o['id']),
            'splits'       => OrderView::splits((int) $o['id']),
            'prescription' => OrderView::prescription($o['prescription_id'] ? (int) $o['prescription_id'] : null),
            'area'         => OrderView::area($o),
            'customer'     => $o['user_id'] ? $this->db->fetch(
                "SELECT u.id, u.name, u.phone, u.email, u.created_at,
                        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS orders_count,
                        (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND status = 'cancelled') AS cancelled_count
                   FROM users u WHERE u.id = :id",
                ['id' => (int) $o['user_id']]
            ) : null,
        ], 'admin');
    }

    public function status(string $id): void
    {
        $to = Helpers::str('status');
        if (!in_array($to, Order::STATUSES, true)) {
            flash('error', __('v_in'));
            back();
        }
        if ($to === 'cancelled') {
            $this->authorize('orders.cancel');
        }
        try {
            Order::changeStatus((int) $id, $to, mb_substr(Helpers::str('note'), 0, 500) ?: null, Helpers::str('notify') === '1', (int) Auth::id());
            flash('success', __('order_status_saved', ['status' => __('os_' . $to)]));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . (int) $id);
    }

    /** Status of one vendor sub-order. */
    public function splitStatus(string $id, string $splitId): void
    {
        $to = Helpers::str('status');
        if (!in_array($to, Order::STATUSES, true)) {
            flash('error', __('v_in'));
            back();
        }
        if ($to === 'cancelled') {
            $this->authorize('orders.cancel');
        }
        $belongs = $this->db->fetchColumn('SELECT 1 FROM order_vendor_splits WHERE id = :s AND order_id = :o', ['s' => (int) $splitId, 'o' => (int) $id]);
        if (!$belongs) {
            $this->notFound();
        }
        try {
            Order::changeSplitStatus((int) $splitId, $to, mb_substr(Helpers::str('note'), 0, 500) ?: null, Helpers::str('notify') === '1', (int) Auth::id());
            flash('success', __('order_status_saved', ['status' => __('os_' . $to)]));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . (int) $id . '#splits');
    }

    public function note(string $id): void
    {
        $note = trim(Helpers::str('note'));
        if ($note === '' || mb_strlen($note) > 500) {
            flash('error', __('v_required'));
            redirect('admin/orders/' . (int) $id);
        }
        try {
            Order::addNote((int) $id, $note, Helpers::str('notify') === '1', (int) Auth::id());
            flash('success', __('note_added'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . (int) $id . '#history');
    }

    public function prescription(string $id): void
    {
        $approve = Helpers::str('decision') === 'approve';
        $note = mb_substr(Helpers::str('note'), 0, 500) ?: null;
        if (!$approve && $note === null) {
            flash('error', __('rx_reject_reason'));
            redirect('admin/orders/' . (int) $id);
        }
        try {
            Order::reviewPrescription((int) $id, $approve, $note, (int) Auth::id());
            flash('success', __($approve ? 'rx_approved_msg' : 'rx_rejected_msg'));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . (int) $id);
    }

    public function payment(string $id, string $paymentId): void
    {
        try {
            Order::verifyPayment((int) $id, (int) $paymentId, Helpers::str('decision') === 'ok', mb_substr(Helpers::str('note'), 0, 255) ?: null, (int) Auth::id());
            flash('success', __('payment_saved'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . (int) $id);
    }

    public function returnDecision(string $id): void
    {
        $approve = Helpers::str('decision') === 'approve';
        $note = mb_substr(Helpers::str('note'), 0, 500) ?: null;
        try {
            Order::decideReturn((int) $id, $approve, Helpers::str('restock') === '1', $note, (int) Auth::id());
            flash('success', __($approve ? 'return_approved_msg' : 'return_rejected_msg'));
        } catch (OrderException | InventoryException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . (int) $id . '#returns');
    }

    public function refunded(string $id): void
    {
        $ref = trim(Helpers::str('reference_no'));
        if ($ref === '' || mb_strlen($ref) > 100) {
            flash('error', __('refund_ref_required'));
            redirect('admin/orders/' . (int) $id . '#returns');
        }
        try {
            $sum = Order::markRefunded((int) $id, $ref, (int) Auth::id());
            flash('success', __('refund_done', ['amount' => money($sum)]));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/orders/' . (int) $id . '#returns');
    }

    /** Bulk: change status of the ticked orders, or print their invoices. */
    public function bulk(): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        $ids = array_slice($ids, 0, 100);
        $action = Helpers::str('bulk_action');
        if (!$ids) {
            flash('warning', __('bulk_none'));
            back();
        }
        if ($action === 'print') {
            redirect('admin/orders/invoices?ids=' . implode(',', $ids));
        }
        if (!in_array($action, ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'], true)) {
            flash('error', __('v_in'));
            back();
        }
        $this->authorize('orders.update_status');
        if ($action === 'cancelled') {
            $this->authorize('orders.cancel');
        }
        sort($ids); // same lock order as everything else
        $ok = 0;
        $failed = [];
        foreach ($ids as $id) {
            try {
                Order::changeStatus($id, $action, __('hist_bulk'), true, (int) Auth::id());
                $ok++;
            } catch (OrderException | InventoryException $e) {
                $no = $this->db->fetchColumn('SELECT order_no FROM orders WHERE id = :id', ['id' => $id]);
                $failed[] = ($no ?: '#' . $id) . ': ' . $e->getMessage();
            }
        }
        if ($ok) {
            flash('success', __('bulk_done', ['n' => num($ok), 'status' => __('os_' . $action)]));
        }
        foreach (array_slice($failed, 0, 10) as $f) {
            flash('error', $f);
        }
        back();
    }

    /** One or many invoices on the print layout. */
    public function invoices(): void
    {
        $ids = Storefront::intList(Helpers::str('ids'));
        $out = [];
        foreach (array_slice($ids, 0, 50) as $id) {
            if ($o = Order::find($id)) {
                $out[] = ['o' => $o, 'items' => OrderView::items($id), 'area' => OrderView::area($o), 'payments' => OrderView::payments($id)];
            }
        }
        if (!$out) {
            $this->notFound();
        }
        $this->view('orders/invoice', ['title' => __('invoice'), 'orders' => $out], 'print');
    }

    public function invoice(string $id): void
    {
        redirect('admin/orders/invoices?ids=' . (int) $id);
    }

    public function export(): void
    {
        $f = $this->filters();
        $rows = OrderView::filtered($f, 5000, 0);
        Helpers::csvDownload('orders-' . date('Ymd-His') . '.csv',
            ['Order no', 'Placed', 'Status', 'Customer', 'Phone', 'District', 'Items', 'Subtotal', 'Discount', 'Delivery', 'COD charge', 'Total', 'Paid', 'Refunded', 'Payment', 'Payment status', 'Rx', 'Coupon'],
            (static function () use ($rows) {
                foreach ($rows as $o) {
                    yield [$o['order_no'], $o['placed_at'], $o['status'], $o['customer_name'], $o['customer_phone'], $o['district_en'],
                        $o['items'], $o['subtotal'], $o['discount_total'], $o['shipping_total'], $o['cod_charge'], $o['grand_total'],
                        $o['paid_amount'], $o['refunded_amount'], $o['payment_method'], $o['payment_status'], $o['requires_prescription'] ? 'yes' : 'no', $o['coupon_code']];
                }
            })()
        );
    }
}
