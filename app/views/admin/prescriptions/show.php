<?php
/** Order-by-prescription request. Vars: $r (first file row), $files, $user (?array), $area, $reviewer, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$canVerify = can('prescriptions.verify');
$canProgress = can('prescriptions.update_status');
$canEdit = $canVerify || $canProgress;
$phone = (string) ($r['guest_phone'] ?: ($user['phone'] ?? ''));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= e(url('admin/prescriptions?type=order_request&status=all')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('prescriptions')) ?></a>
        <h1 class="h4 mb-0"><?= e(__('rx_request')) ?> <?= e($r['group_code']) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <span class="badge text-bg-<?= $r['status'] === 'approved' ? 'success' : ($r['status'] === 'rejected' ? 'danger' : 'warning') ?> fs-6"><?= e(__('rx_status_' . $r['status'])) ?></span>
        <?php if ($r['request_status']): ?><span class="badge text-bg-info fs-6"><?= e(__('rxr_' . $r['request_status'])) ?></span><?php endif; ?>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold"><?= e(__('rx_req_files')) ?> (<?= e(num(count($files))) ?>)</h2>
                <div class="row g-2">
                    <?php foreach ($files as $i => $f): $src = url('admin/prescriptions/' . (int) $f['id'] . '/file'); ?>
                        <div class="col-6 col-md-4">
                            <a href="<?= e($src) ?>" target="_blank" rel="noopener" class="d-block border rounded p-2 text-center text-decoration-none h-100">
                                <?php if ($f['mime_type'] === 'application/pdf'): ?>
                                    <i class="bi bi-file-earmark-pdf display-5 text-danger"></i>
                                <?php else: ?>
                                    <img src="<?= e($src) ?>" alt="" class="img-fluid rounded" style="max-height:160px;object-fit:contain" loading="lazy">
                                <?php endif; ?>
                                <div class="small text-muted text-truncate mt-1"><?= e(__('rx_page_n', ['n' => num($i + 1)])) ?> · <?= e(mb_strimwidth((string) $f['original_name'], 0, 22, '…')) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm small">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4"><?= e(__('customer')) ?></dt>
                    <dd class="col-sm-8"><?= e($r['guest_name'] ?: ($user['name'] ?? '—')) ?>
                        <?php if ($user): ?> <span class="badge text-bg-light"><?= e(__('account')) ?>: <?= e((string) $user['phone']) ?></span><?php else: ?> <span class="badge text-bg-secondary"><?= e(__('guest')) ?></span><?php endif; ?></dd>
                    <dt class="col-sm-4"><?= e(__('phone')) ?></dt>
                    <dd class="col-sm-8"><a href="tel:<?= e($phone) ?>" class="fw-semibold"><i class="bi bi-telephone"></i> <?= e($phone) ?></a></dd>
                    <?php if ($r['patient_name']): ?><dt class="col-sm-4"><?= e(__('patient')) ?></dt><dd class="col-sm-8"><?= e($r['patient_name']) ?></dd><?php endif; ?>
                    <dt class="col-sm-4"><?= e(__('delivery_address')) ?></dt>
                    <dd class="col-sm-8"><?= e($r['address'] ?: '—') ?><?php if ($area): ?><div class="text-muted"><?= e($area) ?></div><?php endif; ?></dd>
                    <dt class="col-sm-4"><?= e(__('rx_req_notes')) ?></dt>
                    <dd class="col-sm-8"><?= $r['notes'] ? nl2br(e($r['notes'])) : '—' ?></dd>
                    <dt class="col-sm-4"><?= e(__('created_at')) ?></dt>
                    <dd class="col-sm-8"><?= e(dt($r['created_at'], 'd M Y, h:i A')) ?></dd>
                    <?php if ($reviewer): ?><dt class="col-sm-4"><?= e(__('reviewed_by')) ?></dt><dd class="col-sm-8"><?= e($reviewer) ?> · <?= e(dt($r['reviewed_at'], 'd M, h:i A')) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <form method="post" action="<?= e(url('admin/prescriptions/' . (int) $r['id'])) ?>" class="card border-0 shadow-sm">
            <?= csrf_field() ?>
            <fieldset <?= $canEdit ? '' : 'disabled' ?>>
            <div class="card-body">
                <h2 class="h6 fw-bold"><?= e(__('rx_req_handle')) ?></h2>
                <label class="form-label small fw-semibold"><?= e(__('rx_pharmacist_check')) ?></label>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
                        <div class="form-check"><input class="form-check-input" type="radio" name="status" value="<?= $s ?>" id="st<?= $s ?>" <?= $r['status'] === $s ? 'checked' : '' ?> <?= $canVerify ? '' : 'disabled' ?>><label class="form-check-label small" for="st<?= $s ?>"><?= e(__('rx_status_' . $s)) ?></label></div>
                    <?php endforeach; ?>
                </div>
                <label class="form-label small fw-semibold" for="rqs"><?= e(__('rx_req_progress')) ?></label>
                <select class="form-select form-select-sm mb-3" id="rqs" name="request_status" <?= $canProgress ? '' : 'disabled' ?>>
                    <?php foreach (Prescription::REQUEST_STATUSES as $s): ?><option value="<?= $s ?>" <?= $r['request_status'] === $s ? 'selected' : '' ?>><?= e(__('rxr_' . $s)) ?></option><?php endforeach; ?>
                </select>
                <label class="form-label small fw-semibold" for="ono"><?= e(__('rx_req_order_no')) ?></label>
                <input class="form-control form-control-sm<?= isset($errors['order_no']) ? ' is-invalid' : '' ?>" id="ono" name="order_no" value="<?= e((string) $r['order_no']) ?>" maxlength="30" placeholder="SB..." <?= $canProgress ? '' : 'disabled' ?>>
                <div class="form-text"><?= e(__('rx_req_order_no_help')) ?></div>
                <?= $err('order_no') ?>
                <label class="form-label small fw-semibold mt-3" for="rvn"><?= e(__('rx_review_note')) ?></label>
                <textarea class="form-control form-control-sm<?= isset($errors['review_note']) ? ' is-invalid' : '' ?>" id="rvn" name="review_note" rows="3" maxlength="500"><?= e((string) $r['review_note']) ?></textarea>
                <?= $err('review_note') ?>
                <div class="form-text"><?= e(__('rx_req_notify_help')) ?></div>
            </div>
            </fieldset>
            <?php if ($canEdit): ?><div class="card-footer bg-white text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
        </form>
    </div>
</div>
