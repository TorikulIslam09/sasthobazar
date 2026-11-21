<?php
/**
 * Customer auth gate: Guest checkout | Login | Register (register is its own page).
 * Vars: $action, $tab (guest|login), $guestOn, $guestType (phone|email), $guestValue (?array), $errors, $cartCount
 */
$errors = $errors ?? [];
$tab = $tab ?? 'login';
$guestOn = !empty($guestOn);
$gType = ($guestType ?? 'phone') === 'email' ? 'email' : 'phone';
$gPhone = old('phone', ($guestValue['type'] ?? '') === 'phone' ? (string) $guestValue['value'] : '');
$gEmail = old('email', ($guestValue['type'] ?? '') === 'email' ? (string) $guestValue['value'] : '');
?>
<div class="sb-auth">
    <div class="sb-block" data-auth-tabs>
        <div class="sb-tabsw<?= $guestOn ? ' sb-tabsw-3' : '' ?>" role="tablist">
            <?php if ($guestOn): ?>
                <a href="<?= e(url('login?tab=guest')) ?>" class="<?= $tab === 'guest' ? 'on' : '' ?>" role="tab" aria-selected="<?= $tab === 'guest' ? 'true' : 'false' ?>" data-auth-tab="guest"><?= e(__('guest_tab')) ?></a>
            <?php endif; ?>
            <a href="<?= e(url('login?tab=login')) ?>" class="<?= $tab === 'login' ? 'on' : '' ?>" role="tab" aria-selected="<?= $tab === 'login' ? 'true' : 'false' ?>" data-auth-tab="login"><?= e(__('login')) ?></a>
            <a href="<?= e(url('register')) ?>" role="tab" aria-selected="false"><?= e(__('register')) ?></a>
        </div>

        <?php if ($guestOn): ?>
        <!-- Guest checkout -->
        <div data-auth-panel="guest" <?= $tab === 'guest' ? '' : 'hidden' ?>>
            <h1 class="h5 mb-1"><?= e(__('guest_title')) ?></h1>
            <p class="text-muted small mb-3"><?= e(__('guest_subtitle')) ?></p>
            <?php if (!$cartCount): ?>
                <div class="alert alert-warning small py-2"><i class="bi bi-cart3"></i> <?= e(__('guest_cart_empty')) ?> <a href="<?= e(url('products')) ?>"><?= e(__('shop_now')) ?></a></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('login/guest')) ?>" novalidate data-guest-form>
                <?= csrf_field() ?>
                <div class="sb-seg mb-3" role="radiogroup" aria-label="<?= e(__('guest_contact_by')) ?>">
                    <input type="radio" class="btn-check" name="contact_type" id="gtPhone" value="phone" <?= $gType === 'phone' ? 'checked' : '' ?>>
                    <label for="gtPhone"><i class="bi bi-phone"></i> <?= e(__('guest_by_phone')) ?></label>
                    <input type="radio" class="btn-check" name="contact_type" id="gtEmail" value="email" <?= $gType === 'email' ? 'checked' : '' ?>>
                    <label for="gtEmail"><i class="bi bi-envelope"></i> <?= e(__('guest_by_email')) ?></label>
                </div>
                <div class="mb-3" data-guest-field="phone" <?= $gType === 'phone' ? '' : 'hidden' ?>>
                    <label class="form-label" for="gPhone"><?= e(__('phone')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input class="form-control<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="gPhone" name="phone" value="<?= e($gPhone) ?>"
                               inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" maxlength="14" pattern="^01[3-9]\d{8}$" <?= $gType === 'phone' ? 'required' : 'disabled' ?>>
                    </div>
                    <div class="invalid-feedback<?= isset($errors['phone']) ? ' d-block' : '' ?>" data-guest-error="phone"><?= e($errors['phone'] ?? __('v_guest_phone')) ?></div>
                </div>
                <div class="mb-3" data-guest-field="email" <?= $gType === 'email' ? '' : 'hidden' ?>>
                    <label class="form-label" for="gEmail"><?= e(__('email')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" type="email" id="gEmail" name="email" value="<?= e($gEmail) ?>"
                               autocomplete="email" placeholder="name@example.com" maxlength="191" <?= $gType === 'email' ? 'required' : 'disabled' ?>>
                    </div>
                    <div class="invalid-feedback<?= isset($errors['email']) ? ' d-block' : '' ?>" data-guest-error="email"><?= e($errors['email'] ?? __('v_email')) ?></div>
                </div>
                <button class="btn btn-primary w-100" <?= $cartCount ? '' : 'disabled' ?>><i class="bi bi-bag-check"></i> <?= e(__('guest_go_checkout')) ?></button>
            </form>
            <p class="text-center small mt-3 mb-1">
                <?= e(__('have_account')) ?> <a href="<?= e(url('login?tab=login')) ?>" data-auth-switch="login"><?= e(__('login_now')) ?></a>
                <span class="text-muted mx-1">|</span>
                <?= e(__('new_here')) ?> <a href="<?= e(url('register')) ?>"><?= e(__('register_now')) ?></a>
            </p>
            <p class="text-center small text-muted mb-0"><i class="bi bi-truck"></i> <?= e(__('guest_track_note')) ?></p>
        </div>
        <?php endif; ?>

        <!-- Login (unchanged) -->
        <div data-auth-panel="login" <?= $tab === 'login' ? '' : 'hidden' ?>>
            <h1 class="h5 mb-1"><?= e(__('customer_login')) ?></h1>
            <p class="text-muted small mb-3"><?= e(__('login_subtitle')) ?></p>
            <form method="post" action="<?= e($action) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="login"><?= e(__('phone')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input class="form-control" id="login" name="login" value="<?= e(old('login')) ?>" required <?= $tab === 'login' ? 'autofocus' : '' ?>
                               inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" maxlength="20">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password"><?= e(__('password')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="<?= e(__('show_password')) ?>"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> <?= e(__('login')) ?></button>
            </form>
            <p class="text-center small mt-3 mb-0"><?= e(__('no_account')) ?> <a href="<?= e(url('register')) ?>"><?= e(__('register')) ?></a>
                <?php if ($guestOn && $cartCount): ?><span class="text-muted mx-1">|</span><a href="<?= e(url('login?tab=guest')) ?>" data-auth-switch="guest"><?= e(__('guest_tab')) ?></a><?php endif; ?></p>
        </div>
    </div>
</div>
