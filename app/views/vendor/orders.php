<?php /** Vendor sub-orders. Vars: $rows, $pager, $status, $q, $counts */
$tabs = ['', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
?>
<h1 class="h4 mb-3"><i class="bi bi-receipt"></i> <?= e(__('my_orders')) ?></h1>
<ul class="nav nav-pills flex-nowrap overflow-auto mb-2 status-tabs">
    <?php foreach ($tabs as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="<?= e(url('vendor-panel/orders' . ($s !== '' ? '?status=' . $s : ''))) ?>">
            <?= e($s === '' ? __('all') : __('os_' . $s)) ?> <span class="badge bg-light text-dark"><?= e(num($s === '' ? array_sum($counts) : ($counts[$s] ?? 0))) ?></span></a></li>
    <?php endforeach; ?>
</ul>
<form class="mb-3 d-flex gap-2" method="get" style="max-width: 420px">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('v_order_search')) ?>" aria-label="<?= e(__('search')) ?>">
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('sub_order')) ?></th><th><?= e(__('customer')) ?></th><th class="text-center"><?= e(__('items')) ?></th><th class="text-end"><?= e(__('v_sales')) ?></th><th class="text-end"><?= e(__('v_net_earning')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a class="fw-semibold" href="<?= e(url('vendor-panel/orders/' . (int) $r['id'])) ?>"><?= e($r['sub_order_no']) ?></a><div class="text-muted"><?= e(dt($r['placed_at'], 'd M, h:i A')) ?></div></td>
                    <td><?= e($r['shipping_name']) ?><div class="text-muted"><?= e(__('pm_' . $r['payment_method'])) ?></div></td>
                    <td class="text-center"><?= e(num($r['items'])) ?></td>
                    <td class="text-end"><?= e(money((float) $r['subtotal'] - (float) $r['discount_total'])) ?></td>
                    <td class="text-end"><?= e(money($r['vendor_earning'])) ?><div class="text-muted"><?= e(__('settle_' . $r['settlement_status'])) ?></div></td>
                    <td><?= OrderView::badge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
