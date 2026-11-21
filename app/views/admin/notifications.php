<?php /** All notifications. Vars: $items, $pager, $unreadOnly, $unread */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-bell"></i> <?= e(__('notifications')) ?></h1>
    <?php if ($unread > 0): ?>
        <form method="post" action="<?= e(url('admin/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-all"></i> <?= e(__('mark_all_read')) ?></button></form>
    <?php endif; ?>
</div>
<ul class="nav nav-pills small mb-3">
    <li class="nav-item"><a class="nav-link<?= $unreadOnly ? '' : ' active' ?>" href="<?= e(url('admin/notifications')) ?>"><?= e(__('all')) ?></a></li>
    <li class="nav-item"><a class="nav-link<?= $unreadOnly ? ' active' : '' ?>" href="<?= e(url('admin/notifications?unread=1')) ?>"><?= e(__('unread')) ?> <span class="badge text-bg-danger"><?= e(num($unread)) ?></span></a></li>
</ul>
<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        <?php if (!$items): ?><div class="list-group-item text-center text-muted py-5"><i class="bi bi-bell-slash fs-3 d-block mb-1"></i><?= e(__('no_notifications')) ?></div><?php endif; ?>
        <?php foreach ($items as $n): ?>
            <a class="list-group-item list-group-item-action d-flex gap-3 align-items-start<?= $n['read_at'] ? '' : ' bg-light' ?>" href="<?= e(url('admin/notifications/' . (int) $n['id'] . '/open')) ?>">
                <span class="notif-icon"><i class="bi <?= e(Notification::icon((string) $n['type'])) ?>"></i></span>
                <span class="flex-grow-1 min-w-0">
                    <span class="d-block small <?= $n['read_at'] ? '' : 'fw-semibold' ?>"><?= e($n['title']) ?></span>
                    <?php if ($n['message']): ?><span class="d-block small text-muted text-truncate"><?= e(Html::excerpt($n['message'], 160)) ?></span><?php endif; ?>
                </span>
                <span class="small text-muted text-nowrap"><?= e(dt($n['created_at'], 'd M, h:i A')) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
