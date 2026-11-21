<?php
declare(strict_types=1);

/**
 * Batches (list, detail, edit, count adjustment, write-off/recall), stock ledger,
 * stock valuation report and vendor-wise stock report (+ CSV exports).
 */
class AdminStockController extends Controller
{
    /* ======================= Batches ======================= */

    public function batches(): void
    {
        $f = $this->batchFilters();
        $p = new Paginator(InventoryReport::countBatches($f), 25);
        $this->view('admin/inventory/batches', [
            'title'     => __('inv_batches'),
            'batches'   => InventoryReport::batches($f, $p->perPage, $p->offset()),
            'paginator' => $p,
            'filters'   => $f,
            'vendors'   => Vendor::options(),
            'suppliers' => Supplier::options(false),
        ], 'admin');
    }

    public function batchesExport(): void
    {
        $f = $this->batchFilters();
        $rows = InventoryReport::batches($f, 100000, 0);
        Audit::log('inventory.export', 'inventory_batches', null, null, ['report' => 'batches', 'rows' => count($rows)]);
        Helpers::csvDownload('batches-' . date('Ymd-His') . '.csv',
            ['batch_id', 'product_id', 'product', 'variant', 'sku', 'batch_no', 'manufacture_date', 'expiry_date', 'days_left',
             'received', 'available', 'unit_cost', 'cost_value', 'status', 'supplier', 'invoice_no', 'seller', 'received_at'],
            array_map(static fn($b) => [
                $b['id'], $b['product_id'], $b['name_en'], $b['variant_name'], $b['sku'], $b['batch_no'], $b['manufacture_date'],
                $b['expiry_date'], $b['days_left'], $b['quantity_received'], $b['quantity_available'], $b['unit_cost_eff'],
                round((int) $b['quantity_available'] * (float) $b['unit_cost_eff'], 2), $b['status'],
                $b['supplier'] ?? $b['supplier_name'], $b['invoice_no'], $b['vendor_name'] ?? 'In-house', $b['received_at'],
            ], $rows)
        );
    }

    public function batch(string $id): void
    {
        $this->renderBatch($this->findBatch((int) $id), [], []);
    }

    /** Edit batch details (number, dates, cost, supplier, invoice). */
    public function batchUpdate(string $id): void
    {
        $batch = $this->findBatch((int) $id);
        $in = $this->postData();
        $errors = $this->validate($in, [
            'batch_no'         => 'required|max:100',
            'manufacture_date' => 'date',
            'expiry_date'      => 'date',
            'cost_price'       => 'decimal',
            'supplier_id'      => 'integer',
            'invoice_no'       => 'max:100',
        ]);
        if (!$errors) {
            try {
                Inventory::updateBatch((int) $batch['id'], $in);
                flash('success', __('inv_batch_saved'));
                redirect('admin/inventory/batches/' . $batch['id']);
            } catch (InventoryException $e) {
                $errors[$e->field ?: 'batch_no'] = $e->getMessage();
            }
        }
        $this->renderBatch($batch, $errors, ['edit' => $in]);
    }

    /** Physical count: set available quantity. */
    public function batchAdjust(string $id): void
    {
        $batch = $this->findBatch((int) $id);
        $in = $this->postData();
        $errors = $this->validate($in, ['counted' => 'required|integer|gte:0|lte:1000000', 'note' => 'required|min:3|max:255']);
        if (!$errors) {
            try {
                $mid = Inventory::adjustBatch((int) $batch['id'], (int) $in['counted'], $in['note']);
                flash($mid ? 'success' : 'info', $mid ? __('inv_adjusted', ['from' => num($batch['quantity_available']), 'to' => num((int) $in['counted'])]) : __('inv_no_change'));
                redirect('admin/inventory/batches/' . $batch['id']);
            } catch (InventoryException $e) {
                $errors['counted'] = $e->getMessage();
            }
        }
        $this->renderBatch($batch, $errors, ['adjust' => $in]);
    }

