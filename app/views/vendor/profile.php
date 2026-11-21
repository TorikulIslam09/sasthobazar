<?php
/** Vendor shop profile. Vars: $vendor, $input, $errors, $divisions, $districts, $upazilas, $docs, $area */
$v = $vendor;
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$loc = static fn(array $r) => tr($r['name_bn'], $r['name_en']);
?>
<h1 class="h4 mb-3"><i class="bi bi-shop"></i> <?= e(__('shop_profile')) ?> <span class="badge text-bg-<?= VendorAccount::BADGES[$v['status']] ?>"><?= e(__('vs_' . $v['status'])) ?></span></h1>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('vr_shop')) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item"><span class="text-muted"><?= e(__('shop_name')) ?>:</span> <?= e($v['shop_name']) ?><?= $v['shop_name_bn'] ? ' / ' . e($v['shop_name_bn']) : '' ?></li>
                <li class="list-group-item"><span class="text-muted"><?= e(__('vendor_type')) ?>:</span> <?= e(VendorType::vendorLabel($v)) ?></li>
                <li class="list-group-item"><span class="text-muted"><?= e(__('owner_name')) ?>:</span> <?= e($v['contact_person']) ?> · <?= e($v['phone']) ?></li>
                <li class="list-group-item"><span class="text-muted"><?= e(__('trade_license_no')) ?>:</span> <?= e($v['trade_license_no'] ?: '—') ?></li>
                <?php if ($v['drug_license_no']): ?><li class="list-group-item"><span class="text-muted"><?= e(__('drug_license_no')) ?>:</span> <?= e($v['drug_license_no']) ?> (<?= e(dt($v['drug_license_expiry'], 'd M Y')) ?>)</li><?php endif; ?>
                <li class="list-group-item"><span class="text-muted"><?= e(__('nid_no')) ?>:</span> <?= e(str_repeat('•', max(0, strlen((string) $v['nid_no']) - 4)) . substr((string) $v['nid_no'], -4)) ?></li>
                <li class="list-group-item"><span class="text-muted"><?= e(__('documents')) ?>:</span>
                    <?php foreach (VendorAccount::DOCS as $d): if (isset($docs[$d])): ?><a class="me-2" href="<?= e(url('vendor-panel/documents/' . $d)) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark"></i> <?= e(__('doc_' . $d)) ?></a><?php endif; endforeach; ?></li>
                <li class="list-group-item"><span class="text-muted"><?= e(__('commission')) ?>:</span> <?= $v['commission_rate'] !== null ? e(num($v['commission_rate'])) . '%' : e(__('cscope_global')) . ' (' . e(num(number_format(Commission::globalRate(), 2))) . '%)' ?></li>
            </ul>
            <div class="card-footer bg-white small text-muted"><?= e(__('vendor_licence_change')) ?></div>
        </div>
    </div>
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('vendor-panel/profile')) ?>" class="card border-0 shadow-sm" novalidate>
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><?= e(__('vr_pickup')) ?> & <?= e(__('vr_payout')) ?></div>
            <div class="card-body">
                <div class="row g-2" data-location-picker data-api="<?= e(url('api/locations')) ?>">
                    <div class="col-md-4">
                        <label class="form-label small" for="dv"><?= e(__('division')) ?> *</label>
                        <select class="<?= $cls('division_id', 'form-select') ?> form-select-sm" id="dv" name="division_id" data-level="division">
                            <option value=""><?= e(__('select')) ?></option>
                            <?php foreach ($divisions as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('division_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($loc($d)) ?></option><?php endforeach; ?>
                        </select><?= $err('division_id') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="ds"><?= e(__('district')) ?> *</label>
                        <select class="<?= $cls('district_id', 'form-select') ?> form-select-sm" id="ds" name="district_id" data-level="district" <?= $districts ? '' : 'disabled' ?>>
                            <option value=""><?= e(__('select')) ?></option>
                            <?php foreach ($districts as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('district_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($loc($d)) ?></option><?php endforeach; ?>
                        </select><?= $err('district_id') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="up"><?= e(__('upazila')) ?></label>
                        <select class="<?= $cls('upazila_id', 'form-select') ?> form-select-sm" id="up" name="upazila_id" data-level="upazila" <?= $upazilas ? '' : 'disabled' ?>>
                            <option value=""><?= e(__('select')) ?></option>
                            <?php foreach ($upazilas as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('upazila_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($loc($d)) ?></option><?php endforeach; ?>
                        </select><?= $err('upazila_id') ?>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small" for="pa"><?= e(__('pickup_address')) ?> *</label>
                        <input class="<?= $cls('pickup_address') ?> form-control-sm" id="pa" name="pickup_address" maxlength="255" value="<?= e($input['pickup_address'] ?? $input['address'] ?? '') ?>"><?= $err('pickup_address') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="pp"><?= e(__('pickup_phone')) ?></label>
                        <input class="<?= $cls('pickup_phone') ?> form-control-sm" id="pp" name="pickup_phone" maxlength="20" value="<?= e($val('pickup_phone')) ?>"><?= $err('pickup_phone') ?>
                    </div>
                </div>
                <hr>
                <div class="row g-2">
                    <?php foreach (['bank_name' => 'bank_name', 'bank_branch' => 'bank_branch', 'bank_routing_no' => 'bank_routing', 'bank_account_name' => 'bank_account_name', 'bank_account_no' => 'bank_account_no'] as $f => $label): ?>
                        <div class="col-md-<?= in_array($f, ['bank_account_name', 'bank_account_no'], true) ? 6 : 4 ?>">
                            <label class="form-label small" for="f_<?= $f ?>"><?= e(__($label)) ?></label>
                            <input class="<?= $cls($f) ?> form-control-sm" id="f_<?= $f ?>" name="<?= $f ?>" maxlength="150" value="<?= e($val($f)) ?>"><?= $err($f) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-4">
                        <label class="form-label small" for="mfs"><?= e(__('mobile_banking')) ?></label>
                        <select class="form-select form-select-sm" id="mfs" name="mobile_banking_provider">
                            <option value="">—</option>
                            <?php foreach (VendorAccount::MFS as $m): ?><option value="<?= $m ?>" <?= $val('mobile_banking_provider') === $m ? 'selected' : '' ?>><?= e(__('mfs_' . $m)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small" for="mfsn"><?= e(__('mobile_banking_no')) ?></label>
                        <input class="<?= $cls('mobile_banking_no') ?> form-control-sm" id="mfsn" name="mobile_banking_no" maxlength="20" value="<?= e($val('mobile_banking_no')) ?>"><?= $err('mobile_banking_no') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="desc"><?= e(__('shop_description')) ?></label>
                        <textarea class="form-control form-control-sm" id="desc" name="description" rows="2" maxlength="1000"><?= e($val('description')) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-brand btn-sm"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div>
        </form>
    </div>
</div>
