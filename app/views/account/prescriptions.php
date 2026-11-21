<?php
/** My prescriptions. Vars: $tab, $rows, $groups (group_code => files), $pager */
$badge = static fn(string $s) => '<span class="badge text-bg-' . ($s === 'approved' ? 'success' : ($s === 'rejected' ? 'danger' : 'warning')) . '">' . e(__('rx_status_' . $s)) . '</span>';
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account')) ?>"><?= e(__('my_account')) ?></a> › <span><?= e(__('my_prescriptions')) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => $tab]) ?>
    <div class="min-w-0">
        <section class="sb-block">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h1 class="h5 mb-0"><i class="bi bi-file-earmark-medical"></i> <?= e(__('my_prescriptions')) ?></h1>
                <a class="btn btn-primary btn-sm" href="<?= e(url('prescription')) ?>"><i class="bi bi-cloud-arrow-up"></i> <?= e(__('upload_prescription')) ?></a>
            </div>
            <p class="small text-muted mb-0"><?= e(__('my_prescriptions_help')) ?></p>
        </section>

        <?php if (!$rows): ?>
            <section class="sb-block sb-empty text-center">
                <i class="bi bi-file-earmark-medical display-6 text-muted"></i>
                <p class="text-muted my-2"><?= e(__('my_prescriptions_empty')) ?></p>
                <a class="btn btn-primary" href="<?= e(url('prescription')) ?>"><i class="bi bi-cloud-arrow-up"></i> <?= e(__('upload_prescription')) ?></a>
            </section>
        <?php endif; ?>

        <?php foreach ($rows as $r): $files = $r['group_code'] ? ($groups[$r['group_code']] ?? [$r]) : [$r]; ?>
            <section class="sb-block small">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                    <div>
                        <b><?= e($r['group_code'] ?: ('#' . num($r['id']))) ?></b>
                        <span class="badge text-bg-light"><?= e(__($r['request_type'] === 'order_request' ? 'rx_type_order_request' : 'rx_type_checkout')) ?></span>
                        <span class="text-muted"> · <?= e(dt($r['created_at'], 'd M Y, h:i A')) ?></span>
                    </div>
                    <div class="d-flex gap-1 flex-wrap">
                        <?= $badge($r['status']) ?>
                        <?php if ($r['request_type'] === 'order_request' && $r['request_status']): ?><span class="badge text-bg-info"><?= e(__('rxr_' . $r['request_status'])) ?></span><?php endif; ?>
                    </div>
                </div>
                <?php if ($r['patient_name']): ?><div><?= e(__('patient')) ?>: <?= e($r['patient_name']) ?></div><?php endif; ?>
                <?php if ($r['notes']): ?><div class="text-muted"><?= e($r['notes']) ?></div><?php endif; ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($files as $i => $f): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('account/prescriptions/' . (int) $f['id'])) ?>" target="_blank" rel="noopener">
                            <i class="bi <?= $f['mime_type'] === 'application/pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' ?>"></i> <?= e(__('rx_page_n', ['n' => num($i + 1)])) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($r['linked_order']): ?>
                    <div class="mt-2"><i class="bi bi-bag-check text-success"></i> <?= e(__('order')) ?>: <a href="<?= e(url('account/orders/' . rawurlencode((string) $r['linked_order']))) ?>">#<?= e($r['linked_order']) ?></a></div>
                <?php endif; ?>
                <?php if ($r['review_note']): ?><div class="text-muted mt-1"><?= e(__('pharmacist_note')) ?>: <?= e($r['review_note']) ?></div><?php endif; ?>
            </section>
        <?php endforeach; ?>
        <div><?= $pager->links() ?></div>
    </div>
</div>
