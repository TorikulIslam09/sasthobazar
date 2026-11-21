<?php
/**
 * Checkout. Vars: $cart, $totals, $addresses, $selected, $methods, $method, $old, $errors,
 *                 $divisions, $districts, $upazilas, $prescriptions, $token
 *       $guest = null (signed-in customer) | ['contact' => [type, value], 'otp' => bool, 'verified' => bool, 'channel' => sms|email]
 */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f, string $d = '') => (string) ($old[$f] ?? $d);
$user = Auth::user() ?? ['name' => '', 'phone' => ''];
$guest = $guest ?? null;
$gPhoneLocked = $guest && $guest['contact']['type'] === 'phone';
if ($gPhoneLocked) {
    $user['phone'] = $guest['contact']['value'];
}
$otpNeeded = $guest && $guest['otp'] && !$guest['verified'];
$locName = static fn(array $r) => tr($r['name_bn'], $r['name_en']);
$methodIcons = ['cod' => 'bi-cash-coin', 'bkash' => 'bi-phone', 'nagad' => 'bi-phone', 'rocket' => 'bi-phone'];
$t = $totals;
$sum = CheckoutController::summaryJson($t);
$optIcons = ['standard' => 'bi-truck', 'express' => 'bi-lightning-charge', 'pickup' => 'bi-shop'];
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('cart')) ?>"><?= e(__('cart')) ?></a> › <span><?= e(__('checkout')) ?></span></nav>

<?php if (isset($errors['_'])): ?><div class="alert alert-danger"><?= e($errors['_']) ?></div><?php endif; ?>

