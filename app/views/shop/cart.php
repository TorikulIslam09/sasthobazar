<?php
/** Cart. Vars: $cart (Cart::summary), $code, $coupon, $totals */
$lines = $cart['lines'];
$freeShipAt = Promo::freeShippingThreshold();
$freeShipLeft = $freeShipAt === null ? null : (float) max(0.0, round($freeShipAt - ($totals['subtotal'] - $totals['discount_total']), 2));
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <span><?= e(__('cart')) ?></span></nav>

<?php if (!$lines): ?>
    <div class="sb-block sb-empty">
        <i class="bi bi-cart3"></i>
        <h1 class="h5"><?= e(__('cart_empty')) ?></h1>
        <p><?= e(__('cart_empty_hint')) ?></p>
        <a class="btn btn-primary" href="<?= e(url('products')) ?>"><?= e(__('shop_now')) ?></a>
    </div>
<?php return; endif; ?>

<div class="sb-cart">
    <section class="sb-block min-w-0">
        <div class="sb-sec-head">
            <h1 class="h5 mb-0"><?= e(__('cart')) ?> <span class="text-muted fw-normal small">(<?= e(__('n_items', ['n' => num($cart['items'])])) ?>)</span></h1>
        </div>
        <?php if ($cart['has_problems']): ?>
            <div class="alert alert-warning small py-2"><i class="bi bi-exclamation-triangle"></i> <?= e(__('cart_has_problems')) ?></div>
        <?php endif; ?>
        <?php if ($cart['has_rx']): ?>
            <div class="alert alert-info small py-2"><i class="bi bi-file-medical"></i> <?= e(__('cart_rx_notice')) ?></div>
        <?php endif; ?>

        <?php foreach ($lines as $l): $link = url('product/' . rawurlencode($l['slug'])); ?>
            <div class="sb-ci<?= $l['problem'] ? ' has-problem' : '' ?>">
                <a href="<?= e($link) ?>" class="sb-ci-img" tabindex="-1">
                    <?php if ($l['image']): ?><img src="<?= e(url($l['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-capsule"></i><?php endif; ?>
                </a>
                <div class="sb-ci-info min-w-0">
                    <a href="<?= e($link) ?>" class="fw-semibold text-dark"><?= e($l['name']) ?></a>
                    <div class="small text-muted">
                        <?= e($l['variant'] !== '' ? $l['variant'] : trim(($l['generic_name'] ?? '') . ' ' . ($l['strength'] ?? ''))) ?>
                        <?php if ((int) $l['requires_prescription']): ?><span class="sb-rx">Rx</span><?php endif; ?>
                        <?php if ($l['flash']): ?><span class="badge text-bg-danger"><i class="bi bi-lightning-charge-fill"></i> <?= e(__('flash_sale')) ?></span><?php endif; ?>
                    </div>
                    <div class="small"><?= e(money($l['unit_price'])) ?><?php if ($l['unit_mrp'] > $l['unit_price']): ?> <s class="text-muted"><?= e(money($l['unit_mrp'])) ?></s><?php endif; ?></div>
                    <?php if (($l['promo']['type'] ?? '') === 'offer'): ?><div class="sb-line-promo"><i class="bi bi-tag"></i> <?= e($l['promo']['label']) ?></div><?php endif; ?>
                    <?php if (!empty($l['bogo'])): ?>
                        <div class="sb-line-promo bogo"><i class="bi bi-gift"></i> <?= e(Promo::label($l['bogo'])) ?>:
                            <?= $l['bogo_free'] > 0 ? e(__('bogo_free_units', ['n' => num($l['bogo_free']), 'amount' => money($l['bogo_discount'])])) : e(__('bogo_add_more', ['n' => num((int) $l['bogo']['buy_qty'] + (int) $l['bogo']['get_qty'])])) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($l['problem']): ?><div class="small text-danger fw-semibold"><i class="bi bi-exclamation-circle"></i> <?= e($l['problem']) ?></div><?php endif; ?>
                </div>
                <form method="post" action="<?= e(url('cart/update')) ?>" class="sb-ci-qty" data-cart-qty>
                    <?= csrf_field() ?>
                    <input type="hidden" name="line_id" value="<?= (int) $l['id'] ?>">
                    <div class="sb-qty sb-qty-sm" data-qty>
                        <button type="button" data-qty-step="-1" aria-label="−">−</button>
                        <input type="number" name="qty" value="<?= (int) $l['quantity'] ?>" min="1" max="<?= max(1, (int) $l['max_qty']) ?>" aria-label="<?= e(__('quantity')) ?>">
                        <button type="button" data-qty-step="1" aria-label="+">+</button>
                    </div>
                    <noscript><button class="btn btn-sm btn-outline-primary mt-1"><?= e(__('update')) ?></button></noscript>
                </form>
                <b class="sb-ci-total"><?= e(money($l['line_total'])) ?></b>
                <form method="post" action="<?= e(url('cart/remove')) ?>" class="sb-ci-x">
                    <?= csrf_field() ?>
                    <input type="hidden" name="line_id" value="<?= (int) $l['id'] ?>">
                    <button class="btn btn-link text-muted p-0" title="<?= e(__('remove')) ?>" aria-label="<?= e(__('remove')) ?>: <?= e($l['name']) ?>"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
        <a href="<?= e(url('products')) ?>" class="d-inline-block mt-3 small"><i class="bi bi-arrow-left"></i> <?= e(__('continue_shopping')) ?></a>
    </section>

    <aside class="sb-block sb-sum">
        <h2 class="h6 fw-bold mb-3"><?= e(__('order_summary')) ?></h2>
        <form method="post" action="<?= e(url('cart/coupon')) ?>" class="sb-coupon">
            <?= csrf_field() ?>
            <?php if ($code && !$totals['coupon_error']): ?>
                <div class="d-flex justify-content-between align-items-center w-100 sb-coupon-on">
                    <span><i class="bi bi-ticket-perforated"></i> <b><?= e($code) ?></b><?php if ($coupon): ?> <small class="text-muted"><?= e(Coupon::summary($coupon)) ?></small><?php endif; ?></span>
                    <button class="btn btn-link btn-sm text-danger p-0" name="remove" value="1"><?= e(__('remove')) ?></button>
                </div>
            <?php else: ?>
                <input class="form-control form-control-sm" name="code" maxlength="50" placeholder="<?= e(__('coupon_code')) ?>" aria-label="<?= e(__('coupon_code')) ?>" value="<?= e($code ?? '') ?>">
                <button class="btn btn-outline-primary btn-sm"><?= e(__('apply')) ?></button>
            <?php endif; ?>
        </form>
        <?php if ($totals['coupon_error']): ?><div class="small text-danger mb-2"><?= e($totals['coupon_error']) ?></div><?php endif; ?>

        <div class="sb-sum-rows">
            <div><span><?= e(__('subtotal')) ?></span><span><?= e(money($totals['subtotal'])) ?></span></div>
            <?php if ($cart['mrp_total'] > $totals['subtotal']): ?>
                <div class="text-success small"><span><?= e(__('you_saved')) ?></span><span><?= e(money($cart['mrp_total'] - $totals['subtotal'])) ?></span></div>
            <?php endif; ?>
            <?php if ($totals['bogo_discount'] > 0): ?>
                <div class="text-success"><span><?= e(__('bogo_discount')) ?></span><span>-<?= e(money($totals['bogo_discount'])) ?></span></div>
            <?php endif; ?>
            <?php if ($totals['coupon_discount'] > 0): ?>
                <div class="text-success"><span><?= e(__('coupon_discount')) ?></span><span>-<?= e(money($totals['coupon_discount'])) ?></span></div>
            <?php endif; ?>
            <?php if ($totals['first_order_discount'] > 0): ?>
                <div class="text-success"><span><?= e(__('first_order_discount')) ?></span><span>-<?= e(money($totals['first_order_discount'])) ?></span></div>
            <?php endif; ?>
            <div class="text-muted small"><span><?= e(__('delivery_charge')) ?></span><span><?= e($totals['free_shipping'] || $freeShipLeft === 0.0 ? __('free') : __('calculated_at_checkout')) ?></span></div>
            <?php if ($totals['vat_total'] > 0): ?>
                <div class="<?= $totals['vat_inclusive'] ? 'text-muted small' : '' ?>"><span><?= e($totals['vat_inclusive'] ? __('vat_included', ['label' => Tax::label()]) : Tax::label()) ?></span><span><?= e(money($totals['vat_total'])) ?></span></div>
            <?php endif; ?>
            <div class="sb-sum-total"><span><?= e(__('total')) ?></span><span><?= e(money($totals['subtotal'] - $totals['discount_total'] + $totals['vat_added'])) ?></span></div>
        </div>
        <?php if ($freeShipLeft !== null && $freeShipLeft > 0 && !$totals['free_shipping']): ?>
            <div class="sb-promo-hint"><i class="bi bi-truck"></i> <span><?= e(__('free_ship_add_more', ['amount' => money($freeShipLeft)])) ?></span></div>
        <?php endif; ?>
        <?php if (!$totals['first_order'] && !$totals['coupon_discount'] && ($teaser = Promo::firstOrderTeaser()) && !Auth::check()): ?>
            <div class="sb-promo-hint"><i class="bi bi-stars"></i> <span><?= e(__('first_order_teaser', ['offer' => Promo::label($teaser)])) ?></span></div>
        <?php endif; ?>
        <?php $min = (float) setting('min_order_amount', 0); if ($totals['subtotal'] < $min): ?>
            <div class="small text-danger mb-2"><?= e(__('min_order', ['amount' => money($min)])) ?></div>
        <?php endif; ?>
        <?php if ($cart['has_problems'] || $totals['subtotal'] < $min): ?>
            <button class="btn btn-primary w-100" disabled><?= e(__('proceed_checkout')) ?></button>
        <?php else: ?>
            <a class="btn btn-primary w-100" href="<?= e(url('checkout')) ?>"><?= e(__('proceed_checkout')) ?> <i class="bi bi-arrow-right"></i></a>
            <?php if (!Auth::check()): ?><p class="small text-muted text-center mt-2 mb-0"><?= e(__('checkout_login_hint')) ?></p><?php endif; ?>
        <?php endif; ?>
    </aside>
</div>
