<?php
declare(strict_types=1);

/**
 * Inventory: dashboard, alert thresholds, manual stock in / stock out / damage, picker lookups (JSON).
 * All stock changes go through Inventory (transactions + row locks); this controller only validates input.
 */
class AdminInventoryController extends Controller
{
    private const LIST_LIMIT = 10;

    /* ======================= Dashboard ======================= */

    public function index(): void
    {
        $vendor = $this->vendorFilter();
        $thr = Helpers::str('threshold');
        $threshold = ctype_digit($thr) && (int) $thr <= 100000 ? (int) $thr : null;
        $days = ctype_digit(Helpers::str('days')) && (int) Helpers::str('days') > 0 && (int) Helpers::str('days') <= 1825
            ? (int) Helpers::str('days') : InventoryReport::expiryDays();

        $this->view('admin/inventory/dashboard', [
            'title'      => __('inventory'),
            'summary'    => InventoryReport::summary($vendor),
            'outOfStock' => InventoryReport::outOfStock($vendor, self::LIST_LIMIT),
            'lowStock'   => InventoryReport::lowStock($vendor, $threshold, self::LIST_LIMIT),
            'nearExpiry' => InventoryReport::nearExpiry($vendor, $days, true, self::LIST_LIMIT),
            'recent'     => InventoryReport::movements(['vendor' => $vendor], 8, 0),
            'vendor'     => $vendor,
            'vendors'    => Vendor::options(),
            'threshold'  => $threshold,
            'days'       => $days,
            'settings'   => [
                'low_stock_threshold'    => InventoryReport::lowStockDefault(),
                'expiry_alert_days'      => InventoryReport::expiryDays(),
                'block_expired_stock_in' => (bool) setting('block_expired_stock_in', true),
            ],
        ], 'admin');
    }

