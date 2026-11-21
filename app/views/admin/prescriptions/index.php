<?php
/** Prescription queue. Vars: $status, $type, $q, $rows, $pager, $counts, $newRequests */
$link = static fn(array $p) => url('admin/prescriptions?' . http_build_query(array_filter($p + ['status' => $status, 'type' => $type, 'q' => $q], static fn($v) => $v !== '')));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-file-medical"></i> <?= e(__('prescriptions')) ?></h1>
    <?php if ($newRequests): ?>
        <a class="btn btn-sm btn-warning" href="<?= e(url('admin/prescriptions?type=order_request&status=all')) ?>"><i class="bi bi-telephone"></i> <?= e(__('rx_new_requests', ['n' => num($newRequests)])) ?></a>
    <?php endif; ?>
</div>
<ul class="nav nav-pills mb-2 status-tabs">
    <?php foreach (['pending', 'approved', 'rejected', 'all'] as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="<?= e($link(['status' => $s])) ?>"><?= e($s === 'all' ? __('all') : __('rx_status_' . $s)) ?><?php if ($s !== 'all'): ?> <span class="badge bg-light text-dark"><?= e(num($counts[$s] ?? 0)) ?></span><?php endif; ?></a></li>
    <?php endforeach; ?>
</ul>
<form class="d-flex flex-wrap gap-2 mb-3" method="get" action="<?= e(url('admin/prescriptions')) ?>">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <select class="form-select form-select-sm w-auto" name="type" onchange="this.form.submit()">
        <option value=""><?= e(__('rx_all_types')) ?></option>
        <option value="order_request" <?= $type === 'order_request' ? 'selected' : '' ?>><?= e(__('rx_type_order_request')) ?></option>
        <option value="checkout" <?= $type === 'checkout' ? 'selected' : '' ?>><?= e(__('rx_type_checkout')) ?></option>
    </select>
    <input class="form-control form-control-sm w-auto" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('rx_search_ph')) ?>" maxlength="50">
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th>#</th><th><?= e(__('type')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('order')) ?></th><th><?= e(__('rx_file')) ?></th><th><?= e(__('created_at')) ?></th><th><?= e(__('status')) ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $isReq = $r['request_type'] === 'order_request';
                $orderNo = $r['order_no'] ?: $r['order_no_linked']; ?>
                <tr>
                    <td><?= e($r['group_code'] ?: num($r['id'])) ?></td>
                    <td><span class="badge <?= $isReq ? 'text-bg-warning' : 'text-bg-light' ?>"><?= e(__($isReq ? 'rx_type_order_request' : 'rx_type_checkout')) ?></span>
                        <?php if ($isReq && $r['request_status']): ?><div class="mt-1"><span class="badge text-bg-info"><?= e(__('rxr_' . $r['request_status'])) ?></span></div><?php endif; ?></td>
                    <td>
                        <?php if ($r['user_id']): ?><?= e($r['customer']) ?><div class="text-muted"><?= e($r['guest_phone'] ?: $r['phone']) ?></div>
                        <?php else: ?><?= e($r['guest_name'] ?: '—') ?> <span class="badge text-bg-secondary"><?= e(__('guest')) ?></span><div class="text-muted"><?= e($r['guest_phone']) ?></div><?php endif; ?>
                    </td>
                    <td><?php if ($r['order_id']): ?><a href="<?= e(url('admin/orders/' . (int) $r['order_id'])) ?>"><?= e($orderNo) ?></a> <?= OrderView::badge($r['order_status']) ?><?php elseif ($orderNo): ?><?= e($orderNo) ?><?php else: ?>—<?php endif; ?></td>
                    <td><a href="<?= e(url('admin/prescriptions/' . (int) $r['id'] . '/file')) ?>" target="_blank" rel="noopener"><i class="bi <?= $r['mime_type'] === 'application/pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' ?>"></i> <?= e(mb_strimwidth((string) $r['original_name'], 0, 26, '…')) ?></a>
                        <?php if ((int) $r['files'] > 1): ?><span class="badge text-bg-light">+<?= e(num((int) $r['files'] - 1)) ?></span><?php endif; ?>
                        <?php if ($r['patient_name']): ?><div class="text-muted"><?= e($r['patient_name']) ?></div><?php endif; ?></td>
                    <td><?= e(dt($r['created_at'], 'd M, h:i A')) ?></td>
                    <td><span class="badge text-bg-<?= $r['status'] === 'approved' ? 'success' : ($r['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= e(__('rx_status_' . $r['status'])) ?></span>
                        <?php if ($r['reviewer']): ?><div class="text-muted"><?= e($r['reviewer']) ?></div><?php endif; ?></td>
                    <td class="text-end">
                        <?php if ($isReq): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/prescriptions/' . (int) $r['id'])) ?>"><?= e(__('open')) ?></a>
                        <?php elseif ($r['order_id']): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/orders/' . (int) $r['order_id'])) ?>"><?= e($r['status'] === 'pending' ? __('rx_review') : __('view')) ?></a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
