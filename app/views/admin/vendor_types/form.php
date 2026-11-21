<?php
/** Vendor type add / edit. Vars: $t (?array), $input, $roots (top categories), $errors, $inUse */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$req = json_decode((string) $input['required_docs'], true) ?: [];
$opt = json_decode((string) $input['optional_docs'], true) ?: [];
$cats = array_map('intval', json_decode((string) $input['allowed_categories'], true) ?: []);
?>
<div class="mb-3">
    <a href="<?= e(url('admin/vendor-types')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('vendor_types')) ?></a>
    <h1 class="h4 mb-0"><?= e($t ? VendorType::label($t) : __('vt_add')) ?></h1>
    <?php if ($inUse): ?><div class="small text-muted"><?= e(__('vt_in_use', ['n' => num($inUse)])) ?></div><?php endif; ?>
</div>
<form method="post" action="<?= e(url($t ? 'admin/vendor-types/' . (int) $t['id'] : 'admin/vendor-types')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="ne"><?= e(__('name')) ?> (English) *</label>
                        <input class="<?= $cls('name_en') ?>" id="ne" name="name_en" value="<?= e($val('name_en')) ?>" maxlength="100" required>
                        <?= $err('name_en') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nb"><?= e(__('name')) ?> (বাংলা)</label>
                        <input class="form-control" id="nb" name="name_bn" value="<?= e($val('name_bn')) ?>" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="de"><?= e(__('description')) ?> (English)</label>
                        <input class="form-control" id="de" name="description_en" value="<?= e($val('description_en')) ?>" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="db"><?= e(__('description')) ?> (বাংলা)</label>
                        <input class="form-control" id="db" name="description_bn" value="<?= e($val('description_bn')) ?>" maxlength="255">
                    </div>
                    <?php if (!$t): ?>
                        <div class="col-md-4">
                            <label class="form-label" for="sl"><?= e(__('slug')) ?></label>
                            <input class="<?= $cls('slug') ?> font-monospace" id="sl" name="slug" value="<?= e($val('slug')) ?>" maxlength="40" placeholder="e.g. optical">
                            <?= $err('slug') ?>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label" for="ic"><?= e(__('icon')) ?> <i class="bi <?= e($val('icon') ?: 'bi-shop') ?>"></i></label>
                        <input class="<?= $cls('icon') ?> font-monospace" id="ic" name="icon" value="<?= e($val('icon')) ?>" maxlength="50" placeholder="bi-shop">
                        <div class="form-text"><?= e(__('vt_icon_help')) ?></div>
                        <?= $err('icon') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="so"><?= e(__('sort_order')) ?></label>
                        <input class="form-control" id="so" name="sort_order" value="<?= e($val('sort_order')) ?>" inputmode="numeric" maxlength="6">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold"><?= e(__('vt_docs')) ?></label>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 small">
                                <thead><tr><th></th><th class="text-center"><?= e(__('required')) ?></th><th class="text-center"><?= e(__('optional')) ?></th></tr></thead>
                                <tbody>
                                <?php foreach (VendorType::DOCS as $d): ?>
                                    <tr><td><?= e(__('doc_' . $d)) ?></td>
                                        <td class="text-center"><input class="form-check-input" type="checkbox" name="required_docs[]" value="<?= $d ?>" <?= in_array($d, $req, true) ? 'checked' : '' ?> aria-label="<?= e(__('required')) ?>"></td>
                                        <td class="text-center"><input class="form-check-input" type="checkbox" name="optional_docs[]" value="<?= $d ?>" <?= in_array($d, $opt, true) ? 'checked' : '' ?> aria-label="<?= e(__('optional')) ?>"></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?= $err('required_docs') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="needs_drug_license" value="1" id="ndl" <?= (int) $val('needs_drug_license') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ndl"><?= e(__('vt_needs_drug')) ?></label>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" name="can_sell_rx" value="1" id="csr" <?= (int) $val('can_sell_rx') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="csr"><?= e(__('vt_can_sell_rx')) ?></label>
                    </div>
                    <?= $err('can_sell_rx') ?>
                    <div class="form-text mb-3"><?= e(__('vt_rx_help')) ?></div>
                    <label class="form-label" for="cr"><?= e(__('vt_commission')) ?> (%)</label>
                    <input class="<?= $cls('commission_rate') ?>" id="cr" name="commission_rate" value="<?= e($val('commission_rate')) ?>" inputmode="decimal" placeholder="<?= e(__('cscope_global')) ?> <?= e(num(number_format(Commission::globalRate(), 2))) ?>%">
                    <div class="form-text"><?= e(__('commission_priority')) ?></div>
                    <?= $err('commission_rate') ?>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="act" <?= (int) $val('is_active') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="act"><?= e(__('vt_active_help')) ?></label>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <label class="form-label fw-semibold"><?= e(__('vt_categories')) ?></label>
                    <div class="form-text mt-0 mb-2"><?= e(__('vt_categories_help')) ?></div>
                    <?php foreach ($roots as $c): ?>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="allowed_categories[]" value="<?= (int) $c['id'] ?>" id="ac<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $cats, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="ac<?= (int) $c['id'] ?>"><?= e(Category::label($c)) ?></label></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button> <a class="btn btn-link" href="<?= e(url('admin/vendor-types')) ?>"><?= e(__('cancel')) ?></a></div>
</form>
