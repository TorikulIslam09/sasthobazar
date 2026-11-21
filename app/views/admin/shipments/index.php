<?php
/** Shipments list. Vars: $rows, $pager, $filters, $counts, $couriers */
$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over, ['page' => null]), static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
};
$tabs = ['ready', 'booked', 'open', 'failed', 'returning', 'delivered', 'returned'];
$tabCount = static fn(string $t) => $t === 'open'
    ? array_sum(array_intersect_key($counts, array_flip(['booked', 'picked_up', 'in_transit', 'out_for_delivery', 'on_hold', 'failed', 'returning'])))
    : (int) ($counts[$t] ?? 0);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-send"></i> <?= e(__('shipments')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (can('shipments.edit')): ?>
            <form method="post" action="<?= e(url('admin/shipments/sync')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> <?= e(__('sync_all')) ?></button></form>
        <?php endif; ?>
        <?php if (can('shipments.export')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/shipments/export') . $qs([])) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a><?php endif; ?>
    </div>
</div>
<?= View::partial('admin/shipments/_nav', ['active' => 'shipments']) ?>

<ul class="nav nav-pills flex-nowrap overflow-auto mb-2 status-tabs">
    <li class="nav-item"><a class="nav-link <?= $filters['status'] === '' ? 'active' : '' ?>" href="<?= e(url('admin/shipments') . $qs(['status' => ''])) ?>"><?= e(__('all')) ?> <span class="badge bg-light text-dark"><?= e(num(array_sum(array_diff_key($counts, ['ready' => 0])))) ?></span></a></li>
    <?php foreach ($tabs as $t): ?>
        <li class="nav-item"><a class="nav-link <?= $filters['status'] === $t ? 'active' : '' ?>" href="<?= e(url('admin/shipments') . $qs(['status' => $t])) ?>"><?= e(__('sh_' . $t)) ?> <span class="badge bg-light text-dark"><?= e(num($tabCount($t))) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form class="card border-0 shadow-sm mb-3" method="get">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-3"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('shipment_search')) ?>" aria-label="<?= e(__('search')) ?>"></div>
        <div class="col-6 col-md-3 col-lg-2"><select class="form-select form-select-sm" name="courier" aria-label="<?= e(__('courier')) ?>"><option value=""><?= e(__('courier')) ?>: <?= e(__('all')) ?></option><?php foreach ($couriers as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filters['courier'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3 col-lg-2"><select class="form-select form-select-sm" name="cod" aria-label="COD"><option value="">COD: <?= e(__('all')) ?></option><?php foreach (array_keys(Shipment::COD_BADGES) as $s): ?><option value="<?= $s ?>" <?= $filters['cod'] === $s ? 'selected' : '' ?>><?= e(__('cod_' . $s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3 col-lg-2"><input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="<?= e(__('from_date')) ?>"></div>
        <div class="col-6 col-md-3 col-lg-2"><input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="<?= e(__('to_date')) ?>"></div>
        <div class="col-12 col-lg-1 d-flex gap-1"><button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i></button><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/shipments')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>

<form method="post" action="<?= e(url('admin/shipments/bulk')) ?>" id="bulkForm" data-confirm="<?= e(__('bulk_confirm')) ?>">
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted"><span data-bulk-count>0</span> <?= e(__('selected')) ?></span>
            <select class="form-select form-select-sm w-auto" name="bulk_action" aria-label="<?= e(__('bulk_action')) ?>">
                <option value="labels"><?= e(__('print_labels')) ?></option>
                <option value="manifest"><?= e(__('print_manifest')) ?></option>
                <?php if (can('shipments.create')): foreach ($couriers as $c): if (!(int) $c['is_active'] || $c['code'] === 'self') { continue; } ?>
                    <option value="book:<?= e($c['code']) ?>"><?= e(__('book_with', ['courier' => $c['name']])) ?></option>
                <?php endforeach; endif; ?>
            </select>
            <button class="btn btn-sm btn-outline-primary" data-bulk-submit disabled><?= e(__('apply')) ?></button>
            <span class="ms-auto small text-muted"><?= e(__('showing')) ?> <?= e(num($pager->from())) ?>–<?= e(num($pager->to())) ?> / <?= e(num($pager->total)) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light"><tr>
                    <th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all aria-label="<?= e(__('select_all')) ?>"></th>
                    <th><?= e(__('shipment')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('seller')) ?></th><th><?= e(__('courier')) ?></th><th class="text-end">COD</th><th><?= e(__('status')) ?></th>
                </tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-row-check aria-label="#<?= (int) $r['id'] ?>"></td>
                        <td><a class="fw-semibold" href="<?= e(url('admin/shipments/' . (int) $r['id'])) ?>">#<?= (int) $r['id'] ?></a> · <?= e($r['sub_order_no'] ?? $r['order_no']) ?>
                            <?php if ($r['delivery_option'] !== 'standard'): ?><span class="badge text-bg-<?= $r['delivery_option'] === 'express' ? 'warning' : 'info' ?>"><?= e(__('dopt_' . $r['delivery_option'])) ?></span><?php endif; ?>
                            <div class="text-muted"><?= e(dt($r['created_at'], 'd M, h:i A')) ?> · <?= e(num((int) $r['weight_grams'])) ?> g</div></td>
                        <td><?= e($r['shipping_name']) ?><div class="text-muted"><?= e($r['shipping_phone']) ?> · <?= e(tr($r['district_bn'], $r['district_en'])) ?></div></td>
                        <td><?= e($r['shop_name'] ?? __('app_name')) ?><div class="text-muted"><?= OrderView::badge((string) $r['split_status']) ?></div></td>
                        <td><?= e($r['courier_name'] ?? '—') ?><?php if ($r['tracking_no']): ?><div class="text-muted font-monospace"><?= e($r['tracking_no']) ?><?= (int) $r['is_mock'] ? ' <span class="badge text-bg-warning">mock</span>' : '' ?></div><?php endif; ?></td>
                        <td class="text-end"><?= (float) $r['cod_amount'] > 0 ? e(money($r['cod_amount'])) : '—' ?><div><span class="badge text-bg-<?= Shipment::COD_BADGES[$r['cod_status']] ?> <?= $r['cod_status'] === 'none' ? 'border' : '' ?>"><?= e(__('cod_' . $r['cod_status'])) ?></span></div></td>
                        <td><?= Shipment::badge($r['status']) ?><?= (int) $r['failed_attempts'] ? '<div class="text-danger">' . e(__('attempts_n', ['n' => num((int) $r['failed_attempts'])])) . '</div>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<div class="mt-3"><?= $pager->links() ?></div>
