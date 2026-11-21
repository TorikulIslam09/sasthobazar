<?php
/** Manual stock out / damage. Vars: $input, $errors, $product, $batches (of the selected product/variant) */
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
?>
<h1 class="h4 mb-3"><i class="bi bi-box-arrow-up"></i> <?= e(__('inv_stock_out')) ?></h1>
<?= View::partial('admin/inventory/_nav', ['active' => 'stock_out']) ?>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" action="<?= e(url('admin/inventory/stock-out')) ?>" class="card border-0 shadow-sm" novalidate
              data-stock-out data-batches-url="<?= e(url('admin/inventory/lookup/batches')) ?>"
              data-confirm="<?= e(__('inv_confirm_out')) ?>">
            <?= csrf_field() ?>
            <div class="card-body row g-3">
                <div class="col-12">
                    <span class="form-label d-block"><?= e(__('type')) ?> *</span>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="type" id="t_out" value="stock_out" <?= $v('type', 'stock_out') === 'stock_out' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="t_out"><i class="bi bi-box-arrow-up"></i> <?= e(__('mv_stock_out')) ?></label>
                        <input type="radio" class="btn-check" name="type" id="t_damage" value="damage" <?= $v('type') === 'damage' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-danger" for="t_damage"><i class="bi bi-heartbreak"></i> <?= e(__('mv_damage')) ?></label>
                    </div>
                    <?= $err('type') ?>
                    <div class="form-text"><?= e(__('inv_out_type_help')) ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="pp"><?= e(__('product')) ?> *</label>
                    <?= View::partial('partials/product_picker', [
                        'name' => 'product_id', 'variantName' => 'variant_id', 'product' => $product, 'variantId' => $v('variant_id'),
                        'error' => $errors['product_id'] ?? null, 'variantError' => $errors['variant_id'] ?? null, 'id' => 'pp', 'required' => true,
                    ]) ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="batch_id"><?= e(__('inv_batch')) ?></label>
                    <select class="<?= $cls('batch_id', 'form-select') ?>" id="batch_id" name="batch_id" data-batch-select data-selected="<?= e($v('batch_id')) ?>">
                        <option value=""><?= e(__('inv_auto_fefo')) ?></option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= $v('batch_id') === (string) $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['batch_no']) ?> · <?= e(__('inv_available_n', ['n' => num($b['quantity_available'])])) ?><?= $b['expiry_date'] ? ' · ' . e(__('inv_exp')) . ' ' . e($b['expiry_date']) : '' ?><?= (int) $b['is_expired'] ? ' · ' . e(__('inv_expired')) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('batch_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="quantity"><?= e(__('quantity')) ?> *</label>
                    <input class="<?= $cls('quantity') ?>" type="number" min="1" id="quantity" name="quantity" required value="<?= e($v('quantity')) ?>">
                    <?= $err('quantity') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="note"><?= e(__('inv_reason')) ?> *</label>
                    <input class="<?= $cls('note') ?>" id="note" name="note" maxlength="255" required value="<?= e($v('note')) ?>" placeholder="<?= e(__('inv_reason_ph')) ?>">
                    <?= $err('note') ?>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-danger"><i class="bi bi-check2"></i> <?= e(__('inv_remove_stock')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/inventory')) ?>"><?= e(__('cancel')) ?></a>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm small">
            <div class="card-body">
                <div class="fw-semibold mb-2"><i class="bi bi-info-circle"></i> <?= e(__('inv_how_it_works')) ?></div>
                <ul class="ps-3 mb-0 text-muted">
                    <li><?= e(__('inv_out_help_1')) ?></li>
                    <li><?= e(__('inv_out_help_2')) ?></li>
                    <li><?= e(__('inv_out_help_3')) ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
