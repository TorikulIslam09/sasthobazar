<?php
/** Add / edit address. Vars: $tab, $address (null = new), $input, $errors, $divisions, $districts, $upazilas */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$locName = static fn(array $r) => tr($r['name_bn'], $r['name_en']);
$action = $address ? url('account/addresses/' . (int) $address['id']) : url('account/addresses');
$return = Helpers::str('return');
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account/addresses')) ?>"><?= e(__('my_addresses')) ?></a> › <span><?= e($title) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => $tab]) ?>
    <section class="sb-block min-w-0">
        <h1 class="h5 mb-3"><?= e($title) ?></h1>
        <form method="post" action="<?= e($action) ?>" novalidate>
            <?= csrf_field() ?>
            <?php if ($return !== ''): ?><input type="hidden" name="return" value="<?= e($return) ?>"><?php endif; ?>

            <fieldset class="mb-3">
                <legend class="form-label"><?= e(__('addr_label')) ?></legend>
                <div class="sb-label-pills">
                    <?php foreach (Address::LABELS as $l): ?>
                        <label>
                            <input type="radio" name="label" value="<?= $l ?>" <?= $val('label') === $l ? 'checked' : '' ?>>
                            <span><i class="bi <?= $l === 'home' ? 'bi-house' : ($l === 'office' ? 'bi-building' : 'bi-geo') ?>"></i> <?= e(__('addr_label_' . $l)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= $err('label') ?>
            </fieldset>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="rn"><?= e(__('addr_recipient')) ?> *</label>
                    <input class="<?= $cls('recipient_name') ?>" id="rn" name="recipient_name" value="<?= e($val('recipient_name')) ?>" required maxlength="150" autocomplete="name">
                    <?= $err('recipient_name') ?>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="ph"><?= e(__('phone')) ?> *</label>
                    <input class="<?= $cls('phone') ?>" id="ph" name="phone" value="<?= e($val('phone')) ?>" required inputmode="tel" placeholder="01XXXXXXXXX" maxlength="20" autocomplete="tel">
                    <?= $err('phone') ?>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="aph"><?= e(__('addr_alt_phone')) ?></label>
                    <input class="<?= $cls('alt_phone') ?>" id="aph" name="alt_phone" value="<?= e($val('alt_phone')) ?>" inputmode="tel" placeholder="01XXXXXXXXX" maxlength="20">
                    <?= $err('alt_phone') ?>
                </div>
            </div>

            <div class="row g-3 mt-0" data-location-picker data-api="<?= e(url('api/locations')) ?>">
                <div class="col-md-4">
                    <label class="form-label" for="dv"><?= e(__('division')) ?> *</label>
                    <select class="<?= $cls('division_id', 'form-select') ?>" id="dv" name="division_id" data-level="division" required>
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= $val('division_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('division_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ds"><?= e(__('district')) ?> *</label>
                    <select class="<?= $cls('district_id', 'form-select') ?>" id="ds" name="district_id" data-level="district" required <?= $districts ? '' : 'disabled' ?>>
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= $val('district_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('district_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="up"><?= e(__('upazila')) ?></label>
                    <select class="<?= $cls('upazila_id', 'form-select') ?>" id="up" name="upazila_id" data-level="upazila" <?= $upazilas ? '' : 'disabled' ?>>
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($upazilas as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= $val('upazila_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('upazila_id') ?>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-md-9">
                    <label class="form-label" for="al"><?= e(__('addr_line')) ?> *</label>
                    <textarea class="<?= $cls('address_line') ?>" id="al" name="address_line" rows="2" required maxlength="255" placeholder="<?= e(__('addr_line_ph')) ?>" autocomplete="street-address"><?= e($val('address_line')) ?></textarea>
                    <?= $err('address_line') ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="pc"><?= e(__('postcode')) ?></label>
                    <input class="<?= $cls('postcode') ?>" id="pc" name="postcode" value="<?= e($val('postcode')) ?>" inputmode="numeric" maxlength="4" placeholder="1207" autocomplete="postal-code">
                    <?= $err('postcode') ?>
                </div>
            </div>

            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDef" <?= !empty($input['is_default']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="isDef"><?= e(__('addr_set_default')) ?></label>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('account/addresses')) ?>"><?= e(__('cancel')) ?></a>
            </div>
        </form>
    </section>
</div>
