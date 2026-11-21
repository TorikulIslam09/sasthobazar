<?php
declare(strict_types=1);

/**
 * Purchase orders and GRN (goods received notes).
 * Business rules and transactions live in PurchaseOrder / Inventory; this controller validates input.
 */
class AdminPurchaseOrderController extends Controller
{
    /* ======================= List ======================= */

    public function index(): void
    {
        $f = $this->filters();
        $p = new Paginator(PurchaseOrder::countFiltered($f), 25);
        $this->view('admin/purchase_orders/index', [
            'title'        => __('purchase_orders'),
            'orders'       => PurchaseOrder::filtered($f, $p->perPage, $p->offset()),
            'paginator'    => $p,
            'filters'      => $f,
            'suppliers'    => Supplier::options(false),
            'statusCounts' => PurchaseOrder::statusCounts(),
        ], 'admin');
    }

    public function export(): void
    {
        $rows = PurchaseOrder::filtered($this->filters(), 100000, 0);
        Audit::log('purchase_orders.export', 'purchase_orders', null, null, ['rows' => count($rows)]);
        Helpers::csvDownload('purchase-orders-' . date('Ymd-His') . '.csv',
            ['po_no', 'supplier', 'seller', 'status', 'order_date', 'expected_date', 'items', 'qty_ordered', 'qty_received', 'subtotal', 'discount', 'tax', 'total'],
            array_map(static fn($o) => [
                $o['po_no'], $o['supplier_name'], $o['vendor_name'] ?? 'In-house', $o['status'], $o['order_date'], $o['expected_date'],
                $o['items_count'], $o['qty_ordered'], $o['qty_received'], $o['subtotal'], $o['discount'], $o['tax'], $o['total'],
            ], $rows)
        );
    }

    /* ======================= Create / edit draft ======================= */

    public function create(): void
    {
        $this->renderForm(null, [
            'order_date' => date('Y-m-d'), 'discount' => '0', 'tax' => '0',
            'supplier_id' => ctype_digit(Helpers::str('supplier_id')) ? Helpers::str('supplier_id') : '',
        ], [], []);
    }

    public function store(): void
    {
        $in = $this->postData();
        $lines = $this->lineInput();
        $errors = $this->validatePo($in, $lines);
        if ($errors) {
            $this->renderForm(null, $in, $lines, $errors);
            return;
        }
        $id = PurchaseOrder::saveDraft(null, $in, $lines);
        if (($in['_after'] ?? '') === 'place' && can('purchase_orders.edit')) {
            PurchaseOrder::place($id);
            flash('success', __('po_placed'));
        } else {
            flash('success', __('po_saved'));
        }
        redirect('admin/purchase-orders/' . $id);
    }

    public function edit(string $id): void
    {
        $po = $this->findPo((int) $id);
        if ($po['status'] !== 'draft') {
            flash('warning', __('po_not_editable'));
            redirect('admin/purchase-orders/' . $po['id']);
        }
        $lines = array_map(static fn($i) => [
            'product_id' => (string) $i['product_id'], 'variant_id' => (string) $i['variant_id'],
            'quantity' => (string) $i['quantity_ordered'], 'unit_cost' => (string) $i['unit_cost'],
        ], PurchaseOrder::items((int) $po['id']));
        $this->renderForm($po, $po, $lines, []);
    }

