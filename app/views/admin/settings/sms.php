<?php
/** SMS settings. Vars: $input, $errors, $recent */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$ro = !can('settings.edit');
?>
<?= View::partial('admin/settings/_nav', ['active' => 'sms']) ?>
<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('admin/settings/sms')) ?>" class="card border-0 shadow-sm" novalidate>
            <?= csrf_field() ?>
            <fieldset <?= $ro ? 'disabled' : '' ?>>
            <div class="card-body row g-3">
                <div class="col-12">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="sms_enabled" value="1" id="se" <?= $val('sms_enabled') ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="se"><?= e(__('sms_enable')) ?></label></div>
                    <div class="form-text"><?= e(__('sms_enable_help')) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sd"><?= e(__('gateway')) ?></label>
                    <select class="form-select" id="sd" name="sms_driver">
                        <?php foreach (Messenger::SMS_DRIVERS as $d): ?><option value="<?= $d ?>" <?= $val('sms_driver') === $d ? 'selected' : '' ?>><?= e(__('sms_driver_' . $d)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" data-show-when="sms_driver:http">
                    <label class="form-label" for="sm"><?= e(__('http_method')) ?></label>
                    <select class="form-select" id="sm" name="sms_api_method">
                        <?php foreach (['GET', 'POST'] as $m): ?><option value="<?= $m ?>" <?= $val('sms_api_method') === $m ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12" data-show-when="sms_driver:http">
                    <label class="form-label" for="su"><?= e(__('sms_api_url')) ?> *</label>
                    <input class="<?= $cls('sms_api_url') ?> font-monospace small" id="su" name="sms_api_url" value="<?= e($val('sms_api_url')) ?>" maxlength="500" placeholder="https://api.example.com/send?api_key={api_key}&senderid={sender}&number={to}&message={message}">
                    <div class="form-text"><?= e(__('sms_api_url_help')) ?></div>
                    <?= $err('sms_api_url') ?>
                </div>
                <div class="col-md-6" data-show-when="sms_driver:http">
                    <label class="form-label" for="sk"><?= e(__('api_key')) ?></label>
                    <input class="<?= $cls('sms_api_key') ?>" type="password" id="sk" name="sms_api_key" value="" autocomplete="new-password" maxlength="255" placeholder="<?= e($val('sms_api_key_set') ? __('secret_saved') : '') ?>">
                    <?php if ($val('sms_api_key_set')): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_sms_api_key" value="1" id="csk"><label class="form-check-label small" for="csk"><?= e(__('clear_saved')) ?></label></div><?php endif; ?>
                </div>
                <div class="col-md-6" data-show-when="sms_driver:http">
                    <label class="form-label" for="ss"><?= e(__('sender_id')) ?></label>
                    <input class="<?= $cls('sms_sender_id') ?>" id="ss" name="sms_sender_id" value="<?= e($val('sms_sender_id')) ?>" maxlength="30">
                </div>
                <div class="col-md-6" data-show-when="sms_driver:http">
                    <label class="form-label" for="sw"><?= e(__('success_keyword')) ?></label>
                    <input class="<?= $cls('sms_success_keyword') ?>" id="sw" name="sms_success_keyword" value="<?= e($val('sms_success_keyword')) ?>" maxlength="60" placeholder="SUCCESS">
                    <div class="form-text"><?= e(__('success_keyword_help')) ?></div>
                </div>
            </div>
            <?php if (!$ro): ?><div class="card-footer bg-white"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
            </fieldset>
        </form>
    </div>
    <div class="col-lg-5">
        <?php if (can('settings.edit')): ?>
            <form method="post" action="<?= e(url('admin/settings/test')) ?>" class="card border-0 shadow-sm mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="channel" value="sms">
                <div class="card-header bg-white fw-semibold"><?= e(__('send_test_sms')) ?></div>
                <div class="card-body d-flex gap-2">
                    <input class="form-control form-control-sm" name="to" inputmode="tel" placeholder="01XXXXXXXXX" required aria-label="<?= e(__('phone')) ?>">
                    <button class="btn btn-outline-primary btn-sm text-nowrap"><i class="bi bi-send"></i> <?= e(__('send')) ?></button>
                </div>
            </form>
        <?php endif; ?>
        <?= View::partial('admin/settings/_recent_messages', ['recent' => $recent]) ?>
    </div>
</div>
