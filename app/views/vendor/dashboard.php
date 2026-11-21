<?php
/** Vendor dashboard. Vars: $vendor, and when active: $periods, $wallet, $toProcess, $waiting, $returns, $lowStock, $nearExpiry, $recent, $rate, $rateScope */
$active = isset($periods);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><?= e(__('dashboard')) ?></h1>
        <span class="small text-muted"><?= e(__('dash_hello', ['name' => Auth::user()['name']])) ?></span>
    </div>
    <?php if ($active): ?><span class="badge text-bg-light border"><?= e(__('your_commission')) ?>: <?= e(num(number_format($rate, 2))) ?>% <span class="text-muted">(<?= e(__('cscope_' . $rateScope)) ?>)</span></span><?php endif; ?>
</div>

<?php if (!$active): ?>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <h2 class="h6"><?= e(__('vendor_next_steps')) ?></h2>
        <ol class="small mb-0">
            <li><?= e(__('vendor_step_review')) ?></li>
            <li><?= e(__('vendor_step_products')) ?></li>
            <li><?= e(__('vendor_step_orders')) ?></li>
        </ol>
        <a class="btn btn-outline-primary btn-sm mt-3" href="<?= e(url('vendor-panel/profile')) ?>"><?= e(__('shop_profile')) ?></a>
    </div></div>
<?php return; endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ([[$toProcess, 'v_to_process', 'bi-box-seam', 'primary', 'vendor-panel/orders?status=confirmed'], [$waiting, 'v_waiting', 'bi-hourglass-split', 'secondary', 'vendor-panel/orders?status=pending'], [$returns, 'dash_return_requests', 'bi-arrow-counterclockwise', 'warning', 'vendor-panel/returns'], [count($lowStock), 'dash_low_stock', 'bi-exclamation-triangle', 'danger', 'vendor-panel/products?stock=low']] as [$n, $label, $icon, $color, $link]): ?>
        <div class="col-6 col-xl-3">
            <a href="<?= e(url($link)) ?>" class="card stat-card card-link border-0 shadow-sm h-100 text-decoration-none <?= $n > 0 ? 'border-start border-4 border-' . $color : '' ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon text-<?= $n > 0 ? $color : 'secondary' ?>"><i class="bi <?= $icon ?>"></i></div>
                    <div><div class="small text-muted"><?= e(__($label)) ?></div><div class="fs-5 fw-semibold text-dark"><?= e(num($n)) ?></div></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light small"><tr><th></th><th class="text-end"><?= e(__('orders')) ?></th><th class="text-end"><?= e(__('v_sales')) ?></th><th class="text-end"><?= e(__('v_commission_deducted')) ?></th><th class="text-end"><?= e(__('v_net_earning')) ?></th></tr></thead>
            <tbody>
                <?php foreach (['today', 'week', 'month'] as $k): $p = $periods[$k]; ?>
                    <tr>
                        <th class="small"><?= e(__('period_' . $k)) ?></th>
                        <td class="text-end"><?= e(num((int) $p['orders'])) ?></td>
                        <td class="text-end"><?= e(money($p['sales'])) ?></td>
                        <td class="text-end text-danger">-<?= e(money($p['commission'])) ?></td>
                        <td class="text-end fw-semibold text-success"><?= e(money($p['earning'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted"><?= e(__('v_period_note')) ?></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2"></i> <?= e(__('wallet')) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_available')) ?></span><strong class="text-success"><?= e(money($wallet['balance'])) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_pending')) ?></span><strong><?= e(money($wallet['pending_balance'])) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_total_earned')) ?></span><strong><?= e(money($wallet['total_earned'])) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_withdrawn')) ?></span><strong><?= e(money($wallet['total_withdrawn'])) ?></strong></li>
            </ul>
            <div class="card-body pt-2"><a class="btn btn-sm btn-brand w-100" href="<?= e(url('vendor-panel/payouts')) ?>"><?= e(__('request_payout')) ?></a></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold"><?= e(__('dash_recent_orders')) ?></span><a class="small" href="<?= e(url('vendor-panel/orders')) ?>"><?= e(__('view_all')) ?></a></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <tbody>
                    <?php if (!$recent): ?><tr><td class="text-center text-muted py-4"><?= e(__('dash_no_orders')) ?></td></tr><?php endif; ?>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><a class="fw-semibold" href="<?= e(url('vendor-panel/orders/' . (int) $r['id'])) ?>"><?= e($r['sub_order_no']) ?></a><div class="text-muted"><?= e(dt($r['placed_at'], 'd M, h:i A')) ?></div></td>
                            <td><?= e($r['shipping_name']) ?></td>
                            <td class="text-end"><?= e(money((float) $r['subtotal'] - (float) $r['discount_total'])) ?></td>
                            <td><?= OrderView::badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= e(__('dash_low_stock')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php if (!$lowStock): ?><li class="list-group-item text-muted"><?= e(__('inv_none_low')) ?></li><?php endif; ?>
                <?php foreach ($lowStock as $p): ?>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-truncate"><?= e(tr($p['name_bn'], $p['name_en'])) ?></span>
                        <span class="badge text-bg-<?= (int) $p['stock_qty'] <= 0 ? 'danger' : 'warning' ?>"><?= e(num((int) $p['stock_qty'])) ?> / <?= e(num((int) $p['low_stock_threshold'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= e(__('dash_near_expiry')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php if (!$nearExpiry): ?><li class="list-group-item text-muted"><?= e(__('inv_none_expiry')) ?></li><?php endif; ?>
                <?php foreach ($nearExpiry as $b): ?>
                    <li class="list-group-item d-flex justify-content-between"><span class="text-truncate"><?= e(tr($b['name_bn'], $b['name_en'])) ?> · <?= e($b['batch_no']) ?></span>
                        <span><?= e(dt($b['expiry_date'], 'd M Y')) ?> · <?= e(num((int) $b['quantity_available'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
