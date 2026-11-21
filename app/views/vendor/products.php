<?php /** Vendor's own products. Vars: $rows, $pager, $q, $stock */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="bi bi-capsule"></i> <?= e(__('my_products')) ?></h1>
</div>
<p class="small text-muted"><?= e(__('v_products_help')) ?></p>
<form class="mb-3 d-flex flex-wrap gap-2" method="get">
    <input class="form-control form-control-sm" style="max-width: 320px" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('search_product')) ?>" aria-label="<?= e(__('search')) ?>">
    <select class="form-select form-select-sm w-auto" name="stock" aria-label="<?= e(__('stock')) ?>">
        <option value=""><?= e(__('all')) ?></option>
        <option value="low" <?= $stock === 'low' ? 'selected' : '' ?>><?= e(__('dash_low_stock')) ?></option>
    </select>
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('product')) ?></th><th><?= e(__('category')) ?></th><th class="text-end"><?= e(__('price')) ?></th><th class="text-end"><?= e(__('stock')) ?></th><th class="text-end"><?= e(__('commission')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $p): [$rate, $scope] = Commission::rateFor($p); ?>
                <tr>
                    <td class="d-flex align-items-center gap-2">
                        <?php if ($p['image']): ?><img src="<?= e(url($p['image'])) ?>" alt="" class="thumb-sm rounded"><?php else: ?><span class="thumb-sm rounded bg-light d-inline-flex align-items-center justify-content-center"><i class="bi bi-capsule text-muted"></i></span><?php endif; ?>
                        <div class="min-w-0"><?php if ($p['status'] === 'active'): ?><a href="<?= e(url('product/' . rawurlencode($p['slug']))) ?>" target="_blank" rel="noopener"><?= e(tr($p['name_bn'], $p['name_en'])) ?></a><?php else: ?><?= e(tr($p['name_bn'], $p['name_en'])) ?><?php endif; ?>
                            <div class="text-muted"><?= e(trim(($p['generic_name'] ?? '') . ' ' . ($p['strength'] ?? ''))) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?></div></div>
                    </td>
                    <td><?= e(tr($p['cat_bn'], $p['cat_en'])) ?></td>
                    <td class="text-end"><?= e(money($p['price'])) ?><?php if ((float) $p['mrp'] > (float) $p['price']): ?><div class="text-muted"><s><?= e(money($p['mrp'])) ?></s></div><?php endif; ?></td>
                    <td class="text-end"><?php if ((int) $p['track_stock']): ?><span class="badge text-bg-<?= (int) $p['stock_qty'] <= 0 ? 'danger' : ((int) $p['stock_qty'] <= (int) $p['low_stock_threshold'] ? 'warning' : 'light border') ?>"><?= e(num((int) $p['stock_qty'])) ?></span><?php else: ?>—<?php endif; ?>
                        <div class="text-muted"><?= e(__('sold_n', ['n' => num((int) $p['sold_count'])])) ?></div></td>
                    <td class="text-end"><?= e(num(number_format($rate, 2))) ?>%<div class="text-muted"><?= e(__('cscope_' . $scope)) ?></div></td>
                    <td><span class="badge text-bg-<?= $p['status'] === 'active' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e(__('status_' . $p['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
