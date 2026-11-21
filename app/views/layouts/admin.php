<?php
/** Admin panel layout: top bar (menu toggle, language, store link, notification bell, user menu) + permission-based sidebar. */
$user = Auth::user();
$unread = Notification::unreadCount((int) $user['id']);
$latest = Notification::latest((int) $user['id'], 8);
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="admin-body lang-<?= e(lang()) ?>">

<header class="navbar navbar-dark bg-brand sticky-top shadow-sm px-2 admin-topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Menu">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand fw-bold me-0" href="<?= e(url('admin')) ?>">
            <i class="bi bi-heart-pulse-fill"></i> <span class="d-none d-sm-inline"><?= e(__('app_name')) ?></span>
        </a>
    </div>

    <div class="d-flex align-items-center gap-1 gap-sm-2">
        <?= View::partial('partials/lang_switch') ?>
        <a class="btn btn-sm btn-outline-light d-none d-md-inline-block" href="<?= e(url('/')) ?>" target="_blank" rel="noopener" title="<?= e(__('visit_store')) ?>"><i class="bi bi-shop"></i></a>

        <!-- Notification bell (refreshed by AJAX polling, see app.js) -->
        <div class="dropdown" data-notif-poll="<?= e(url('admin/notifications/poll')) ?>" data-notif-read="<?= e(url('admin/notifications/read-all')) ?>"
             data-notif-last="<?= $latest ? (int) $latest[0]['id'] : 0 ?>" data-notif-empty="<?= e(__('no_notifications')) ?>">
            <button class="btn btn-sm btn-outline-light position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="<?= e(__('notifications')) ?>" aria-label="<?= e(__('notifications')) ?>">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-notif-count <?= $unread > 0 ? '' : 'hidden' ?>><?= e($unread > 99 ? '99+' : num($unread)) ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 notif-menu shadow">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong class="small"><?= e(__('notifications')) ?></strong>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-notif-readall <?= $unread > 0 ? '' : 'hidden' ?>><?= e(__('mark_all_read')) ?></button>
                </div>
                <div data-notif-list>
                    <?php if (!$latest): ?>
                        <div class="text-center text-muted small py-4"><i class="bi bi-bell-slash fs-4 d-block mb-1"></i><?= e(__('no_notifications')) ?></div>
                    <?php else: foreach ($latest as $n): ?>
                        <a class="dropdown-item small py-2 d-flex gap-2 <?= $n['read_at'] ? '' : 'fw-semibold bg-light' ?>" href="<?= e(url('admin/notifications/' . (int) $n['id'] . '/open')) ?>">
                            <i class="bi <?= e(Notification::icon((string) $n['type'])) ?> text-brand"></i>
                            <span class="min-w-0"><span class="d-block text-truncate"><?= e($n['title']) ?></span><span class="d-block text-muted fw-normal" style="font-size:.75rem"><?= e(dt($n['created_at'], 'd M, h:i A')) ?></span></span>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
                <a class="d-block text-center small py-2 border-top text-decoration-none" href="<?= e(url('admin/notifications')) ?>"><?= e(__('view_all')) ?></a>
            </div>
        </div>

        <!-- User menu -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?= e($user['name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text small text-muted"><?= e(Auth::roleName()) ?></span></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/profile')) ?>"><i class="bi bi-person-gear"></i> <?= e(__('my_profile')) ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?>
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> <?= e(__('logout')) ?></button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="d-flex">
    <?= View::partial('partials/admin_sidebar') ?>
    <main class="flex-grow-1 p-3 p-lg-4 admin-main">
        <?= View::partial('partials/flash') ?>
        <?= $content ?>
    </main>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="notifToasts" aria-live="polite"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
