<?php /** Order tracking box. Vars: $t */ ?>
<?php if ($t('subtitle') !== ''): ?><p class="mb-2"><?= nl2br(e($t('subtitle'))) ?></p><?php endif; ?>
<form method="get" action="<?= e(url('track')) ?>" class="row g-2">
    <div class="col-sm-5"><input class="form-control" name="order_no" maxlength="30" required placeholder="<?= e(__('order_no')) ?> (SB…)" aria-label="<?= e(__('order_no')) ?>"></div>
    <div class="col-sm-5"><input class="form-control" name="contact" maxlength="191" required placeholder="<?= e(__('track_contact_ph')) ?>" aria-label="<?= e(__('track_contact')) ?>"></div>
    <div class="col-sm-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> <?= e(__('ftrack_btn')) ?></button></div>
</form>
