<?php /** Login form for customer / admin / vendor. Vars: $panel, $action, $title */ ?>
<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h1 class="h5 mb-3 text-center"><?= e($title) ?></h1>
        <form method="post" action="<?= e($action) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="login"><?= e(__('email_or_phone')) ?></label>
                <input class="form-control" id="login" name="login" value="<?= e(old('login')) ?>" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password"><?= e(__('password')) ?></label>
                <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-brand w-100"><?= e(__('login')) ?></button>
        </form>
        <?php if ($panel === 'vendor' && setting('vendor_registration_enabled', true)): ?>
            <p class="text-center small mt-3 mb-0"><?= e(__('vendor_no_account')) ?> <a href="<?= e(url('vendor-panel/register')) ?>"><?= e(__('vendor_register')) ?></a></p>
        <?php endif; ?>
        <?php if ($panel === 'customer'): ?>
            <p class="text-center small mt-3 mb-0"><?= e(__('no_account')) ?> <a href="<?= e(url('register')) ?>"><?= e(__('register')) ?></a></p>
        <?php endif; ?>
    </div>
</div>
