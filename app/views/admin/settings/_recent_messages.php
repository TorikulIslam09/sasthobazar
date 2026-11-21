<?php /** Last messages of one channel. Vars: $recent */ ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold"><?= e(__('recent_messages')) ?></span><?php if (can('notifications.view')): ?><a class="small" href="<?= e(url('admin/messages')) ?>"><?= e(__('view_all')) ?></a><?php endif; ?></div>
    <ul class="list-group list-group-flush small">
        <?php if (!$recent): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
        <?php foreach ($recent as $m): ?>
            <li class="list-group-item">
                <div class="d-flex justify-content-between gap-2"><span class="text-truncate"><?= e($m['recipient']) ?></span><?= View::partial('admin/settings/_msg_status', ['s' => $m['status']]) ?></div>
                <div class="text-muted text-truncate"><?= e($m['subject'] ?: Html::excerpt($m['body'], 80)) ?> · <?= e(dt($m['created_at'], 'd M, h:i A')) ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
