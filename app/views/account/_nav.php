<?php
/** Account side menu (horizontal chips on phones). Var: $tab */
$u = Auth::user();
$items = [
    'profile'   => ['account', 'bi-person', __('my_profile')],
    'orders'    => ['account/orders', 'bi-bag', __('my_orders')],
    'prescriptions' => ['account/prescriptions', 'bi-file-earmark-medical', __('my_prescriptions')],
    'addresses' => ['account/addresses', 'bi-geo-alt', __('my_addresses')],
    'wishlist'  => ['account/wishlist', 'bi-heart', __('wishlist')],
    'support'   => ['account/support', 'bi-headset', __('my_tickets')],
];
?>
<nav class="sb-block sb-acc-nav" aria-label="<?= e(__('my_account')) ?>">
    <div class="sb-acc-user">
        <span class="sb-avatar">
            <?php if (!empty($u['avatar'])): ?><img src="<?= e(url($u['avatar'])) ?>" alt=""><?php else: ?><i class="bi bi-person"></i><?php endif; ?>
        </span>
        <b class="text-truncate"><?= e($u['name']) ?></b>
        <span class="text-muted small"><?= e(num((string) $u['phone'])) ?></span>
    </div>
    <div class="sb-acc-links">
        <?php foreach ($items as $key => [$path, $icon, $label]): ?>
            <a href="<?= e(url($path)) ?>" class="<?= $tab === $key ? 'on' : '' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('logout')) ?>" class="d-inline"><?= csrf_field() ?>
            <button class="text-danger"><i class="bi bi-box-arrow-right"></i> <?= e(__('logout')) ?></button>
        </form>
    </div>
</nav>
