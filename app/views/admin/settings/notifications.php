<?php /** Notification events. Vars: $matrix, $smsOn, $mailOn */ $ro = !can('settings.edit'); ?>
<?= View::partial('admin/settings/_nav', ['active' => 'notifications']) ?>
<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" action="<?= e(url('admin/settings/notifications')) ?>" class="card border-0 shadow-sm">
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><?= e(__('notify_customers_vendors')) ?></div>
            <fieldset <?= $ro ? 'disabled' : '' ?>>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('event')) ?></th><th class="text-center">SMS</th><th class="text-center"><?= e(__('email')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($matrix as $ev => $m): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e(__('ev_' . $ev)) ?></div><div class="text-muted"><?= e(__('ev_' . $ev . '_help')) ?></div></td>
                            <td class="text-center"><input class="form-check-input" type="checkbox" name="ev[<?= $ev ?>][sms]" value="1" <?= $m['sms'] ? 'checked' : '' ?> aria-label="SMS: <?= e(__('ev_' . $ev)) ?>"></td>
                            <td class="text-center"><input class="form-check-input" type="checkbox" name="ev[<?= $ev ?>][email]" value="1" <?= $m['email'] ? 'checked' : '' ?> aria-label="Email: <?= e(__('ev_' . $ev)) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!$ro): ?><div class="card-footer bg-white"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
            </fieldset>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2"><span>SMS</span><span class="badge text-bg-<?= $smsOn ? 'success' : 'secondary' ?>"><?= e(__($smsOn ? 'gateway_on' : 'gateway_log_only')) ?></span></div>
                <div class="d-flex justify-content-between"><span><?= e(__('email')) ?></span><span class="badge text-bg-<?= $mailOn ? 'success' : 'secondary' ?>"><?= e(__($mailOn ? 'gateway_on' : 'gateway_log_only')) ?></span></div>
            </div>
        </div>
        <div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('notify_help')) ?></div>
    </div>
</div>
