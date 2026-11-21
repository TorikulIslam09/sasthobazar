<?php
/**
 * Admin dashboard.
 * Vars: $attention, $totals, $canOrders, $canStock, $canRx, $canRefund
 *       (orders) $sales[today|month|last|yesterday], $avgOrder, $chart (30 days), $statusCounts, $recent, $topProducts
 *       (stock) $lowStock, $expiring   (commission) $commission[today|month]
 */
$tiles = [
    // key => [icon, color, link, permission-ok]
    'pending_orders'      => ['bi-hourglass-split', 'warning', 'admin/orders?status=pending'],
    'rx_review'           => ['bi-file-medical', 'info', 'admin/orders?status=prescription_review'],
    'unverified_payments' => ['bi-phone', 'danger', 'admin/orders?unverified=1'],
    'to_ship'             => ['bi-box-seam', 'primary', 'admin/orders?status=confirmed'],
    'return_requests'     => ['bi-arrow-counterclockwise', 'warning', 'admin/refunds?status=requested'],
    'refunds_to_pay'      => ['bi-cash-coin', 'danger', 'admin/refunds?status=approved'],
    'low_stock'           => ['bi-exclamation-triangle', 'warning', 'admin/inventory'],
    'out_of_stock'        => ['bi-x-octagon', 'danger', 'admin/inventory'],
    'near_expiry'         => ['bi-calendar-x', 'danger', 'admin/inventory'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h4 mb-0"><?= e(__('dashboard')) ?></h1>
        <span class="small text-muted"><?= e(__('dash_hello', ['name' => Auth::user()['name']])) ?> · <?= e(dt(date('Y-m-d H:i:s'), 'l, d M Y')) ?></span>
    </div>
    <div class="d-flex gap-2">
        <?php if ($canOrders): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/orders')) ?>"><i class="bi bi-receipt"></i> <?= e(__('orders')) ?></a><?php endif; ?>
        <?php if (can('inventory.adjust')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/inventory/stock-in')) ?>"><i class="bi bi-box-arrow-in-down"></i> <?= e(__('inv_stock_in')) ?></a><?php endif; ?>
    </div>
</div>
<?php if (empty($canDash)): ?>
    <div class="alert alert-light border"><i class="bi bi-shield-lock"></i> <?= e(__('dash_no_access')) ?></div>
<?php endif; ?>

<?php if ($attention): ?>
    <h2 class="h6 text-muted text-uppercase mb-2"><?= e(__('dash_attention')) ?></h2>
    <div class="row g-3 mb-4">
        <?php foreach ($attention as $key => $count): [$icon, $color, $link] = $tiles[$key]; ?>
            <div class="col-6 col-md-4 col-xl-3">
                <a href="<?= e(url($link)) ?>" class="card stat-card card-link border-0 shadow-sm h-100 text-decoration-none <?= $count > 0 ? 'border-start border-4 border-' . e($color) : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon text-<?= e($count > 0 ? $color : 'secondary') ?>"><i class="bi <?= e($icon) ?>"></i></div>
                        <div class="min-w-0">
                            <div class="small text-muted text-truncate"><?= e(__('dash_' . $key)) ?></div>
                            <div class="fs-5 fw-semibold text-dark"><?= e(num($count)) ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canOrders): ?>
    <?php
    $pctChange = static function (float $now, float $before): string {
        if ($before <= 0) {
            return '';
        }
        $p = ($now - $before) / $before * 100;
        return '<span class="' . ($p >= 0 ? 'text-success' : 'text-danger') . '"><i class="bi bi-arrow-' . ($p >= 0 ? 'up' : 'down') . '-short"></i>' . e(num(number_format(abs($p), 1))) . '%</span>';
    };
    $statusList = ['pending', 'prescription_review', 'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'cancelled', 'returned'];
    $statusData = array_values(array_filter(array_map(static fn($st) => [__('os_' . $st), (int) ($statusCounts[$st] ?? 0)], $statusList), static fn($x) => $x[1] > 0));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted"><?= e(__('dash_sales_today')) ?></div>
                <div class="fs-5 fw-semibold"><?= e(money($sales['today']['total'])) ?></div>
                <div class="small text-muted"><?= e(__('dash_n_orders', ['n' => num((int) $sales['today']['n'])])) ?> · <?= e(__('dash_vs_yesterday')) ?> <?= $pctChange((float) $sales['today']['total'], (float) $sales['yesterday']['total']) ?: '—' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted"><?= e(__('dash_sales_month')) ?></div>
                <div class="fs-5 fw-semibold"><?= e(money($sales['month']['total'])) ?></div>
                <div class="small text-muted"><?= e(__('dash_n_orders', ['n' => num((int) $sales['month']['n'])])) ?> · <?= e(__('dash_vs_last_month')) ?> <?= $pctChange((float) $sales['month']['total'], (float) $sales['last']['total']) ?: '—' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted"><?= e(__('dash_commission_month')) ?></div>
                <div class="fs-5 fw-semibold text-success"><?= isset($commission) ? e(money($commission['month'])) : '—' ?></div>
                <div class="small text-muted"><?= isset($commission) ? e(__('dash_today_amount', ['amount' => money($commission['today'])])) : '' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted"><?= e(__('dash_avg_order')) ?></div>
                <div class="fs-5 fw-semibold"><?= e(money($avgOrder)) ?></div>
                <div class="small text-muted"><?= e(__('dash_this_month')) ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= e(__('dash_sales_chart_30')) ?></span>
                    <?php if (can('reports.view')): ?><a class="small" href="<?= e(url('admin/reports/sales?from=' . date('Y-m-d', strtotime('-29 days')) . '&to=' . date('Y-m-d'))) ?>"><?= e(__('rp_sales')) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                </div>
                <div class="card-body">
                    <div style="height: 280px">
                        <canvas data-chart="<?= e(json_encode(['type' => 'bar', 'money' => true, 'labels' => array_map(static fn($c) => dt($c['date'], 'd M'), $chart), 'datasets' => [
                            ['label' => __('rp_net_sales'), 'data' => array_map(static fn($c) => round($c['total'], 2), $chart), 'kind' => 'bar'],
                            ['label' => __('orders'), 'data' => array_column($chart, 'n'), 'kind' => 'line'],
                        ]], JSON_UNESCAPED_UNICODE)) ?>" role="img" aria-label="<?= e(__('dash_sales_chart_30')) ?>"></canvas>
                    </div>
                    <div class="small text-muted mt-2"><?= e(__('dash_chart_note')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><?= e(__('dash_by_status')) ?></div>
                <div class="card-body pb-0">
                    <?php if ($statusData): ?>
                        <div style="height: 170px"><canvas data-chart="<?= e(json_encode(['type' => 'doughnut', 'labels' => array_column($statusData, 0), 'datasets' => [['label' => __('orders'), 'data' => array_column($statusData, 1)]]], JSON_UNESCAPED_UNICODE)) ?>" role="img" aria-label="<?= e(__('dash_by_status')) ?>"></canvas></div>
                    <?php endif; ?>
                </div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($statusList as $st): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                            <a href="<?= e(url('admin/orders?status=' . $st)) ?>" class="text-decoration-none"><?= OrderView::badge($st) ?></a>
                            <strong><?= e(num($statusCounts[$st] ?? 0)) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= e(__('dash_recent_orders')) ?></span>
                    <a href="<?= e(url('admin/orders')) ?>" class="small"><?= e(__('view_all')) ?></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light"><tr><th><?= e(__('order')) ?></th><th><?= e(__('customer')) ?></th><th class="text-end"><?= e(__('total')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
                        <tbody>
                        <?php if (!$recent): ?><tr><td colspan="4" class="text-center text-muted py-4"><?= e(__('dash_no_orders')) ?></td></tr><?php endif; ?>
                        <?php foreach ($recent as $o): ?>
                            <tr>
                                <td><a href="<?= e(url('admin/orders/' . (int) $o['id'])) ?>" class="fw-semibold"><?= e($o['order_no']) ?></a>
                                    <?php if ($o['requires_prescription']): ?><span class="badge text-bg-danger">Rx</span><?php endif; ?>
                                    <div class="text-muted"><?= e(dt($o['placed_at'], 'd M, h:i A')) ?></div></td>
                                <td><?= e($o['customer_name']) ?><div class="text-muted"><?= e($o['customer_phone']) ?></div></td>
                                <td class="text-end"><?= e(money($o['grand_total'])) ?><div class="text-muted"><?= e(__('pm_' . $o['payment_method'])) ?></div></td>
                                <td><?= OrderView::badge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><?= e(__('dash_top_products')) ?></div>
                <ul class="list-group list-group-flush small">
                    <?php if (!$topProducts): ?><li class="list-group-item text-muted"><?= e(__('dash_no_sales')) ?></li><?php endif; ?>
                    <?php foreach ($topProducts as $i => $p): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span class="text-truncate"><span class="text-muted"><?= e(num($i + 1)) ?>.</span> <?= e($p['name']) ?></span>
                            <span class="text-nowrap"><strong><?= e(num((int) $p['qty'])) ?></strong> · <?= e(money($p['revenue'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php if ($canStock): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= e(__('dash_low_stock')) ?></span>
                    <a href="<?= e(url('admin/inventory')) ?>" class="small"><?= e(__('view_all')) ?></a>
                </div>
                <ul class="list-group list-group-flush small">
                    <?php if (!$lowStock): ?><li class="list-group-item text-muted"><?= e(__('inv_none_low')) ?></li><?php endif; ?>
                    <?php foreach ($lowStock as $p): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-truncate"><?= e(tr($p['name_bn'], $p['name_en'])) ?></span>
                            <span class="badge text-bg-<?= (int) $p['stock_qty'] <= 0 ? 'danger' : 'warning' ?>"><?= e(num((int) $p['stock_qty'])) ?> / <?= e(num((int) $p['low_stock_threshold'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($canStock): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= e(__('dash_expiring')) ?></span>
                    <a href="<?= e(url(can('reports.view') ? 'admin/reports/expiry' : 'admin/inventory')) ?>" class="small"><?= e(__('view_all')) ?></a>
                </div>
                <ul class="list-group list-group-flush small">
                    <?php if (!$expiring): ?><li class="list-group-item text-muted"><?= e(__('dash_none_expiring')) ?></li><?php endif; ?>
                    <?php foreach ($expiring as $b): $dl = (int) $b['days_left']; ?>
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span class="text-truncate"><?= e(tr($b['name_bn'], $b['name_en'])) ?> <span class="text-muted">· <?= e($b['batch_no']) ?> × <?= e(num((int) $b['quantity_available'])) ?></span></span>
                            <span class="badge text-bg-<?= $dl < 0 ? 'danger' : ($dl <= 30 ? 'warning' : 'light border') ?> text-nowrap"><?= e($dl < 0 ? __('rp_expired') : dt($b['expiry_date'], 'd M Y')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
    <div class="col-12">
        <div class="row g-3">
            <?php foreach (['total_products' => ['bi-capsule', 'info'], 'total_customers' => ['bi-people', 'dark'], 'new_customers' => ['bi-person-plus', 'success'], 'total_vendors' => ['bi-shop', 'secondary']] as $key => [$icon, $color]): ?>
                <div class="col-6 col-lg-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="stat-icon text-<?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                            <div>
                                <div class="small text-muted"><?= e(__('dash_' . $key)) ?></div>
                                <div class="fs-5 fw-semibold"><?= e(num($totals[$key])) ?></div>
                                <?php if ($key === 'new_customers'): ?><div class="small text-muted"><?= e(__('dash_today_n', ['n' => num($totals['new_today'])])) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= e(asset('assets/js/charts.js')) ?>" defer></script>
