<?php /** Couriers. Vars: $couriers, $stats, $mockMode */
$canEdit = can('couriers.edit');
?>
<h1 class="h4 mb-3"><i class="bi bi-bicycle"></i> <?= e(__('couriers')) ?></h1>
<?= View::partial('admin/shipments/_nav', ['active' => 'couriers']) ?>

<form method="post" action="<?= e(url('admin/couriers/mock-mode')) ?>" class="alert <?= $mockMode ? 'alert-warning' : 'alert-success' ?> d-flex flex-wrap align-items-center gap-2">
    <?= csrf_field() ?>
    <i class="bi <?= $mockMode ? 'bi-cone-striped' : 'bi-plug' ?> fs-5"></i>
    <div class="flex-grow-1"><b><?= e(__($mockMode ? 'mock_on' : 'mock_off')) ?></b><div class="small"><?= e(__('mock_help')) ?></div></div>
    <?php if ($canEdit): ?>
        <input type="hidden" name="courier_mock_mode" value="<?= $mockMode ? '0' : '1' ?>">
        <button class="btn btn-sm <?= $mockMode ? 'btn-outline-dark' : 'btn-outline-warning' ?>" data-confirm-click="<?= e(__($mockMode ? 'mock_turn_off_confirm' : 'mock_turn_on_confirm')) ?>"><?= e(__($mockMode ? 'mock_turn_off' : 'mock_turn_on')) ?></button>
    <?php endif; ?>
</form>

