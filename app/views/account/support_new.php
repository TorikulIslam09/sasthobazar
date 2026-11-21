<?php
/** Open a ticket. Vars: $orders, $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account/support')) ?>"><?= e(__('my_tickets')) ?></a> › <span><?= e(__('open_ticket')) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => 'support']) ?>
    <section class="sb-block min-w-0">
        <h1 class="h5 mb-3"><?= e(__('open_ticket')) ?></h1>
        <div class="alert alert-light border small"><i class="bi bi-lightbulb"></i> <?= e(__('ticket_return_hint')) ?></div>
        <form method="post" action="<?= e(url('account/support')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="cat"><?= e(__('ticket_topic')) ?> *</label>
                    <select class="<?= $cls('category', 'form-select') ?>" id="cat" name="category">
                        <?php foreach (Support::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $val('category') === $c ? 'selected' : '' ?>><?= e(__('tkc_' . $c)) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('category') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ord"><?= e(__('related_order')) ?></label>
                    <select class="<?= $cls('order_no', 'form-select') ?>" id="ord" name="order_no">
                        <option value=""><?= e(__('none')) ?></option>
                        <?php foreach ($orders as $o): ?><option value="<?= e($o['order_no']) ?>" <?= $val('order_no') === $o['order_no'] ? 'selected' : '' ?>>#<?= e($o['order_no']) ?> · <?= e(dt($o['placed_at'], 'd M Y')) ?> · <?= e(money($o['grand_total'])) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('order_no') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="sub"><?= e(__('subject')) ?> *</label>
                    <input class="<?= $cls('subject') ?>" id="sub" name="subject" value="<?= e($val('subject')) ?>" maxlength="200" required>
                    <?= $err('subject') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="msg"><?= e(__('message')) ?> *</label>
                    <textarea class="<?= $cls('message') ?>" id="msg" name="message" rows="6" maxlength="3000" required placeholder="<?= e(__('ticket_message_ph')) ?>"><?= e($val('message')) ?></textarea>
                    <?= $err('message') ?>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-send"></i> <?= e(__('send')) ?></button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('account/support')) ?>"><?= e(__('cancel')) ?></a>
                </div>
            </div>
        </form>
    </section>
</div>
