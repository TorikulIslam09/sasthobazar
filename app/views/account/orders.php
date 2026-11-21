<?php /** Order history. Vars: $tab, $status, $orders, $pager */
$tabs = ['' => __('all'), 'open' => __('orders_open'), 'delivered' => __('os_delivered'), 'cancelled' => __('os_cancelled'), 'returned' => __('os_returned')];
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account')) ?>"><?= e(__('my_account')) ?></a> › <span><?= e(__('my_orders')) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => $tab]) ?>
    <section class="sb-block min-w-0">
        <div class="sb-sec-head"><h1 class="h5 mb-0"><?= e(__('my_orders')) ?></h1></div>
        <div class="sb-chips mb-3">
            <?php foreach ($tabs as $k => $label): ?>
                <a href="<?= e(url('account/orders' . ($k !== '' ? '?status=' . $k : ''))) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if (!$orders): ?>
            <div class="sb-empty">
                <i class="bi bi-bag"></i>
                <p><?= e(__('no_orders')) ?></p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('products')) ?>"><?= e(__('shop_now')) ?></a>
            </div>
        <?php else: foreach ($orders as $o): ?>
            <a class="sb-order-card" href="<?= e(url('account/orders/' . $o['order_no'])) ?>">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <span><b>#<?= e($o['order_no']) ?></b> <span class="text-muted small"><?= e(dt($o['placed_at'], 'd M Y, h:i A')) ?></span></span>
                    <?= OrderView::badge($o['status']) ?>
                </div>
                <div class="d-flex justify-content-between flex-wrap gap-2 mt-1 small">
                    <span class="text-muted text-truncate"><?= e($o['first_item']) ?><?= (int) $o['items'] > 1 ? ' ' . e(__('and_more', ['n' => num((int) $o['items'] - 1)])) : '' ?></span>
                    <span><b><?= e(money($o['grand_total'])) ?></b> · <?= e(__('pm_' . $o['payment_method'])) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
            <div class="mt-3"><?= $pager->links() ?></div>
        <?php endif; ?>
    </section>
</div>
