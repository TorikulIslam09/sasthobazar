<?php
/** Payments. Vars: $f (filters), $rows, $pager, $totals, $byMethod, $toVerify */
$qs = http_build_query(array_filter($f, static fn($v) => $v !== ''));
$back = 'admin/payments' . ($qs !== '' ? '?' . $qs : '');
$stColor = ['initiated' => 'light', 'pending' => 'warning', 'success' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary', 'refunded' => 'info'];
$canEdit = can('payments.edit');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-credit-card"></i> <?= e(__('payments')) ?></h1>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($toVerify): ?><a class="btn btn-warning btn-sm" href="<?= e(url('admin/payments?status=to_verify')) ?>"><i class="bi bi-phone"></i> <?= e(__('pay_to_verify_n', ['n' => num($toVerify)])) ?></a><?php endif; ?>
        <?php if (can('payments.export')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/payments/export') . ($qs !== '' ? '?' . $qs : '')) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a><?php endif; ?>
    </div>
</div>

<div class="row g-2 mb-3">
    <?php foreach ([['bi-check-circle text-success', __('pay_received'), $totals['received']], ['bi-hourglass-split text-warning', __('pay_waiting'), $totals['waiting']],
                    ['bi-arrow-counterclockwise text-info', __('refunded'), $totals['refunded']], ['bi-percent text-muted', __('pay_fees'), $totals['fees']]] as [$ic, $lbl, $amt]): ?>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted"><i class="bi <?= $ic ?>"></i> <?= e($lbl) ?></div><div class="fs-5 fw-bold"><?= e(money($amt)) ?></div>
        </div></div></div>
    <?php endforeach; ?>
</div>
<?php if ($byMethod): ?>
    <div class="d-flex flex-wrap gap-2 mb-3 small">
        <?php foreach ($byMethod as $m): ?><span class="badge text-bg-light border fw-normal p-2"><?= e(__('pm_' . $m['method'])) ?>: <b><?= e(money($m['received'])) ?></b> (<?= e(num($m['n'])) ?>)</span><?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="card border-0 shadow-sm mb-3" method="get" action="<?= e(url('admin/payments')) ?>">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-3"><input class="form-control form-control-sm" type="search" name="q" value="<?= e($f['q']) ?>" maxlength="100" placeholder="<?= e(__('pay_search_ph')) ?>"></div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="status" aria-label="<?= e(__('status')) ?>">
                <option value=""><?= e(__('status')) ?>: <?= e(__('all')) ?></option>
                <option value="to_verify" <?= $f['status'] === 'to_verify' ? 'selected' : '' ?>><?= e(__('pay_to_verify')) ?></option>
                <?php foreach (AdminPaymentController::STATUSES as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(__('pst_' . $s)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="method" aria-label="<?= e(__('payment_method')) ?>">
                <option value=""><?= e(__('payment_method')) ?>: <?= e(__('all')) ?></option>
                <?php foreach (AdminPaymentController::METHODS as $m): ?><option value="<?= $m ?>" <?= $f['method'] === $m ? 'selected' : '' ?>><?= e(__('pm_' . $m)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2"><input class="form-control form-control-sm" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="<?= e(__('from')) ?>"></div>
        <div class="col-6 col-md-2"><input class="form-control form-control-sm" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="<?= e(__('to')) ?>"></div>
        <div class="col-12 col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill" title="<?= e(__('filter')) ?>"><i class="bi bi-funnel"></i></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/payments')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th>#</th><th><?= e(__('date')) ?></th><th><?= e(__('order')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('payment_method')) ?></th><th><?= e(__('trx_id')) ?></th><th class="text-end"><?= e(__('amount')) ?></th><th><?= e(__('status')) ?></th><th style="min-width:180px"></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $waiting = $r['status'] === 'pending' && $r['gateway'] !== null && $r['order_status'] !== 'cancelled'; ?>
                <tr class="<?= $waiting ? 'table-warning' : '' ?>">
                    <td><?= e(num($r['id'])) ?></td>
                    <td class="text-nowrap"><?= e(dt($r['created_at'], 'd M, h:i A')) ?></td>
                    <td><?php if (can('orders.view')): ?><a href="<?= e(url('admin/orders/' . (int) $r['order_id'])) ?>">#<?= e($r['order_no']) ?></a><?php else: ?>#<?= e($r['order_no']) ?><?php endif; ?>
                        <div><?= OrderView::badge($r['order_status']) ?></div></td>
                    <td><?= e($r['customer_name']) ?><div class="text-muted"><?= e((string) $r['customer_phone']) ?></div></td>
                    <td><?= e(__('pm_' . $r['method'])) ?><?php if ($r['payer_account']): ?><div class="text-muted"><?= e(__('pay_from')) ?> <?= e($r['payer_account']) ?></div><?php endif; ?></td>
                    <td class="font-monospace"><?= e((string) ($r['transaction_id'] ?: '—')) ?></td>
                    <td class="text-end fw-semibold"><?= e(money($r['amount'])) ?><?php if ((float) $r['fee'] > 0): ?><div class="text-muted fw-normal"><?= e(__('pay_fee')) ?> <?= e(money($r['fee'])) ?></div><?php endif; ?></td>
                    <td><span class="badge text-bg-<?= $stColor[$r['status']] ?? 'light' ?>"><?= e(__('pst_' . $r['status'])) ?></span>
                        <?php if ($r['receiver']): ?><div class="text-muted"><?= e($r['receiver']) ?></div><?php endif; ?></td>
                    <td>
                        <?php if ($waiting && $canEdit): ?>
                            <form method="post" action="<?= e(url('admin/payments/' . (int) $r['id'] . '/verify')) ?>" class="d-flex flex-wrap gap-1">
                                <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                                <input class="form-control form-control-sm" name="note" maxlength="255" placeholder="<?= e(__('note')) ?>" aria-label="<?= e(__('note')) ?>">
                                <button class="btn btn-success btn-sm" name="decision" value="ok"><i class="bi bi-check2"></i> <?= e(__('pay_verify')) ?></button>
                                <button class="btn btn-outline-danger btn-sm" name="decision" value="bad" data-confirm-click="<?= e(__('pay_reject_confirm')) ?>"><?= e(__('pay_reject')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
<p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> <?= e(__('pay_page_help')) ?></p>
