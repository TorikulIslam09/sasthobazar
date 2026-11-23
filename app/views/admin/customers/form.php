<?php
/** Add customer. Vars: $old, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f) => 'form-control' . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($old[$f] ?? '');
?>
<div class="mb-3">
    <a href="<?= e(url('admin/customers')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('customers')) ?></a>
    <h1 class="h4 mb-0"><i class="bi bi-person-plus"></i> <?= e(__('cust_add')) ?></h1>
</div>
<form method="post" action="<?= e(url('admin/customers')) ?>" class="card border-0 shadow-sm" style="max-width: 640px" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="cn"><?= e(__('name')) ?> *</label><input class="<?= $cls('name') ?>" id="cn" name="name" value="<?= e($val('name')) ?>" maxlength="150" required><?= $err('name') ?></div>
        <div class="col-md-6"><label class="form-label" for="cp"><?= e(__('phone')) ?> *</label><input class="<?= $cls('phone') ?>" id="cp" name="phone" value="<?= e($val('phone')) ?>" inputmode="tel" maxlength="14" placeholder="01XXXXXXXXX" required><?= $err('phone') ?></div>
        <div class="col-md-6"><label class="form-label" for="ce"><?= e(__('email_optional')) ?></label><input class="<?= $cls('email') ?>" type="email" id="ce" name="email" value="<?= e($val('email')) ?>" maxlength="191"><?= $err('email') ?></div>
        <div class="col-md-6"><label class="form-label" for="cw"><?= e(__('password')) ?> *</label><input class="<?= $cls('password') ?>" type="password" id="cw" name="password" minlength="8" maxlength="72" autocomplete="new-password" required><?= $err('password') ?>
            <div class="form-text"><?= e(__('cust_add_password_help')) ?></div></div>
    </div>
    <div class="card-footer bg-white"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button> <a class="btn btn-link" href="<?= e(url('admin/customers')) ?>"><?= e(__('cancel')) ?></a></div>
</form>
