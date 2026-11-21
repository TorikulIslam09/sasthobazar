<?php
/** Batch list. Vars: $batches, $paginator, $filters, $vendors, $suppliers */
$statusBadge = ['active' => 'bg-success-subtle text-success', 'depleted' => 'bg-secondary-subtle text-secondary', 'expired' => 'bg-danger-subtle text-danger', 'recalled' => 'bg-dark text-white'];
$exportQs = http_build_query(array_filter($filters));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-layers"></i> <?= e(__('inv_batches')) ?></h1>
    <?php if (can('inventory.export')): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/inventory/batches/export') . ($exportQs ? '?' . $exportQs : '')) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
    <?php endif; ?>
</div>
<?= View::partial('admin/inventory/_nav', ['active' => 'batches']) ?>

<form class="card border-0 shadow-sm mb-3" method="get">
    <?php if ($filters['product_id']): ?><input type="hidden" name="product_id" value="<?= (int) $filters['product_id'] ?>"><?php endif; ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-3"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('inv_search_batch')) ?>"></div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="status" aria-label="<?= e(__('status')) ?>">
                <option value=""><?= e(__('status')) ?>: <?= e(__('all')) ?></option>
                <?php foreach (['in_stock', 'active', 'depleted', 'expired', 'recalled'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(__('batch_' . $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="expiry" aria-label="<?= e(__('inv_expiry_date')) ?>">
                <option value=""><?= e(__('inv_expiry_date')) ?>: <?= e(__('all')) ?></option>
                <option value="near" <?= $filters['expiry'] === 'near' ? 'selected' : '' ?>><?= e(__('inv_near_expiry')) ?></option>
                <option value="expired" <?= $filters['expiry'] === 'expired' ? 'selected' : '' ?>><?= e(__('inv_expired')) ?></option>
                <option value="none" <?= $filters['expiry'] === 'none' ? 'selected' : '' ?>><?= e(__('inv_no_expiry')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="supplier_id" aria-label="<?= e(__('supplier')) ?>">
                <option value=""><?= e(__('supplier')) ?>: <?= e(__('all')) ?></option>
                <?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $filters['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-1">
            <select class="form-select form-select-sm" name="sort" aria-label="<?= e(__('sort')) ?>">
                <option value="expiry"><?= e(__('inv_sort_expiry')) ?></option>
                <option value="qty" <?= $filters['sort'] === 'qty' ? 'selected' : '' ?>><?= e(__('inv_sort_qty')) ?></option>
                <option value="received" <?= $filters['sort'] === 'received' ? 'selected' : '' ?>><?= e(__('inv_sort_received')) ?></option>
            </select>
        </div>
        <div class="col-12 col-lg-2 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/inventory/batches')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('product')) ?></th>
                    <th><?= e(__('inv_batch_no')) ?></th>
                    <th><?= e(__('inv_expiry_date')) ?></th>
                    <th class="text-end"><?= e(__('inv_available')) ?></th>
                    <th class="text-end d-none d-md-table-cell"><?= e(__('inv_cost_value')) ?></th>
                    <th class="d-none d-lg-table-cell"><?= e(__('supplier')) ?></th>
                    <th><?= e(__('status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$batches): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($batches as $b):
                $dl = $b['days_left'] !== null ? (int) $b['days_left'] : null;
                $expCls = $dl === null ? 'text-muted' : ($dl < 0 ? 'text-danger fw-semibold' : ($dl <= 30 ? 'text-warning-emphasis fw-semibold' : '')); ?>
                <tr>
                    <td>
                        <a class="fw-semibold text-reset" href="<?= e(url('admin/inventory/batches/' . $b['id'])) ?>"><?= e(Product::label($b)) ?></a>
                        <div class="text-muted"><?= e($b['variant_name'] ?: ($b['sku'] ?: '')) ?><?= $b['vendor_name'] ? ' · ' . e($b['vendor_name']) : '' ?></div>
                    </td>
                    <td class="font-monospace"><a href="<?= e(url('admin/inventory/batches/' . $b['id'])) ?>"><?= e($b['batch_no']) ?></a></td>
                    <td class="<?= $expCls ?> text-nowrap">
                        <?= $b['expiry_date'] ? e(dt($b['expiry_date'], 'd M Y')) : '—' ?>
                        <?php if ($dl !== null && (int) $b['quantity_available'] > 0): ?><div class="fw-normal small"><?= $dl < 0 ? e(__('inv_expired')) : e(__('inv_days_left', ['n' => num($dl)])) ?></div><?php endif; ?>
                    </td>
                    <td class="text-end"><strong><?= e(num($b['quantity_available'])) ?></strong> <span class="text-muted">/ <?= e(num($b['quantity_received'])) ?></span></td>
                    <td class="text-end d-none d-md-table-cell"><?= e(money((int) $b['quantity_available'] * (float) $b['unit_cost_eff'])) ?></td>
                    <td class="d-none d-lg-table-cell"><?= e($b['supplier'] ?? $b['supplier_name'] ?? '—') ?></td>
                    <td><span class="badge <?= e($statusBadge[$b['status']] ?? 'bg-light') ?>"><?= e(__('batch_' . $b['status'])) ?></span></td>
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
