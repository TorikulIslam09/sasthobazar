<?php
/** Purchase order list. Vars: $orders, $paginator, $filters, $suppliers, $statusCounts */
$badge = PurchaseOrderView::BADGES;
$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over, ['page' => null]), static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
};
$open = ($statusCounts['ordered'] ?? 0) + ($statusCounts['partial'] ?? 0);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-clipboard-check"></i> <?= e(__('purchase_orders')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (can('purchase_orders.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/purchase-orders/export') . $qs([])) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('purchase_orders.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/purchase-orders/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('new_po')) ?></a>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-pills flex-nowrap overflow-auto mb-2 status-tabs">
    <li class="nav-item"><a class="nav-link <?= $filters['status'] === '' ? 'active' : '' ?>" href="<?= e(url('admin/purchase-orders') . $qs(['status' => ''])) ?>"><?= e(__('all')) ?> <span class="badge bg-light text-dark"><?= e(num(array_sum($statusCounts))) ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $filters['status'] === 'open' ? 'active' : '' ?>" href="<?= e(url('admin/purchase-orders') . $qs(['status' => 'open'])) ?>"><?= e(__('po_open')) ?> <span class="badge bg-light text-dark"><?= e(num($open)) ?></span></a></li>
    <?php foreach (PurchaseOrder::STATUSES as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $filters['status'] === $s ? 'active' : '' ?>" href="<?= e(url('admin/purchase-orders') . $qs(['status' => $s])) ?>"><?= e(__('po_status_' . $s)) ?> <span class="badge bg-light text-dark"><?= e(num($statusCounts[$s] ?? 0)) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form class="card border-0 shadow-sm mb-3" method="get">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-4"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('po_search')) ?>"></div>
        <div class="col-12 col-md-4 col-lg-3">
            <select class="form-select form-select-sm" name="supplier_id" aria-label="<?= e(__('supplier')) ?>">
                <option value=""><?= e(__('supplier')) ?>: <?= e(__('all')) ?></option>
                <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $filters['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 col-lg-2"><input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>" title="<?= e(__('from_date')) ?>" aria-label="<?= e(__('from_date')) ?>"></div>
        <div class="col-6 col-md-2 col-lg-2"><input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>" title="<?= e(__('to_date')) ?>" aria-label="<?= e(__('to_date')) ?>"></div>
        <div class="col-12 col-md-4 col-lg-1 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/purchase-orders')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>PO</th>
                    <th><?= e(__('supplier')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('po_order_date')) ?></th>
                    <th><?= e(__('po_received')) ?></th>
                    <th class="text-end"><?= e(__('total')) ?></th>
                    <th><?= e(__('status')) ?></th>
                    <th class="text-end"><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$orders): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($orders as $o):
                $pct = (int) $o['qty_ordered'] > 0 ? (int) round($o['qty_received'] / $o['qty_ordered'] * 100) : 0;
                $late = in_array($o['status'], ['ordered', 'partial'], true) && $o['expected_date'] && $o['expected_date'] < date('Y-m-d'); ?>
                <tr>
                    <td><a class="fw-semibold font-monospace" href="<?= e(url('admin/purchase-orders/' . $o['id'])) ?>"><?= e($o['po_no']) ?></a>
                        <div class="text-muted"><?= e(__('po_n_items', ['n' => num($o['items_count'])])) ?><?= $o['vendor_name'] ? ' · ' . e($o['vendor_name']) : '' ?></div></td>
                    <td><?= e($o['supplier_name']) ?></td>
                    <td class="d-none d-md-table-cell text-nowrap"><?= e(dt($o['order_date'], 'd M Y')) ?>
                        <?php if ($o['expected_date']): ?><div class="<?= $late ? 'text-danger' : 'text-muted' ?>"><?= e(__('po_expected')) ?> <?= e(dt($o['expected_date'], 'd M')) ?><?= $late ? ' · ' . e(__('po_late')) : '' ?></div><?php endif; ?></td>
                    <td style="min-width:120px">
                        <div class="d-flex justify-content-between"><span><?= e(num($o['qty_received'])) ?> / <?= e(num($o['qty_ordered'])) ?></span><span class="text-muted"><?= e(num($pct)) ?>%</span></div>
                        <div class="progress" style="height:4px"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
                    </td>
                    <td class="text-end text-nowrap"><?= e(money($o['total'])) ?></td>
                    <td><span class="badge <?= e($badge[$o['status']]) ?>"><?= e(__('po_status_' . $o['status'])) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if (in_array($o['status'], ['ordered', 'partial'], true) && can('purchase_orders.receive')): ?>
                            <a class="btn btn-sm btn-success" href="<?= e(url('admin/purchase-orders/' . $o['id'] . '/receive')) ?>" title="<?= e(__('grn_receive')) ?>"><i class="bi bi-box-arrow-in-down"></i></a>
                        <?php endif; ?>
                        <?php if ($o['status'] === 'draft' && can('purchase_orders.edit')): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/purchase-orders/' . $o['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/purchase-orders/' . $o['id'])) ?>" title="<?= e(__('view')) ?>"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted"><?= e(__('showing', ['from' => num($paginator->from()), 'to' => num($paginator->to()), 'total' => num($paginator->total)])) ?></span>
        <?= $paginator->links() ?>
    </div>
</div>
