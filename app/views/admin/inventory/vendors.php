<?php
/** Vendor-wise stock. Var: $rows */
$sum = static fn(string $k) => array_sum(array_map(static fn($r) => (float) $r[$k], $rows));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-shop"></i> <?= e(__('inv_vendor_report')) ?></h1>
    <?php if (can('inventory.export')): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/inventory/reports/vendors/export')) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
    <?php endif; ?>
</div>
<?= View::partial('admin/inventory/_nav', ['active' => 'vendors']) ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('seller')) ?></th>
                    <th class="text-end"><?= e(__('products')) ?></th>
                    <th class="text-end"><?= e(__('out_of_stock')) ?></th>
                    <th class="text-end"><?= e(__('low_stock')) ?></th>
                    <th class="text-end"><?= e(__('inv_batches')) ?></th>
                    <th class="text-end"><?= e(__('inv_units_on_hand')) ?></th>
                    <th class="text-end"><?= e(__('inv_purchase_value')) ?></th>
                    <th class="text-end"><?= e(__('inv_sale_value')) ?></th>
                    <th class="text-end"><?= e(__('inv_near_expiry')) ?></th>
                    <th class="text-end"><?= e(__('inv_expired')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): $vq = $r['vendor_id'] ? (string) $r['vendor_id'] : 'inhouse'; ?>
                <tr>
                    <td>
                        <a class="fw-semibold text-reset" href="<?= e(url('admin/inventory?vendor=' . $vq)) ?>"><?= e($r['vendor_id'] ? tr($r['vendor_name_bn'], $r['vendor_name']) : __('in_house')) ?></a>
                        <?php if ($r['vendor_id'] && $r['vendor_status'] !== 'approved'): ?><span class="badge bg-secondary-subtle text-secondary"><?= e($r['vendor_status']) ?></span><?php endif; ?>
                    </td>
                    <td class="text-end"><?= e(num($r['products'])) ?></td>
                    <td class="text-end <?= (int) $r['out_of_stock'] ? 'text-danger fw-semibold' : '' ?>"><?= e(num($r['out_of_stock'])) ?></td>
                    <td class="text-end <?= (int) $r['low_stock'] ? 'text-warning-emphasis fw-semibold' : '' ?>"><?= e(num($r['low_stock'])) ?></td>
                    <td class="text-end"><a href="<?= e(url('admin/inventory/batches?status=in_stock&vendor=' . $vq)) ?>"><?= e(num($r['batches'])) ?></a></td>
                    <td class="text-end"><?= e(num(number_format((float) $r['qty']))) ?></td>
                    <td class="text-end"><a class="text-reset" href="<?= e(url('admin/inventory/reports/valuation?vendor=' . $vq)) ?>"><?= e(money($r['cost_value'])) ?></a></td>
                    <td class="text-end"><?= e(money($r['sale_value'])) ?></td>
                    <td class="text-end <?= (int) $r['near_expiry_qty'] ? 'text-warning-emphasis' : '' ?>"><?= e(num($r['near_expiry_qty'])) ?></td>
                    <td class="text-end <?= (int) $r['expired_qty'] ? 'text-danger' : '' ?>"><?= e(num($r['expired_qty'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if (count($rows) > 1): ?>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td><?= e(__('total')) ?></td>
                        <?php foreach (['products', 'out_of_stock', 'low_stock', 'batches'] as $k): ?><td class="text-end"><?= e(num((int) $sum($k))) ?></td><?php endforeach; ?>
                        <td class="text-end"><?= e(num(number_format($sum('qty')))) ?></td>
                        <td class="text-end"><?= e(money($sum('cost_value'))) ?></td>
                        <td class="text-end"><?= e(money($sum('sale_value'))) ?></td>
                        <td class="text-end"><?= e(num((int) $sum('near_expiry_qty'))) ?></td>
                        <td class="text-end"><?= e(num((int) $sum('expired_qty'))) ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted"><?= e(__('inv_vendor_note', ['n' => num(InventoryReport::expiryDays())])) ?></div>
</div>
