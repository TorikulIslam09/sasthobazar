<?php
/** Customer profile + password. Vars: $tab, $user, $input, $errors, $addressCount, $wishlistCount, $defaultAddress */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <span><?= e(__('my_account')) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => $tab]) ?>
    <div class="min-w-0">
        <div class="sb-acc-stats">
            <a href="<?= e(url('account/addresses')) ?>" class="sb-block"><i class="bi bi-geo-alt"></i><b><?= e(num($addressCount)) ?></b><span><?= e(__('my_addresses')) ?></span></a>
            <a href="<?= e(url('account/wishlist')) ?>" class="sb-block"><i class="bi bi-heart"></i><b><?= e(num($wishlistCount)) ?></b><span><?= e(__('wishlist')) ?></span></a>
            <div class="sb-block"><i class="bi bi-calendar-check"></i><b class="fs-6"><?= e(dt($user['created_at'], 'M Y')) ?></b><span><?= e(__('member_since')) ?></span></div>
        </div>

        <?php if ($defaultAddress): ?>
            <div class="sb-block small">
                <div class="d-flex justify-content-between"><b><i class="bi bi-house"></i> <?= e(__('addr_default')) ?></b><a href="<?= e(url('account/addresses')) ?>"><?= e(__('edit')) ?></a></div>
                <div class="mt-1"><?= e($defaultAddress['recipient_name']) ?> · <?= e(num($defaultAddress['phone'])) ?></div>
                <div class="text-muted"><?= e($defaultAddress['address_line']) ?>, <?= e(Address::areaLine($defaultAddress)) ?></div>
            </div>
        <?php endif; ?>

        <section class="sb-block">
            <h1 class="h5 mb-3"><?= e(__('my_profile')) ?></h1>
            <form method="post" action="<?= e(url('account/profile')) ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="sb-avatar sb-avatar-lg">
                        <?php if ($user['avatar']): ?><img src="<?= e(url($user['avatar'])) ?>" alt=""><?php else: ?><i class="bi bi-person"></i><?php endif; ?>
                    </span>
                    <div class="min-w-0">
                        <label class="form-label mb-1" for="avatar"><?= e(__('profile_photo')) ?></label>
                        <input class="<?= $cls('avatar') ?> form-control-sm" type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text"><?= e(__('avatar_rules')) ?></div>
                        <?= $err('avatar') ?>
                        <?php if ($user['avatar']): ?>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="rmAvatar">
                                <label class="form-check-label small" for="rmAvatar"><?= e(__('remove_image')) ?></label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name"><?= e(__('name')) ?> *</label>
                        <input class="<?= $cls('name') ?>" id="name" name="name" value="<?= e($val('name')) ?>" required maxlength="150" autocomplete="name">
                        <?= $err('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phoneRo"><?= e(__('phone')) ?></label>
                        <input class="form-control" id="phoneRo" value="<?= e($user['phone']) ?>" disabled>
                        <div class="form-text"><?= e(__('phone_change_note')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email"><?= e(__('email_optional')) ?></label>
                        <input class="<?= $cls('email') ?>" type="email" id="email" name="email" value="<?= e($val('email')) ?>" maxlength="191" autocomplete="email">
                        <?= $err('email') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="gender"><?= e(__('gender')) ?></label>
                        <select class="<?= $cls('gender', 'form-select') ?>" id="gender" name="gender">
                            <option value="">—</option>
                            <?php foreach (['male', 'female', 'other'] as $g): ?>
                                <option value="<?= $g ?>" <?= $val('gender') === $g ? 'selected' : '' ?>><?= e(__('gender_' . $g)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('gender') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="dob"><?= e(__('date_of_birth')) ?></label>
                        <input class="<?= $cls('date_of_birth') ?>" type="date" id="dob" name="date_of_birth" value="<?= e($val('date_of_birth')) ?>" max="<?= e(date('Y-m-d')) ?>">
                        <?= $err('date_of_birth') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="lang"><?= e(__('preferred_language')) ?></label>
                        <select class="<?= $cls('lang', 'form-select') ?>" id="lang" name="lang">
                            <option value="bn" <?= $val('lang') === 'bn' ? 'selected' : '' ?>>বাংলা</option>
                            <option value="en" <?= $val('lang') === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                        <?= $err('lang') ?>
                    </div>
                </div>
                <button class="btn btn-primary mt-3"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
            </form>
        </section>

        <section class="sb-block" id="password">
            <h2 class="h5 mb-3"><?= e(__('change_password')) ?></h2>
            <form method="post" action="<?= e(url('account/password')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="cur"><?= e(__('current_password')) ?></label>
                        <input class="<?= $cls('current_password') ?>" type="password" id="cur" name="current_password" required autocomplete="current-password">
                        <?= $err('current_password') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="npw"><?= e(__('new_password')) ?></label>
                        <input class="<?= $cls('password') ?>" type="password" id="npw" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
                        <?= $err('password') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="npw2"><?= e(__('confirm_password')) ?></label>
                        <input class="form-control" type="password" id="npw2" name="password_confirmation" required minlength="8" maxlength="72" autocomplete="new-password">
                    </div>
                </div>
                <p class="form-text"><?= e(__('password_other_devices')) ?></p>
                <button class="btn btn-outline-primary"><i class="bi bi-key"></i> <?= e(__('change_password')) ?></button>
            </form>
        </section>
    </div>
</div>
