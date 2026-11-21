<?php
/** Audit log detail. Vars: $log, $old, $new, $fields */
$fmt = static function ($v): string {
    if ($v === null) {
        return '<span class="text-muted">NULL</span>';
    }
    if (is_array($v)) {
        return '<code class="text-break">' . e(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</code>';
    }
    return e(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
};
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url('admin/audit-logs')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e(__('audit_logs')) ?> #<?= (int) $log['id'] ?>
</h1>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><div class="text-muted"><?= e(__('time')) ?></div><?= e(dt($log['created_at'], 'd M Y, h:i:s A')) ?></li>
                <li class="list-group-item"><div class="text-muted"><?= e(__('user')) ?></div>
                    <?= $log['user_name'] !== null ? e($log['user_name']) . ' <span class="text-muted">(#' . (int) $log['user_id'] . ', ' . e($log['user_email']) . ')</span>' : e(__('system')) ?></li>
                <li class="list-group-item"><div class="text-muted"><?= e(__('action')) ?></div><code><?= e($log['action']) ?></code></li>
                <li class="list-group-item"><div class="text-muted"><?= e(__('entity')) ?></div><?= e($log['entity_type'] ?? '—') ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></li>
                <li class="list-group-item"><div class="text-muted"><?= e(__('ip_address')) ?></div><span class="font-monospace"><?= e($log['ip_address']) ?></span></li>
                <li class="list-group-item"><div class="text-muted"><?= e(__('user_agent')) ?></div><span class="text-break"><?= e($log['user_agent']) ?></span></li>
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('field')) ?></th><th><?= e(__('old_value')) ?></th><th><?= e(__('new_value')) ?></th></tr></thead>
                    <tbody>
                    <?php if (!$fields): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($fields as $f): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($f) ?></td>
                            <td class="<?= array_key_exists($f, $old) ? 'bg-danger-subtle' : '' ?>"><?= array_key_exists($f, $old) ? $fmt($old[$f]) : '' ?></td>
                            <td class="<?= array_key_exists($f, $new) ? 'bg-success-subtle' : '' ?>"><?= array_key_exists($f, $new) ? $fmt($new[$f]) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
