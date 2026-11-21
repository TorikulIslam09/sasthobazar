<?php
/**
 * Guest order placed: thank-you + summary + "create an account to track orders" (one password field).
 * Vars: $o, $items, $area, $canAccount, $phoneTaken, $errors
 */
$errors = $errors ?? [];
$contact = $o['customer_email'] && !$o['customer_phone'] ? $o['customer_email'] : $o['customer_phone'];
$trackUrl = url('track?order_no=' . rawurlencode($o['order_no']) . '&contact=' . rawurlencode((string) $contact));
?>
<div class="sb-auth" style="max-width: 720px">
    <section class="sb-block text-center">
        <div class="display-6 text-success mb-1"><i class="bi bi-check-circle-fill"></i></div>
        <h1 class="h5 mb-1"><?= e(__('order_placed_title')) ?></h1>
        <p class="mb-1"><?= e(__('order_no')) ?>: <b>#<?= e($o['order_no']) ?></b></p>
        <p class="small text-muted mb-3"><?= e(__('placed_contact_note', ['contact' => (string) $contact])) ?></p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a class="btn btn-outline-primary btn-sm" href="<?= e($trackUrl) ?>"><i class="bi bi-truck"></i> <?= e(__('track_order')) ?></a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('products')) ?>"><i class="bi bi-bag"></i> <?= e(__('continue_shopping')) ?></a>
        </div>
    </section>

    <?php if ($canAccount): ?>
        <section class="sb-block sb-placed-account" id="createAccount">
            <h2 class="h6 fw-bold mb-1"><i class="bi bi-person-plus"></i> <?= e(__('placed_account_title')) ?></h2>
            <?php if ($phoneTaken): ?>
                <p class="small mb-2"><?= e(__('placed_phone_taken')) ?></p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('login?tab=login')) ?>"><i class="bi bi-box-arrow-in-right"></i> <?= e(__('login_now')) ?></a>
            <?php else: ?>
                <p class="small text-muted mb-2"><?= e(__('placed_account_help', ['phone' => (string) $o['customer_phone']])) ?></p>
                <form method="post" action="<?= e(url('order/placed/' . $o['order_no'] . '/account')) ?>" class="row g-2 align-items-start" novalidate>
                    <?= csrf_field() ?>
                    <div class="col-sm-8">
                        <label class="visually-hidden" for="paPass"><?= e(__('password')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" type="password" id="paPass" name="password" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="<?= e(__('placed_password_ph')) ?>">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#paPass" aria-label="<?= e(__('show_password')) ?>"><i class="bi bi-eye"></i></button>
                        </div>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-sm-4"><button class="btn btn-primary w-100"><?= e(__('placed_create_btn')) ?></button></div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="sb-block">
        <h2 class="h6 fw-bold"><?= e(__('items')) ?></h2>
        <?php foreach ($items as $it): ?>
            <div class="sb-oi">
                <span class="sb-ci-img"><?php if ($it['image']): ?><img src="<?= e(url($it['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-capsule"></i><?php endif; ?></span>
                <div class="min-w-0 flex-grow-1">
                    <b><?= e(OrderView::itemName($it)) ?></b>
                    <div class="small text-muted"><?= e(money($it['unit_price'])) ?> × <?= e(num($it['quantity'])) ?></div>
                </div>
                <b><?= e(money($it['line_total'])) ?></b>
            </div>
        <?php endforeach; ?>
        <div class="sb-sum-rows mt-2">
            <div><span><?= e(__('subtotal')) ?></span><span><?= e(money($o['subtotal'])) ?></span></div>
            <?php if ((float) ($o['promo_discount'] ?? 0) > 0): ?><div class="text-success"><span><?= e(__('offer_discount')) ?></span><span>-<?= e(money($o['promo_discount'])) ?></span></div><?php endif; ?>
            <?php if ((float) $o['coupon_discount'] > 0): ?><div class="text-success"><span><?= e(__('coupon_discount')) ?> (<?= e($o['coupon_code']) ?>)</span><span>-<?= e(money($o['coupon_discount'])) ?></span></div><?php endif; ?>
            <div><span><?= e(__('delivery_charge')) ?></span><span><?= (float) $o['shipping_total'] > 0 ? e(money($o['shipping_total'])) : e(__('free')) ?></span></div>
            <?php if ((float) $o['cod_charge'] > 0): ?><div><span><?= e(__('cod_charge')) ?></span><span><?= e(money($o['cod_charge'])) ?></span></div><?php endif; ?>
            <?php if ((float) ($o['vat_total'] ?? 0) > 0): ?><div class="<?= Tax::orderVatOnTop($o) ? '' : 'text-muted small' ?>"><span><?= e(Tax::rowLabel($o)) ?></span><span><?= e(money($o['vat_total'])) ?></span></div><?php endif; ?>
            <div class="sb-sum-total"><span><?= e(__('total')) ?></span><span><?= e(money($o['grand_total'])) ?></span></div>
        </div>
    </section>

    <section class="sb-block small">
        <div class="row g-3">
            <div class="col-sm-6">
                <h2 class="h6 fw-bold"><?= e(__('delivery_address')) ?></h2>
                <b><?= e($o['shipping_name']) ?></b> · <?= e(num($o['shipping_phone'])) ?><br>
                <?= e($o['shipping_address']) ?><br><span class="text-muted"><?= e($area) ?></span>
            </div>
            <div class="col-sm-6">
                <h2 class="h6 fw-bold"><?= e(__('payment')) ?></h2>
                <div class="d-flex justify-content-between"><span><?= e(__('pm_' . $o['payment_method'])) ?></span><?= OrderView::payBadge($o['payment_status']) ?></div>
            </div>
        </div>
    </section>
</div>