    public function update(string $id): void
    {
        $po = $this->findPo((int) $id);
        $in = $this->postData();
        $lines = $this->lineInput();
        $errors = $this->validatePo($in, $lines);
        if ($errors) {
            $this->renderForm($po, array_merge($po, $in), $lines, $errors);
            return;
        }
        try {
            PurchaseOrder::saveDraft((int) $po['id'], $in, $lines);
            if (($in['_after'] ?? '') === 'place') {
                PurchaseOrder::place((int) $po['id']);
                flash('success', __('po_placed'));
            } else {
                flash('success', __('po_saved'));
            }
        } catch (InventoryException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/purchase-orders/' . $po['id']);
    }

    /* ======================= View / status ======================= */

    public function show(string $id): void
    {
        $po = $this->findPo((int) $id);
        $this->view('admin/purchase_orders/show', [
            'title' => $po['po_no'],
            'po'    => $po,
            'items' => PurchaseOrder::items((int) $po['id']),
            'grns'  => PurchaseOrder::grns((int) $po['id']),
            'print' => Helpers::str('print') === '1',
        ], Helpers::str('print') === '1' ? 'print' : 'admin');
    }

    public function place(string $id): void
    {
        $this->statusAction((int) $id, static fn(int $i) => PurchaseOrder::place($i), 'po_placed');
    }

    public function cancel(string $id): void
    {
        $this->statusAction((int) $id, static fn(int $i) => PurchaseOrder::cancel($i), 'po_cancelled');
    }

    public function close(string $id): void
    {
        $this->statusAction((int) $id, static fn(int $i) => PurchaseOrder::close($i), 'po_closed');
    }

    public function destroy(string $id): void
    {
        $po = $this->findPo((int) $id);
        try {
            PurchaseOrder::deleteDraft((int) $po['id']);
            flash('success', __('po_deleted', ['no' => $po['po_no']]));
            redirect('admin/purchase-orders');
        } catch (InventoryException $e) {
            flash('error', $e->getMessage());
            redirect('admin/purchase-orders/' . $po['id']);
        }
    }

    private function statusAction(int $id, callable $fn, string $msg): void
    {
        $po = $this->findPo($id);
        try {
            $fn((int) $po['id']);
            flash('success', __($msg));
        } catch (InventoryException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/purchase-orders/' . $po['id']);
    }

    /* ======================= Receive (GRN) ======================= */

    public function receiveForm(string $id): void
    {
        $po = $this->findPo((int) $id);
        if (!in_array($po['status'], ['ordered', 'partial'], true)) {
            flash('warning', __('po_not_receivable'));
            redirect('admin/purchase-orders/' . $po['id']);
        }
        $this->renderReceive($po, ['received_date' => date('Y-m-d')], [], []);
    }

    public function receive(string $id): void
    {
        $po = $this->findPo((int) $id);
        $items = array_column(PurchaseOrder::items((int) $po['id']), null, 'id');
        $in = $this->postData();
        $raw = (array) ($_POST['lines'] ?? []);

        $errors = $this->validate($in, [
            'received_date' => 'required|date',
            'invoice_no'    => 'max:100',
            'notes'         => 'max:500',
        ]);
        if (!isset($errors['received_date']) && $in['received_date'] > date('Y-m-d')) {
            $errors['received_date'] = __('v_not_future');
        }

        $lines = [];
        $form = [];
        foreach ($items as $itemId => $item) {
            $r = is_array($raw[$itemId] ?? null) ? $raw[$itemId] : [];
            $l = [];
            foreach (['quantity', 'batch_no', 'manufacture_date', 'expiry_date', 'unit_cost'] as $k) {
                $l[$k] = is_scalar($r[$k] ?? null) ? trim((string) $r[$k]) : '';
            }
            $form[$itemId] = $l;
            if ($l['quantity'] === '' || $l['quantity'] === '0') {
                continue;
            }
            $e = $this->validate($l, [
                'quantity'         => 'integer|gte:1|lte:' . max(1, (int) $item['remaining']),
                'batch_no'         => 'required|max:100',
                'manufacture_date' => 'date',
                'expiry_date'      => 'date',
                'unit_cost'        => 'required|decimal',
            ]);
            if ((int) $item['remaining'] <= 0) {
                $e['quantity'] = __('grn_line_complete');
            }
            foreach ($e as $f => $m) {
                $errors["lines.{$itemId}.{$f}"] = $m;
            }
            $lines[$itemId] = $l;
        }
        if (!$lines && !$errors) {
            $errors['lines'] = __('grn_nothing');
        }

        if (!$errors) {
            try {
                $grnId = PurchaseOrder::receive((int) $po['id'], $in + ['invoice_no' => '', 'notes' => ''], $lines);
                flash('success', __('grn_posted'));
                redirect('admin/purchase-orders/grn/' . $grnId);
            } catch (InventoryException $e) {
                $errors[$e->field ?: 'lines'] = $e->getMessage();
            }
        }
        $this->renderReceive($po, $in, $form, $errors);
    }

    public function grn(string $id): void
    {
        $g = PurchaseOrder::grnFull((int) $id);
        if (!$g) {
            $this->notFound();
        }
        $print = Helpers::str('print') === '1';
        $this->view('admin/purchase_orders/grn', ['title' => $g['grn_no'], 'grn' => $g, 'print' => $print], $print ? 'print' : 'admin');
    }

    private function renderReceive(array $po, array $input, array $lines, array $errors): void
    {
        $this->view('admin/purchase_orders/receive', [
            'title'  => __('grn_receive') . ' · ' . $po['po_no'],
            'po'     => $po,
            'items'  => PurchaseOrder::items((int) $po['id']),
            'input'  => $input,
            'lines'  => $lines,
            'errors' => $errors,
        ], 'admin');
    }

    /* ======================= helpers ======================= */

    private function filters(): array
    {
        $s = static fn(string $k) => Helpers::str($k);
        $date = static fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
        $st = $s('status');
        return [
            'q'           => mb_substr($s('q'), 0, 100),
            'status'      => (in_array($st, PurchaseOrder::STATUSES, true) || $st === 'open') ? $st : '',
            'supplier_id' => ctype_digit($s('supplier_id')) ? (int) $s('supplier_id') : 0,
            'from'        => $date($s('from')),
            'to'          => $date($s('to')),
        ];
    }

    private function findPo(int $id): array
    {
        $po = PurchaseOrder::findFull($id);
        if (!$po) {
            $this->notFound();
        }
        return $po;
    }

    /** items[key][product_id|variant_id|quantity|unit_cost]; blank rows dropped. */
    private function lineInput(): array
    {
        $out = [];
        foreach ((array) ($_POST['items'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $l = [];
            foreach (['product_id', 'variant_id', 'quantity', 'unit_cost'] as $k) {
                $l[$k] = is_scalar($r[$k] ?? null) ? trim((string) $r[$k]) : '';
            }
            if ($l['product_id'] === '' && $l['quantity'] === '' && $l['unit_cost'] === '') {
                continue;
            }
            $out[] = $l;
            if (count($out) > PurchaseOrder::MAX_ITEMS) {
                break;
            }
        }
        return $out;
    }

    private function validatePo(array $in, array $lines): array
    {
        $errors = $this->validate($in, [
            'supplier_id'   => 'required|integer',
            'vendor_id'     => 'integer',
            'order_date'    => 'required|date',
            'expected_date' => 'date',
            'discount'      => 'decimal',
            'tax'           => 'decimal',
            'notes'         => 'max:5000',
        ]);
        if (!isset($errors['supplier_id'])) {
            $sup = Supplier::find((int) $in['supplier_id']);
            if (!$sup) {
                $errors['supplier_id'] = __('v_in');
            } elseif (!(int) $sup['is_active']) {
                $errors['supplier_id'] = __('supplier_inactive');
            }
        }
        $vendorId = ($in['vendor_id'] ?? '') !== '' ? (int) $in['vendor_id'] : null;
        if (!isset($errors['vendor_id']) && $vendorId !== null && !Vendor::existsActive($vendorId)) {
            $errors['vendor_id'] = __('v_in');
        }
        if (!isset($errors['expected_date']) && ($in['expected_date'] ?? '') !== '' && !isset($errors['order_date']) && $in['expected_date'] < $in['order_date']) {
            $errors['expected_date'] = __('po_expected_before_order');
        }

        if (!$lines) {
            $errors['items'] = __('po_no_items');
        } elseif (count($lines) > PurchaseOrder::MAX_ITEMS) {
            $errors['items'] = __('po_too_many_items', ['n' => PurchaseOrder::MAX_ITEMS]);
        }
        $seen = [];
        $subtotal = 0.0;
        foreach ($lines as $i => $l) {
            $e = $this->validate($l, [
                'product_id' => 'required|integer',
                'variant_id' => 'integer',
                'quantity'   => 'required|integer|gte:1|lte:1000000',
                'unit_cost'  => 'required|decimal',
            ]);
            if (!isset($e['product_id'])) {
                $p = Product::findLive((int) $l['product_id']);
                if (!$p) {
                    $e['product_id'] = __('inv_product_not_found');
                } else {
                    $vid = $l['variant_id'] !== '' ? (int) $l['variant_id'] : 0;
                    $hasVariants = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM product_variants WHERE product_id = :p AND is_active = 1', ['p' => $p['id']]);
                    if ($vid && !$this->db->fetchColumn('SELECT 1 FROM product_variants WHERE id = :v AND product_id = :p', ['v' => $vid, 'p' => $p['id']])) {
                        $e['variant_id'] = __('inv_variant_invalid');
                    } elseif (!$vid && $hasVariants) {
                        $e['variant_id'] = __('inv_variant_required');
                    }
                    if ((int) ($p['vendor_id'] ?? 0) !== (int) $vendorId) {
                        $e['product_id'] = __('po_product_other_seller');
                    }
                    $key = $p['id'] . ':' . $vid;
                    if (isset($seen[$key])) {
                        $e['product_id'] = __('po_duplicate_line', ['n' => num($seen[$key] + 1)]);
                    }
                    $seen[$key] = $i;
                }
            }
            if (!isset($e['quantity']) && !isset($e['unit_cost'])) {
                $subtotal += (int) $l['quantity'] * (float) $l['unit_cost'];
            }
            foreach ($e as $f => $m) {
                $errors["items.{$i}.{$f}"] = $m;
            }
        }
        if (!isset($errors['discount']) && (float) ($in['discount'] ?? 0) > $subtotal) {
            $errors['discount'] = __('po_discount_too_big');
        }
        if (array_filter(array_keys($errors), static fn($k) => str_starts_with((string) $k, 'items.'))) {
            $errors['items'] ??= __('po_lines_have_errors');
        }
        return $errors;
    }

    private function renderForm(?array $po, array $input, array $lines, array $errors): void
    {
        // product labels + variants for the rows being re-rendered
        $products = [];
        $ids = array_unique(array_filter(array_map(static fn($l) => (int) $l['product_id'], $lines)));
        if ($ids) {
            foreach ($this->db->fetchAll('SELECT id, name_en, sku, strength, stock_qty FROM products WHERE id IN (' . implode(',', $ids) . ')') as $p) {
                $p['variants'] = $this->db->fetchAll('SELECT id, name_en, sku FROM product_variants WHERE product_id = :p AND is_active = 1 ORDER BY is_default DESC, id', ['p' => $p['id']]);
                $products[$p['id']] = $p;
            }
        }
        $this->view('admin/purchase_orders/form', [
            'title'     => $po ? __('edit_po') . ' ' . $po['po_no'] : __('new_po'),
            'po'        => $po,
            'input'     => $input,
            'lines'     => $lines,
            'products'  => $products,
            'errors'    => $errors,
            'suppliers' => Supplier::options(),
            'vendors'   => Vendor::options(),
        ], 'admin');
    }
}
