<?php
/** Vendor panel layout: top bar + section menu + status banner. */
$user = Auth::user();
$vendor = Auth::vendor();
$current = Helpers::currentPath();
$unread = $user ? Notification::unreadCount((int) $user['id']) : 0;
$latest = $user ? Notification::latest((int) $user['id'], 5) : [];
$menu = [
    'vendor-panel'          => ['bi-speedometer2', __('dashboard')],
    'vendor-panel/orders'   => ['bi-receipt', __('my_orders')],
    'vendor-panel/products' => ['bi-capsule', __('my_products')],
    'vendor-panel/offers'   => ['bi-gift', __('my_offers')],
    'vendor-panel/returns'  => ['bi-arrow-counterclockwise', __('returns_refunds')],
    'vendor-panel/payouts'  => ['bi-wallet2', __('wallet_payouts')],
    'vendor-panel/profile'  => ['bi-shop', __('shop_profile')],
];
$active = static fn(string $p) => $p === 'vendor-panel' ? $current === $p : ($current === $p || str_starts_with($current, $p . '/'));
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="admin-body vendor-body lang-<?= e(lang()) ?>">

<nav class="navbar navbar-dark bg-vendor sticky-top shadow-sm px-2">
    <a class="navbar-brand fw-bold text-truncate" href="<?= e(url('vendor-panel')) ?>" style="max-width: 60vw">
        <i class="bi bi-shop"></i> <?= e($vendor ? tr($vendor['shop_name_bn'], $vendor['shop_name']) : __('vendor_panel')) ?>
    </a>
    <div class="d-flex align-items-center gap-1 gap-sm-2">
        <?= View::partial('partials/lang_switch') ?>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-light position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="<?= e(__('notifications')) ?>">
                <i class="bi bi-bell"></i>
                <?php if ($unread > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= e(num(min(99, $unread))) ?></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 notif-menu shadow">
                <?php if (!$latest): ?>
                    <div class="text-center text-muted small py-4"><?= e(__('no_notifications')) ?></div>
                <?php else: foreach ($latest as $n): $href = (is_string($n['link']) && str_starts_with($n['link'], '/') && !str_starts_with($n['link'], '//')) ? $n['link'] : '#'; ?>
                    <a class="dropdown-item small py-2 <?= $n['read_at'] ? '' : 'fw-semibold bg-light' ?>" href="<?= e($href) ?>">
                        <div class="text-truncate"><?= e($n['title']) ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= e(dt($n['created_at'])) ?></div>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?= e($user['name'] ?? '') ?></span></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('vendor-panel/profile')) ?>"><i class="bi bi-shop"></i> <?= e(__('shop_profile')) ?></a></li>
                <?php if ($vendor && $vendor['status'] === 'approved'): ?><li><a class="dropdown-item" href="<?= e(url('seller/' . rawurlencode($vendor['slug']))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('view_shop_page')) ?></a></li><?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('vendor-panel/logout')) ?>"><?= csrf_field() ?>
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> <?= e(__('logout')) ?></button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php if ($vendor): ?>
    <div class="bg-white border-bottom">
        <div class="container">
            <ul class="nav nav-underline vendor-nav flex-nowrap overflow-auto">
                <?php foreach ($menu as $path => [$icon, $label]): ?>
                    <li class="nav-item"><a class="nav-link text-nowrap <?= $active($path) ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<main class="container py-3 py-lg-4">
    <?php if ($vendor && $vendor['status'] !== 'approved'): ?>
        <div class="alert alert-<?= $vendor['status'] === 'pending' ? 'info' : 'warning' ?> d-flex gap-2 align-items-start">
            <i class="bi bi-info-circle fs-5"></i>
            <div>
                <b><?= e(__('vs_' . $vendor['status'])) ?></b> — <?= e(__('vendor_banner_' . $vendor['status'])) ?>
                <?php $reason = $vendor['status'] === 'rejected' ? $vendor['rejection_reason'] : ($vendor['status'] === 'suspended' ? $vendor['suspension_reason'] : null); ?>
                <?php if ($reason): ?><div class="small mt-1"><?= e(__('reason')) ?>: <?= e($reason) ?></div><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?= View::partial('partials/flash') ?>
    <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
