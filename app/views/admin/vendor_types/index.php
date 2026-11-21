<?php
/** Vendor types list. Vars: $types, $counts (type id => vendors) */
$canAdd = can('vendor_types.create');
$edit = can('vendor_types.edit');
$cats = Category::allById();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= e(url('admin/vendors')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('vendors_label')) ?></a>
        <h1 class="h4 mb-0"><i class="bi bi-tags"></i> <?= e(__('vendor_types')) ?></h1>
    </div>
    <?php if ($canAdd): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/vendor-types/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('vt_add')) ?></a><?php endif; ?>
</div>
<div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('vt_help')) ?></div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('vendor_type')) ?></th><th><?= e(__('vt_docs')) ?></th><th><?= e(__('vt_rules')) ?></th><th><?= e(__('vt_categories')) ?></th><th class="text-end"><?= e(__('commission')) ?></th><th class="text-end"><?= e(__('vendors_label')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($types as $t): $allowed = VendorType::allowedCategories($t); ?>
                <tr class="<?= (int) $t['is_active'] ? '' : 'text-muted' ?>">
                    <td><i class="bi <?= e($t['icon'] ?: 'bi-shop') ?> me-1"></i> <b><?= e(VendorType::label($t)) ?></b>
                        <?php if (!(int) $t['is_active']): ?><span class="badge text-bg-secondary"><?= e(__('inactive')) ?></span><?php endif; ?>
                        <div class="text-muted"><?= e(VendorType::description($t)) ?></div></td>
                    <td><?php foreach (VendorType::requiredDocs($t) as $d): ?><span class="badge text-bg-danger-subtle text-danger-emphasis border me-1"><?= e(__('doc_' . $d)) ?></span><?php endforeach; ?>
                        <?php foreach (VendorType::optionalDocs($t) as $d): ?><span class="badge text-bg-light border me-1"><?= e(__('doc_' . $d)) ?></span><?php endforeach; ?></td>
                    <td><?php if ((int) $t['needs_drug_license']): ?><div><i class="bi bi-file-earmark-medical"></i> <?= e(__('vt_needs_drug')) ?></div><?php endif; ?>
                        <div><?= (int) $t['can_sell_rx'] ? '<i class="bi bi-check-circle text-success"></i> ' . e(__('vt_can_sell_rx')) : '<i class="bi bi-x-circle text-danger"></i> ' . e(__('vt_no_rx')) ?></div></td>
                    <td><?= $allowed === null ? e(__('all')) : e(implode(', ', array_map(static fn($id) => isset($cats[$id]) ? Category::label($cats[$id]) : '#' . $id, $allowed))) ?></td>
                    <td class="text-end"><?= $t['commission_rate'] !== null ? e(num($t['commission_rate'])) . '%' : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end"><?= e(num($counts[$t['id']] ?? 0)) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($edit): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/vendor-types/' . (int) $t['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="<?= e(url('admin/vendor-types/' . (int) $t['id'] . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" title="<?= e((int) $t['is_active'] ? __('deactivate') : __('activate')) ?>"><i class="bi <?= (int) $t['is_active'] ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
