<?php
/** Product list. Vars: $products, $paginator, $filters, $categories, $brands, $vendors, $statusCounts */
$statusBadge = [
    'draft' => 'bg-secondary-subtle text-secondary', 'pending' => 'bg-warning-subtle text-warning-emphasis',
    'active' => 'bg-success-subtle text-success', 'inactive' => 'bg-dark-subtle text-dark', 'rejected' => 'bg-danger-subtle text-danger',
];
$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over, ['page' => null]), static fn($v) => $v !== '' && $v !== 0 && $v !== null);
    unset($q['per_page']);
    if (($filters['per_page'] ?? 20) !== 20) $q['per_page'] = $filters['per_page'];
    return $q ? '?' . http_build_query($q) : '';
};
$exportQs = http_build_query(array_filter(array_diff_key($filters, ['per_page' => 1, 'sort' => 1])));
$activeFilters = count(array_filter(array_diff_key($filters, ['q' => 1, 'sort' => 1, 'per_page' => 1, 'status' => 1])));
$allCount = array_sum($statusCounts);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-capsule"></i> <?= e(__('products')) ?></h1>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('products.import')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/products/import')) ?>"><i class="bi bi-upload"></i> <?= e(__('import_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('products.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/products/export') . ($exportQs ? '?' . $exportQs : '')) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('products.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/products/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_product')) ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- Status tabs -->
<ul class="nav nav-pills nav-pills-sm flex-nowrap overflow-auto mb-2 status-tabs">
    <li class="nav-item"><a class="nav-link <?= $filters['status'] === '' ? 'active' : '' ?>" href="<?= e(url('admin/products') . $qs(['status' => ''])) ?>"><?= e(__('all')) ?> <span class="badge bg-light text-dark"><?= e(num($allCount)) ?></span></a></li>
    <?php foreach (Product::STATUSES as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $filters['status'] === $s ? 'active' : '' ?>" href="<?= e(url('admin/products') . $qs(['status' => $s])) ?>"><?= e(__('status_' . $s)) ?> <span class="badge bg-light text-dark"><?= e(num($statusCounts[$s] ?? 0)) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form class="card border-0 shadow-sm mb-3" method="get">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('search_product')) ?>" autofocus>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <select class="form-select form-select-sm" name="category_id">
                <option value=""><?= e(__('category')) ?>: <?= e(__('all')) ?></option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;', (int) $c['depth']) . e(Category::label($c)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select class="form-select form-select-sm" name="brand_id">
                <option value=""><?= e(__('brand')) ?>: <?= e(__('all')) ?></option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $filters['brand_id'] === (int) $b['id'] ? 'selected' : '' ?>><?= e(Brand::label($b)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 col-lg-3 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#moreFilters" aria-expanded="<?= $activeFilters > 2 ? 'true' : 'false' ?>">
                <i class="bi bi-sliders"></i><?php if ($activeFilters): ?> <span class="badge bg-brand"><?= e(num($activeFilters)) ?></span><?php endif; ?>
            </button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/products')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>

        <div class="collapse <?= ($filters['vendor'] || $filters['type'] || $filters['rx'] !== '' || $filters['stock'] || $filters['sort'] !== 'newest' || $filters['per_page'] !== 20) ? 'show' : '' ?>" id="moreFilters">
            <div class="row g-2 pt-1">
                <div class="col-6 col-md-3 col-lg-2">
                    <select class="form-select form-select-sm" name="vendor">
                        <option value=""><?= e(__('seller')) ?>: <?= e(__('all')) ?></option>
                        <option value="inhouse" <?= $filters['vendor'] === 'inhouse' ? 'selected' : '' ?>><?= e(__('in_house')) ?></option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int) $v['id'] ?>" <?= $filters['vendor'] === (string) $v['id'] ? 'selected' : '' ?>><?= e(Vendor::label($v)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <select class="form-select form-select-sm" name="type">
                        <option value=""><?= e(__('type')) ?>: <?= e(__('all')) ?></option>
                        <?php foreach (Product::TYPES as $t): ?>
                            <option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(__('type_' . $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <select class="form-select form-select-sm" name="rx">
                        <option value="">Rx: <?= e(__('all')) ?></option>
                        <option value="1" <?= $filters['rx'] === '1' ? 'selected' : '' ?>><?= e(__('rx_required')) ?></option>
                        <option value="0" <?= $filters['rx'] === '0' ? 'selected' : '' ?>><?= e(__('rx_not_required')) ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <select class="form-select form-select-sm" name="stock">
                        <option value=""><?= e(__('stock')) ?>: <?= e(__('all')) ?></option>
                        <option value="low" <?= $filters['stock'] === 'low' ? 'selected' : '' ?>><?= e(__('low_stock')) ?></option>
                        <option value="out" <?= $filters['stock'] === 'out' ? 'selected' : '' ?>><?= e(__('out_of_stock')) ?></option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <select class="form-select form-select-sm" name="sort">
                        <?php foreach (array_keys(Product::SORTS) as $s): ?>
                            <option value="<?= e($s) ?>" <?= $filters['sort'] === $s ? 'selected' : '' ?>><?= e(__('sort_' . $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <select class="form-select form-select-sm" name="per_page">
                        <?php foreach ([20, 50, 100] as $n): ?>
                            <option value="<?= $n ?>" <?= $filters['per_page'] === $n ? 'selected' : '' ?>><?= e(__('per_page', ['n' => num($n)])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width:260px"><?= e(__('product')) ?></th>
                    <th class="d-none d-lg-table-cell"><?= e(__('category')) ?> / <?= e(__('brand')) ?></th>
                    <th class="text-end"><?= e(__('price')) ?></th>
                    <th class="text-center d-none d-md-table-cell"><?= e(__('stock')) ?></th>
                    <th><?= e(__('status')) ?></th>
                    <th class="text-end"><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-1"></i><?= e(__('no_records')) ?>
                    <?php if (can('products.create') && !$filters['q'] && !$activeFilters): ?>
                        <div class="mt-2"><a class="btn btn-sm btn-brand" href="<?= e(url('admin/products/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_product')) ?></a></div>
                    <?php endif; ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($products as $p):
                $onSale = (float) $p['price'] < (float) $p['mrp'];
                $low = (int) $p['track_stock'] && (int) $p['stock_qty'] <= (int) $p['low_stock_threshold']; ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($p['image']): ?>
                                <img src="<?= e(url($p['image'])) ?>" alt="" class="rounded border thumb-md bg-white" loading="lazy">
                            <?php else: ?>
                                <span class="thumb-md d-inline-flex align-items-center justify-content-center rounded bg-light text-muted"><i class="bi bi-image"></i></span>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate-2">
                                    <?php if (can('products.edit')): ?>
                                        <a class="text-reset text-decoration-none" href="<?= e(url('admin/products/' . $p['id'] . '/edit')) ?>"><?= e(Product::label($p)) ?></a>
                                    <?php else: ?><?= e(Product::label($p)) ?><?php endif; ?>
                                    <?php if ((int) $p['requires_prescription']): ?><span class="badge bg-danger-subtle text-danger" title="<?= e(__('rx_required')) ?>">Rx</span><?php endif; ?>
                                    <?php if ((int) $p['is_featured']): ?><i class="bi bi-star-fill text-warning small" title="<?= e(__('featured')) ?>"></i><?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    <?= e(trim(($p['generic_name'] ?? '') . ' ' . ($p['strength'] ?? '')) ?: __('type_' . $p['product_type'])) ?>
                                    <?php if ($p['sku']): ?> · <span class="font-monospace"><?= e($p['sku']) ?></span><?php endif; ?>
                                    <?php if ((int) $p['variants_count']): ?> · <span class="badge bg-light text-dark border"><?= e(__('n_variants', ['n' => num($p['variants_count'])])) ?></span><?php endif; ?>
                                </div>
                                <div class="small text-muted"><i class="bi bi-shop"></i> <?= e($p['vendor_id'] ? tr($p['vendor_name_bn'], $p['vendor_name']) : __('in_house')) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell small">
                        <div><?= e($p['category_name_en'] ? tr($p['category_name_bn'], $p['category_name_en']) : '—') ?></div>
                        <div class="text-muted"><?= e($p['brand_name'] ? tr($p['brand_name_bn'], $p['brand_name']) : '—') ?></div>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="fw-semibold"><?= e(money($p['price'])) ?></div>
                        <?php if ($onSale): ?><div class="small text-muted text-decoration-line-through"><?= e(money($p['mrp'])) ?></div><?php endif; ?>
                    </td>
                    <td class="text-center d-none d-md-table-cell">
                        <?php if (!(int) $p['track_stock']): ?>
                            <span class="text-muted small">∞</span>
                        <?php else: ?>
                            <span class="badge <?= (int) $p['stock_qty'] <= 0 ? 'bg-danger' : ($low ? 'bg-warning text-dark' : 'bg-light text-dark border') ?>"><?= e(num($p['stock_qty'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= e($statusBadge[$p['status']] ?? 'bg-light') ?>"><?= e(__('status_' . $p['status'])) ?></span>
                        <div class="small text-muted d-none d-xl-block"><?= e(dt($p['updated_at'] ?? $p['created_at'], 'd M Y')) ?></div>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if (can('products.edit')): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/products/' . $p['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (can('products.delete')): ?>
                            <?= View::partial('partials/delete_button', [
                                'action'  => url('admin/products/' . $p['id'] . '/delete'),
                                'message' => __('confirm_delete_product', ['name' => $p['name_en']]),
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
