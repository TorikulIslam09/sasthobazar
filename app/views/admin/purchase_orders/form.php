<?php
/**
 * New / edit draft purchase order.
 * Vars: $po (null on create), $input, $lines (form rows), $products (id => product with variants, for re-render),
 *       $errors, $suppliers, $vendors
 */
$action = $po ? url('admin/purchase-orders/' . $po['id']) : url('admin/purchase-orders');
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);

$row = static function ($i, array $l, ?array $product) use ($errors): string {
    $n = is_int($i) ? $i : -1;
    $ie = static fn(string $f) => $errors["items.{$n}.{$f}"] ?? null;
    ob_start(); ?>
    <tr data-po-row>
        <td style="min-width:280px">
            <?= View::partial('partials/product_picker', [
                'name' => "items[{$i}][product_id]", 'variantName' => "items[{$i}][variant_id]", 'product' => $product,
                'variantId' => $l['variant_id'] ?? '', 'error' => $ie('product_id'), 'variantError' => $ie('variant_id'),
            ]) ?>
        </td>
        <td style="width:120px">
            <input class="form-control form-control-sm <?= $ie('quantity') ? 'is-invalid' : '' ?>" type="number" min="1" name="items[<?= e($i) ?>][quantity]" value="<?= e($l['quantity'] ?? '') ?>" data-po-qty aria-label="<?= e(__('quantity')) ?>">
            <?php if ($m = $ie('quantity')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
        </td>
        <td style="width:140px">
            <input class="form-control form-control-sm <?= $ie('unit_cost') ? 'is-invalid' : '' ?>" inputmode="decimal" name="items[<?= e($i) ?>][unit_cost]" value="<?= e($l['unit_cost'] ?? '') ?>" placeholder="0.00" data-po-cost aria-label="<?= e(__('inv_unit_cost')) ?>">
            <?php if ($m = $ie('unit_cost')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
        </td>
        <td class="text-end text-nowrap pt-2" style="width:130px" data-po-line-total>—</td>
        <td class="text-end" style="width:50px"><button type="button" class="btn btn-sm btn-outline-danger" data-po-remove title="<?= e(__('remove')) ?>"><i class="bi bi-x-lg"></i></button></td>
    </tr>
    <?php return (string) ob_get_clean();
};
if (!$lines) {
    $lines = [['product_id' => '', 'variant_id' => '', 'quantity' => '', 'unit_cost' => '']];
}
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url($po ? 'admin/purchase-orders/' . $po['id'] : 'admin/purchase-orders')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e($title) ?>
</h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" id="poForm" novalidate>
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="supplier_id"><?= e(__('supplier')) ?> *</label>
                <select class="<?= $cls('supplier_id', 'form-select') ?>" id="supplier_id" name="supplier_id" required>
                    <option value=""><?= e(__('select')) ?></option>
                    <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $v('supplier_id') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                </select>
                <?= $err('supplier_id') ?>
                <?php if (can('suppliers.create')): ?><div class="form-text"><a href="<?= e(url('admin/suppliers/create')) ?>" target="_blank" rel="noopener"><i class="bi bi-plus"></i> <?= e(__('add_supplier')) ?></a></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="vendor_id"><?= e(__('po_stock_owner')) ?></label>
                <select class="<?= $cls('vendor_id', 'form-select') ?>" id="vendor_id" name="vendor_id">
                    <option value=""><?= e(__('in_house')) ?></option>
                    <?php foreach ($vendors as $vd): ?><option value="<?= (int) $vd['id'] ?>" <?= $v('vendor_id') === (string) $vd['id'] ? 'selected' : '' ?>><?= e(Vendor::label($vd)) ?></option><?php endforeach; ?>
                </select>
                <?= $err('vendor_id') ?>
                <div class="form-text"><?= e(__('po_owner_help')) ?></div>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label" for="order_date"><?= e(__('po_order_date')) ?> *</label>
                <input class="<?= $cls('order_date') ?>" type="date" id="order_date" name="order_date" required value="<?= e($v('order_date')) ?>">
                <?= $err('order_date') ?>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label" for="expected_date"><?= e(__('po_expected_date')) ?></label>
                <input class="<?= $cls('expected_date') ?>" type="date" id="expected_date" name="expected_date" value="<?= e($v('expected_date')) ?>">
                <?= $err('expected_date') ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-list-check"></i> <?= e(__('po_items')) ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary" data-po-add><i class="bi bi-plus-lg"></i> <?= e(__('po_add_item')) ?></button>
        </div>
        <?php if (isset($errors['items'])): ?><div class="alert alert-danger m-3 mb-0 py-2 small"><?= e($errors['items']) ?></div><?php endif; ?>
        <div class="table-responsive po-items">
            <table class="table align-top mb-0">
                <thead class="table-light small">
                    <tr><th><?= e(__('product')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('inv_unit_cost')) ?></th><th class="text-end"><?= e(__('po_line_total')) ?></th><th></th></tr>
                </thead>
                <tbody data-po-body>
                    <?php foreach (array_values($lines) as $i => $l): ?>
                        <?= $row($i, $l, $products[(int) $l['product_id']] ?? null) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <template id="poRowTemplate"><?= $row('__i__', [], null) ?></template>
        <div class="card-body border-top">
            <div class="row justify-content-end">
                <div class="col-md-5 col-lg-4">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted"><?= e(__('po_subtotal')) ?></span><strong data-po-subtotal>৳0.00</strong></div>
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                        <label class="text-muted" for="discount"><?= e(__('po_discount')) ?> (−)</label>
                        <input class="<?= $cls('discount') ?> form-control-sm text-end" style="max-width:140px" inputmode="decimal" id="discount" name="discount" value="<?= e($v('discount', '0')) ?>" data-po-discount>
                    </div>
                    <?= $err('discount') ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                        <label class="text-muted" for="tax"><?= e(__('po_tax')) ?> (+)</label>
                        <input class="<?= $cls('tax') ?> form-control-sm text-end" style="max-width:140px" inputmode="decimal" id="tax" name="tax" value="<?= e($v('tax', '0')) ?>" data-po-tax>
                    </div>
                    <?= $err('tax') ?>
                    <div class="d-flex justify-content-between border-top pt-2 fs-5"><span><?= e(__('total')) ?></span><strong data-po-total>৳0.00</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label" for="notes"><?= e(__('note')) ?></label>
            <textarea class="<?= $cls('notes') ?>" id="notes" name="notes" rows="2" maxlength="5000" placeholder="<?= e(__('po_notes_ph')) ?>"><?= e($v('notes')) ?></textarea>
            <?= $err('notes') ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 form-actions">
        <button class="btn btn-outline-primary" name="_after" value="draft"><i class="bi bi-save"></i> <?= e(__('po_save_draft')) ?></button>
        <?php if (can('purchase_orders.edit')): ?>
            <button class="btn btn-brand" name="_after" value="place"><i class="bi bi-send"></i> <?= e(__('po_save_place')) ?></button>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/purchase-orders')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
