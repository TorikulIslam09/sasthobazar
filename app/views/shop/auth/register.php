<?php
/** Customer registration. Vars: $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f) => isset($errors[$f]) ? 'form-control is-invalid' : 'form-control';
?>
<div class="sb-auth">
    <div class="sb-block">
        <div class="sb-tabsw<?= Guest::enabled() ? ' sb-tabsw-3' : '' ?>" role="tablist">
            <?php if (Guest::enabled()): ?><a href="<?= e(url('login?tab=guest')) ?>" role="tab" aria-selected="false"><?= e(__('guest_tab')) ?></a><?php endif; ?>
            <a href="<?= e(url('login?tab=login')) ?>" role="tab" aria-selected="false"><?= e(__('login')) ?></a>
            <a href="<?= e(url('register')) ?>" class="on" role="tab" aria-selected="true"><?= e(__('register')) ?></a>
        </div>
        <h1 class="h5 mb-1"><?= e(__('create_account')) ?></h1>
        <p class="text-muted small mb-3"><?= e(__('register_subtitle')) ?></p>
        <form method="post" action="<?= e(url('register')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="name"><?= e(__('name')) ?> *</label>
                <input class="<?= $cls('name') ?>" id="name" name="name" value="<?= e(old('name')) ?>" required maxlength="150" autocomplete="name">
                <?= $err('name') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone"><?= e(__('phone')) ?> *</label>
                <input class="<?= $cls('phone') ?>" id="phone" name="phone" value="<?= e(old('phone')) ?>" required inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" maxlength="20">
                <?= $err('phone') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email"><?= e(__('email_optional')) ?></label>
                <input class="<?= $cls('email') ?>" type="email" id="email" name="email" value="<?= e(old('email')) ?>" maxlength="191" autocomplete="email">
                <?= $err('email') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password"><?= e(__('password')) ?> *</label>
                <div class="input-group has-validation">
                    <input class="<?= $cls('password') ?>" type="password" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="<?= e(__('show_password')) ?>"><i class="bi bi-eye"></i></button>
                </div>
                <div class="form-text"><?= e(__('password_hint')) ?></div>
                <?= $err('password') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation"><?= e(__('confirm_password')) ?> *</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" maxlength="72" autocomplete="new-password">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input<?= isset($errors['agree']) ? ' is-invalid' : '' ?>" type="checkbox" name="agree" value="1" id="agree" <?= old('agree') ? 'checked' : '' ?>>
                <label class="form-check-label small" for="agree"><?= e(__('agree_terms')) ?></label>
                <?= $err('agree') ?>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-person-plus"></i> <?= e(__('register')) ?></button>
        </form>
        <p class="text-center small mt-3 mb-0"><?= e(__('have_account')) ?> <a href="<?= e(url('login')) ?>"><?= e(__('login')) ?></a></p>
    </div>
</div>
