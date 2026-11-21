<?php
/**
 * Inventory dashboard.
 * Vars: $summary, $outOfStock, $lowStock, $nearExpiry, $recent, $vendor, $vendors, $threshold, $days, $settings
 */
$s = $summary;
$vq = $vendor !== '' ? '&vendor=' . urlencode($vendor) : '';
$margin = $s['sale_value'] > 0 ? ($s['sale_value'] - $s['cost_value']) / $s['sale_value'] * 100 : 0;
$tile = static function (string $icon, string $color, string $label, string $value, string $sub = '', ?string $href = null): string {
    $inner = '<div class="d-flex align-items-center gap-3"><span class="stat-icon bg-' . $color . '-subtle text-' . $color . '"><i class="bi ' . $icon . '"></i></span>'
        . '<div class="min-w-0"><div class="small text-muted">' . e($label) . '</div><div class="fs-5 fw-semibold text-nowrap">' . e($value) . '</div>'
        . ($sub !== '' ? '<div class="small text-muted text-truncate">' . e($sub) . '</div>' : '') . '</div></div>';
    return '<div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100' . ($href ? ' card-link' : '') . '"><div class="card-body">'
        . ($href ? '<a class="stretched-link text-reset text-decoration-none" href="' . e($href) . '">' . $inner . '</a>' : $inner) . '</div></div></div>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-box-seam"></i> <?= e(__('inventory')) ?></h1>
    <div class="d-flex flex-wrap gap-2">
        <form method="get" class="d-flex gap-1">
            <select class="form-select form-select-sm" name="vendor" onchange="this.form.submit()" aria-label="<?= e(__('seller')) ?>">
                <option value=""><?= e(__('seller')) ?>: <?= e(__('all')) ?></option>
                <option value="inhouse" <?= $vendor === 'inhouse' ? 'selected' : '' ?>><?= e(__('in_house')) ?></option>
                <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $vendor === (string) $v['id'] ? 'selected' : '' ?>><?= e(Vendor::label($v)) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if (can('inventory.adjust')): ?>
            <a class="btn btn-sm btn-outline-danger" href="<?= e(url('admin/inventory/stock-out')) ?>"><i class="bi bi-box-arrow-up"></i> <?= e(__('inv_stock_out')) ?></a>
            <a class="btn btn-sm btn-brand" href="<?= e(url('admin/inventory/stock-in')) ?>"><i class="bi bi-box-arrow-in-down"></i> <?= e(__('inv_stock_in')) ?></a>
        <?php endif; ?>
    </div>
</div>
<?= View::partial('admin/inventory/_nav', ['active' => 'dashboard']) ?>

<div class="row g-3 mb-3">
    <?= $tile('bi-boxes', 'primary', __('inv_current_stock'), num(number_format($s['units'])) . ' ' . __('inv_units'), __('inv_n_products', ['n' => num($s['products_in_stock'])]), url('admin/inventory/batches?status=in_stock' . $vq)) ?>
    <?= $tile('bi-box-arrow-in-down', 'success', __('inv_total_received'), num(number_format($s['received'])), __('inv_lost', ['n' => num(number_format($s['lost']))]), url('admin/inventory/movements?type=purchase' . $vq)) ?>
    <?= $tile('bi-cart-check', 'info', __('inv_total_sold'), num(number_format($s['sold'])), __('inv_last_30', ['n' => num(number_format($s['sold_30d']))]), url('admin/inventory/movements?type=sale' . $vq)) ?>
    <?= $tile('bi-cash-coin', 'warning', __('inv_stock_value'), money($s['cost_value']), __('inv_sale_value_margin', ['v' => money($s['sale_value']), 'm' => num(round($margin, 1))]), url('admin/inventory/reports/valuation?' . ltrim($vq, '&'))) ?>
</div>
<div class="row g-3 mb-3">
    <?= $tile('bi-x-octagon', 'danger', __('out_of_stock'), num($s['out_of_stock']), __('inv_products'), url('admin/products?stock=out')) ?>
    <?= $tile('bi-exclamation-triangle', 'warning', __('low_stock'), num($s['low_stock']), __('inv_products'), url('admin/products?stock=low')) ?>
    <?= $tile('bi-hourglass-split', 'warning', __('inv_near_expiry'), num($s['near_expiry']), __('inv_batches_within', ['n' => num($days)]), url('admin/inventory/batches?expiry=near' . $vq)) ?>
    <?= $tile('bi-calendar-x', 'danger', __('inv_expired_on_hand'), num($s['expired']), __('inv_value', ['v' => money($s['expired_value'])]), url('admin/inventory/batches?expiry=expired&status=in_stock' . $vq)) ?>
</div>

<div class="row g-3">
    <!-- Out of stock -->
    <div class="col-xl-4 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-danger"><i class="bi bi-x-octagon"></i> <?= e(__('out_of_stock')) ?></span>
                <a class="small" href="<?= e(url('admin/products?stock=out')) ?>"><?= e(__('view_all')) ?></a>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if (!$outOfStock): ?><li class="list-group-item text-muted text-center py-4"><i class="bi bi-check-circle text-success"></i> <?= e(__('inv_none_out')) ?></li><?php endif; ?>
                <?php foreach ($outOfStock as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <div class="text-truncate fw-semibold"><?= e(Product::label($p)) ?></div>
                            <div class="text-muted"><?= e($p['sku'] ?: '—') ?> · <?= e(__('status_' . $p['status'])) ?><?= $p['last_out'] ? ' · ' . e(__('inv_last_out')) . ' ' . e(dt($p['last_out'], 'd M')) : '' ?></div>
                        </div>
                        <?php if (can('inventory.adjust')): ?>
                            <a class="btn btn-sm btn-outline-success text-nowrap" href="<?= e(url('admin/inventory/stock-in?product_id=' . $p['id'])) ?>" title="<?= e(__('inv_stock_in')) ?>"><i class="bi bi-plus-lg"></i></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Low stock -->
    <div class="col-xl-4 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> <?= e(__('low_stock')) ?></span>
                    <a class="small" href="<?= e(url('admin/products?stock=low')) ?>"><?= e(__('view_all')) ?></a>
                </div>
                <form method="get" class="d-flex align-items-center gap-1 mt-2 small">
                    <?php if ($vendor !== ''): ?><input type="hidden" name="vendor" value="<?= e($vendor) ?>"><?php endif; ?>
                    <label class="text-muted text-nowrap" for="threshold"><?= e(__('inv_stock_at_most')) ?></label>
                    <input class="form-control form-control-sm" style="width:80px" type="number" min="0" id="threshold" name="threshold" value="<?= e($threshold ?? '') ?>" placeholder="<?= e(__('inv_per_product')) ?>">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button>
                </form>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if (!$lowStock): ?><li class="list-group-item text-muted text-center py-4"><i class="bi bi-check-circle text-success"></i> <?= e(__('inv_none_low')) ?></li><?php endif; ?>
                <?php foreach ($lowStock as $p): $pct = min(100, (int) round($p['stock_qty'] / max(1, $p['low_stock_threshold']) * 100)); ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-truncate fw-semibold"><?= e(Product::label($p)) ?></span>
                            <span class="text-nowrap"><strong><?= e(num($p['stock_qty'])) ?></strong> / <?= e(num($threshold ?? $p['low_stock_threshold'])) ?></span>
                        </div>
                        <div class="progress mt-1" style="height:4px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-warning" style="width: <?= $pct ?>%"></div></div>
                        <div class="text-muted mt-1"><?= e(__('inv_sold_30d', ['n' => num($p['sold_30d'])])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Near expiry -->
    <div class="col-xl-4 col-lg-12">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-danger-emphasis"><i class="bi bi-hourglass-split"></i> <?= e(__('inv_near_expiry')) ?></span>
                    <a class="small" href="<?= e(url('admin/inventory/batches?expiry=near' . $vq)) ?>"><?= e(__('view_all')) ?></a>
                </div>
                <form method="get" class="d-flex align-items-center gap-1 mt-2 small">
                    <?php if ($vendor !== ''): ?><input type="hidden" name="vendor" value="<?= e($vendor) ?>"><?php endif; ?>
                    <label class="text-muted text-nowrap" for="days"><?= e(__('inv_within_days')) ?></label>
                    <input class="form-control form-control-sm" style="width:80px" type="number" min="1" max="1825" id="days" name="days" value="<?= e($days) ?>">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button>
                </form>
            </div>
            <ul class="list-group list-group-flush small">
                <?php if (!$nearExpiry): ?><li class="list-group-item text-muted text-center py-4"><i class="bi bi-check-circle text-success"></i> <?= e(__('inv_none_expiry')) ?></li><?php endif; ?>
                <?php foreach ($nearExpiry as $b): $expired = (int) $b['days_left'] < 0; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="min-w-0">
                            <a class="text-reset fw-semibold text-truncate d-block" href="<?= e(url('admin/inventory/batches/' . $b['id'])) ?>"><?= e(Product::label($b)) ?><?= $b['variant_name'] ? ' · ' . e($b['variant_name']) : '' ?></a>
                            <div class="text-muted"><span class="font-monospace"><?= e($b['batch_no']) ?></span> · <?= e(num($b['quantity_available'])) ?> <?= e(__('inv_units')) ?> · <?= e(money($b['cost_value'])) ?></div>
                        </div>
                        <span class="badge text-nowrap <?= $expired ? 'bg-danger' : ((int) $b['days_left'] <= 30 ? 'bg-warning text-dark' : 'bg-light text-dark border') ?>">
                            <?= $expired ? e(__('inv_expired')) : e(__('inv_days_left', ['n' => num($b['days_left'])])) ?><br><span class="fw-normal"><?= e(dt($b['expiry_date'], 'd M Y')) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Recent movements -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-clock-history"></i> <?= e(__('inv_recent_movements')) ?></span>
                <a class="small" href="<?= e(url('admin/inventory/movements')) ?>"><?= e(__('view_all')) ?></a>
            </div>
            <?= View::partial('admin/inventory/_movement_table', ['rows' => $recent, 'compact' => true]) ?>
        </div>
    </div>

    <!-- Settings -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders"></i> <?= e(__('inv_alert_settings')) ?></div>
            <?php if (can('inventory.configure')): ?>
                <form method="post" action="<?= e(url('admin/inventory/settings')) ?>" class="card-body" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small" for="low_stock_threshold"><?= e(__('inv_low_stock_threshold')) ?></label>
                        <input class="form-control form-control-sm" type="number" min="0" max="100000" id="low_stock_threshold" name="low_stock_threshold" value="<?= e($settings['low_stock_threshold']) ?>" required>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="apply_all" name="apply_all" value="1">
                            <label class="form-check-label small" for="apply_all"><?= e(__('inv_apply_all')) ?></label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="expiry_alert_days"><?= e(__('inv_expiry_alert_days')) ?></label>
                        <input class="form-control form-control-sm" type="number" min="1" max="1825" id="expiry_alert_days" name="expiry_alert_days" value="<?= e($settings['expiry_alert_days']) ?>" required>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="block_expired_stock_in" name="block_expired_stock_in" value="1" <?= $settings['block_expired_stock_in'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="block_expired_stock_in"><?= e(__('inv_block_expired')) ?></label>
                    </div>
                    <button class="btn btn-sm btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
                </form>
            <?php else: ?>
                <div class="card-body small">
                    <div><?= e(__('inv_low_stock_threshold')) ?>: <strong><?= e(num($settings['low_stock_threshold'])) ?></strong></div>
                    <div><?= e(__('inv_expiry_alert_days')) ?>: <strong><?= e(num($settings['expiry_alert_days'])) ?></strong></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
