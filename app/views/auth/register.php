<?php
/** Customer registration. Vars: $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f) => isset($errors[$f]) ? 'form-control is-invalid' : 'form-control';
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h1 class="h5 mb-3 text-center"><?= e(__('register')) ?></h1>
        <form method="post" action="<?= e(url('register')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="name"><?= e(__('name')) ?></label>
                <input class="<?= $cls('name') ?>" id="name" name="name" value="<?= e(old('name')) ?>" required maxlength="150">
                <?= $err('name') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone"><?= e(__('phone')) ?></label>
                <input class="<?= $cls('phone') ?>" id="phone" name="phone" value="<?= e(old('phone')) ?>" required inputmode="tel" placeholder="01XXXXXXXXX">
                <?= $err('phone') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email"><?= e(__('email_optional')) ?></label>
                <input class="<?= $cls('email') ?>" type="email" id="email" name="email" value="<?= e(old('email')) ?>">
                <?= $err('email') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password"><?= e(__('password')) ?></label>
                <input class="<?= $cls('password') ?>" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <?= $err('password') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation"><?= e(__('confirm_password')) ?></label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <button class="btn btn-brand w-100"><?= e(__('register')) ?></button>
        </form>
        <p class="text-center small mt-3 mb-0"><?= e(__('have_account')) ?> <a href="<?= e(url('login')) ?>"><?= e(__('login')) ?></a></p>
    </div>
</div>
