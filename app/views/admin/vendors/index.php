<?php /** Vendor list. Vars: $rows, $pager, $status, $q, $counts */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-shop"></i> <?= e(__('vendors_label')) ?></h1>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('vendor_types.view')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/vendor-types')) ?>"><i class="bi bi-tags"></i> <?= e(__('vendor_types')) ?></a><?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('vendor-panel/register')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('vendor_register')) ?></a>
    </div>
</div>
<ul class="nav nav-pills flex-nowrap overflow-auto mb-2 status-tabs">
    <li class="nav-item"><a class="nav-link <?= $status === '' ? 'active' : '' ?>" href="<?= e(url('admin/vendors')) ?>"><?= e(__('all')) ?> <span class="badge bg-light text-dark"><?= e(num(array_sum($counts))) ?></span></a></li>
    <?php foreach (VendorAccount::STATUSES as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="<?= e(url('admin/vendors?status=' . $s)) ?>"><?= e(__('vs_' . $s)) ?> <span class="badge bg-light text-dark"><?= e(num($counts[$s] ?? 0)) ?></span></a></li>
    <?php endforeach; ?>
    <?php if ($deleted ?? 0): ?><li class="nav-item"><a class="nav-link <?= $status === 'deleted' ? 'active' : '' ?>" href="<?= e(url('admin/vendors?status=deleted')) ?>"><i class="bi bi-trash"></i> <?= e(__('vdel_tab')) ?> <span class="badge bg-light text-dark"><?= e(num($deleted)) ?></span></a></li><?php endif; ?>
</ul>
<form class="mb-3 d-flex gap-2" method="get" style="max-width: 460px">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('vendor_search')) ?>" aria-label="<?= e(__('search')) ?>">
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('shop_name')) ?></th><th><?= e(__('owner_name')) ?></th><th><?= e(__('vendor_type')) ?></th><th class="text-center"><?= e(__('products')) ?></th><th class="text-center"><?= e(__('orders')) ?></th><th class="text-end"><?= e(__('wallet')) ?></th><th class="text-end"><?= e(__('commission')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $v): ?>
                <tr>
                    <td><a class="fw-semibold" href="<?= e(url('admin/vendors/' . (int) $v['id'])) ?>"><?= e($v['shop_name']) ?></a><div class="text-muted"><?= e(dt($v['created_at'], 'd M Y')) ?></div></td>
                    <td><?= e($v['contact_person']) ?><div class="text-muted"><?= e($v['phone']) ?></div></td>
                    <td><?= e(VendorType::vendorLabel($v)) ?></td>
                    <td class="text-center"><?= e(num($v['products'])) ?></td>
                    <td class="text-center"><?= e(num($v['orders'])) ?></td>
                    <td class="text-end"><?= e(money((float) $v['balance'])) ?><div class="text-muted"><?= e(__('wallet_pending')) ?> <?= e(money((float) $v['pending_balance'])) ?></div></td>
                    <td class="text-end"><?= $v['commission_rate'] !== null ? e(num($v['commission_rate'])) . '%' : '<span class="text-muted">' . e(__('cscope_global')) . '</span>' ?></td>
                    <td><span class="badge text-bg-<?= VendorAccount::BADGES[$v['status']] ?>"><?= e(__('vs_' . $v['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
