<?php
/** Add / edit employee. Vars: $user (null on create), $input, $errors, $roles */
$isSuper = $user && $user['user_type'] === 'super_admin';
$action = $user ? url('admin/employees/' . $user['id']) : url('admin/employees');
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url('admin/employees')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e($title) ?>
</h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="card border-0 shadow-sm" style="max-width: 820px" autocomplete="off" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name"><?= e(__('name')) ?> *</label>
            <input class="<?= $cls('name') ?>" id="name" name="name" maxlength="150" required value="<?= e($v('name')) ?>">
            <?= $err('name') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email"><?= e(__('email')) ?> *</label>
            <input class="<?= $cls('email') ?>" type="email" id="email" name="email" maxlength="191" required value="<?= e($v('email')) ?>">
            <?= $err('email') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="phone"><?= e(__('phone_optional')) ?></label>
            <input class="<?= $cls('phone') ?>" id="phone" name="phone" inputmode="tel" placeholder="01XXXXXXXXX" value="<?= e($v('phone')) ?>">
            <?= $err('phone') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="role_id"><?= e(__('role')) ?> *</label>
            <?php if ($isSuper): ?>
                <input class="form-control" value="<?= e(__('super_admin')) ?>" disabled>
                <div class="form-text"><?= e(__('super_admin_role_fixed')) ?></div>
            <?php else: ?>
                <select class="<?= $cls('role_id', 'form-select') ?>" id="role_id" name="role_id" required>
                    <option value=""><?= e(__('select')) ?></option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $v('role_id') === (int) $r['id'] ? 'selected' : '' ?>><?= e(Role::label($r)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('role_id') ?>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="status"><?= e(__('status')) ?></label>
            <select class="<?= $cls('status', 'form-select') ?>" id="status" name="status">
                <option value="active" <?= $v('status', 'active') === 'active' ? 'selected' : '' ?>><?= e(__('active')) ?></option>
                <option value="inactive" <?= $v('status') === 'inactive' ? 'selected' : '' ?>><?= e(__('inactive')) ?></option>
            </select>
            <?= $err('status') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="lang"><?= e(__('preferred_language')) ?></label>
            <select class="form-select" id="lang" name="lang">
                <option value="bn" <?= $v('lang', 'bn') === 'bn' ? 'selected' : '' ?>><?= e(__('bangla')) ?></option>
                <option value="en" <?= $v('lang') === 'en' ? 'selected' : '' ?>><?= e(__('english')) ?></option>
            </select>
        </div>

        <div class="col-12"><hr class="my-1"></div>
        <div class="col-md-6">
            <label class="form-label" for="password"><?= e($user ? __('new_password') : __('password')) ?><?= $user ? '' : ' *' ?></label>
            <input class="<?= $cls('password') ?>" type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= $user ? '' : 'required' ?>>
            <?= $err('password') ?>
            <?php if ($user): ?><div class="form-text"><?= e(__('password_keep')) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password_confirmation"><?= e(__('confirm_password')) ?></label>
            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password">
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/employees')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