    /** Damage / expired (qty or all) / recall (everything, blocks the batch). */
    public function batchWriteOff(string $id): void
    {
        $batch = $this->findBatch((int) $id);
        $in = $this->postData();
        $errors = $this->validate($in, [
            'type'     => 'required|in:damage,expired,recall',
            'quantity' => 'integer|gte:1|lte:1000000',
            'note'     => 'required|min:3|max:255',
        ]);
        if (!$errors) {
            try {
                $qty = ($in['quantity'] ?? '') !== '' ? (int) $in['quantity'] : null;
                $out = Inventory::writeOff((int) $batch['id'], $in['type'], $qty, $in['note']);
                $n = array_sum(array_column($out, 'quantity'));
                flash('success', __('inv_written_off', ['qty' => num($n), 'type' => __('mv_' . $in['type'])]));
                redirect('admin/inventory/batches/' . $batch['id']);
            } catch (InventoryException $e) {
                $errors['wo_' . ($e->field === 'quantity' ? 'quantity' : 'note')] = $e->getMessage();
            }
        } else {
            $errors = array_combine(array_map(static fn($k) => 'wo_' . $k, array_keys($errors)), $errors);
        }
        $this->renderBatch($batch, $errors, ['writeoff' => $in]);
    }

    private function findBatch(int $id): array
    {
        $b = InventoryReport::batch($id);
        if (!$b) {
            $this->notFound();
        }
        return $b;
    }

    private function renderBatch(array $batch, array $errors, array $input): void
    {
        $this->view('admin/inventory/batch', [
            'title'     => __('inv_batch') . ' ' . $batch['batch_no'],
            'batch'     => $batch,
            'movements' => InventoryReport::movements(['batch_id' => $batch['id']], 100, 0),
            'suppliers' => Supplier::options(false),
            'errors'    => $errors,
            'input'     => $input,
        ], 'admin');
    }

    private function batchFilters(): array
    {
        $s = static fn(string $k) => Helpers::str($k);
        $v = $s('vendor');
        return [
            'q'           => mb_substr($s('q'), 0, 100),
            'status'      => in_array($s('status'), ['active', 'in_stock', 'depleted', 'expired', 'recalled'], true) ? $s('status') : '',
            'expiry'      => in_array($s('expiry'), ['expired', 'near', 'none'], true) ? $s('expiry') : '',
            'vendor'      => ($v === 'inhouse' || ctype_digit($v)) ? $v : '',
            'supplier_id' => ctype_digit($s('supplier_id')) ? (int) $s('supplier_id') : 0,
            'product_id'  => ctype_digit($s('product_id')) ? (int) $s('product_id') : 0,
            'sort'        => in_array($s('sort'), ['expiry', 'qty', 'received'], true) ? $s('sort') : 'expiry',
        ];
    }

    /* ======================= Ledger ======================= */

    public function movements(): void
    {
        $f = $this->movementFilters();
        $p = new Paginator(InventoryReport::countMovements($f), 50);
        $this->view('admin/inventory/movements', [
            'title'     => __('inv_movements'),
            'rows'      => InventoryReport::movements($f, $p->perPage, $p->offset()),
            'paginator' => $p,
            'filters'   => $f,
            'vendors'   => Vendor::options(),
            'product'   => $f['product_id'] ? Product::find($f['product_id']) : null,
        ], 'admin');
    }

    public function movementsExport(): void
    {
        $f = $this->movementFilters();
        $rows = InventoryReport::movements($f, 200000, 0);
        Audit::log('inventory.export', 'stock_movements', null, null, ['report' => 'movements', 'filters' => array_filter($f), 'rows' => count($rows)]);
        Helpers::csvDownload('stock-movements-' . date('Ymd-His') . '.csv',
            ['id', 'date', 'type', 'product_id', 'product', 'variant', 'sku', 'batch_no', 'quantity', 'balance_after', 'unit_cost', 'reference', 'note', 'user'],
            array_map(static fn($m) => [
                $m['id'], $m['created_at'], $m['type'], $m['product_id'], $m['name_en'], $m['variant_name'], $m['sku'], $m['batch_no'],
                $m['quantity'], $m['balance_after'], $m['unit_cost'], trim($m['reference_type'] . ' ' . $m['reference_id']), $m['note'], $m['user_name'],
            ], $rows)
        );
    }

