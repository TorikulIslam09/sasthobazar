<?php
/**
 * Order by prescription (/prescription). Signed-in customers or guests.
 * Vars: $user (?array), $enabled, $guestContact (?array), $divisions, $districts, $upazilas, $old, $errors, $maxMb
 */
$errors = $errors ?? [];
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f, string $d = '') => (string) ($old[$f] ?? $d);
$locName = static fn(array $r) => tr($r['name_bn'], $r['name_en']);
$gPhone = ($guestContact['type'] ?? '') === 'phone' ? (string) $guestContact['value'] : '';
$gEmail = ($guestContact['type'] ?? '') === 'email' ? (string) $guestContact['value'] : '';
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <span><?= e(__('rx_order_title')) ?></span></nav>

<section class="sb-block sb-rx-hero">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="sb-rx-hero-ico"><i class="bi bi-file-earmark-medical"></i></span>
        <div class="flex-grow-1 min-w-0">
            <h1 class="h5 mb-1"><?= e(__('rx_order_title')) ?></h1>
            <p class="text-muted small mb-0"><?= e(__('rx_order_subtitle')) ?></p>
        </div>
    </div>
    <ol class="sb-rx-steps">
        <li><i class="bi bi-cloud-arrow-up"></i><span><?= e(__('rx_step_1')) ?></span></li>
        <li><i class="bi bi-telephone"></i><span><?= e(__('rx_step_2')) ?></span></li>
        <li><i class="bi bi-truck"></i><span><?= e(__('rx_step_3')) ?></span></li>
    </ol>
</section>

