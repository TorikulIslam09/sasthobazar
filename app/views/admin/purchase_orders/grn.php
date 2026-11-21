<?php /** Goods received note (printable with ?print=1). Vars: $grn, $print */ $g = $grn; ?>
<?php if ($print): ?>
    <style>@media print { .no-print { display: none !important; } } body { background: #fff; }</style>
    <div class="container py-4">
<?php endif; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">
        <?php if (!$print): ?><a href="<?= e(url('admin/purchase-orders/' . $g['purchase_order_id'])) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a><?php endif; ?>
        <?= e(__('grn_title')) ?> <span class="font-monospace"><?= e($g['grn_no']) ?></span>
    </h1>
    <div class="no-print">
        <?php if ($print): ?>
            <button class="btn btn-sm btn-brand" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/purchase-orders/grn/' . $g['id'] . '?print=1')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('print')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body small row">
    <div class="col-md-6">
        <div class="text-muted"><?= e(__('supplier')) ?></div>
        <div class="fw-semibold fs-6"><?= e($g['supplier_name']) ?></div>
        <div><?= e($g['supplier_phone'] ?? '') ?></div><div class="text-muted"><?= e($g['supplier_address'] ?? '') ?></div>
    </div>
    <div class="col-md-6"><dl class="row mb-0">
        <dt class="col-5 text-muted fw-normal">PO</dt><dd class="col-7"><?php if ($print): ?><?= e($g['po_no']) ?><?php else: ?><a href="<?= e(url('admin/purchase-orders/' . $g['purchase_order_id'])) ?>"><?= e($g['po_no']) ?></a><?php endif; ?></dd>
        <dt class="col-5 text-muted fw-normal"><?= e(__('grn_received_date')) ?></dt><dd class="col-7"><?= e(dt($g['received_date'], 'd M Y')) ?></dd>
        <dt class="col-5 text-muted fw-normal"><?= e(__('grn_invoice')) ?></dt><dd class="col-7"><?= e($g['invoice_no'] ?? '—') ?></dd>
        <dt class="col-5 text-muted fw-normal"><?= e(__('po_stock_owner')) ?></dt><dd class="col-7"><?= e($g['vendor_name'] ?? __('in_house')) ?></dd>
        <dt class="col-5 text-muted fw-normal"><?= e(__('grn_received_by')) ?></dt><dd class="col-7"><?= e($g['received_by_name'] ?? '—') ?>, <?= e(dt($g['created_at'])) ?></dd>
    </dl></div>
</div></div>

<div class="card border-0 shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light"><tr><th>#</th><th><?= e(__('product')) ?></th><th><?= e(__('inv_batch_no')) ?></th><th><?= e(__('inv_mfg_date')) ?></th><th><?= e(__('inv_expiry_date')) ?></th>
                <th class="text-end"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('inv_unit_cost')) ?></th><th class="text-end"><?= e(__('po_line_total')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($g['items'] as $n => $i): ?>
                <tr>
                    <td class="text-muted"><?= e(num($n + 1)) ?></td>
                    <td><?= e($i['name_en']) ?><?= $i['variant_name'] ? ' · ' . e($i['variant_name']) : '' ?></td>
                    <td class="font-monospace"><?php if (!$print && $i['batch_id']): ?><a href="<?= e(url('admin/inventory/batches/' . $i['batch_id'])) ?>"><?= e($i['batch_no']) ?></a><?php else: ?><?= e($i['batch_no']) ?><?php endif; ?></td>
                    <td><?= $i['manufacture_date'] ? e(dt($i['manufacture_date'], 'd M Y')) : '—' ?></td>
                    <td><?= $i['expiry_date'] ? e(dt($i['expiry_date'], 'd M Y')) : '—' ?></td>
                    <td class="text-end"><?= e(num($i['quantity'])) ?></td>
                    <td class="text-end"><?= e(money($i['unit_cost'])) ?></td>
                    <td class="text-end"><?= e(money($i['line_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="fw-semibold"><tr><td colspan="5" class="text-end"><?= e(__('total')) ?></td><td class="text-end"><?= e(num($g['total_quantity'])) ?></td><td></td><td class="text-end"><?= e(money($g['total_cost'])) ?></td></tr></tfoot>
        </table>
    </div>
</div>
<?php if ($g['notes']): ?><div class="small mb-3"><strong><?= e(__('note')) ?>:</strong> <?= e($g['notes']) ?></div><?php endif; ?>
<?php if ($print): ?>
    <div class="row mt-5 small text-center"><div class="col-4 border-top pt-2"><?= e(__('grn_received_by')) ?></div><div class="col-4"></div><div class="col-4 border-top pt-2"><?= e(__('grn_checked_by')) ?></div></div>
    </div>
<?php endif; ?>
