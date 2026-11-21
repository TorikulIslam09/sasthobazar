<?php /** Brands list. Vars: $brands, $paginator, $filters */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-award"></i> <?= e(__('brands')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (can('brands.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/brands/export') . '?' . http_build_query(array_filter($filters))) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('brands.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/brands/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_brand')) ?></a>
        <?php endif; ?>
    </div>
</div>

<form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('search_brand')) ?>">
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="type">
                <option value=""><?= e(__('type')) ?>: <?= e(__('all')) ?></option>
                <option value="manufacturer" <?= $filters['type'] === 'manufacturer' ? 'selected' : '' ?>><?= e(__('manufacturer')) ?></option>
                <option value="brand" <?= $filters['type'] === 'brand' ? 'selected' : '' ?>><?= e(__('brand_only')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value=""><?= e(__('status')) ?>: <?= e(__('all')) ?></option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>><?= e(__('active')) ?></option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>><?= e(__('inactive')) ?></option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/brands')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('name')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('type')) ?></th>
                    <th class="d-none d-lg-table-cell"><?= e(__('country')) ?></th>
                    <th class="text-center"><?= e(__('products')) ?></th>
                    <th><?= e(__('status')) ?></th>
                    <th class="text-end"><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$brands): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($brands as $b): $n = (int) $b['products_count']; ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($b['logo']): ?>
                                <img src="<?= e(url($b['logo'])) ?>" alt="" class="rounded border thumb-sm bg-white" style="object-fit:contain">
                            <?php else: ?>
                                <span class="thumb-sm d-inline-flex align-items-center justify-content-center rounded bg-light text-muted fw-bold"><?= e(mb_strtoupper(mb_substr($b['name'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= e(Brand::label($b)) ?>
                                    <?php if ((int) $b['is_featured']): ?><i class="bi bi-star-fill text-warning small"></i><?php endif; ?>
                                </div>
                                <div class="small text-muted"><?= e($b['slug']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small"><?= e(__((int) $b['is_manufacturer'] ? 'manufacturer' : 'brand_only')) ?></td>
                    <td class="d-none d-lg-table-cell small"><?= e($b['country'] ?? '—') ?></td>
                    <td class="text-center">
                        <?php if ($n && can('products.view')): ?>
                            <a href="<?= e(url('admin/products?brand_id=' . $b['id'])) ?>"><?= e(num($n)) ?></a>
                        <?php else: ?><?= e(num($n)) ?><?php endif; ?>
                    </td>
                    <td><span class="badge <?= (int) $b['is_active'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= e(__((int) $b['is_active'] ? 'active' : 'inactive')) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('brands.edit')): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/brands/' . $b['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (can('brands.delete')): ?>
                            <?= View::partial('partials/delete_button', [
                                'action'  => url('admin/brands/' . $b['id'] . '/delete'),
                                'message' => __('confirm_delete_named', ['name' => $b['name']]),
                                'blocked' => $n > 0 ? __('brand_has_products', ['name' => $b['name'], 'n' => num($n)]) : '',
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
