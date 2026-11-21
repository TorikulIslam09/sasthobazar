<?php
/** Batch detail. Vars: $batch, $movements, $suppliers, $errors, $input (['edit'|'adjust'|'writeoff' => posted values]) */
$b = $batch;
$edit = $input['edit'] ?? $b;
$adj = $input['adjust'] ?? [];
$wo = $input['writeoff'] ?? [];
$e = static fn(array $src, string $k, $d = '') => (string) ($src[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control form-control-sm') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
$dl = $b['days_left'] !== null ? (int) $b['days_left'] : null;
$cost = $b['cost_price'] !== null ? (float) $b['cost_price'] : null;
$locked = $b['status'] === 'recalled';
$statusBadge = ['active' => 'bg-success', 'depleted' => 'bg-secondary', 'expired' => 'bg-danger', 'recalled' => 'bg-dark'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">
        <a href="<?= e(url('admin/inventory/batches')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
        <?= e(__('inv_batch')) ?> <span class="font-monospace"><?= e($b['batch_no']) ?></span>
        <span class="badge <?= e($statusBadge[$b['status']] ?? 'bg-light') ?> fs-6 align-middle"><?= e(__('batch_' . $b['status'])) ?></span>
    </h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/inventory/movements?product_id=' . $b['product_id'])) ?>"><i class="bi bi-journal-text"></i> <?= e(__('inv_product_ledger')) ?></a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>
<?php if ($dl !== null && $dl < 0 && (int) $b['quantity_available'] > 0 && $b['status'] === 'active'): ?>
    <div class="alert alert-danger"><i class="bi bi-calendar-x"></i> <?= e(__('inv_batch_expired_notice', ['n' => num($b['quantity_available'])])) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="fw-semibold"><?= e(Product::label($b)) ?></div>
                <div class="small text-muted mb-3"><?= e($b['variant_name'] ?: '') ?> <?= e($b['sku'] ? 'SKU ' . $b['sku'] : '') ?></div>
                <div class="row g-2 text-center mb-3">
                    <div class="col-6"><div class="border rounded p-2"><div class="small text-muted"><?= e(__('inv_available')) ?></div><div class="fs-4 fw-semibold"><?= e(num($b['quantity_available'])) ?></div></div></div>
                    <div class="col-6"><div class="border rounded p-2"><div class="small text-muted"><?= e(__('inv_received')) ?></div><div class="fs-4 fw-semibold text-muted"><?= e(num($b['quantity_received'])) ?></div></div></div>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal"><?= e(__('inv_expiry_date')) ?></dt>
                    <dd class="col-7 <?= $dl !== null && $dl < 0 ? 'text-danger fw-semibold' : '' ?>"><?= $b['expiry_date'] ? e(dt($b['expiry_date'], 'd M Y')) . ($dl !== null ? ' (' . ($dl < 0 ? e(__('inv_expired')) : e(__('inv_days_left', ['n' => num($dl)]))) . ')' : '') : '—' ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('inv_mfg_date')) ?></dt><dd class="col-7"><?= $b['manufacture_date'] ? e(dt($b['manufacture_date'], 'd M Y')) : '—' ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('inv_unit_cost')) ?></dt><dd class="col-7"><?= $cost !== null ? e(money($cost)) : '—' ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('inv_cost_value')) ?></dt><dd class="col-7"><?= e(money((int) $b['quantity_available'] * (float) $cost)) ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('supplier')) ?></dt><dd class="col-7"><?= e($b['supplier'] ?? $b['supplier_name'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('inv_invoice_no')) ?></dt><dd class="col-7"><?= e($b['invoice_no'] ?? '—') ?></dd>
                    <?php if ($b['grn_no']): ?>
                        <dt class="col-5 text-muted fw-normal">GRN</dt><dd class="col-7"><a href="<?= e(url('admin/purchase-orders/grn/' . $b['grn_id'])) ?>"><?= e($b['grn_no']) ?></a></dd>
                    <?php endif; ?>
                    <dt class="col-5 text-muted fw-normal"><?= e(__('inv_received_at')) ?></dt><dd class="col-7"><?= e(dt($b['received_at'])) ?><br><span class="text-muted"><?= e($b['received_by_name'] ?? '') ?></span></dd>
                </dl>
            </div>
        </div>

        <?php if (can('inventory.adjust') && !$locked): ?>
            <!-- Count adjustment -->
            <form method="post" action="<?= e(url('admin/inventory/batches/' . $b['id'] . '/adjust')) ?>" class="card border-0 shadow-sm mb-3" novalidate>
                <?= csrf_field() ?>
                <div class="card-header bg-white fw-semibold"><i class="bi bi-123"></i> <?= e(__('inv_physical_count')) ?></div>
                <div class="card-body">
                    <label class="form-label small" for="counted"><?= e(__('inv_counted_qty')) ?></label>
                    <input class="<?= $cls('counted') ?>" type="number" min="0" id="counted" name="counted" value="<?= e($e($adj, 'counted', $b['quantity_available'])) ?>" required>
                    <?= $err('counted') ?>
                    <label class="form-label small mt-2" for="adj_note"><?= e(__('inv_reason')) ?> *</label>
                    <input class="<?= $cls('note') ?>" id="adj_note" name="note" maxlength="255" value="<?= e($e($adj, 'note')) ?>" placeholder="<?= e(__('inv_count_ph')) ?>" required>
                    <?= $err('note') ?>
                    <button class="btn btn-sm btn-warning mt-2"><i class="bi bi-check2"></i> <?= e(__('inv_save_count')) ?></button>
                </div>
            </form>

            <!-- Write-off -->
            <form method="post" action="<?= e(url('admin/inventory/batches/' . $b['id'] . '/write-off')) ?>" class="card border-danger-subtle mb-3" novalidate data-confirm="<?= e(__('inv_confirm_writeoff')) ?>" data-confirm-danger="1">
                <?= csrf_field() ?>
                <div class="card-header bg-white fw-semibold text-danger"><i class="bi bi-trash3"></i> <?= e(__('inv_write_off')) ?></div>
                <div class="card-body">
                    <select class="form-select form-select-sm mb-2" name="type" aria-label="<?= e(__('type')) ?>" data-writeoff-type>
                        <option value="damage" <?= $e($wo, 'type') === 'damage' ? 'selected' : '' ?>><?= e(__('mv_damage')) ?></option>
                        <option value="expired" <?= $e($wo, 'type', $dl !== null && $dl < 0 ? 'expired' : '') === 'expired' ? 'selected' : '' ?>><?= e(__('mv_expired')) ?></option>
                        <option value="recall" <?= $e($wo, 'type') === 'recall' ? 'selected' : '' ?>><?= e(__('mv_recall')) ?> (<?= e(__('inv_recall_all')) ?>)</option>
                    </select>
                    <input class="<?= $cls('wo_quantity') ?>" type="number" min="1" max="<?= (int) $b['quantity_available'] ?>" name="quantity" value="<?= e($e($wo, 'quantity')) ?>" placeholder="<?= e(__('inv_all_remaining', ['n' => num($b['quantity_available'])])) ?>" aria-label="<?= e(__('quantity')) ?>" data-writeoff-qty>
                    <?= $err('wo_quantity') ?>
                    <input class="<?= $cls('wo_note') ?> mt-2" name="note" maxlength="255" value="<?= e($e($wo, 'note')) ?>" placeholder="<?= e(__('inv_reason')) ?> *" aria-label="<?= e(__('inv_reason')) ?>" required>
                    <?= $err('wo_note') ?>
                    <?= $err('wo_type') ?>
                    <button class="btn btn-sm btn-danger mt-2"><i class="bi bi-trash3"></i> <?= e(__('inv_write_off')) ?></button>
                </div>
            </form>
        <?php elseif ($locked): ?>
            <div class="alert alert-dark small"><i class="bi bi-slash-circle"></i> <?= e(__('inv_recalled_notice')) ?></div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <?php if (can('inventory.batches')): ?>
            <form method="post" action="<?= e(url('admin/inventory/batches/' . $b['id'])) ?>" class="card border-0 shadow-sm mb-3" novalidate>
                <?= csrf_field() ?>
                <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil"></i> <?= e(__('inv_batch_details')) ?></div>
                <div class="card-body row g-2">
                    <div class="col-md-4">
                        <label class="form-label small" for="batch_no"><?= e(__('inv_batch_no')) ?> *</label>
                        <input class="<?= $cls('batch_no') ?> font-monospace text-uppercase" id="batch_no" name="batch_no" maxlength="100" value="<?= e($e($edit, 'batch_no')) ?>">
                        <?= $err('batch_no') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label small" for="manufacture_date"><?= e(__('inv_mfg_date')) ?></label>
                        <input class="<?= $cls('manufacture_date') ?>" type="date" id="manufacture_date" name="manufacture_date" value="<?= e($e($edit, 'manufacture_date')) ?>">
                        <?= $err('manufacture_date') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label small" for="expiry_date"><?= e(__('inv_expiry_date')) ?></label>
                        <input class="<?= $cls('expiry_date') ?>" type="date" id="expiry_date" name="expiry_date" value="<?= e($e($edit, 'expiry_date')) ?>">
                        <?= $err('expiry_date') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label small" for="cost_price"><?= e(__('inv_unit_cost')) ?></label>
                        <input class="<?= $cls('cost_price') ?>" inputmode="decimal" id="cost_price" name="cost_price" value="<?= e($e($edit, 'cost_price')) ?>">
                        <?= $err('cost_price') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label small" for="supplier_id"><?= e(__('supplier')) ?></label>
                        <select class="<?= $cls('supplier_id', 'form-select form-select-sm') ?>" id="supplier_id" name="supplier_id">
                            <option value="">—</option>
                            <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $e($edit, 'supplier_id') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="invoice_no"><?= e(__('inv_invoice_no')) ?></label>
                        <input class="<?= $cls('invoice_no') ?>" id="invoice_no" name="invoice_no" maxlength="100" value="<?= e($e($edit, 'invoice_no')) ?>">
                        <?= $err('invoice_no') ?>
                    </div>
                    <div class="col-12"><button class="btn btn-sm btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
                        <span class="small text-muted ms-2"><?= e(__('inv_edit_no_stock')) ?></span></div>
                </div>
            </form>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> <?= e(__('inv_batch_history')) ?></div>
            <?= View::partial('admin/inventory/_movement_table', ['rows' => $movements, 'hideProduct' => true]) ?>
        </div>
    </div>
</div>
