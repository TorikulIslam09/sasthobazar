<?php
/** Stock movement rows. Vars: $rows, $compact (bool: hide some columns), $hideProduct (bool) */
$compact = $compact ?? false;
$hideProduct = $hideProduct ?? false;
$badge = [
    'purchase' => 'bg-success', 'stock_in' => 'bg-success', 'return' => 'bg-info text-dark', 'cancel' => 'bg-info text-dark',
    'sale' => 'bg-primary', 'stock_out' => 'bg-secondary', 'adjustment' => 'bg-warning text-dark',
    'damage' => 'bg-danger', 'expired' => 'bg-danger', 'recall' => 'bg-dark', 'transfer' => 'bg-light text-dark border',
];
?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0 small">
        <thead class="table-light">
            <tr>
                <th><?= e(__('time')) ?></th>
                <th><?= e(__('type')) ?></th>
                <?php if (!$hideProduct): ?><th><?= e(__('product')) ?></th><?php endif; ?>
                <th><?= e(__('inv_batch')) ?></th>
                <th class="text-end"><?= e(__('quantity')) ?></th>
                <th class="text-end"><?= e(__('inv_balance')) ?></th>
                <?php if (!$compact): ?>
                    <th class="text-end d-none d-lg-table-cell"><?= e(__('inv_unit_cost')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('inv_reference')) ?></th>
                    <th class="d-none d-xl-table-cell"><?= e(__('user')) ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
        <?php foreach ($rows as $m): $q = (int) $m['quantity']; ?>
            <tr>
                <td class="text-nowrap"><?= e(dt($m['created_at'], 'd M y, h:i A')) ?></td>
                <td><span class="badge <?= e($badge[$m['type']] ?? 'bg-light text-dark') ?>"><?= e(__('mv_' . $m['type'])) ?></span></td>
                <?php if (!$hideProduct): ?>
                    <td class="text-truncate" style="max-width:240px">
                        <a class="text-reset" href="<?= e(url('admin/inventory/movements?product_id=' . $m['product_id'])) ?>"><?= e(Product::label($m)) ?></a>
                        <?= $m['variant_name'] ? '<span class="text-muted"> · ' . e($m['variant_name']) . '</span>' : '' ?>
                    </td>
                <?php endif; ?>
                <td class="font-monospace"><?= $m['batch_id'] ? '<a href="' . e(url('admin/inventory/batches/' . $m['batch_id'])) . '">' . e($m['batch_no']) . '</a>' : '—' ?></td>
                <td class="text-end fw-semibold <?= $q > 0 ? 'text-success' : 'text-danger' ?>"><?= $q > 0 ? '+' : '' ?><?= e(num($q)) ?></td>
                <td class="text-end"><?= e(num($m['balance_after'])) ?></td>
                <?php if (!$compact): ?>
                    <td class="text-end d-none d-lg-table-cell"><?= $m['unit_cost'] !== null ? e(money($m['unit_cost'])) : '—' ?></td>
                    <td class="d-none d-md-table-cell">
                        <?php if ($m['reference_type'] === 'grn' && $m['reference_id']): ?>
                            <a href="<?= e(url('admin/purchase-orders/grn/' . $m['reference_id'])) ?>">GRN #<?= (int) $m['reference_id'] ?></a>
                        <?php else: ?>
                            <span class="text-muted"><?= e(__('ref_' . ($m['reference_type'] ?: 'manual'))) ?></span>
                        <?php endif; ?>
                        <?php if ($m['note']): ?><div class="text-muted text-truncate" style="max-width:220px" title="<?= e($m['note']) ?>"><?= e($m['note']) ?></div><?php endif; ?>
                    </td>
                    <td class="d-none d-xl-table-cell"><?= e($m['user_name'] ?? __('system')) ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
