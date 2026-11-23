<?php
/** Customers list. Vars: $tab (registered|guests), $f (filters), $rows, $pager, $summary */
$qs = static fn(array $extra) => http_build_query(array_filter(array_merge($f, $extra), static fn($v) => $v !== '' && $v !== null));
$stColor = ['active' => 'success', 'inactive' => 'secondary', 'banned' => 'danger'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-people"></i> <?= e(__('customers')) ?></h1>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($tab === 'registered' && can('customers.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/customers/export') . '?' . $qs([])) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('customers.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/customers/create')) ?>"><i class="bi bi-person-plus"></i> <?= e(__('cust_add')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-2 mb-3">
    <?php foreach ([['bi-people', __('cust_total'), $summary['total']], ['bi-person-plus', __('cust_new_month'), $summary['new_month']],
                    ['bi-slash-circle', __('cst_banned'), $summary['statuses']['banned'] ?? 0], ['bi-person-dash', __('cust_guest_buyers'), $summary['guests']]] as [$ic, $lbl, $n]): ?>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted"><i class="bi <?= $ic ?>"></i> <?= e($lbl) ?></div><div class="fs-5 fw-bold"><?= e(num($n)) ?></div>
        </div></div></div>
    <?php endforeach; ?>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'registered' ? 'active' : '' ?>" href="<?= e(url('admin/customers')) ?>"><?= e(__('cust_registered')) ?> <span class="badge bg-light text-dark"><?= e(num($summary['total'])) ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'guests' ? 'active' : '' ?>" href="<?= e(url('admin/customers?tab=guests')) ?>"><?= e(__('cust_guest_buyers')) ?> <span class="badge bg-light text-dark"><?= e(num($summary['guests'])) ?></span></a></li>
</ul>

<form class="card border-0 shadow-sm mb-3" method="get" action="<?= e(url('admin/customers')) ?>">
    <?php if ($tab === 'guests'): ?><input type="hidden" name="tab" value="guests"><?php endif; ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-<?= $tab === 'guests' ? '10' : '4' ?>">
            <input class="form-control form-control-sm" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('cust_search_ph')) ?>" maxlength="100">
        </div>
        <?php if ($tab === 'registered'): ?>
            <div class="col-6 col-md-2">
                <select class="form-select form-select-sm" name="status" aria-label="<?= e(__('status')) ?>">
                    <option value=""><?= e(__('status')) ?>: <?= e(__('all')) ?></option>
                    <?php foreach (Customer::STATUSES as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(__('cst_' . $s)) ?> (<?= e(num($summary['statuses'][$s] ?? 0)) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select form-select-sm" name="buyers" aria-label="<?= e(__('orders')) ?>">
                    <option value=""><?= e(__('orders')) ?>: <?= e(__('all')) ?></option>
                    <option value="1" <?= $f['buyers'] === '1' ? 'selected' : '' ?>><?= e(__('cust_has_orders')) ?></option>
                    <option value="0" <?= $f['buyers'] === '0' ? 'selected' : '' ?>><?= e(__('cust_no_orders')) ?></option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select form-select-sm" name="sort" aria-label="<?= e(__('sort_by')) ?>">
                    <?php foreach (Customer::SORTS as $s): ?><option value="<?= $s ?>" <?= $f['sort'] === $s ? 'selected' : '' ?>><?= e(__('csort_' . $s)) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-6 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/customers' . ($tab === 'guests' ? '?tab=guests' : ''))) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
        <?php if ($tab === 'registered'): ?>
            <div class="col-6 col-md-2"><label class="form-label small mb-0" for="cf"><?= e(__('cust_joined_from')) ?></label><input class="form-control form-control-sm" type="date" id="cf" name="from" value="<?= e($f['from']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0" for="ct"><?= e(__('cust_joined_to')) ?></label><input class="form-control form-control-sm" type="date" id="ct" name="to" value="<?= e($f['to']) ?>"></div>
        <?php endif; ?>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <?php if ($tab === 'registered'): ?>
                <thead class="table-light"><tr><th><?= e(__('name')) ?></th><th><?= e(__('phone')) ?> / <?= e(__('email')) ?></th><th class="text-end"><?= e(__('orders')) ?></th><th class="text-end"><?= e(__('cust_spent')) ?></th><th><?= e(__('cust_last_order')) ?></th><th><?= e(__('joined')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><a class="fw-semibold" href="<?= e(url('admin/customers/' . (int) $r['id'])) ?>"><?= e($r['name']) ?></a></td>
                        <td><?= e((string) $r['phone']) ?><?php if ($r['phone_verified_at']): ?> <i class="bi bi-patch-check-fill text-success" title="<?= e(__('verified')) ?>"></i><?php endif; ?>
                            <?php if ($r['email']): ?><div class="text-muted"><?= e($r['email']) ?></div><?php endif; ?></td>
                        <td class="text-end"><?= e(num($r['orders'])) ?></td>
                        <td class="text-end"><?= e(money($r['spent'])) ?></td>
                        <td><?= $r['last_order'] ? e(dt($r['last_order'], 'd M Y')) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= e(dt($r['created_at'], 'd M Y')) ?></td>
                        <td><span class="badge text-bg-<?= $stColor[$r['status']] ?? 'light' ?>"><?= e(__('cst_' . $r['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            <?php else: ?>
                <thead class="table-light"><tr><th><?= e(__('name')) ?></th><th><?= e(__('phone')) ?> / <?= e(__('email')) ?></th><th class="text-end"><?= e(__('orders')) ?></th><th class="text-end"><?= e(__('cust_spent')) ?></th><th><?= e(__('cust_last_order')) ?></th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['name']) ?> <span class="badge text-bg-secondary"><?= e(__('guest')) ?></span></td>
                        <td><?= e($r['phone']) ?><?php if ((int) $r['verified'] > 0): ?> <i class="bi bi-patch-check-fill text-success" title="<?= e(__('verified')) ?>"></i><?php endif; ?>
                            <?php if ($r['email']): ?><div class="text-muted"><?= e($r['email']) ?></div><?php endif; ?></td>
                        <td class="text-end"><?= e(num($r['orders'])) ?></td>
                        <td class="text-end"><?= e(money($r['spent'])) ?></td>
                        <td><?= e(dt($r['last_order'], 'd M Y')) ?></td>
                        <td class="text-end"><?php if (can('orders.view')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/orders?q=' . rawurlencode($r['phone']))) ?>"><?= e(__('orders')) ?></a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
<?php if ($tab === 'guests'): ?><p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> <?= e(__('cust_guests_help')) ?></p><?php endif; ?>
