<?php /** Backup & restore. Vars: $backups, $dbSize, $tables, $maxUp, $cron */
$beat = $cron['heartbeat'] ? strtotime((string) $cron['heartbeat']) : 0;
$cronOk = $beat > time() - 3600;
$size = static function (float $b): string {
    foreach (['B', 'KB', 'MB', 'GB'] as $u) {
        if ($b < 1024 || $u === 'GB') {
            return num(number_format($b, $u === 'B' ? 0 : 1)) . ' ' . $u;
        }
        $b /= 1024;
    }
    return '';
};
?>
<?= View::partial('admin/settings/_nav', ['active' => 'backup']) ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-database-down"></i> <?= e(__('backup_create_title')) ?></div>
            <div class="card-body">
                <p class="small text-muted"><?= e(__('backup_db_info', ['tables' => num($tables), 'size' => $size($dbSize)])) ?></p>
                <form method="post" action="<?= e(url('admin/settings/backup')) ?>" class="d-flex flex-wrap gap-2">
                    <?= csrf_field() ?>
                    <button class="btn btn-brand" name="download" value="1"><i class="bi bi-download"></i> <?= e(__('backup_download_now')) ?></button>
                    <button class="btn btn-outline-secondary"><i class="bi bi-hdd"></i> <?= e(__('backup_save_server')) ?></button>
                </form>
                <p class="small text-muted mt-2 mb-0"><?= e(__('backup_files_note')) ?></p>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= e(__('backup_saved')) ?> <span class="text-muted small fw-normal">(<?= e(num(count($backups))) ?>)</span></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('file')) ?></th><th><?= e(__('date')) ?></th><th class="text-end"><?= e(__('size')) ?></th><th class="text-end"></th></tr></thead>
                    <tbody>
                    <?php if (!$backups): ?><tr><td colspan="4" class="text-center text-muted py-4"><?= e(__('backup_none')) ?></td></tr><?php endif; ?>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td class="text-break"><?= e($b['name']) ?><?php if (str_contains($b['name'], 'before-restore')): ?> <span class="badge text-bg-warning"><?= e(__('backup_safety')) ?></span><?php endif; ?></td>
                            <td class="text-nowrap"><?= e(dt(date('Y-m-d H:i:s', $b['time']), 'd M Y, h:i A')) ?></td>
                            <td class="text-end text-nowrap"><?= e($size($b['size'])) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/settings/backup/download/' . $b['name'])) ?>" title="<?= e(__('download')) ?>"><i class="bi bi-download"></i></a>
                                <?= View::partial('partials/delete_button', ['action' => url('admin/settings/backup/delete/' . $b['name']), 'message' => __('confirm_delete_named', ['name' => $b['name']])]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <form method="post" action="<?= e(url('admin/settings/backup/schedule')) ?>" class="card border-0 shadow-sm mb-3">
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history"></i> <?= e(__('backup_schedule')) ?></div>
            <div class="card-body small">
                <div class="alert alert-<?= $cronOk ? 'success' : 'warning' ?> py-2 small">
                    <i class="bi <?= $cronOk ? 'bi-check-circle' : 'bi-exclamation-triangle' ?>"></i>
                    <?= e($beat ? __('cron_last_run', ['date' => dt((string) $cron['heartbeat'], 'd M Y, h:i A')]) : __('cron_never')) ?>
                    <?php if (!$cronOk): ?><div class="mt-1"><?= e(__('cron_setup_hint')) ?> <code>*/5 * * * * php <?= e(ROOT_PATH) ?>/cron/cron.php</code></div><?php endif; ?>
                </div>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="backup_auto" value="1" id="ba" <?= $cron['auto'] ? 'checked' : '' ?>><label class="form-check-label" for="ba"><?= e(__('backup_auto')) ?></label></div>
                <label class="form-label small" for="bkp"><?= e(__('backup_keep')) ?></label>
                <input class="form-control form-control-sm" style="max-width: 120px" id="bkp" name="backup_keep" value="<?= (int) $cron['keep'] ?>" inputmode="numeric">
                <div class="text-muted mt-2"><?= e($cron['backup'] ? __('backup_last_auto', ['date' => dt((string) $cron['backup'], 'd M Y, h:i A')]) : __('backup_no_auto_yet')) ?></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div>
        </form>
        <form method="post" action="<?= e(url('admin/settings/backup/restore')) ?>" enctype="multipart/form-data" class="card border-danger shadow-sm" data-confirm="<?= e(__('restore_confirm_js')) ?>" data-confirm-danger="1">
            <?= csrf_field() ?>
            <div class="card-header bg-danger-subtle fw-semibold text-danger"><i class="bi bi-exclamation-triangle"></i> <?= e(__('restore_title')) ?></div>
            <div class="card-body">
                <p class="small"><?= e(__('restore_warning')) ?></p>
                <label class="form-label small" for="bk"><?= e(__('restore_upload')) ?></label>
                <input class="form-control form-control-sm" type="file" id="bk" name="backup" accept=".sql,.gz,application/gzip,application/sql">
                <div class="form-text"><?= e(__('restore_upload_help', ['size' => $size($maxUp)])) ?></div>
                <?php if ($backups): ?>
                    <label class="form-label small mt-2" for="sv"><?= e(__('restore_or_saved')) ?></label>
                    <select class="form-select form-select-sm" id="sv" name="saved">
                        <option value=""><?= e(__('none')) ?></option>
                        <?php foreach ($backups as $b): ?><option value="<?= e($b['name']) ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <label class="form-label small mt-3" for="pw"><?= e(__('your_password')) ?> *</label>
                <input class="form-control form-control-sm" type="password" id="pw" name="password" required autocomplete="current-password">
                <label class="form-label small mt-2" for="cf"><?= e(__('restore_type_word')) ?> *</label>
                <input class="form-control form-control-sm" id="cf" name="confirm" required placeholder="RESTORE" autocomplete="off">
            </div>
            <div class="card-footer bg-white"><button class="btn btn-danger btn-sm"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('restore_button')) ?></button></div>
        </form>
    </div>
</div>
