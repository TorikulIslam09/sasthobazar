<?php /** Category tree. Vars: $categories (flat tree rows with depth/path), $filtered, $filters */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-diagram-3"></i> <?= e(__('categories')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (can('categories.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/categories/export')) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('categories.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/categories/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_category')) ?></a>
        <?php endif; ?>
    </div>
</div>

<form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-6">
            <input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('search_category')) ?>">
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" name="status">
                <option value=""><?= e(__('status')) ?>: <?= e(__('all')) ?></option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>><?= e(__('active')) ?></option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>><?= e(__('inactive')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/categories')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('name')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('slug')) ?></th>
                    <th class="text-center"><?= e(__('products')) ?></th>
                    <th class="text-center d-none d-sm-table-cell"><?= e(__('sort_order')) ?></th>
                    <th><?= e(__('status')) ?></th>
                    <th class="text-end"><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$categories): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($categories as $c):
                $depth = $filtered ? 0 : (int) $c['depth'];
                $children = (int) $c['children_count'];
                $products = (int) $c['products_count'];
                $blocked = $children > 0 ? __('category_has_children', ['name' => $c['name_en'], 'n' => num($children)])
                         : ($products > 0 ? __('category_has_products', ['name' => $c['name_en'], 'n' => num($products)]) : ''); ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2" style="padding-left: <?= $depth * 1.5 ?>rem">
                            <?php if ($depth > 0): ?><span class="text-muted">└</span><?php endif; ?>
                            <?php if ($c['image']): ?>
                                <img src="<?= e(url($c['image'])) ?>" alt="" class="rounded border thumb-sm">
                            <?php else: ?>
                                <span class="thumb-sm d-inline-flex align-items-center justify-content-center rounded bg-light text-muted"><i class="bi <?= e($c['icon'] ?: 'bi-folder') ?>"></i></span>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= e(Category::label($c)) ?></div>
                                <div class="small text-muted">
                                    <?= $filtered ? e($c['path']) : e(lang() === 'bn' ? $c['name_en'] : (string) $c['name_bn']) ?>
                                    <?php if ((int) $c['requires_prescription']): ?><span class="badge bg-danger-subtle text-danger ms-1">Rx</span><?php endif; ?>
                                    <?php if ((int) $c['is_featured']): ?><span class="badge bg-warning-subtle text-warning-emphasis ms-1"><i class="bi bi-star-fill"></i></span><?php endif; ?>
                                    <?php if ($children): ?><span class="badge bg-light text-muted border ms-1"><?= e(__('n_subcategories', ['n' => num($children)])) ?></span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small text-muted"><?= e($c['slug']) ?></td>
                    <td class="text-center">
                        <?php if ($products && can('products.view')): ?>
                            <a href="<?= e(url('admin/products?category_id=' . $c['id'])) ?>"><?= e(num($products)) ?></a>
                        <?php else: ?><?= e(num($products)) ?><?php endif; ?>
                    </td>
                    <td class="text-center d-none d-sm-table-cell"><?= e(num($c['sort_order'])) ?></td>
                    <td><span class="badge <?= (int) $c['is_active'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= e(__((int) $c['is_active'] ? 'active' : 'inactive')) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('categories.create')): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/categories/create?parent=' . $c['id'])) ?>" title="<?= e(__('add_subcategory')) ?>"><i class="bi bi-node-plus"></i></a>
                        <?php endif; ?>
                        <?php if (can('categories.edit')): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/categories/' . $c['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (can('categories.delete')): ?>
                            <?= View::partial('partials/delete_button', [
                                'action'  => url('admin/categories/' . $c['id'] . '/delete'),
                                'message' => __('confirm_delete_named', ['name' => Category::label($c)]),
                                'blocked' => $blocked,
                            ]) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted"><?= e(__('n_categories', ['n' => num(count($categories))])) ?></div>
</div>
