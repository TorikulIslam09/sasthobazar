<?php
/** Email settings. Vars: $input, $errors, $recent */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$ro = !can('settings.edit');
?>
<?= View::partial('admin/settings/_nav', ['active' => 'email']) ?>
<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('admin/settings/email')) ?>" class="card border-0 shadow-sm" novalidate>
            <?= csrf_field() ?>
            <fieldset <?= $ro ? 'disabled' : '' ?>>
            <div class="card-body row g-3">
                <div class="col-12">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="mail_enabled" value="1" id="me" <?= $val('mail_enabled') ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="me"><?= e(__('mail_enable')) ?></label></div>
                    <div class="form-text"><?= e(__('mail_enable_help')) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="md"><?= e(__('mail_driver')) ?></label>
                    <select class="form-select" id="md" name="mail_driver">
                        <?php foreach (Messenger::MAIL_DRIVERS as $d): ?><option value="<?= $d ?>" <?= $val('mail_driver') === $d ? 'selected' : '' ?>><?= e(__('mail_driver_' . $d)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"></div>
                <div class="col-md-6" data-show-when="mail_driver:mail smtp">
                    <label class="form-label" for="mfa"><?= e(__('from_address')) ?> *</label>
                    <input class="<?= $cls('mail_from_address') ?>" type="email" id="mfa" name="mail_from_address" value="<?= e($val('mail_from_address')) ?>" maxlength="190" placeholder="no-reply@example.com">
                    <?= $err('mail_from_address') ?>
                </div>
                <div class="col-md-6" data-show-when="mail_driver:mail smtp">
                    <label class="form-label" for="mfn"><?= e(__('from_name')) ?></label>
                    <input class="<?= $cls('mail_from_name') ?>" id="mfn" name="mail_from_name" value="<?= e($val('mail_from_name')) ?>" maxlength="100">
                </div>
                <div class="col-md-8" data-show-when="mail_driver:smtp">
                    <label class="form-label" for="mh"><?= e(__('smtp_host')) ?> *</label>
                    <input class="<?= $cls('mail_host') ?>" id="mh" name="mail_host" value="<?= e($val('mail_host')) ?>" maxlength="190" placeholder="smtp.gmail.com">
                    <?= $err('mail_host') ?>
                </div>
                <div class="col-6 col-md-2" data-show-when="mail_driver:smtp">
                    <label class="form-label" for="mp"><?= e(__('smtp_port')) ?></label>
                    <input class="<?= $cls('mail_port') ?>" id="mp" name="mail_port" value="<?= e($val('mail_port')) ?>" inputmode="numeric">
                    <?= $err('mail_port') ?>
                </div>
                <div class="col-6 col-md-2" data-show-when="mail_driver:smtp">
                    <label class="form-label" for="mx"><?= e(__('encryption')) ?></label>
                    <select class="form-select" id="mx" name="mail_encryption">
                        <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('none')] as $k => $l): ?><option value="<?= $k ?>" <?= $val('mail_encryption') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" data-show-when="mail_driver:smtp">
                    <label class="form-label" for="mu"><?= e(__('username')) ?></label>
                    <input class="<?= $cls('mail_username') ?>" id="mu" name="mail_username" value="<?= e($val('mail_username')) ?>" maxlength="190" autocomplete="off">
                </div>
                <div class="col-md-6" data-show-when="mail_driver:smtp">
                    <label class="form-label" for="mpw"><?= e(__('password')) ?></label>
                    <input class="<?= $cls('mail_password') ?>" type="password" id="mpw" name="mail_password" value="" autocomplete="new-password" maxlength="255" placeholder="<?= e($val('mail_password_set') ? __('secret_saved') : '') ?>">
                    <?php if ($val('mail_password_set')): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_mail_password" value="1" id="cmp"><label class="form-check-label small" for="cmp"><?= e(__('clear_saved')) ?></label></div><?php endif; ?>
                </div>
                <div class="col-12 small text-muted" data-show-when="mail_driver:smtp"><i class="bi bi-info-circle"></i> <?= e(__('smtp_help')) ?></div>
            </div>
            <?php if (!$ro): ?><div class="card-footer bg-white"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
            </fieldset>
        </form>
    </div>
    <div class="col-lg-5">
        <?php if (can('settings.edit')): ?>
            <form method="post" action="<?= e(url('admin/settings/test')) ?>" class="card border-0 shadow-sm mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="channel" value="email">
                <div class="card-header bg-white fw-semibold"><?= e(__('send_test_email')) ?></div>
                <div class="card-body d-flex gap-2">
                    <input class="form-control form-control-sm" type="email" name="to" placeholder="you@example.com" required aria-label="<?= e(__('email')) ?>">
                    <button class="btn btn-outline-primary btn-sm text-nowrap"><i class="bi bi-send"></i> <?= e(__('send')) ?></button>
                </div>
            </form>
        <?php endif; ?>
        <?= View::partial('admin/settings/_recent_messages', ['recent' => $recent]) ?>
    </div>
</div>
