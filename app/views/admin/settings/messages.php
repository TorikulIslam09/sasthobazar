<?php /** Message log. Vars: $messages, $pager, $channel, $status, $q, $counts */ ?>
<?php if (can('settings.view')): ?>
    <?= View::partial('admin/settings/_nav', ['active' => 'messages']) ?>
<?php else: ?>
    <h1 class="h4 mb-3"><i class="bi bi-list-ul"></i> <?= e(__('message_log')) ?></h1>
<?php endif; ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2">
        <input class="form-control form-control-sm" style="max-width: 240px" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('message_search_ph')) ?>" aria-label="<?= e(__('search')) ?>">
        <select class="form-select form-select-sm" style="max-width: 120px" name="channel" aria-label="<?= e(__('channel')) ?>">
            <option value=""><?= e(__('all')) ?></option><option value="sms" <?= $channel === 'sms' ? 'selected' : '' ?>>SMS</option><option value="email" <?= $channel === 'email' ? 'selected' : '' ?>><?= e(__('email')) ?></option>
        </select>
        <select class="form-select form-select-sm" style="max-width: 140px" name="status" aria-label="<?= e(__('status')) ?>">
            <option value=""><?= e(__('all')) ?></option>
            <?php foreach (['sent', 'failed', 'queued', 'logged'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(__('msg_st_' . $s)) ?> (<?= e(num((int) ($counts[$s] ?? 0))) ?>)</option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
    </form>
    <?php if (can('notifications.send') && ((int) ($counts['failed'] ?? 0) + (int) ($counts['queued'] ?? 0)) > 0): ?>
        <form method="post" action="<?= e(url('admin/messages/retry')) ?>"><?= csrf_field() ?><input type="hidden" name="all" value="1">
            <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-repeat"></i> <?= e(__('retry_failed')) ?></button></form>
    <?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('date')) ?></th><th><?= e(__('channel')) ?></th><th><?= e(__('recipient')) ?></th><th><?= e(__('message')) ?></th><th><?= e(__('event')) ?></th><th><?= e(__('status')) ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$messages): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <tr>
                    <td class="text-nowrap"><?= e(dt($m['created_at'], 'd M, h:i A')) ?></td>
                    <td><i class="bi <?= $m['channel'] === 'sms' ? 'bi-chat-dots' : 'bi-envelope' ?>"></i> <?= e($m['channel'] === 'sms' ? 'SMS' : __('email')) ?></td>
                    <td class="text-nowrap"><?= e($m['recipient']) ?></td>
                    <td style="max-width: 360px"><?php if ($m['subject']): ?><div class="fw-semibold text-truncate"><?= e($m['subject']) ?></div><?php endif; ?><div class="text-muted text-truncate"><?= e(Html::excerpt($m['body'], 140)) ?></div>
                        <?php if ($m['response'] && $m['status'] === 'failed'): ?><div class="text-danger text-truncate" title="<?= e($m['response']) ?>"><?= e($m['response']) ?></div><?php endif; ?></td>
                    <td><?= $m['event'] ? e(__('ev_' . $m['event'])) : '—' ?></td>
                    <td><?= View::partial('admin/settings/_msg_status', ['s' => $m['status']]) ?><?= (int) $m['attempts'] > 1 ? '<div class="text-muted">×' . e(num((int) $m['attempts'])) . '</div>' : '' ?></td>
                    <td class="text-end"><?php if (can('notifications.send') && in_array($m['status'], ['failed', 'queued'], true)): ?>
                        <form method="post" action="<?= e(url('admin/messages/retry')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="<?= e(__('retry')) ?>"><i class="bi bi-arrow-repeat"></i></button></form>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
<p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> <?= e(__('message_log_help')) ?></p>
