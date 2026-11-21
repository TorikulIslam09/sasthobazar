<?php
/** Admin order list. Vars: $filters, $orders, $pager, $counts */
$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over, ['page' => null]), static fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
};
$tabs = ['pending', 'prescription_review', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
$canStatus = can('orders.update_status');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-receipt"></i> <?= e(__('orders')) ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm <?= $filters['return'] ? 'active' : '' ?>" href="<?= e(url('admin/orders') . $qs(['return' => $filters['return'] ? '' : '1'])) ?>"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('return_requests')) ?></a>
        <?php if (can('orders.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/orders/export') . $qs([])) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-pills flex-nowrap overflow-auto mb-2 status-tabs">
    <li class="nav-item"><a class="nav-link <?= $filters['status'] === '' ? 'active' : '' ?>" href="<?= e(url('admin/orders') . $qs(['status' => ''])) ?>"><?= e(__('all')) ?> <span class="badge bg-light text-dark"><?= e(num(array_sum($counts))) ?></span></a></li>
    <?php foreach ($tabs as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $filters['status'] === $s ? 'active' : '' ?>" href="<?= e(url('admin/orders') . $qs(['status' => $s])) ?>"><?= e(__('os_' . $s)) ?> <span class="badge bg-light text-dark"><?= e(num($counts[$s] ?? 0)) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form class="card border-0 shadow-sm mb-3" method="get">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <?php if ($filters['return']): ?><input type="hidden" name="return" value="1"><?php endif; ?>
    <?php if ($filters['unverified']): ?><input type="hidden" name="unverified" value="1"><?php endif; ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-3"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('order_search')) ?>" aria-label="<?= e(__('search')) ?>"></div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="payment_method" aria-label="<?= e(__('payment_method')) ?>">
                <option value=""><?= e(__('payment_method')) ?>: <?= e(__('all')) ?></option>
                <?php foreach (Order::PAYMENT_METHODS as $m): ?><option value="<?= $m ?>" <?= $filters['payment_method'] === $m ? 'selected' : '' ?>><?= e(__('pm_' . $m)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="payment_status" aria-label="<?= e(__('payment_status')) ?>">
                <option value=""><?= e(__('payment_status')) ?>: <?= e(__('all')) ?></option>
                <?php foreach (array_keys(OrderView::PAY_BADGES) as $s): ?><option value="<?= $s ?>" <?= $filters['payment_status'] === $s ? 'selected' : '' ?>><?= e(__('ps_' . $s)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 col-lg-2"><input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>" title="<?= e(__('from_date')) ?>" aria-label="<?= e(__('from_date')) ?>"></div>
        <div class="col-6 col-md-2 col-lg-2"><input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>" title="<?= e(__('to_date')) ?>" aria-label="<?= e(__('to_date')) ?>"></div>
        <div class="col-12 col-md-2 col-lg-1">
            <div class="form-check small"><input class="form-check-input" type="checkbox" name="rx" value="1" id="fRx" <?= $filters['rx'] ? 'checked' : '' ?>><label class="form-check-label" for="fRx">Rx</label></div>
        </div>
        <div class="col-12 col-lg-auto d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/orders')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<form method="post" action="<?= e(url('admin/orders/bulk')) ?>" id="bulkForm" data-confirm="<?= e(__('bulk_confirm')) ?>">
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted"><span data-bulk-count>0</span> <?= e(__('selected')) ?></span>
            <select class="form-select form-select-sm w-auto" name="bulk_action" aria-label="<?= e(__('bulk_action')) ?>">
                <option value="print"><?= e(__('bulk_print')) ?></option>
                <?php if ($canStatus): foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $s): ?>
                    <option value="<?= $s ?>"><?= e(__('bulk_mark', ['status' => __('os_' . $s)])) ?></option>
                <?php endforeach; if (can('orders.cancel')): ?>
                    <option value="cancelled"><?= e(__('bulk_mark', ['status' => __('os_cancelled')])) ?></option>
                <?php endif; endif; ?>
            </select>
            <button class="btn btn-sm btn-outline-primary" data-bulk-submit disabled><?= e(__('apply')) ?></button>
            <span class="ms-auto small text-muted"><?= e(__('showing')) ?> <?= e(num($pager->from())) ?>–<?= e(num($pager->to())) ?> / <?= e(num($pager->total)) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all aria-label="<?= e(__('select_all')) ?>"></th>
                        <th><?= e(__('order')) ?></th>
                        <th><?= e(__('customer')) ?></th>
                        <th class="d-none d-md-table-cell"><?= e(__('items')) ?></th>
                        <th class="text-end"><?= e(__('total')) ?></th>
                        <th><?= e(__('payment')) ?></th>
                        <th><?= e(__('status')) ?></th>
                        <th class="text-end"><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr>
                <?php endif; foreach ($orders as $o): ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $o['id'] ?>" data-row-check aria-label="<?= e($o['order_no']) ?>"></td>
                        <td>
                            <a href="<?= e(url('admin/orders/' . (int) $o['id'])) ?>" class="fw-semibold"><?= e($o['order_no']) ?></a>
                            <?php if ($o['requires_prescription']): ?><span class="badge text-bg-danger">Rx</span><?php endif; ?>
                            <?php if ((int) $o['open_returns']): ?><span class="badge text-bg-warning" title="<?= e(__('return_requests')) ?>"><i class="bi bi-arrow-counterclockwise"></i></span><?php endif; ?>
                            <div class="text-muted"><?= e(dt($o['placed_at'], 'd M, h:i A')) ?></div>
                        </td>
                        <td><?= e($o['customer_name']) ?><div class="text-muted"><?= e($o['customer_phone']) ?> · <?= e(tr($o['district_bn'], $o['district_en'])) ?></div></td>
                        <td class="d-none d-md-table-cell"><?= e(num($o['items'])) ?></td>
                        <td class="text-end fw-semibold"><?= e(money($o['grand_total'])) ?></td>
                        <td><?= e(__('pm_' . $o['payment_method'])) ?><div><?= OrderView::payBadge($o['payment_status']) ?>
                            <?php if ((int) $o['unverified']): ?><span class="badge text-bg-warning"><?= e(__('trx_unverified')) ?></span><?php endif; ?></div></td>
                        <td><?= OrderView::badge($o['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/orders/' . (int) $o['id'])) ?>" title="<?= e(__('view')) ?>"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/orders/' . (int) $o['id'] . '/invoice')) ?>" target="_blank" rel="noopener" title="<?= e(__('invoice')) ?>"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<div class="mt-3"><?= $pager->links() ?></div>
