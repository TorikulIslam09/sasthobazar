<?php
/** My profile + change password. Vars: $user, $input, $errors */
$cls = static fn(string $k) => 'form-control' . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
?>
<h1 class="h4 mb-3"><i class="bi bi-person-gear"></i> <?= e(__('my_profile')) ?></h1>

<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="<?= e(url('admin/profile')) ?>" class="card border-0 shadow-sm h-100" novalidate>
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><?= e(__('my_profile')) ?></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="name"><?= e(__('name')) ?></label>
                    <input class="<?= $cls('name') ?>" id="name" name="name" maxlength="150" value="<?= e($input['name']) ?>" required>
                    <?= $err('name') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= e(__('email')) ?></label>
                    <input class="form-control" value="<?= e($user['email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="phone"><?= e(__('phone_optional')) ?></label>
                    <input class="<?= $cls('phone') ?>" id="phone" name="phone" inputmode="tel" value="<?= e($input['phone']) ?>">
                    <?= $err('phone') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lang"><?= e(__('preferred_language')) ?></label>
                    <select class="form-select" id="lang" name="lang">
                        <option value="bn" <?= $user['lang'] === 'bn' ? 'selected' : '' ?>><?= e(__('bangla')) ?></option>
                        <option value="en" <?= $user['lang'] === 'en' ? 'selected' : '' ?>><?= e(__('english')) ?></option>
                    </select>
                </div>
                <div class="small text-muted"><?= e(__('role')) ?>: <strong><?= e(Auth::roleName()) ?></strong> · <?= e(__('last_login')) ?>: <?= e(dt($user['last_login_at'])) ?></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-brand"><?= e(__('save')) ?></button></div>
        </form>
    </div>
    <div class="col-lg-6">
        <form method="post" action="<?= e(url('admin/profile/password')) ?>" class="card border-0 shadow-sm h-100" novalidate>
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><?= e(__('change_password')) ?></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="current_password"><?= e(__('current_password')) ?></label>
                    <input class="<?= $cls('current_password') ?>" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    <?= $err('current_password') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password"><?= e(__('new_password')) ?></label>
                    <input class="<?= $cls('password') ?>" type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                    <?= $err('password') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation"><?= e(__('confirm_password')) ?></label>
                    <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-warning"><i class="bi bi-key"></i> <?= e(__('change_password')) ?></button></div>
        </form>
    </div>
</div>
