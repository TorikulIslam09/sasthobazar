<?php /** Public order tracking. Vars: $no, $contact (mobile or email), $order, $timeline, $error */ ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <span><?= e(__('track_order')) ?></span></nav>
<div class="sb-auth" style="max-width: 640px">
    <section class="sb-block">
        <h1 class="h5"><i class="bi bi-truck"></i> <?= e(__('track_order')) ?></h1>
        <form method="get" action="<?= e(url('track')) ?>" class="row g-2 align-items-end">
            <div class="col-sm-5">
                <label class="form-label small" for="tno"><?= e(__('order_no')) ?></label>
                <input class="form-control" id="tno" name="order_no" value="<?= e($no) ?>" maxlength="30" required placeholder="SB2611...">
            </div>
            <div class="col-sm-5">
                <label class="form-label small" for="tph"><?= e(__('track_contact')) ?></label>
                <input class="form-control" id="tph" name="contact" value="<?= e($contact) ?>" maxlength="191" required placeholder="<?= e(__('track_contact_ph')) ?>" autocomplete="email">
            </div>
            <div class="col-sm-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
        </form>
        <?php if ($error): ?><div class="alert alert-warning small mt-3 mb-0"><?= e($error) ?></div><?php endif; ?>
    </section>
    <?php if ($order): ?>
        <section class="sb-block">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <b>#<?= e($order['order_no']) ?></b>
                <?= OrderView::badge($order['status']) ?>
            </div>
            <?= View::partial('orders/_timeline', ['timeline' => $timeline]) ?>
            <p class="small text-muted mb-0"><?= e(__('placed_on')) ?> <?= e(dt($order['placed_at'])) ?> · <?= e(money($order['grand_total'])) ?></p>
        </section>
    <?php endif; ?>
</div>
