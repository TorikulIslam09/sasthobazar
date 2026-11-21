<?php /** Suppliers list. Vars: $suppliers, $paginator, $filters */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-truck-front"></i> <?= e(__('suppliers')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (can('suppliers.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/suppliers/export') . '?' . http_build_query(array_filter($filters))) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('suppliers.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/suppliers/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_supplier')) ?></a>
        <?php endif; ?>
    </div>
</div>

<form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-6"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('search_supplier')) ?>"></div>
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" name="status" aria-label="<?= e(__('status')) ?>">
                <option value=""><?= e(__('status')) ?>: <?= e(__('all')) ?></option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>><?= e(__('active')) ?></option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>><?= e(__('inactive')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/suppliers')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('name')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('phone')) ?></th>
                    <th class="text-center"><?= e(__('purchase_orders')) ?></th>
                    <th class="text-end d-none d-lg-table-cell"><?= e(__('sup_received_value')) ?></th>
                    <th><?= e(__('status')) ?></th>
                    <th class="text-end"><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$suppliers): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($suppliers as $s): $used = (int) $s['po_count'] > 0; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($s['name']) ?></div>
                        <div class="small text-muted"><?= e(trim(($s['contact_person'] ?? '') . ($s['payment_terms'] ? ' · ' . $s['payment_terms'] : ''), ' ·')) ?></div>
                    </td>
                    <td class="d-none d-md-table-cell small"><?= e($s['phone'] ?? '—') ?><div class="text-muted text-break"><?= e($s['email'] ?? '') ?></div></td>
                    <td class="text-center">
                        <a href="<?= e(url('admin/purchase-orders?supplier_id=' . $s['id'])) ?>"><?= e(num($s['po_count'])) ?></a>
                        <?php if ((int) $s['open_po_count']): ?><span class="badge bg-info-subtle text-info-emphasis"><?= e(__('sup_n_open', ['n' => num($s['open_po_count'])])) ?></span><?php endif; ?>
                    </td>
                    <td class="text-end d-none d-lg-table-cell small"><?= e(money($s['received_value'])) ?><div class="text-muted"><?= $s['last_received'] ? e(dt($s['last_received'], 'd M Y')) : '' ?></div></td>
                    <td><span class="badge <?= (int) $s['is_active'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= e(__((int) $s['is_active'] ? 'active' : 'inactive')) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('purchase_orders.create') && (int) $s['is_active']): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/purchase-orders/create?supplier_id=' . $s['id'])) ?>" title="<?= e(__('new_po')) ?>"><i class="bi bi-cart-plus"></i></a>
                        <?php endif; ?>
                        <?php if (can('suppliers.edit')): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/suppliers/' . $s['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (can('suppliers.delete')): ?>
                            <?= View::partial('partials/delete_button', [
                                'action' => url('admin/suppliers/' . $s['id'] . '/delete'),
                                'message' => __('confirm_delete_named', ['name' => $s['name']]),
                                'blocked' => $used ? __('supplier_in_use_short') : '',
                            ]) ?>
                        <?php endif; ?>
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
