<?php
/** Receive goods (create GRN). Vars: $po, $items, $input, $lines (po_item_id => posted line), $errors */
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url('admin/purchase-orders/' . $po['id'])) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e(__('grn_receive')) ?> · <span class="font-monospace"><?= e($po['po_no']) ?></span>
    <span class="small text-muted fw-normal">(<?= e($po['supplier_name']) ?>)</span>
</h1>
<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e($errors['lines'] ?? __('form_has_errors')) ?><div class="small"><?= e(__('grn_nothing_saved')) ?></div></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/purchase-orders/' . $po['id'] . '/receive')) ?>" novalidate
      data-confirm="<?= e(__('grn_confirm')) ?>" id="grnForm">
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-3 col-6">
                <label class="form-label" for="received_date"><?= e(__('grn_received_date')) ?> *</label>
                <input class="<?= $cls('received_date') ?>" type="date" id="received_date" name="received_date" max="<?= date('Y-m-d') ?>" required value="<?= e($v('received_date')) ?>">
                <?= $err('received_date') ?>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label" for="invoice_no"><?= e(__('grn_invoice')) ?></label>
                <input class="<?= $cls('invoice_no') ?>" id="invoice_no" name="invoice_no" maxlength="100" value="<?= e($v('invoice_no')) ?>">
                <?= $err('invoice_no') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="notes"><?= e(__('note')) ?></label>
                <input class="<?= $cls('notes') ?>" id="notes" name="notes" maxlength="500" value="<?= e($v('notes')) ?>" placeholder="<?= e(__('grn_notes_ph')) ?>">
                <?= $err('notes') ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><i class="bi bi-box-arrow-in-down"></i> <?= e(__('grn_lines')) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-grn-fill><i class="bi bi-check2-all"></i> <?= e(__('grn_receive_all')) ?></button>
        </div>
        <div class="table-responsive">
            <table class="table align-top mb-0 small grn-table">
                <thead class="table-light">
                    <tr><th style="min-width:200px"><?= e(__('product')) ?></th><th class="text-end"><?= e(__('po_remaining')) ?></th><th style="width:110px"><?= e(__('grn_receive_qty')) ?></th>
                        <th style="min-width:140px"><?= e(__('inv_batch_no')) ?></th><th style="min-width:140px"><?= e(__('inv_mfg_date')) ?></th><th style="min-width:140px"><?= e(__('inv_expiry_date')) ?></th><th style="width:120px"><?= e(__('inv_unit_cost')) ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $i):
                    $id = (int) $i['id'];
                    $l = $lines[$id] ?? [];
                    $done = (int) $i['remaining'] <= 0;
                    $le = static fn(string $f) => $errors["lines.{$id}.{$f}"] ?? null;
                    $lc = static fn(string $f) => 'form-control form-control-sm' . ($le($f) ? ' is-invalid' : '');
                    $fe = static fn(string $f) => ($m = $le($f)) ? '<div class="invalid-feedback d-block">' . e($m) . '</div>' : ''; ?>
                    <tr class="<?= $done ? 'table-light text-muted' : '' ?>" data-grn-row data-remaining="<?= (int) $i['remaining'] ?>">
                        <td><div class="fw-semibold"><?= e($i['name_en']) ?><?= $i['variant_name'] ? ' · ' . e($i['variant_name']) : '' ?></div>
                            <div class="text-muted"><?= e(__('po_ordered')) ?> <?= e(num($i['quantity_ordered'])) ?> · <?= e(__('po_received')) ?> <?= e(num($i['quantity_received'])) ?>
                                <?= $i['product_type'] === 'medicine' ? '· <span class="text-danger">' . e(__('inv_expiry_needed')) . '</span>' : '' ?></div></td>
                        <td class="text-end fw-semibold"><?= e(num($i['remaining'])) ?></td>
                        <?php if ($done): ?>
                            <td colspan="5" class="text-success"><i class="bi bi-check-circle"></i> <?= e(__('grn_line_complete')) ?></td>
                        <?php else: ?>
                            <td><input class="<?= $lc('quantity') ?>" type="number" min="0" max="<?= (int) $i['remaining'] ?>" name="lines[<?= $id ?>][quantity]" value="<?= e($l['quantity'] ?? '') ?>" placeholder="0" data-grn-qty aria-label="<?= e(__('grn_receive_qty')) ?>"><?= $fe('quantity') ?></td>
                            <td><input class="<?= $lc('batch_no') ?> font-monospace text-uppercase" name="lines[<?= $id ?>][batch_no]" maxlength="100" value="<?= e($l['batch_no'] ?? '') ?>" aria-label="<?= e(__('inv_batch_no')) ?>"><?= $fe('batch_no') ?></td>
                            <td><input class="<?= $lc('manufacture_date') ?>" type="date" max="<?= date('Y-m-d') ?>" name="lines[<?= $id ?>][manufacture_date]" value="<?= e($l['manufacture_date'] ?? '') ?>" aria-label="<?= e(__('inv_mfg_date')) ?>"><?= $fe('manufacture_date') ?></td>
                            <td><input class="<?= $lc('expiry_date') ?>" type="date" name="lines[<?= $id ?>][expiry_date]" value="<?= e($l['expiry_date'] ?? '') ?>" aria-label="<?= e(__('inv_expiry_date')) ?>"><?= $fe('expiry_date') ?></td>
                            <td><input class="<?= $lc('unit_cost') ?>" inputmode="decimal" name="lines[<?= $id ?>][unit_cost]" value="<?= e($l['unit_cost'] ?? $i['unit_cost']) ?>" aria-label="<?= e(__('inv_unit_cost')) ?>"><?= $fe('unit_cost') ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted"><?= e(__('grn_help')) ?></div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-success"><i class="bi bi-check2"></i> <?= e(__('grn_post')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/purchase-orders/' . $po['id'])) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
