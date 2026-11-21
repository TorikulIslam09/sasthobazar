<?php
/** Settings → Checkout (guest checkout). Vars: $input, $smsLive, $mailLive, $guestOrders */
$ro = !can('settings.edit');
?>
<?= View::partial('admin/settings/_nav', ['active' => 'checkout']) ?>
<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('admin/settings/checkout')) ?>" class="card border-0 shadow-sm">
            <?= csrf_field() ?>
            <fieldset <?= $ro ? 'disabled' : '' ?>>
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-person-badge"></i> <?= e(__('guest_checkout')) ?></h2>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="guest_checkout_enabled" value="1" id="gce" <?= $input['guest_checkout_enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="gce"><?= e(__('guest_checkout_enable')) ?></label>
                </div>
                <div class="form-text mb-3"><?= e(__('guest_checkout_enable_help')) ?></div>

                <label class="form-label fw-semibold"><?= e(__('guest_default_tab')) ?></label>
                <div class="d-flex gap-3 mb-1">
                    <?php foreach (['phone' => 'guest_by_phone', 'email' => 'guest_by_email'] as $v => $lbl): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="guest_default_contact" value="<?= $v ?>" id="gdc<?= $v ?>" <?= $input['guest_default_contact'] === $v ? 'checked' : '' ?>>
                            <label class="form-check-label" for="gdc<?= $v ?>"><?= e(__($lbl)) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-text mb-3"><?= e(__('guest_default_tab_help')) ?></div>

                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch" name="guest_otp_required" value="1" id="gor" <?= $input['guest_otp_required'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="gor"><?= e(__('guest_otp_required')) ?></label>
                </div>
                <div class="form-text"><?= e(__('guest_otp_required_help')) ?></div>
                <?php if ($input['guest_otp_required'] && (!$smsLive || !$mailLive)): ?>
                    <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i>
                        <?= e(__('guest_otp_mock_warning', ['which' => implode(' / ', array_filter([!$smsLive ? 'SMS' : null, !$mailLive ? 'Email' : null]))])) ?>
                        <a href="<?= e(url('admin/settings/sms')) ?>"><?= e(__('set_sms')) ?></a> · <a href="<?= e(url('admin/settings/email')) ?>"><?= e(__('set_email')) ?></a>
                    </div>
                <?php endif; ?>
            </div>
            </fieldset>
            <?php if (!$ro): ?><div class="card-footer bg-white text-end"><button class="btn btn-primary"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm small">
            <div class="card-body">
                <div class="d-flex justify-content-between"><span><?= e(__('guest_orders_30d')) ?></span><b><?= e(num($guestOrders)) ?></b></div>
                <hr>
                <p class="mb-1 fw-semibold"><?= e(__('guest_how_title')) ?></p>
                <ol class="ps-3 mb-0 text-muted">
                    <li><?= e(__('guest_how_1')) ?></li>
                    <li><?= e(__('guest_how_2')) ?></li>
                    <li><?= e(__('guest_how_3')) ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>
