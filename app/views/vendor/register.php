<?php
/**
 * Vendor sign-up - short form: 1. business type  2. shop & login  3. documents for that type.
 * Vars: $old, $errors, $types (id => vendor type row), $typeId, $divisions, $districts, $upazilas
 */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f, string $d = '') => (string) ($old[$f] ?? $d);
$loc = static fn(array $r) => tr($r['name_bn'], $r['name_en']);
$field = static function (string $name, string $label, bool $req = false, string $extra = '') use ($err, $cls, $val): string {
    return '<label class="form-label" for="f_' . e($name) . '">' . e($label) . ($req ? ' <span class="text-danger">*</span>' : '') . '</label>'
        . '<input class="' . $cls($name) . '" id="f_' . e($name) . '" name="' . e($name) . '" value="' . e($val($name)) . '" ' . $extra . '>' . $err($name);
};
$accept = 'image/jpeg,image/png,image/webp,application/pdf';
$cur = $types[$typeId] ?? null;
$curReq = $cur ? VendorType::requiredDocs($cur) : [];
$curOpt = $cur ? VendorType::optionalDocs($cur) : [];
// document blocks: key => [number field or null, number label, file label, extra number attributes]
$docDefs = [
    'trade_license' => ['trade_license_no', __('trade_license_no'), __('trade_license_file'), 'maxlength="100"'],
    'nid'           => ['nid_no', __('nid_no'), __('nid_file'), 'inputmode="numeric" maxlength="17" placeholder="10 / 13 / 17"'],
    'drug_license'  => ['drug_license_no', __('drug_license_no'), __('drug_license_file'), 'maxlength="100"'],
    'bsti'          => [null, '', __('doc_bsti'), ''],
    'tin'           => ['tin_no', __('tin_no'), __('doc_tin'), 'maxlength="50"'],
];
?>
<div class="card border-0 shadow-sm vr-card">
    <div class="card-body p-3 p-md-4">
        <h1 class="h4 mb-1"><i class="bi bi-shop"></i> <?= e(__('vendor_register')) ?></h1>
        <p class="text-muted small mb-3"><?= e(__('vr_intro_short')) ?></p>
        <form method="post" action="<?= e(url('vendor-panel/register')) ?>" enctype="multipart/form-data" novalidate data-vr-form>
            <?= csrf_field() ?>

            <!-- 1. Business type -->
            <h2 class="vr-step"><span><?= e(num(1)) ?></span> <?= e(__('vr_step_type')) ?></h2>
            <div class="vr-types" role="radiogroup" aria-label="<?= e(__('vendor_type')) ?>">
                <?php foreach ($types as $t):
                    $req = VendorType::requiredDocs($t);
                    $opt = VendorType::optionalDocs($t); ?>
                    <input type="radio" class="btn-check" name="vendor_type_id" id="vt<?= (int) $t['id'] ?>" value="<?= (int) $t['id'] ?>" <?= $typeId === (int) $t['id'] ? 'checked' : '' ?>
                           data-req="<?= e(implode(',', $req)) ?>" data-opt="<?= e(implode(',', $opt)) ?>" data-drug="<?= (int) $t['needs_drug_license'] ?>">
                    <label class="vr-type" for="vt<?= (int) $t['id'] ?>">
                        <i class="bi <?= e($t['icon'] ?: 'bi-shop') ?>"></i>
                        <b><?= e(VendorType::label($t)) ?></b>
                        <small><?= e(VendorType::description($t)) ?></small>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= $err('vendor_type_id') ?>

            <!-- 2. Shop & login -->
            <h2 class="vr-step"><span><?= e(num(2)) ?></span> <?= e(__('vr_step_shop')) ?></h2>
            <div class="row g-3">
                <div class="col-md-6"><?= $field('shop_name', __('shop_name'), true, 'maxlength="150" required') ?></div>
                <div class="col-md-6"><?= $field('contact_person', __('owner_name'), true, 'maxlength="150" autocomplete="name" required') ?></div>
                <div class="col-md-6"><?= $field('phone', __('phone'), true, 'inputmode="tel" maxlength="14" placeholder="01XXXXXXXXX" autocomplete="tel" required') ?><div class="form-text"><?= e(__('vendor_phone_login')) ?></div></div>
                <div class="col-md-6">
                    <label class="form-label" for="f_pw"><?= e(__('password')) ?> <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input class="<?= $cls('password') ?>" type="password" id="f_pw" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#f_pw" aria-label="<?= e(__('show_password')) ?>"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="form-text"><?= e(__('v_min', ['n' => num(8)])) ?></div>
                    <?= $err('password') ?>
                </div>
                <div class="col-md-6"><?= $field('email', __('email_optional'), false, 'type="email" maxlength="191" autocomplete="email"') ?></div>
            </div>
            <div class="row g-3 mt-0" data-location-picker data-api="<?= e(url('api/locations')) ?>">
                <div class="col-md-4">
                    <label class="form-label" for="f_dv"><?= e(__('division')) ?> <span class="text-danger">*</span></label>
                    <select class="<?= $cls('division_id', 'form-select') ?>" id="f_dv" name="division_id" data-level="division">
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($divisions as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('division_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($loc($d)) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('division_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="f_ds"><?= e(__('district')) ?> <span class="text-danger">*</span></label>
                    <select class="<?= $cls('district_id', 'form-select') ?>" id="f_ds" name="district_id" data-level="district" <?= $districts ? '' : 'disabled' ?>>
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($districts as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('district_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($loc($d)) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('district_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="f_up"><?= e(__('upazila')) ?></label>
                    <select class="<?= $cls('upazila_id', 'form-select') ?>" id="f_up" name="upazila_id" data-level="upazila" <?= $upazilas ? '' : 'disabled' ?>>
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($upazilas as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('upazila_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($loc($d)) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('upazila_id') ?>
                </div>
                <div class="col-12"><?= $field('pickup_address', __('pickup_address'), true, 'maxlength="255" placeholder="' . e(__('addr_line_ph')) . '"') ?></div>
            </div>

            <!-- 3. Documents (depend on the type) -->
            <h2 class="vr-step"><span><?= e(num(3)) ?></span> <?= e(__('vr_step_docs')) ?></h2>
            <p class="small text-muted mb-2" data-vr-docs-hint <?= $cur ? 'hidden' : '' ?>><i class="bi bi-arrow-up"></i> <?= e(__('vr_pick_type_first')) ?></p>
            <div class="vr-docs">
                <?php foreach ($docDefs as $doc => [$numField, $numLabel, $fileLabel, $attr]):
                    $isReq = in_array($doc, $curReq, true);
                    $show = $isReq || in_array($doc, $curOpt, true); ?>
                    <div class="vr-doc" data-doc="<?= $doc ?>" <?= $show ? '' : 'hidden' ?>>
                        <div class="vr-doc-head"><b><?= e(__('doc_' . $doc)) ?></b> <span class="badge text-bg-danger" data-doc-req <?= $isReq ? '' : 'hidden' ?>><?= e(__('required')) ?></span><span class="badge text-bg-light" data-doc-opt <?= $isReq ? 'hidden' : '' ?>><?= e(__('optional')) ?></span></div>
                        <div class="row g-2">
                            <?php if ($numField): ?>
                                <div class="<?= $doc === 'drug_license' ? 'col-sm-4' : 'col-sm-6' ?>"><?= $field($numField, $numLabel, false, $attr) ?></div>
                            <?php endif; ?>
                            <?php if ($doc === 'drug_license'): ?>
                                <div class="col-sm-3"><?= $field('drug_license_expiry', __('drug_license_expiry'), false, 'type="date"') ?></div>
                            <?php endif; ?>
                            <div class="<?= $numField ? ($doc === 'drug_license' ? 'col-sm-5' : 'col-sm-6') : 'col-12' ?>">
                                <label class="form-label" for="f_<?= $doc ?>_file"><?= e($fileLabel) ?></label>
                                <input class="<?= $cls($doc . '_file') ?>" type="file" id="f_<?= $doc ?>_file" name="<?= $doc ?>_file" accept="<?= $accept ?>" <?= $show ? '' : 'disabled' ?>>
                                <?= $err($doc . '_file') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-text"><?= e(__('vendor_docs_rules')) ?></div>
            <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-wallet2"></i> <?= e(__('vr_payout_later_note')) ?></div>

            <div class="form-check mt-3">
                <input class="form-check-input<?= isset($errors['agree']) ? ' is-invalid' : '' ?>" type="checkbox" name="agree" value="1" id="f_agree" <?= $val('agree') ? 'checked' : '' ?>>
                <label class="form-check-label small" for="f_agree"><?= e(__('vendor_agree', ['rate' => num(number_format(Commission::globalRate(), 2))])) ?></label>
                <?= $err('agree') ?>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                <button class="btn btn-brand btn-lg"><i class="bi bi-send"></i> <?= e(__('vendor_submit')) ?></button>
                <a class="btn btn-link" href="<?= e(url('vendor-panel/login')) ?>"><?= e(__('have_account')) ?> <?= e(__('login')) ?></a>
            </div>
        </form>
    </div>
</div>
