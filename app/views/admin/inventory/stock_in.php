<?php
/** Manual stock in. Vars: $input, $errors, $product (selected, with variants), $suppliers */
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
?>
<h1 class="h4 mb-3"><i class="bi bi-box-arrow-in-down"></i> <?= e(__('inv_stock_in')) ?></h1>
<?= View::partial('admin/inventory/_nav', ['active' => 'stock_in']) ?>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" action="<?= e(url('admin/inventory/stock-in')) ?>" class="card border-0 shadow-sm" novalidate data-stock-form>
            <?= csrf_field() ?>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label" for="pp"><?= e(__('product')) ?> *</label>
                    <?= View::partial('partials/product_picker', [
                        'name' => 'product_id', 'variantName' => 'variant_id', 'product' => $product, 'variantId' => $v('variant_id'),
                        'error' => $errors['product_id'] ?? null, 'variantError' => $errors['variant_id'] ?? null, 'id' => 'pp', 'required' => true,
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="batch_no"><?= e(__('inv_batch_no')) ?> *</label>
                    <input class="<?= $cls('batch_no') ?> font-monospace text-uppercase" id="batch_no" name="batch_no" maxlength="100" required value="<?= e($v('batch_no')) ?>" placeholder="B2611A">
                    <?= $err('batch_no') ?>
                    <div class="form-text"><?= e(__('inv_batch_merge_help')) ?></div>
                </div>
                <div class="col-md-4 col-6">
                    <label class="form-label" for="manufacture_date"><?= e(__('inv_mfg_date')) ?></label>
                    <input class="<?= $cls('manufacture_date') ?>" type="date" id="manufacture_date" name="manufacture_date" max="<?= date('Y-m-d') ?>" value="<?= e($v('manufacture_date')) ?>">
                    <?= $err('manufacture_date') ?>
                </div>
                <div class="col-md-4 col-6">
                    <label class="form-label" for="expiry_date"><?= e(__('inv_expiry_date')) ?></label>
                    <input class="<?= $cls('expiry_date') ?>" type="date" id="expiry_date" name="expiry_date" value="<?= e($v('expiry_date')) ?>">
                    <?= $err('expiry_date') ?>
                    <div class="form-text"><?= e(__('inv_expiry_help')) ?></div>
                </div>
                <div class="col-md-4 col-6">
                    <label class="form-label" for="quantity"><?= e(__('quantity')) ?> *</label>
                    <input class="<?= $cls('quantity') ?>" type="number" min="1" max="1000000" id="quantity" name="quantity" required value="<?= e($v('quantity')) ?>">
                    <?= $err('quantity') ?>
                </div>
                <div class="col-md-4 col-6">
                    <label class="form-label" for="unit_cost"><?= e(__('inv_unit_cost')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">৳</span>
                        <input class="<?= $cls('unit_cost') ?>" inputmode="decimal" id="unit_cost" name="unit_cost" value="<?= e($v('unit_cost')) ?>" placeholder="<?= e($product['cost_price'] ?? '0.00') ?>" data-cost-input>
                    </div>
                    <?= $err('unit_cost') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supplier_id"><?= e(__('supplier')) ?></label>
                    <select class="<?= $cls('supplier_id', 'form-select') ?>" id="supplier_id" name="supplier_id">
                        <option value="">—</option>
                        <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $v('supplier_id') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('supplier_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="invoice_no"><?= e(__('inv_invoice_no')) ?></label>
                    <input class="<?= $cls('invoice_no') ?>" id="invoice_no" name="invoice_no" maxlength="100" value="<?= e($v('invoice_no')) ?>">
                    <?= $err('invoice_no') ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="note"><?= e(__('note')) ?></label>
                    <input class="<?= $cls('note') ?>" id="note" name="note" maxlength="255" value="<?= e($v('note')) ?>" placeholder="<?= e(__('inv_note_in_ph')) ?>">
                    <?= $err('note') ?>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('inv_receive_stock')) ?></button>
                <button class="btn btn-outline-primary" name="_after" value="new"><?= e(__('save_and_add_another')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/inventory')) ?>"><?= e(__('cancel')) ?></a>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm small">
            <div class="card-body">
                <div class="fw-semibold mb-2"><i class="bi bi-info-circle"></i> <?= e(__('inv_how_it_works')) ?></div>
                <ul class="ps-3 mb-0 text-muted">
                    <li><?= e(__('inv_in_help_1')) ?></li>
                    <li><?= e(__('inv_in_help_2')) ?></li>
                    <li><?= e(__('inv_in_help_3')) ?></li>
                    <?php if (can('purchase_orders.receive')): ?><li><?= e(__('inv_in_help_po')) ?> <a href="<?= e(url('admin/purchase-orders')) ?>"><?= e(__('purchase_orders')) ?> →</a></li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
