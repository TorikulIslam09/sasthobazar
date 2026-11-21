<?php
/** VAT / tax settings. Vars: $input, $errors, $special (products with their own VAT %) */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$canEdit = can('accounting.configure');
?>
<h1 class="h4 mb-3"><i class="bi bi-sliders"></i> <?= e(__('acc_tax_settings')) ?></h1>
<?= View::partial('admin/accounting/_nav', ['active' => 'tax']) ?>
<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('admin/accounting/tax')) ?>" class="card border-0 shadow-sm" novalidate>
            <?= csrf_field() ?>
            <div class="card-body row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="vat_enabled" value="1" id="ve" <?= $val('vat_enabled') ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                        <label class="form-check-label fw-semibold" for="ve"><?= e(__('acc_vat_enable')) ?></label>
                    </div>
                    <div class="form-text"><?= e(__('acc_vat_enable_help')) ?></div>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="vr"><?= e(__('acc_vat_rate')) ?> (%) *</label>
                    <input class="<?= $cls('vat_rate') ?>" id="vr" name="vat_rate" value="<?= e($val('vat_rate')) ?>" inputmode="decimal" <?= $canEdit ? '' : 'disabled' ?>>
                    <?= $err('vat_rate') ?>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="vl"><?= e(__('acc_vat_label')) ?> *</label>
                    <input class="<?= $cls('vat_label') ?>" id="vl" name="vat_label" value="<?= e($val('vat_label')) ?>" maxlength="20" <?= $canEdit ? '' : 'disabled' ?>>
                    <?= $err('vat_label') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="vb"><?= e(__('vat_reg_no')) ?></label>
                    <input class="<?= $cls('vat_reg_no') ?>" id="vb" name="vat_reg_no" value="<?= e($val('vat_reg_no')) ?>" maxlength="30" <?= $canEdit ? '' : 'disabled' ?>>
                    <?= $err('vat_reg_no') ?>
                </div>
                <div class="col-12">
                    <label class="form-label d-block"><?= e(__('acc_vat_mode')) ?></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="vat_inclusive" value="1" id="vi1" <?= $val('vat_inclusive') !== '0' ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                        <label class="form-check-label" for="vi1"><?= e(__('acc_vat_inclusive')) ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="vat_inclusive" value="0" id="vi0" <?= $val('vat_inclusive') === '0' ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                        <label class="form-check-label" for="vi0"><?= e(__('acc_vat_exclusive')) ?></label>
                    </div>
                    <?= $err('vat_inclusive') ?>
                </div>
            </div>
            <?php if ($canEdit): ?>
                <div class="card-footer bg-white"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('acc_vat_how')) ?></div>
            <div class="card-body small">
                <ul class="mb-0 ps-3">
                    <li><?= e(__('acc_vat_how_1')) ?></li>
                    <li><?= e(__('acc_vat_how_2')) ?></li>
                    <li><?= e(__('acc_vat_how_3')) ?></li>
                    <li><?= e(__('acc_vat_how_4')) ?></li>
                </ul>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= e(__('acc_vat_special')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php if (!$special): ?><li class="list-group-item text-muted"><?= e(__('acc_vat_special_none')) ?></li><?php endif; ?>
                <?php foreach ($special as $p): ?>
                    <li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('admin/products/' . (int) $p['id'] . '/edit')) ?>"><?= e($p['name_en']) ?></a><span><?= e(num((float) $p['vat_percent'] + 0)) ?>%</span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
