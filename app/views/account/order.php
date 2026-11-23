<?php
/**
 * Customer order detail. Vars: $tab, $o, $items, $history, $timeline, $payments, $refunds, $prescription, $area, $errors, $placed
 */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$openReturn = array_filter($refunds, static fn($r) => $r['status'] === 'requested');
$canReturn = Order::returnable($o) && !$openReturn;
$canCancel = Order::customerCanCancel($o);
$open = !in_array($o['status'], ['delivered', 'cancelled', 'returned', 'failed'], true);
$returnable = array_filter($items, static fn($it) => $it['status'] === 'active' && (int) $it['quantity'] > (int) $it['returned_qty']);
?>
<nav class="sb-crumbs"><a href="<?= e(url('account')) ?>"><?= e(__('my_account')) ?></a> › <a href="<?= e(url('account/orders')) ?>"><?= e(__('my_orders')) ?></a> › <span>#<?= e($o['order_no']) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => $tab]) ?>
    <div class="min-w-0">
        <?php if ($placed): ?>
            <div class="sb-block sb-placed">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <h1 class="h5 mb-1"><?= e(__('thank_you_order')) ?></h1>
                    <p class="mb-0 small"><?= e(__($o['status'] === 'prescription_review' ? 'placed_rx_text' : 'placed_text')) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <section class="sb-block">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <h2 class="h5 mb-0"><?= e(__('order')) ?> #<?= e($o['order_no']) ?></h2>
                    <span class="small text-muted"><?= e(__('placed_on')) ?> <?= e(dt($o['placed_at'])) ?></span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?= OrderView::badge($o['status']) ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('account/orders/' . $o['order_no'] . '/invoice')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('invoice')) ?></a>
                    <?php if ($canCancel): ?><a class="btn btn-outline-danger btn-sm" href="#cancel"><i class="bi bi-x-circle"></i> <?= e(__('cancel_order')) ?></a><?php endif; ?>
                </div>
            </div>
            <?php if ($open && !$canCancel): ?>
                <p class="small text-muted mb-2"><i class="bi bi-info-circle"></i> <?= e(__('cancel_too_late')) ?> <a href="<?= e(url('account/support/new?order=' . rawurlencode($o['order_no']))) ?>"><?= e(__('cancel_ask_support')) ?></a></p>
            <?php endif; ?>
            <?= View::partial('orders/_timeline', ['timeline' => $timeline]) ?>
            <?php foreach ($splits as $sp): if (!$sp['tracking_no'] || count($splits) > 1) { continue; }
                $turl = $sp['tracking_url_template'] ? str_replace('{tracking_no}', rawurlencode((string) $sp['tracking_no']), (string) $sp['tracking_url_template']) : null; ?>
                <div class="small border-top pt-2"><i class="bi bi-truck"></i> <?= e($sp['courier_name']) ?> · <?= e(__('tracking')) ?>: <b class="user-select-all"><?= e($sp['tracking_no']) ?></b>
                    <?php if ($turl && !(int) $sp['is_mock']): ?> · <a href="<?= e($turl) ?>" target="_blank" rel="noopener"><?= e(__('track_on_courier')) ?></a><?php endif; ?></div>
            <?php endforeach; ?>
            <?php if (count($splits) > 1): ?>
                <div class="small border-top pt-2">
                    <div class="text-muted mb-1"><?= e(__('sub_orders_customer')) ?></div>
                    <?php foreach ($splits as $sp): ?>
                        <div class="d-flex justify-content-between flex-wrap gap-2 py-1">
                            <span><?= e($sp['sub_order_no']) ?> · <?= e($sp['vendor_id'] ? tr($sp['shop_name_bn'], $sp['shop_name']) : __('app_name')) ?> · <?= e(__('n_items', ['n' => num($sp['items'])])) ?>
                                <?php if ($sp['tracking_note']): ?><span class="text-muted">— <?= e($sp['tracking_note']) ?></span><?php endif; ?>
                                <?php if ($sp['tracking_no']): ?><span class="text-muted">— <?= e($sp['courier_name']) ?> <?= e($sp['tracking_no']) ?></span><?php endif; ?></span>
                            <?= OrderView::badge($sp['status']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($o['status'] === 'cancelled' && $o['cancel_reason']): ?>
                <p class="small text-danger mb-0"><?= e(__('cancel_reason')) ?>: <?= e($o['cancel_reason']) ?></p>
            <?php endif; ?>
        </section>

        <?php if ($o['requires_prescription'] && $prescription): ?>
            <section class="sb-block small">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <span><i class="bi bi-file-medical"></i> <b><?= e(__('prescription')) ?></b> —
                        <a href="<?= e(url('account/prescriptions/' . (int) $prescription['id'])) ?>" target="_blank" rel="noopener"><?= e($prescription['original_name'] ?: __('view')) ?></a></span>
                    <span class="badge text-bg-<?= $prescription['status'] === 'approved' ? 'success' : ($prescription['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= e(__('rx_status_' . $prescription['status'])) ?></span>
                </div>
                <?php if ($prescription['review_note']): ?><div class="text-muted mt-1"><?= e(__('pharmacist_note')) ?>: <?= e($prescription['review_note']) ?></div><?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="sb-block">
            <h2 class="h6 fw-bold"><?= e(__('items')) ?></h2>
            <?php foreach ($items as $it): ?>
                <div class="sb-oi">
                    <span class="sb-ci-img"><?php if ($it['image']): ?><img src="<?= e(url($it['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-capsule"></i><?php endif; ?></span>
                    <div class="min-w-0 flex-grow-1">
                        <?php if ($it['slug']): ?><a href="<?= e(url('product/' . rawurlencode($it['slug']))) ?>" class="text-dark fw-semibold"><?= e(OrderView::itemName($it)) ?></a><?php else: ?><b><?= e(OrderView::itemName($it)) ?></b><?php endif; ?>
                        <div class="small text-muted"><?= e(money($it['unit_price'])) ?> × <?= e(num($it['quantity'])) ?>
                            <?php if ((int) $it['returned_qty'] > 0): ?><span class="badge text-bg-dark"><?= e(__('returned_n', ['n' => num($it['returned_qty'])])) ?></span><?php endif; ?>
                            <?php if ($it['status'] === 'cancelled'): ?><span class="badge text-bg-danger"><?= e(__('os_cancelled')) ?></span><?php endif; ?>
                        </div>
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
                <?php if ((float) $o['refunded_amount'] > 0): ?><div class="text-success"><span><?= e(__('refunded')) ?></span><span><?= e(money($o['refunded_amount'])) ?></span></div><?php endif; ?>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-md-6">
                <section class="sb-block h-100 small">
                    <h2 class="h6 fw-bold"><?= e(__('delivery_address')) ?></h2>
                    <b><?= e($o['shipping_name']) ?></b> · <?= e(num($o['shipping_phone'])) ?><br>
                    <?= e($o['shipping_address']) ?><br><span class="text-muted"><?= e($area) ?></span>
                    <?php if ($o['customer_note']): ?><div class="mt-2"><?= e(__('order_note')) ?>: <?= e($o['customer_note']) ?></div><?php endif; ?>
                </section>
            </div>
            <div class="col-md-6">
                <section class="sb-block h-100 small">
                    <h2 class="h6 fw-bold"><?= e(__('payment')) ?></h2>
                    <div class="d-flex justify-content-between"><span><?= e(__('pm_' . $o['payment_method'])) ?></span><?= OrderView::payBadge($o['payment_status']) ?></div>
                    <?php foreach ($payments as $p): if (!$p['transaction_id']) { continue; } ?>
                        <div class="text-muted mt-1"><?= e(__('trx_id')) ?>: <?= e($p['transaction_id']) ?> · <?= e(__('pay_st_' . $p['status'])) ?></div>
                    <?php endforeach; ?>
                </section>
            </div>
        </div>

        <?php if ($canCancel): ?>
            <section class="sb-block mt-3" id="cancel">
                <h2 class="h6 fw-bold text-danger"><i class="bi bi-x-circle"></i> <?= e(__('cancel_order')) ?></h2>
                <form method="post" action="<?= e(url('account/orders/' . $o['order_no'] . '/cancel')) ?>" class="d-flex flex-wrap gap-2 align-items-end"
                      data-confirm="<?= e(__('cancel_confirm')) ?>" data-confirm-danger="1">
                    <?= csrf_field() ?>
                    <div class="flex-grow-1">
                        <label class="form-label small" for="cr"><?= e(__('cancel_reason_optional')) ?></label>
                        <input class="form-control form-control-sm" id="cr" name="reason" maxlength="255">
                    </div>
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> <?= e(__('cancel_order')) ?></button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($refunds): ?>
            <section class="sb-block mt-3">
                <h2 class="h6 fw-bold"><?= e(__('returns_refunds')) ?></h2>
                <?php foreach ($refunds as $r): ?>
                    <div class="d-flex justify-content-between flex-wrap gap-2 small border-bottom py-2">
                        <span><?= e($r['product_name'] ?? __('whole_order')) ?> × <?= e($r['quantity'] !== null ? num($r['quantity']) : '—') ?> — <?= e(money($r['amount'])) ?></span>
                        <span class="badge text-bg-<?= ['requested' => 'warning', 'approved' => 'info', 'processed' => 'success', 'rejected' => 'danger'][$r['status']] ?>"><?= e(__('rf_' . $r['status'])) ?></span>
                    </div>
                    <?php if ($r['admin_note'] && $r['status'] !== 'requested'): ?><div class="small text-muted"><?= e($r['admin_note']) ?></div><?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($canReturn && $returnable): ?>
            <section class="sb-block mt-3" id="return">
                <h2 class="h6 fw-bold"><?= e(__('request_return')) ?></h2>
                <p class="small text-muted"><?= e(__('return_help', ['n' => num((int) setting('return_window_days', 7))])) ?></p>
                <form method="post" action="<?= e(url('account/orders/' . $o['order_no'] . '/return')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php foreach ($returnable as $it): $max = (int) $it['quantity'] - (int) $it['returned_qty']; ?>
                        <div class="d-flex justify-content-between align-items-center gap-2 py-1 small">
                            <label for="rq<?= (int) $it['id'] ?>" class="text-truncate"><?= e(OrderView::itemName($it)) ?></label>
                            <select class="form-select form-select-sm w-auto" id="rq<?= (int) $it['id'] ?>" name="qty[<?= (int) $it['id'] ?>]">
                                <?php for ($i = 0; $i <= $max; $i++): ?><option value="<?= $i ?>" <?= old('qty') === (string) $i ? 'selected' : '' ?>><?= e(num($i)) ?></option><?php endfor; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <?= $err('qty') ?>
                    <div class="row g-2 mt-1">
                        <div class="col-12">
                            <label class="form-label small" for="rr"><?= e(__('return_reason')) ?> *</label>
                            <textarea class="form-control form-control-sm<?= isset($errors['reason']) ? ' is-invalid' : '' ?>" id="rr" name="reason" rows="2" maxlength="500"><?= e(old('reason')) ?></textarea>
                            <?= $err('reason') ?>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label small" for="rm"><?= e(__('refund_method')) ?> *</label>
                            <select class="form-select form-select-sm" id="rm" name="refund_method">
                                <?php foreach (Order::REFUND_METHODS as $m): ?><option value="<?= $m ?>" <?= old('refund_method') === $m ? 'selected' : '' ?>><?= e(__('rm_' . $m)) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label small" for="ra"><?= e(__('refund_account')) ?></label>
                            <input class="form-control form-control-sm<?= isset($errors['account']) ? ' is-invalid' : '' ?>" id="ra" name="account" maxlength="255" value="<?= e(old('account')) ?>" placeholder="<?= e(__('refund_account_ph')) ?>">
                            <?= $err('account') ?>
                        </div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm mt-2"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('submit_return')) ?></button>
                </form>
            </section>
        <?php endif; ?>

        <?php $notes = array_filter($history, static fn($h) => $h['note']); if ($notes): ?>
            <section class="sb-block mt-3 small">
                <h2 class="h6 fw-bold"><?= e(__('order_updates')) ?></h2>
                <?php foreach (array_reverse($notes) as $h): ?>
                    <div class="border-bottom py-1"><span class="text-muted"><?= e(dt($h['created_at'], 'd M, h:i A')) ?></span> — <?= e($h['note']) ?></div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