    /** Alert settings. Optional: apply the default threshold to every product. */
    public function settings(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'low_stock_threshold' => 'required|integer|gte:0|lte:100000',
            'expiry_alert_days'   => 'required|integer|gte:1|lte:1825',
        ]);
        if ($errors) {
            foreach ($errors as $f => $m) {
                flash('error', __('inv_' . $f) . ': ' . $m);
            }
            redirect('admin/inventory');
        }
        $values = [
            'low_stock_threshold'    => (string) (int) $in['low_stock_threshold'],
            'expiry_alert_days'      => (string) (int) $in['expiry_alert_days'],
            'block_expired_stock_in' => ($in['block_expired_stock_in'] ?? '') === '1' ? '1' : '0',
        ];
        $applied = 0;
        $this->db->transaction(function (Database $db) use ($values, $in, &$applied) {
            foreach ($values as $key => $val) {
                $old = $db->fetchColumn('SELECT `value` FROM settings WHERE `key` = :k', ['k' => $key]);
                $db->query(
                    "INSERT INTO settings (`group`, `key`, `value`, `type`, updated_by) VALUES ('inventory', :k, :v, :t, :u)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by)",
                    ['k' => $key, 'v' => $val, 't' => $key === 'block_expired_stock_in' ? 'bool' : 'int', 'u' => Auth::id()]
                );
                if ((string) $old !== $val) {
                    Audit::log('settings.update', 'settings', null, [$key => $old], [$key => $val]);
                }
            }
            if (($in['apply_all'] ?? '') === '1') {
                $applied = $db->query('UPDATE products SET low_stock_threshold = :t WHERE deleted_at IS NULL', ['t' => (int) $values['low_stock_threshold']])->rowCount();
                Audit::log('inventory.threshold_all', 'products', null, null, ['low_stock_threshold' => (int) $values['low_stock_threshold'], 'products' => $applied]);
            }
        });
        Helpers::clearSettingsCache();
        flash('success', __('inv_settings_saved') . ($applied ? ' ' . __('inv_threshold_applied', ['n' => num($applied)]) : ''));
        redirect('admin/inventory');
    }

    /* ======================= Stock in ======================= */

    public function stockInForm(): void
    {
        $this->renderStockIn(['received_date' => date('Y-m-d')], [], $this->prefillProduct());
    }

    public function stockIn(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'product_id'       => 'required|integer',
            'variant_id'       => 'integer',
            'batch_no'         => 'required|max:100',
            'manufacture_date' => 'date',
            'expiry_date'      => 'date',
            'quantity'         => 'required|integer|gte:1|lte:1000000',
            'unit_cost'        => 'decimal',
            'supplier_id'      => 'integer',
            'invoice_no'       => 'max:100',
            'note'             => 'max:255',
        ]);
        if (!isset($errors['supplier_id']) && ($in['supplier_id'] ?? '') !== '' && !Supplier::find((int) $in['supplier_id'])) {
            $errors['supplier_id'] = __('v_in');
        }
        if (!$errors) {
            try {
                $r = Inventory::receive([
                    'product_id'       => (int) $in['product_id'],
                    'variant_id'       => $in['variant_id'] ?? null,
                    'batch_no'         => $in['batch_no'],
                    'manufacture_date' => $in['manufacture_date'] ?? null,
                    'expiry_date'      => $in['expiry_date'] ?? null,
                    'quantity'         => (int) $in['quantity'],
                    'unit_cost'        => $in['unit_cost'] ?? null,
                    'supplier_id'      => $in['supplier_id'] ?? null,
                    'invoice_no'       => $in['invoice_no'] ?? null,
                    'type'             => 'stock_in',
                    'reference_type'   => 'manual',
                    'note'             => $in['note'] ?? null,
                ]);
                flash('success', __('inv_stock_in_done', ['qty' => num((int) $in['quantity']), 'balance' => num($r['balance'])]));
                redirect(($in['_after'] ?? '') === 'new' ? 'admin/inventory/stock-in' : 'admin/inventory/batches/' . $r['batch_id']);
            } catch (InventoryException $e) {
                $errors[$e->field ?: 'quantity'] = $e->getMessage();
            }
        }
        $this->renderStockIn($in, $errors, $this->pickedProduct($in));
    }

    private function renderStockIn(array $input, array $errors, ?array $product): void
    {
        $this->view('admin/inventory/stock_in', [
            'title'     => __('inv_stock_in'),
            'input'     => $input,
            'errors'    => $errors,
            'product'   => $product,
            'suppliers' => Supplier::options(),
        ], 'admin');
    }

    /* ======================= Stock out / damage ======================= */

    public function stockOutForm(): void
    {
        $this->renderStockOut(['type' => Helpers::str('type') === 'damage' ? 'damage' : 'stock_out', 'batch_id' => Helpers::str('batch_id')], [], $this->prefillProduct());
    }

    public function stockOut(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'product_id' => 'required|integer',
            'variant_id' => 'integer',
            'type'       => 'required|in:stock_out,damage',
            'batch_id'   => 'integer',
            'quantity'   => 'required|integer|gte:1|lte:1000000',
            'note'       => 'required|min:3|max:255',
        ]);
        if (($in['type'] ?? '') === 'damage' && ($in['batch_id'] ?? '') === '') {
            $errors['batch_id'] = __('inv_batch_required_damage');
        }
        if (!$errors) {
            try {
                $out = Inventory::remove([
                    'product_id'     => (int) $in['product_id'],
                    'variant_id'     => $in['variant_id'] ?? null,
                    'batch_id'       => ($in['batch_id'] ?? '') !== '' ? (int) $in['batch_id'] : null,
                    'quantity'       => (int) $in['quantity'],
                    'type'           => $in['type'],
                    'reference_type' => 'manual',
                    'note'           => $in['note'],
                ]);
                $parts = array_map(static fn($r) => $r['batch_no'] . ' × ' . num($r['quantity']), $out);
                flash('success', __('inv_stock_out_done', ['qty' => num((int) $in['quantity']), 'batches' => implode(', ', $parts)]));
                redirect('admin/inventory/movements?product_id=' . (int) $in['product_id']);
            } catch (InventoryException $e) {
                $errors[$e->field ?: 'quantity'] = $e->getMessage();
            }
        }
        $this->renderStockOut($in, $errors, $this->pickedProduct($in));
    }

    private function renderStockOut(array $input, array $errors, ?array $product): void
    {
        $this->view('admin/inventory/stock_out', [
            'title'   => __('inv_stock_out'),
            'input'   => $input,
            'errors'  => $errors,
            'product' => $product,
            'batches' => $product ? InventoryReport::batchesFor((int) $product['id'], ($input['variant_id'] ?? '') !== '' ? (int) $input['variant_id'] : null) : [],
        ], 'admin');
    }

    /* ======================= JSON lookups ======================= */

    /** GET admin/inventory/lookup/products?q= */
    public function lookupProducts(): void
    {
        $q = mb_substr(Helpers::str('q'), 0, 100);
        $this->json(mb_strlen($q) < 2 ? [] : InventoryReport::searchProducts($q));
    }

    /** GET admin/inventory/lookup/batches?product_id=&variant_id= */
    public function lookupBatches(): void
    {
        $pid = (int) Helpers::str('product_id');
        $vid = Helpers::str('variant_id');
        $this->json($pid ? InventoryReport::batchesFor($pid, ctype_digit($vid) && $vid !== '0' ? (int) $vid : null) : []);
    }

    /* ======================= helpers ======================= */

    private function vendorFilter(): string
    {
        $v = Helpers::str('vendor');
        return ($v === 'inhouse' || ctype_digit($v)) ? $v : '';
    }

    /** ?product_id= pre-selects a product (links from product list / batch page). */
    private function prefillProduct(): ?array
    {
        $id = Helpers::str('product_id');
        return ctype_digit($id) ? $this->pickedProduct(['product_id' => $id]) : null;
    }

    /** Product (with variants) for re-rendering the picker after a validation error. */
    private function pickedProduct(array $in): ?array
    {
        if (!ctype_digit((string) ($in['product_id'] ?? ''))) {
            return null;
        }
        $p = Product::findLive((int) $in['product_id']);
        if (!$p) {
            return null;
        }
        $p['variants'] = $this->db->fetchAll('SELECT id, name_en, sku, stock_qty FROM product_variants WHERE product_id = :p AND is_active = 1 ORDER BY is_default DESC, id', ['p' => $p['id']]);
        return $p;
    }
}
