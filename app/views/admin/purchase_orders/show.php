<?php
/** Purchase order detail (also printable with ?print=1). Vars: $po, $items, $grns, $print */
$badge = PurchaseOrderView::BADGES;
$st = $po['status'];
$received = array_sum(array_column($items, 'quantity_received'));
?>
<?php if ($print): ?>
    <style>@media print { .no-print { display: none !important; } } body { background: #fff; }</style>
    <div class="container py-4">
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">
        <?php if (!$print): ?><a href="<?= e(url('admin/purchase-orders')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a><?php endif; ?>
        <?= e(__('purchase_order')) ?> <span class="font-monospace"><?= e($po['po_no']) ?></span>
        <span class="badge <?= e($badge[$st]) ?> fs-6 align-middle"><?= e(__('po_status_' . $st)) ?></span>
    </h1>
    <div class="d-flex flex-wrap gap-2 no-print">
        <?php if ($print): ?>
            <button class="btn btn-sm btn-brand" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/purchase-orders/' . $po['id'] . '?print=1')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('print')) ?></a>
            <?php if ($st === 'draft' && can('purchase_orders.edit')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/purchase-orders/' . $po['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> <?= e(__('edit')) ?></a>
                <form method="post" action="<?= e(url('admin/purchase-orders/' . $po['id'] . '/place')) ?>" data-confirm="<?= e(__('po_confirm_place')) ?>"><?= csrf_field() ?>
                    <button class="btn btn-sm btn-brand"><i class="bi bi-send"></i> <?= e(__('po_place')) ?></button></form>
            <?php endif; ?>
            <?php if (in_array($st, ['ordered', 'partial'], true) && can('purchase_orders.receive')): ?>
                <a class="btn btn-sm btn-success" href="<?= e(url('admin/purchase-orders/' . $po['id'] . '/receive')) ?>"><i class="bi bi-box-arrow-in-down"></i> <?= e(__('grn_receive')) ?></a>
            <?php endif; ?>
            <?php if ($st === 'partial' && can('purchase_orders.cancel')): ?>
                <form method="post" action="<?= e(url('admin/purchase-orders/' . $po['id'] . '/close')) ?>" data-confirm="<?= e(__('po_confirm_close')) ?>"><?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-dark"><i class="bi bi-lock"></i> <?= e(__('po_close')) ?></button></form>
            <?php endif; ?>
            <?php if (in_array($st, ['draft', 'ordered'], true) && $received === 0 && can('purchase_orders.cancel')): ?>
                <form method="post" action="<?= e(url('admin/purchase-orders/' . $po['id'] . '/cancel')) ?>" data-confirm="<?= e(__('po_confirm_cancel')) ?>" data-confirm-danger="1"><?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> <?= e(__('po_cancel')) ?></button></form>
            <?php endif; ?>
            <?php if ($st === 'draft' && can('purchase_orders.delete')): ?>
                <?= View::partial('partials/delete_button', ['action' => url('admin/purchase-orders/' . $po['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $po['po_no']]), 'label' => __('delete')]) ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body small">
            <div class="text-muted"><?= e(__('supplier')) ?></div>
            <div class="fw-semibold fs-6"><?= e($po['supplier_name']) ?></div>
            <div><?= e($po['supplier_phone'] ?? '') ?> <?= e($po['supplier_email'] ?? '') ?></div>
            <div class="text-muted"><?= e($po['supplier_address'] ?? '') ?></div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-5 text-muted fw-normal"><?= e(__('po_stock_owner')) ?></dt><dd class="col-7"><?= e($po['vendor_name'] ?? __('in_house')) ?></dd>
                <dt class="col-5 text-muted fw-normal"><?= e(__('po_order_date')) ?></dt><dd class="col-7"><?= e(dt($po['order_date'], 'd M Y')) ?></dd>
                <dt class="col-5 text-muted fw-normal"><?= e(__('po_expected_date')) ?></dt><dd class="col-7"><?= $po['expected_date'] ? e(dt($po['expected_date'], 'd M Y')) : '—' ?></dd>
                <dt class="col-5 text-muted fw-normal"><?= e(__('po_created_by')) ?></dt><dd class="col-7"><?= e($po['created_by_name'] ?? '—') ?>, <?= e(dt($po['created_at'], 'd M Y')) ?></dd>
                <?php if ($po['ordered_at']): ?><dt class="col-5 text-muted fw-normal"><?= e(__('po_ordered_at')) ?></dt><dd class="col-7"><?= e(dt($po['ordered_at'])) ?></dd><?php endif; ?>
            </dl>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light">
                <tr><th>#</th><th><?= e(__('product')) ?></th><th class="text-end"><?= e(__('po_ordered')) ?></th><th class="text-end"><?= e(__('po_received')) ?></th>
                    <th class="text-end"><?= e(__('po_remaining')) ?></th><th class="text-end"><?= e(__('inv_unit_cost')) ?></th><th class="text-end"><?= e(__('po_line_total')) ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $n => $i): ?>
                <tr>
                    <td class="text-muted"><?= e(num($n + 1)) ?></td>
                    <td><div class="fw-semibold"><?= e($i['name_en']) ?><?= $i['variant_name'] ? ' · ' . e($i['variant_name']) : '' ?></div><div class="text-muted"><?= e($i['variant_sku'] ?: $i['sku'] ?: '') ?></div></td>
                    <td class="text-end"><?= e(num($i['quantity_ordered'])) ?></td>
                    <td class="text-end text-success"><?= e(num($i['quantity_received'])) ?></td>
                    <td class="text-end <?= (int) $i['remaining'] > 0 && in_array($st, ['ordered', 'partial'], true) ? 'fw-semibold' : 'text-muted' ?>"><?= e(num($i['remaining'])) ?></td>
                    <td class="text-end"><?= e(money($i['unit_cost'])) ?></td>
                    <td class="text-end"><?= e(money($i['line_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="6" class="text-end text-muted"><?= e(__('po_subtotal')) ?></td><td class="text-end"><?= e(money($po['subtotal'])) ?></td></tr>
                <?php if ((float) $po['discount'] > 0): ?><tr><td colspan="6" class="text-end text-muted"><?= e(__('po_discount')) ?></td><td class="text-end">−<?= e(money($po['discount'])) ?></td></tr><?php endif; ?>
                <?php if ((float) $po['tax'] > 0): ?><tr><td colspan="6" class="text-end text-muted"><?= e(__('po_tax')) ?></td><td class="text-end">+<?= e(money($po['tax'])) ?></td></tr><?php endif; ?>
                <tr class="fw-semibold fs-6"><td colspan="6" class="text-end"><?= e(__('total')) ?></td><td class="text-end"><?= e(money($po['total'])) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if ($po['notes']): ?><div class="card border-0 shadow-sm mb-3"><div class="card-body small"><strong><?= e(__('note')) ?>:</strong> <?= nl2br(e($po['notes'])) ?></div></div><?php endif; ?>

<?php if (!$print): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt"></i> <?= e(__('grn_list')) ?></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light"><tr><th>GRN</th><th><?= e(__('grn_received_date')) ?></th><th><?= e(__('inv_invoice_no')) ?></th><th class="text-end"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('grn_value')) ?></th><th><?= e(__('grn_received_by')) ?></th></tr></thead>
                <tbody>
                <?php if (!$grns): ?><tr><td colspan="6" class="text-center text-muted py-3"><?= e(__('grn_none')) ?></td></tr><?php endif; ?>
                <?php foreach ($grns as $g): ?>
                    <tr>
                        <td><a class="font-monospace" href="<?= e(url('admin/purchase-orders/grn/' . $g['id'])) ?>"><?= e($g['grn_no']) ?></a></td>
                        <td><?= e(dt($g['received_date'], 'd M Y')) ?></td>
                        <td><?= e($g['invoice_no'] ?? '—') ?></td>
                        <td class="text-end"><?= e(num($g['total_quantity'])) ?></td>
                        <td class="text-end"><?= e(money($g['total_cost'])) ?></td>
                        <td><?= e($g['received_by_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="row mt-5 small text-center"><div class="col-4 border-top pt-2"><?= e(__('po_prepared_by')) ?></div><div class="col-4"></div><div class="col-4 border-top pt-2"><?= e(__('po_approved_by')) ?></div></div>
    </div>
<?php endif; ?>
