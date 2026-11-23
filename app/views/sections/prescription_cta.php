<?php /** Vars: $t */ ?>
<a href="<?= e(url('prescription')) ?>" class="sb-rx-cta mb-0">
    <span class="sb-rx-hero-ico"><i class="bi bi-file-earmark-medical"></i></span>
    <span class="flex-grow-1 min-w-0"><b><?= e(__('rx_cta_title')) ?></b><small><?= e($t('subtitle') !== '' ? $t('subtitle') : __('rx_cta_text')) ?></small></span>
    <span class="btn btn-light btn-sm fw-semibold"><?= e(__('upload_prescription')) ?></span>
</a>
