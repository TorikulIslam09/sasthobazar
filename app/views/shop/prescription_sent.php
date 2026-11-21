<?php /** Prescription request sent. Vars: $code, $first, $files */ ?>
<div class="sb-auth" style="max-width: 620px">
    <section class="sb-block text-center">
        <div class="display-6 text-success mb-1"><i class="bi bi-check-circle-fill"></i></div>
        <h1 class="h5 mb-1"><?= e(__('rx_req_sent_title')) ?></h1>
        <p class="mb-1"><?= e(__('rx_req_ref')) ?>: <b><?= e($code) ?></b> · <?= e(__('rx_req_n_files', ['n' => num($files)])) ?></p>
        <p class="small text-muted mb-3"><?= e(__('rx_req_sent_text', ['phone' => (string) $first['guest_phone']])) ?></p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <?php if ($first['user_id'] !== null): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('account/prescriptions')) ?>"><i class="bi bi-folder2-open"></i> <?= e(__('my_prescriptions')) ?></a>
            <?php else: ?>
                <a class="btn btn-outline-primary btn-sm" href="<?= e(url('register')) ?>"><i class="bi bi-person-plus"></i> <?= e(__('rx_req_register_hint')) ?></a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('prescription')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('rx_req_another')) ?></a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('products')) ?>"><i class="bi bi-bag"></i> <?= e(__('continue_shopping')) ?></a>
        </div>
    </section>
</div>
