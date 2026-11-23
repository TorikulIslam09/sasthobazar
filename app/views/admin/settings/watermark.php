<?php
/** Settings → Watermark. Vars: $cfg, $enabled, $available, $logo, $total, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$canEdit = can('settings.edit');
?>
<?= View::partial('admin/settings/_nav', ['active' => 'watermark']) ?>
<?php if (!$available): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <?= e(__('wm_no_gd')) ?></div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="<?= e(url('admin/settings/watermark')) ?>" enctype="multipart/form-data" class="card border-0 shadow-sm" data-wm-form>
            <?= csrf_field() ?>
            <fieldset <?= $canEdit ? '' : 'disabled' ?>>
            <div class="card-body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="watermark_enabled" value="1" id="wme" <?= $enabled ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="wme"><?= e(__('wm_enable')) ?></label>
                </div>
                <div class="form-text mb-3"><?= e(__('wm_enable_help')) ?></div>

                <label class="form-label small fw-semibold"><?= e(__('wm_type')) ?></label>
                <div class="d-flex gap-3 mb-2">
                    <?php foreach (['text', 'logo'] as $t): ?>
                        <div class="form-check"><input class="form-check-input" type="radio" name="watermark_type" value="<?= $t ?>" id="wt<?= $t ?>" <?= $cfg['type'] === $t || ($t === 'logo' && setting('watermark_type') === 'logo') ? 'checked' : '' ?> data-wm><label class="form-check-label" for="wt<?= $t ?>"><?= e(__('wm_type_' . $t)) ?></label></div>
                    <?php endforeach; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label small" for="wmt"><?= e(__('wm_text')) ?></label>
                    <input class="form-control" id="wmt" name="watermark_text" value="<?= e($cfg['text']) ?>" maxlength="40" data-wm>
                </div>
                <div class="mb-3">
                    <label class="form-label small" for="wml"><?= e(__('wm_logo')) ?></label>
                    <?php if ($logo !== ''): ?>
                        <div class="mb-1 p-2 rounded" style="background:repeating-conic-gradient(#eee 0 25%,#fff 0 50%) 0 0/16px 16px"><img src="<?= e(url($logo)) ?>" alt="" style="max-height:60px"></div>
                        <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="wmrl"><label class="form-check-label small" for="wmrl"><?= e(__('pb_remove_image')) ?></label></div>
                    <?php endif; ?>
                    <input class="form-control form-control-sm<?= isset($errors['logo']) ? ' is-invalid' : '' ?>" type="file" id="wml" name="logo" accept="image/png,image/webp">
                    <div class="form-text"><?= e(__('wm_logo_help')) ?></div>
                    <?= $err('logo') ?>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small" for="wmp"><?= e(__('wm_position')) ?></label>
                        <select class="form-select" id="wmp" name="watermark_position" data-wm>
                            <?php foreach (Watermark::POSITIONS as $p): ?><option value="<?= $p ?>" <?= $cfg['position'] === $p ? 'selected' : '' ?>><?= e(__('wm_pos_' . $p)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="wmc"><?= e(__('wm_color')) ?></label>
                        <input class="form-control form-control-color w-100" type="color" id="wmc" name="watermark_color" value="<?= e($cfg['color']) ?>" data-wm>
                        <?= $err('watermark_color') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="wmo"><?= e(__('wm_opacity')) ?>: <b data-wm-out="wmo"><?= (int) $cfg['opacity'] ?></b>%</label>
                        <input class="form-range" type="range" min="5" max="100" id="wmo" name="watermark_opacity" value="<?= (int) $cfg['opacity'] ?>" data-wm>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="wms"><?= e(__('wm_size')) ?>: <b data-wm-out="wms"><?= (int) $cfg['size'] ?></b>%</label>
                        <input class="form-range" type="range" min="5" max="80" id="wms" name="watermark_size" value="<?= (int) $cfg['size'] ?>" data-wm>
                    </div>
                </div>
            </div>
            </fieldset>
            <?php if ($canEdit): ?><div class="card-footer bg-white"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-eye"></i> <?= e(__('wm_preview')) ?></div>
            <div class="card-body text-center">
                <?php if ($available): ?>
                    <img src="<?= e(url('admin/settings/watermark/preview')) ?>" alt="<?= e(__('wm_preview')) ?>" class="img-fluid rounded border" style="max-height:380px" data-wm-preview data-src="<?= e(url('admin/settings/watermark/preview')) ?>">
                    <div class="form-text"><?= e(__('wm_preview_help')) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($canEdit && $available): ?>
            <div class="card border-0 shadow-sm small">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-images"></i> <?= e(__('wm_existing')) ?></div>
                <div class="card-body">
                    <p class="mb-2"><?= e(__('wm_existing_help', ['n' => num($total)])) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="<?= e(url('admin/settings/watermark/apply')) ?>"><?= csrf_field() ?>
                            <input type="hidden" name="offset" value="<?= (int) ($_SESSION['wm_continue'] ?? 0) ?>">
                            <button class="btn btn-sm btn-brand" data-confirm-click="<?= e(__('wm_apply_confirm')) ?>"><i class="bi bi-magic"></i> <?= e(isset($_SESSION['wm_continue']) ? __('wm_continue') : __('wm_apply_all')) ?></button></form>
                        <form method="post" action="<?= e(url('admin/settings/watermark/remove')) ?>"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" data-confirm-click="<?= e(__('wm_remove_confirm')) ?>"><i class="bi bi-eraser"></i> <?= e(__('wm_remove_all')) ?></button></form>
                    </div>
                    <div class="form-text mt-2"><?= e(__('wm_originals_note')) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
