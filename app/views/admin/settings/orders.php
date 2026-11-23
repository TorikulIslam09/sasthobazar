<?php
/** Orders & payments settings tab. Vars: $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => is_bool($input[$f] ?? null) ? ($input[$f] ? '1' : '') : (string) ($input[$f] ?? '');
$ro = !can('settings.edit');
?>
<?= View::partial('admin/settings/_nav', ['active' => 'orders']) ?>
<form method="post" action="<?= e(url('admin/settings/orders')) ?>" novalidate>
    <?= csrf_field() ?>
    <fieldset <?= $ro ? 'disabled' : '' ?>>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><?= e(__('payment_methods')) ?></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="cod_enabled" value="1" id="cod" <?= $val('cod_enabled') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cod"><?= e(__('pm_cod')) ?></label>
                    </div>
                    <?php foreach (Order::MANUAL_METHODS as $m): ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="<?= $m ?>_enabled" value="1" id="<?= $m ?>On" <?= $val($m . '_enabled') ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="<?= $m ?>On"><?= e(__('pm_' . $m)) ?> (<?= e(__('manual_trx')) ?>)</label>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-7">
                                    <label class="form-label small" for="<?= $m ?>Num"><?= e(__('pay_receive_number')) ?></label>
                                    <input class="<?= $cls($m . '_number') ?> form-control-sm" id="<?= $m ?>Num" name="<?= $m ?>_number" value="<?= e($val($m . '_number')) ?>" inputmode="tel" maxlength="20" placeholder="01XXXXXXXXX">
                                    <?= $err($m . '_number') ?>
                                </div>
                                <div class="col-5">
                                    <label class="form-label small" for="<?= $m ?>Type"><?= e(__('account_type')) ?></label>
                                    <select class="form-select form-select-sm" id="<?= $m ?>Type" name="<?= $m ?>_account_type">
                                        <?php foreach (AdminSettingController::ACCOUNT_TYPES as $t): ?><option value="<?= $t ?>" <?= $val($m . '_account_type') === $t ? 'selected' : '' ?>><?= e(__('acct_' . $t)) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p class="small text-muted mb-0"><?= e(__('manual_pay_help')) ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><?= e(__('order_rules')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-6">
                        <label class="form-label" for="mino"><?= e(__('min_order_amount')) ?></label>
                        <input class="<?= $cls('min_order_amount') ?>" id="mino" name="min_order_amount" value="<?= e($val('min_order_amount')) ?>" inputmode="decimal">
                        <?= $err('min_order_amount') ?>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="maxc"><?= e(__('max_cod_amount')) ?></label>
                        <input class="<?= $cls('max_cod_amount') ?>" id="maxc" name="max_cod_amount" value="<?= e($val('max_cod_amount')) ?>" inputmode="decimal">
                        <div class="form-text"><?= e(__('zero_no_limit')) ?></div>
                        <?= $err('max_cod_amount') ?>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="rwd"><?= e(__('return_window_days')) ?></label>
                        <input class="<?= $cls('return_window_days') ?>" id="rwd" name="return_window_days" value="<?= e($val('return_window_days')) ?>" inputmode="numeric">
                        <?= $err('return_window_days') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ccu"><?= e(__('cancel_until_label')) ?></label>
                        <select class="form-select" id="ccu" name="customer_cancel_until">
                            <?php foreach (array_keys(Order::CANCEL_UNTIL) as $cu): ?><option value="<?= $cu ?>" <?= $val('customer_cancel_until') === $cu ? 'selected' : '' ?>><?= e(__('cancel_until_' . $cu)) ?></option><?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= e(__('cancel_until_help')) ?></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="pre"><?= e(__('order_prefix')) ?></label>
                        <input class="<?= $cls('order_prefix') ?> text-uppercase" id="pre" name="order_prefix" value="<?= e($val('order_prefix')) ?>" maxlength="6">
                        <?= $err('order_prefix') ?>
                    </div>
                    <p class="small text-muted mb-0"><?= e(__('delivery_rates_note')) ?></p>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><?= e(__('set_sellers')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="dcr"><?= e(__('default_commission')) ?> (%)</label>
                        <input class="<?= $cls('default_commission_rate') ?>" id="dcr" name="default_commission_rate" value="<?= e($val('default_commission_rate')) ?>" inputmode="decimal">
                        <div class="form-text"><?= e(__('default_commission_help')) ?></div>
                        <?= $err('default_commission_rate') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="pma"><?= e(__('payout_min_amount')) ?></label>
                        <input class="<?= $cls('payout_min_amount') ?>" id="pma" name="payout_min_amount" value="<?= e($val('payout_min_amount')) ?>" inputmode="decimal">
                        <?= $err('payout_min_amount') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="phd"><?= e(__('payout_hold_days')) ?></label>
                        <input class="<?= $cls('payout_hold_days') ?>" id="phd" name="payout_hold_days" value="<?= e($val('payout_hold_days')) ?>" inputmode="numeric">
                        <div class="form-text"><?= e(__('payout_hold_help')) ?></div>
                        <?= $err('payout_hold_days') ?>
                    </div>
                    <div class="col-6 col-md-3 d-flex flex-column justify-content-center gap-1">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="vendor_registration_enabled" value="1" id="vre" <?= $val('vendor_registration_enabled') ? 'checked' : '' ?>><label class="form-check-label small" for="vre"><?= e(__('vendor_registration_enabled')) ?></label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="vendor_auto_approve" value="1" id="vaa" <?= $val('vendor_auto_approve') ? 'checked' : '' ?>><label class="form-check-label small" for="vaa"><?= e(__('vendor_auto_approve')) ?></label></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!$ro): ?><button class="btn btn-brand mt-3"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button><?php endif; ?>
    </fieldset>
</form>