    private function movementFilters(): array
    {
        $s = static fn(string $k) => Helpers::str($k);
        $types = array_merge(Inventory::IN_TYPES, Inventory::OUT_TYPES, ['adjustment']);
        $date = static fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
        $v = $s('vendor');
        return [
            'q'          => mb_substr($s('q'), 0, 100),
            'type'       => in_array($s('type'), $types, true) ? $s('type') : '',
            'product_id' => ctype_digit($s('product_id')) ? (int) $s('product_id') : 0,
            'batch_id'   => ctype_digit($s('batch_id')) ? (int) $s('batch_id') : 0,
            'vendor'     => ($v === 'inhouse' || ctype_digit($v)) ? $v : '',
            'from'       => $date($s('from')),
            'to'         => $date($s('to')),
        ];
    }

    /* ======================= Valuation ======================= */

    public function valuation(): void
    {
        $f = $this->valuationFilters();
        $p = new Paginator(InventoryReport::countValuation($f), 50);
        $this->view('admin/inventory/valuation', [
            'title'      => __('inv_valuation'),
            'rows'       => InventoryReport::valuation($f, $p->perPage, $p->offset()),
            'totals'     => InventoryReport::valuationTotals($f),
            'paginator'  => $p,
            'filters'    => $f,
            'vendors'    => Vendor::options(),
            'categories' => Category::tree(),
        ], 'admin');
    }

    public function valuationExport(): void
    {
        $f = $this->valuationFilters();
        $rows = InventoryReport::valuation($f, 100000, 0);
        Audit::log('inventory.export', 'inventory_batches', null, null, ['report' => 'valuation', 'rows' => count($rows)]);
        Helpers::csvDownload('stock-valuation-' . date('Ymd-His') . '.csv',
            ['product_id', 'product', 'sku', 'category', 'quantity', 'purchase_value', 'sale_value', 'potential_profit', 'margin_pct', 'qty_without_cost', 'qty_expired'],
            array_map(static fn($r) => [
                $r['id'], $r['name_en'], $r['sku'], $r['category'], $r['qty'], round((float) $r['cost_value'], 2), round((float) $r['sale_value'], 2),
                round((float) $r['sale_value'] - (float) $r['cost_value'], 2),
                (float) $r['sale_value'] > 0 ? round(((float) $r['sale_value'] - (float) $r['cost_value']) / (float) $r['sale_value'] * 100, 1) : '',
                $r['qty_no_cost'], $r['qty_expired'],
            ], $rows)
        );
    }

    private function valuationFilters(): array
    {
        $s = static fn(string $k) => Helpers::str($k);
        $v = $s('vendor');
        return [
            'q'           => mb_substr($s('q'), 0, 100),
            'category_id' => ctype_digit($s('category_id')) ? (int) $s('category_id') : 0,
            'vendor'      => ($v === 'inhouse' || ctype_digit($v)) ? $v : '',
            'expired'     => $s('expired') === 'exclude' ? 'exclude' : '',
            'sort'        => in_array($s('sort'), ['value', 'margin', 'qty', 'name'], true) ? $s('sort') : 'value',
        ];
    }

    /* ======================= Vendor-wise ======================= */

    public function vendors(): void
    {
        $this->view('admin/inventory/vendors', ['title' => __('inv_vendor_report'), 'rows' => InventoryReport::vendorWise()], 'admin');
    }

    public function vendorsExport(): void
    {
        $rows = InventoryReport::vendorWise();
        Audit::log('inventory.export', 'inventory_batches', null, null, ['report' => 'vendor_wise', 'rows' => count($rows)]);
        Helpers::csvDownload('vendor-stock-' . date('Ymd-His') . '.csv',
            ['vendor_id', 'seller', 'products', 'stock_tracked', 'out_of_stock', 'low_stock', 'batches', 'quantity', 'purchase_value', 'sale_value', 'near_expiry_qty', 'expired_qty'],
            array_map(static fn($r) => [
                $r['vendor_id'] ?? '', $r['vendor_id'] ? $r['vendor_name'] : 'In-house', $r['products'], $r['tracked'], $r['out_of_stock'],
                $r['low_stock'], $r['batches'], $r['qty'], round((float) $r['cost_value'], 2), round((float) $r['sale_value'], 2),
                $r['near_expiry_qty'], $r['expired_qty'],
            ], $rows)
        );
    }
}