<?php if (!$enabled): ?>
    <div class="sb-block"><div class="alert alert-warning mb-0"><?= e(__('rx_req_disabled')) ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(url('prescription')) ?>" enctype="multipart/form-data" class="sb-checkout" novalidate data-rx-form>
    <?= csrf_field() ?>
    <div class="min-w-0">
        <section class="sb-block">
            <h2 class="sb-step-title"><span><?= e(num(1)) ?></span> <?= e(__('rx_req_files')) ?></h2>
            <label class="sb-rx-drop<?= isset($errors['files']) ? ' is-invalid' : '' ?>" for="rxFiles" data-rx-drop>
                <i class="bi bi-camera"></i>
                <b><?= e(__('rx_req_pick')) ?></b>
                <span class="small text-muted"><?= e(__('rx_rules', ['mb' => num($maxMb)])) ?> · <?= e(__('rx_req_max_files', ['n' => num(Prescription::MAX_FILES)])) ?></span>
                <input type="file" id="rxFiles" name="files[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple required class="visually-hidden" data-rx-input data-max="<?= Prescription::MAX_FILES ?>">
            </label>
            <ul class="sb-rx-list" data-rx-list></ul>
            <?= $err('files') ?>
            <?php if ($errors): ?><div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> <?= e(__('rx_reselect')) ?></div><?php endif; ?>
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label" for="rxPatient"><?= e(__('rx_patient_name')) ?></label>
                    <input class="form-control" id="rxPatient" name="patient_name" value="<?= e($val('patient_name')) ?>" maxlength="150">
                </div>
                <div class="col-12">
                    <label class="form-label" for="rxNotes"><?= e(__('rx_req_notes')) ?></label>
                    <textarea class="form-control" id="rxNotes" name="notes" rows="3" maxlength="500" placeholder="<?= e(__('rx_req_notes_ph')) ?>"><?= e($val('notes')) ?></textarea>
                </div>
            </div>
        </section>

        <section class="sb-block">
            <h2 class="sb-step-title"><span><?= e(num(2)) ?></span> <?= e(__('rx_req_contact')) ?></h2>
            <div class="row g-3">
                <?php if ($user): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= e(__('name')) ?></label>
                        <input class="form-control sb-lock" value="<?= e($user['name']) ?>" readonly>
                    </div>
                <?php else: ?>
                    <div class="col-md-6">
                        <label class="form-label" for="rxName"><?= e(__('name')) ?> *</label>
                        <input class="<?= $cls('name') ?>" id="rxName" name="name" value="<?= e($val('name')) ?>" maxlength="150" autocomplete="name" required>
                        <?= $err('name') ?>
                    </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label" for="rxPhone"><?= e(__('phone')) ?> *</label>
                    <input class="<?= $cls('phone') ?>" id="rxPhone" name="phone" value="<?= e($val('phone', $user ? (string) $user['phone'] : $gPhone)) ?>" inputmode="tel" maxlength="14" placeholder="01XXXXXXXXX" autocomplete="tel" required>
                    <div class="form-text"><?= e(__('rx_req_phone_help')) ?></div>
                    <?= $err('phone') ?>
                </div>
                <?php if (!$user): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="rxEmail"><?= e(__('email')) ?> <span class="text-muted small">(<?= e(__('optional')) ?>)</span></label>
                        <input class="<?= $cls('email') ?>" type="email" id="rxEmail" name="email" value="<?= e($val('email', $gEmail)) ?>" maxlength="191" autocomplete="email">
                        <?= $err('email') ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="row g-3 mt-0" data-location-picker data-api="<?= e(url('api/locations')) ?>">
                <div class="col-md-4">
                    <label class="form-label" for="dv"><?= e(__('division')) ?></label>
                    <select class="<?= $cls('division_id', 'form-select') ?>" id="dv" name="division_id" data-level="division">
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($divisions as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('division_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('division_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ds"><?= e(__('district')) ?></label>
                    <select class="<?= $cls('district_id', 'form-select') ?>" id="ds" name="district_id" data-level="district" <?= $districts ? '' : 'disabled' ?>>
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($districts as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('district_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('district_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="up"><?= e(__('upazila')) ?></label>
                    <select class="<?= $cls('upazila_id', 'form-select') ?>" id="up" name="upazila_id" data-level="upazila" <?= $upazilas ? '' : 'disabled' ?>>
                        <option value=""><?= e(__('select')) ?></option>
                        <?php foreach ($upazilas as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $val('upazila_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($locName($d)) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('upazila_id') ?>
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label" for="rxAddr"><?= e(__('addr_line')) ?> *</label>
                <input class="<?= $cls('address') ?>" id="rxAddr" name="address" value="<?= e($val('address')) ?>" maxlength="255" placeholder="<?= e(__('addr_line_ph')) ?>" autocomplete="street-address" required>
                <?= $err('address') ?>
            </div>
        </section>
    </div>

    <aside class="sb-co-side">
        <section class="sb-block">
            <h2 class="h6 fw-bold"><i class="bi bi-shield-check text-success"></i> <?= e(__('rx_req_how')) ?></h2>
            <ul class="small text-muted ps-3 mb-3">
                <li><?= e(__('rx_req_how_1')) ?></li>
                <li><?= e(__('rx_req_how_2')) ?></li>
                <li><?= e(__('rx_req_how_3')) ?></li>
            </ul>
            <div class="form-check mb-3">
                <input class="form-check-input<?= isset($errors['agree']) ? ' is-invalid' : '' ?>" type="checkbox" name="agree" value="1" id="rxAgree" <?= $val('agree') ? 'checked' : '' ?> required>
                <label class="form-check-label small" for="rxAgree"><?= e(__('rx_req_agree')) ?></label>
                <?= $err('agree') ?>
            </div>
            <button class="btn btn-primary w-100 btn-lg"><i class="bi bi-send"></i> <?= e(__('rx_req_submit')) ?></button>
            <?php if (!$user): ?>
                <p class="small text-muted text-center mt-2 mb-0"><?= e(__('have_account')) ?> <a href="<?= e(url('login?tab=login')) ?>"><?= e(__('login_now')) ?></a> — <?= e(__('rx_req_login_note')) ?></p>
            <?php else: ?>
                <p class="small text-center mt-2 mb-0"><a href="<?= e(url('account/prescriptions')) ?>"><i class="bi bi-folder2-open"></i> <?= e(__('my_prescriptions')) ?></a></p>
            <?php endif; ?>
        </section>
    </aside>
</form>
<?php endif; ?>