<div class="row g-3">
    <?php foreach ($couriers as $c): $cfg = Courier::config($c); $adapter = isset(Courier::ADAPTERS[$c['code']]) ? Courier::adapter($c) : null; $st = $stats[$c['id']] ?? ['n' => 0, 'open' => 0]; ?>
        <div class="col-lg-6" id="c<?= (int) $c['id'] ?>">
            <form method="post" action="<?= e(url('admin/couriers/' . (int) $c['id'])) ?>" class="card border-0 shadow-sm h-100">
                <?= csrf_field() ?>
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= e($c['name']) ?> <code class="small"><?= e($c['code']) ?></code></span>
                    <span>
                        <?php if ($adapter && $c['code'] !== 'self'): ?><span class="badge text-bg-<?= $adapter->configured() ? 'success' : 'secondary' ?>"><?= e(__($adapter->configured() ? 'keys_set' : 'keys_missing')) ?></span>
                            <span class="badge text-bg-<?= $adapter->isMock() ? 'warning' : 'primary' ?>"><?= e(__($adapter->isMock() ? 'mock' : 'live')) ?></span><?php endif; ?>
                        <span class="badge text-bg-<?= (int) $c['is_active'] ? 'success' : 'light border' ?>"><?= e(__((int) $c['is_active'] ? 'active' : 'inactive')) ?></span>
                    </span>
                </div>
                <div class="card-body small row g-2">
                    <div class="col-12 text-muted"><?= e(__('courier_stats', ['n' => num((int) $st['n']), 'open' => num((int) $st['open'])])) ?></div>
                    <div class="col-md-6"><label class="form-label mb-0" for="n<?= (int) $c['id'] ?>"><?= e(__('name')) ?></label><input class="form-control form-control-sm" id="n<?= (int) $c['id'] ?>" name="name" value="<?= e($c['name']) ?>" maxlength="100" <?= $canEdit ? '' : 'disabled' ?>></div>
                    <div class="col-md-6"><label class="form-label mb-0" for="p<?= (int) $c['id'] ?>"><?= e(__('phone')) ?></label><input class="form-control form-control-sm" id="p<?= (int) $c['id'] ?>" name="phone" value="<?= e($c['phone']) ?>" maxlength="20" <?= $canEdit ? '' : 'disabled' ?>></div>
                    <?php if ($c['code'] !== 'self'): ?>
                        <div class="col-12"><label class="form-label mb-0" for="u<?= (int) $c['id'] ?>"><?= e(__('api_base_url')) ?></label><input class="form-control form-control-sm" id="u<?= (int) $c['id'] ?>" name="api_base_url" value="<?= e($c['api_base_url']) ?>" maxlength="255" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <?php if ($c['code'] !== 'pathao'): ?><div class="col-md-6"><label class="form-label mb-0" for="k<?= (int) $c['id'] ?>"><?= e(__('api_key')) ?></label><input class="form-control form-control-sm" id="k<?= (int) $c['id'] ?>" name="api_key" type="password" autocomplete="off" placeholder="<?= $c['api_key'] ? '•••••• (' . e(__('saved')) . ')' : '' ?>" <?= $canEdit ? '' : 'disabled' ?>></div><?php endif; ?>
                        <div class="col-md-6"><label class="form-label mb-0" for="s<?= (int) $c['id'] ?>"><?= e(__($c['code'] === 'pathao' ? 'cfg_client_secret' : 'api_secret')) ?></label><input class="form-control form-control-sm" id="s<?= (int) $c['id'] ?>" name="api_secret" type="password" autocomplete="off" placeholder="<?= $c['api_secret'] ? '•••••• (' . e(__('saved')) . ')' : '' ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <?php foreach (AdminCourierController::FIELDS[$c['code']] ?? [] as $f): $secret = $f === 'password'; ?>
                            <div class="col-md-6"><label class="form-label mb-0" for="f<?= (int) $c['id'] . $f ?>"><?= e(__('cfg_' . $f)) ?></label>
                                <input class="form-control form-control-sm" id="f<?= (int) $c['id'] . $f ?>" name="cfg_<?= e($f) ?>" <?= $secret ? 'type="password" autocomplete="off" placeholder="' . (!empty($cfg[$f]) ? '•••••• (' . e(__('saved')) . ')' : '') . '"' : 'value="' . e($cfg[$f] ?? '') . '"' ?> maxlength="190" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <?php endforeach; ?>
                        <div class="col-12"><label class="form-label mb-0" for="t<?= (int) $c['id'] ?>"><?= e(__('tracking_url')) ?></label><input class="form-control form-control-sm" id="t<?= (int) $c['id'] ?>" name="tracking_url_template" value="<?= e($c['tracking_url_template']) ?>" maxlength="255" placeholder="https://…{tracking_no}" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label mb-0"><?= e(__('webhook_url')) ?></label>
                            <div class="input-group input-group-sm"><input class="form-control font-monospace" value="<?= e(Courier::webhookUrl($c)) ?>" readonly onclick="this.select()" aria-label="<?= e(__('webhook_url')) ?>"></div>
                            <div class="form-text"><?= e(__('webhook_help')) ?></div></div>
                    <?php else: ?>
                        <div class="col-12 text-muted"><?= e(__('own_delivery_help')) ?></div>
                        <div class="col-12"><label class="form-label mb-0"><?= e(__('webhook_url')) ?> (<?= e(__('rider_app')) ?>)</label><input class="form-control form-control-sm font-monospace" value="<?= e(Courier::webhookUrl($c)) ?>" readonly onclick="this.select()" aria-label="<?= e(__('webhook_url')) ?>"></div>
                    <?php endif; ?>
                    <div class="col-12 d-flex flex-wrap gap-3 mt-1">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="a<?= (int) $c['id'] ?>" <?= (int) $c['is_active'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="a<?= (int) $c['id'] ?>"><?= e(__('active')) ?></label></div>
                        <?php if ($c['code'] !== 'self'): ?>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="mock" value="1" id="m<?= (int) $c['id'] ?>" <?= !empty($cfg['mock']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="m<?= (int) $c['id'] ?>"><?= e(__('mock_this')) ?></label></div>
                        <?php endif; ?>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="regen_token" value="1" id="r<?= (int) $c['id'] ?>" <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="r<?= (int) $c['id'] ?>"><?= e(__('regen_webhook')) ?></label></div>
                    </div>
                </div>
                <?php if ($c['code'] === 'pathao'): ?>
                    <div class="card-body small pt-0">
                        <div class="alert alert-light border mb-2 py-2">
                            <b><i class="bi bi-info-circle"></i> <?= e(__('pathao_setup_title')) ?></b>
                            <ol class="mb-0 ps-3 mt-1">
                                <li><?= e(__('pathao_setup_1')) ?></li>
                                <li><?= e(__('pathao_setup_2')) ?></li>
                                <li><?= e(__('pathao_setup_3')) ?></li>
                                <li><?= e(__('pathao_setup_4')) ?></li>
                                <li><?= e(__('pathao_setup_5')) ?></li>
                            </ol>
                        </div>
                        <?php if (!empty($_SESSION['pathao_stores'])): ?>
                            <div class="border rounded p-2 mb-2"><b><?= e(__('pathao_your_stores')) ?></b>
                                <?php foreach ($_SESSION['pathao_stores'] as $stx): ?>
                                    <div class="d-flex justify-content-between gap-2 border-top pt-1 mt-1"><span><?= e($stx['name']) ?> <span class="text-muted"><?= e($stx['address']) ?></span></span><code class="user-select-all"><?= e($stx['id']) ?></code></div>
                                <?php endforeach; ?>
                            </div>
                            <?php unset($_SESSION['pathao_stores']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($canEdit): ?><div class="card-footer bg-white d-flex flex-wrap gap-2"><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button>
                    <?php if ($c['code'] === 'pathao'): ?><button class="btn btn-outline-secondary btn-sm" formaction="<?= e(url('admin/couriers/' . (int) $c['id'] . '/test')) ?>" formnovalidate><i class="bi bi-plug"></i> <?= e(__('pathao_test_btn')) ?></button><?php endif; ?>
                </div><?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
</div>