<form method="post" action="<?= e(url('checkout')) ?>" enctype="multipart/form-data" class="sb-cart" id="checkoutForm"
      data-quote-url="<?= e(url('checkout/quote')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="checkout_token" value="<?= e($token) ?>">
    <div class="min-w-0">
        <?php if ($guest): $gc = $guest['contact']; ?>
        <!-- Guest: contact + verification -->
        <section class="sb-block" id="guestContact">
            <h2 class="sb-step-title"><span><i class="bi bi-person-check"></i></span> <?= e(__('guest_contact_title')) ?></h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="gcMain"><?= e(__($gc['type'] === 'phone' ? 'phone' : 'email')) ?> <i class="bi bi-lock-fill text-muted small" title="<?= e(__('guest_locked')) ?>"></i></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi <?= $gc['type'] === 'phone' ? 'bi-phone' : 'bi-envelope' ?>"></i></span>
                        <input class="form-control sb-lock" id="gcMain" value="<?= e($gc['value']) ?>" readonly aria-readonly="true">
                        <a class="btn btn-outline-secondary" href="<?= e(url('login?tab=guest')) ?>" title="<?= e(__('change')) ?>"><i class="bi bi-pencil"></i></a>
                    </div>
                </div>
                <?php if ($gc['type'] === 'phone'): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="gcEmail"><?= e(__('email')) ?> <span class="text-muted small">(<?= e(__('optional')) ?>)</span></label>
                        <input class="<?= $cls('email') ?>" type="email" id="gcEmail" name="email" value="<?= e($val('email')) ?>" maxlength="191" autocomplete="email" placeholder="name@example.com">
                        <div class="form-text"><?= e(__('guest_email_optional_help')) ?></div>
                        <?= $err('email') ?>
                    </div>
                <?php else: ?>
                    <div class="col-md-6 small text-muted align-self-end"><i class="bi bi-info-circle"></i> <?= e(__('guest_email_mobile_note')) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($guest['otp']): ?>
                <div class="sb-otp-box mt-3<?= $guest['verified'] ? ' ok' : '' ?>" data-otp-box
                     data-send-url="<?= e(url('checkout/otp/send')) ?>" data-verify-url="<?= e(url('checkout/otp/verify')) ?>">
                    <div data-otp-done <?= $guest['verified'] ? '' : 'hidden' ?>><i class="bi bi-patch-check-fill text-success"></i> <b><?= e(__($gc['type'] === 'phone' ? 'otp_phone_verified' : 'otp_email_verified')) ?></b></div>
                    <div data-otp-todo <?= $guest['verified'] ? 'hidden' : '' ?>>
                        <div class="small mb-2"><i class="bi bi-shield-lock"></i> <?= e(__($gc['type'] === 'phone' ? 'otp_need_phone' : 'otp_need_email')) ?></div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-otp-send><i class="bi bi-send"></i> <span data-otp-send-label><?= e(__('otp_send')) ?></span></button>
                            <input class="form-control form-control-sm sb-otp-input" data-otp-code inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" aria-label="<?= e(__('otp_code')) ?>" hidden>
                            <button type="button" class="btn btn-primary btn-sm" data-otp-verify hidden><i class="bi bi-check2"></i> <?= e(__('otp_verify')) ?></button>
                        </div>
                        <div class="small mt-2" data-otp-msg role="status" aria-live="polite"></div>
                    </div>
                    <?= $err('otp') ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <!-- Delivery option -->
        <section class="sb-block">
            <h2 class="sb-step-title"><span><i class="bi bi-truck"></i></span> <?= e(__('delivery_option')) ?></h2>
            <div class="sb-paybox sb-delivery-opts">
                <?php foreach (Shipping::OPTIONS as $op): $avail = $sum['available'][$op] ?? false; ?>
                    <label class="<?= $avail ? '' : 'is-off' ?>" data-option="<?= $op ?>">
                        <input type="radio" name="delivery_option" value="<?= $op ?>" <?= $option === $op ? 'checked' : '' ?> <?= $avail || $option === $op ? '' : 'disabled' ?>>
                        <span><i class="bi <?= $optIcons[$op] ?>"></i> <b><?= e(__('dopt_' . $op)) ?></b>
                            <small class="d-block text-muted"><?= e(__('dopt_' . $op . '_help')) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (setting('store_pickup_address')): ?><p class="small text-muted mb-0" data-pickup-note <?= $option === 'pickup' ? '' : 'hidden' ?>><i class="bi bi-geo-alt"></i> <?= e(setting('store_pickup_address')) ?></p><?php endif; ?>
            <?= $err('delivery_option') ?>
        </section>

        <!-- 1. Address -->
        <section class="sb-block" data-address-section <?= $option === 'pickup' ? 'hidden' : '' ?>>
            <h2 class="sb-step-title"><span>1</span> <?= e(__('delivery_address')) ?></h2>
            <?php if ($addresses): ?>
                <div class="sb-addr-pick">
                    <?php foreach ($addresses as $a): ?>
                        <label>
                            <input type="radio" name="address_id" value="<?= (int) $a['id'] ?>" data-district="<?= (int) $a['district_id'] ?>" <?= $selected === (string) $a['id'] ? 'checked' : '' ?>>
                            <span>
                                <b><?= e($a['recipient_name']) ?></b> · <?= e(num($a['phone'])) ?>
                                <span class="badge text-bg-light border ms-1"><?= e(Address::labelText($a['label'])) ?></span>
                                <small class="d-block text-muted"><?= e($a['address_line']) ?>, <?= e(Address::areaLine($a)) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <label>
                        <input type="radio" name="address_id" value="new" <?= $selected === 'new' ? 'checked' : '' ?>>
                        <span><i class="bi bi-plus-circle"></i> <?= e(__('addr_new')) ?></span>
                    </label>
                </div>
                <?= $err('address_id') ?>
            <?php else: ?>
                <input type="hidden" name="address_id" value="new">
            <?php endif; ?>

            <div class="sb-new-addr" data-new-address <?= $selected === 'new' || !$addresses ? '' : 'hidden' ?>>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="rn"><?= e(__('addr_recipient')) ?> *</label>
                        <input class="<?= $cls('recipient_name') ?>" id="rn" name="recipient_name" value="<?= e($val('recipient_name', $user['name'])) ?>" maxlength="150" autocomplete="name">
                        <?= $err('recipient_name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ph"><?= e(__('phone')) ?> *</label>
                        <input class="<?= $cls('phone') ?><?= $gPhoneLocked ? ' sb-lock' : '' ?>" id="ph" name="phone" value="<?= e($gPhoneLocked ? (string) $user['phone'] : $val('phone', (string) $user['phone'])) ?>" inputmode="tel" maxlength="20" placeholder="01XXXXXXXXX" autocomplete="tel" <?= $gPhoneLocked ? 'readonly' : '' ?>>
                        <?php if ($guest && !$gPhoneLocked): ?><div class="form-text"><?= e(__('guest_delivery_phone_help')) ?></div><?php endif; ?>
                        <?= $err('phone') ?>
                    </div>
                </div>
                <div class="row g-3 mt-0" data-location-picker data-api="<?= e(url('api/locations')) ?>">
                    <div class="col-md-4">
                        <label class="form-label" for="dv"><?= e(__('division')) ?> *</label>
                        <select class="<?= $cls('division_id', 'form-select') ?>" id="dv" name="division_id" data-level="division">
                            <option value=""><?= e(__('select')) ?></option>
                            <?php foreach ($divisions as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('division_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option><?php endforeach; ?>
                        </select>
                        <?= $err('division_id') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ds"><?= e(__('district')) ?> *</label>
                        <select class="<?= $cls('district_id', 'form-select') ?>" id="ds" name="district_id" data-level="district" <?= $districts ? '' : 'disabled' ?>>
                            <option value=""><?= e(__('select')) ?></option>
                            <?php foreach ($districts as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('district_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option><?php endforeach; ?>
                        </select>
                        <?= $err('district_id') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="up"><?= e(__('upazila')) ?></label>
                        <select class="<?= $cls('upazila_id', 'form-select') ?>" id="up" name="upazila_id" data-level="upazila" <?= $upazilas ? '' : 'disabled' ?>>
                            <option value=""><?= e(__('select')) ?></option>
                            <?php foreach ($upazilas as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('upazila_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option><?php endforeach; ?>
                        </select>
                        <?= $err('upazila_id') ?>
                    </div>
                </div>
                <div class="row g-3 mt-0">
                    <div class="col-md-9">
                        <label class="form-label" for="al"><?= e(__('addr_line')) ?> *</label>
                        <input class="<?= $cls('address_line') ?>" id="al" name="address_line" value="<?= e($val('address_line')) ?>" maxlength="255" placeholder="<?= e(__('addr_line_ph')) ?>" autocomplete="street-address">
                        <?= $err('address_line') ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="pc"><?= e(__('postcode')) ?></label>
                        <input class="<?= $cls('postcode') ?>" id="pc" name="postcode" value="<?= e($val('postcode')) ?>" maxlength="4" inputmode="numeric">
                        <?= $err('postcode') ?>
                    </div>
                </div>
                <?php if (!$guest): ?>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="save_address" value="1" id="saveAddr" <?= $val('save_address', '1') ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="saveAddr"><?= e(__('addr_save_book')) ?></label>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 2. Prescription (Rx items only) -->
        <?php if ($cart['has_rx']): ?>
            <section class="sb-block">
                <h2 class="sb-step-title"><span>2</span> <?= e(__('prescription')) ?></h2>
                <p class="small text-muted"><?= e(__('rx_checkout_help')) ?></p>
                <?php if ($prescriptions): ?>
                    <label class="form-label" for="rxExisting"><?= e(__('rx_use_previous')) ?></label>
                    <select class="form-select mb-2" id="rxExisting" name="prescription_id">
                        <option value=""><?= e(__('rx_upload_new')) ?></option>
                        <?php foreach ($prescriptions as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $val('prescription_id') === (string) $p['id'] ? 'selected' : '' ?>>
                                #<?= e(num($p['id'])) ?> — <?= e(dt($p['created_at'], 'd M Y')) ?> — <?= e(__('rx_status_' . $p['status'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <div data-rx-upload>
                    <label class="form-label" for="rxFile"><?= e(__('rx_file')) ?> *</label>
                    <input class="<?= $cls('prescription') ?>" type="file" id="rxFile" name="prescription" accept="image/jpeg,image/png,image/webp,application/pdf">
                    <div class="form-text"><?= e(__('rx_rules', ['mb' => num((int) setting('prescription_max_size_mb', 5))])) ?></div>
                    <label class="form-label mt-2" for="patient"><?= e(__('rx_patient_name')) ?></label>
                    <input class="form-control" id="patient" name="patient_name" maxlength="150" value="<?= e($val('patient_name')) ?>">
                </div>
                <?= $err('prescription') ?>
            </section>
        <?php endif; ?>

        <!-- 3. Payment -->
        <section class="sb-block">
            <h2 class="sb-step-title"><span><?= $cart['has_rx'] ? 3 : 2 ?></span> <?= e(__('payment_method')) ?></h2>
            <?php if (!$methods): ?>
                <div class="alert alert-warning small"><?= e(__('pay_method_unavailable')) ?></div>
            <?php endif; ?>
            <div class="sb-paybox">
                <?php foreach ($methods as $m): ?>
                    <label>
                        <input type="radio" name="payment_method" value="<?= e($m) ?>" <?= $method === $m ? 'checked' : '' ?>>
                        <span><i class="bi <?= e($methodIcons[$m]) ?>"></i> <?= e(__('pm_' . $m)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= $err('payment_method') ?>
            <?php foreach (Order::MANUAL_METHODS as $m): if (!in_array($m, $methods, true)) { continue; } ?>
                <div class="sb-manual-pay" data-manual="<?= e($m) ?>" <?= $method === $m ? '' : 'hidden' ?>>
                    <ol class="small mb-2">
                        <li><?= __('manual_step1', ['method' => '<b>' . e(__('pm_' . $m)) . '</b>', 'type' => e(__('acct_' . setting($m . '_account_type', 'personal'))), 'number' => '<b class="user-select-all">' . e(setting($m . '_number', '')) . '</b>']) ?></li>
                        <li><?= __('manual_step2', ['amount' => '<b data-pay-amount>' . e(money($t['grand_total'])) . '</b>']) ?></li>
                        <li><?= e(__('manual_step3')) ?></li>
                    </ol>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label" for="trx_<?= e($m) ?>"><?= e(__('trx_id')) ?> *</label>
                            <input class="<?= $cls('trx_id') ?> text-uppercase" id="trx_<?= e($m) ?>" name="trx_id" value="<?= e($method === $m ? $val('trx_id') : '') ?>" maxlength="20" autocomplete="off" <?= $method === $m ? '' : 'disabled' ?>>
                            <?= $method === $m ? $err('trx_id') : '' ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="payer_<?= e($m) ?>"><?= e(__('payer_number')) ?> *</label>
                            <input class="<?= $cls('payer_account') ?>" id="payer_<?= e($m) ?>" name="payer_account" value="<?= e($method === $m ? $val('payer_account') : '') ?>" inputmode="tel" maxlength="20" placeholder="01XXXXXXXXX" <?= $method === $m ? '' : 'disabled' ?>>
                            <?= $method === $m ? $err('payer_account') : '' ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <label class="form-label mt-3" for="note"><?= e(__('order_note')) ?></label>
            <textarea class="form-control" id="note" name="customer_note" rows="2" maxlength="500" placeholder="<?= e(__('order_note_ph')) ?>"><?= e($val('customer_note')) ?></textarea>
        </section>
    </div>

    <!-- Summary -->
    <aside class="sb-block sb-sum">
        <h2 class="h6 fw-bold mb-3"><?= e(__('order_summary')) ?> <span class="text-muted fw-normal small">(<?= e(__('n_items', ['n' => num($cart['items'])])) ?>)</span></h2>
        <ul class="sb-sum-items">
            <?php foreach ($cart['lines'] as $l): ?>
                <li><span class="text-truncate"><?= e($l['name']) ?><?= $l['variant'] !== '' ? ' — ' . e($l['variant']) : '' ?> × <?= e(num($l['quantity'])) ?></span><span><?= e(money($l['line_total'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <div class="sb-sum-rows" data-summary>
            <div><span><?= e(__('subtotal')) ?></span><span data-sum="subtotal"><?= e(money($t['subtotal'])) ?></span></div>
            <?php if ($t['bogo_discount'] > 0): ?><div class="text-success"><span><?= e(__('bogo_discount')) ?></span><span>-<?= e(money($t['bogo_discount'])) ?></span></div><?php endif; ?>
            <?php if ($t['first_order_discount'] > 0): ?><div class="text-success"><span><?= e(__('first_order_discount')) ?> <small class="d-block text-muted"><?= e(Promo::title($t['first_order']['offer'])) ?></small></span><span>-<?= e(money($t['first_order_discount'])) ?></span></div><?php endif; ?>
            <div class="text-success" <?= $t['coupon_discount'] > 0 ? '' : 'hidden' ?> data-row="discount"><span><?= e(__('coupon_discount')) ?> <?= e(Cart::couponCode() ?? '') ?></span><span data-sum="discount">-<?= e(money($t['coupon_discount'])) ?></span></div>
            <div><span><?= e(__('delivery_charge')) ?> <small class="text-muted d-block" data-sum="eta"><?= e($sum['eta']) ?></small></span>
                <span data-sum="shipping"><?= e($sum['shipping']) ?></span></div>
            <ul class="sb-ship-groups" data-ship-groups <?= count($sum['groups']) > 1 && $sum['ok'] ? '' : 'hidden' ?>>
                <?php foreach ($sum['groups'] as $g): ?>
                    <li><span><?= e($g['seller']) ?> <small class="text-muted"><?= e($g['zone']) ?></small></span><span><?= e($g['fee']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($t['vat_total'] > 0 && !$t['vat_inclusive']): ?><div><span><?= e(Tax::label()) ?></span><span><?= e(money($t['vat_total'])) ?></span></div><?php endif; ?>
            <div <?= $t['cod_charge'] > 0 ? '' : 'hidden' ?> data-row="cod"><span><?= e(__('cod_charge')) ?></span><span data-sum="cod_charge"><?= e(money($t['cod_charge'])) ?></span></div>
            <div class="sb-sum-total"><span><?= e(__('total')) ?></span><span data-sum="grand_total"><?= e(money($t['grand_total'])) ?></span></div>
            <?php if ($t['vat_total'] > 0 && $t['vat_inclusive']): ?><div class="small text-muted"><span><?= e(__('vat_included', ['label' => Tax::label()])) ?></span><span><?= e(money($t['vat_total'])) ?></span></div><?php endif; ?>
            <div class="small text-muted" data-sum="free_hint"><?= e(implode(' · ', array_filter(array_column($sum['groups'], 'hint')))) ?></div>
            <?php if (count($sum['groups']) > 1): ?><div class="small text-muted" data-multi-note><i class="bi bi-box-seam"></i> <?= e(__('ships_in_parts', ['n' => num(count($sum['groups']))])) ?></div><?php endif; ?>
        </div>
        <?php if ($t['coupon_error']): ?><div class="small text-danger mb-2"><?= e($t['coupon_error']) ?></div><?php endif; ?>
        <?= $err('coupon') ?>
        <div class="form-check my-2">
            <input class="form-check-input<?= isset($errors['agree']) ? ' is-invalid' : '' ?>" type="checkbox" name="agree" value="1" id="agreeChk" <?= $val('agree') ? 'checked' : '' ?>>
            <label class="form-check-label small" for="agreeChk"><?= e(__('agree_terms')) ?></label>
            <?= $err('agree') ?>
        </div>
        <button class="btn btn-primary w-100 btn-lg" <?= $methods ? '' : 'disabled' ?>><i class="bi bi-bag-check"></i> <?= e(__('place_order')) ?></button>
        <?php if ($cart['has_rx']): ?><p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle"></i> <?= e(__('rx_after_order')) ?></p><?php endif; ?>
    </aside>
</form>
