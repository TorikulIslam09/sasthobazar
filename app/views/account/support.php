<?php /** My tickets. Vars: $tickets */ ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account')) ?>"><?= e(__('my_account')) ?></a> › <span><?= e(__('my_tickets')) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => 'support']) ?>
    <section class="sb-block min-w-0">
        <div class="sb-sec-head">
            <h1 class="h5 mb-0"><?= e(__('my_tickets')) ?></h1>
            <a class="btn btn-primary btn-sm" href="<?= e(url('account/support/new')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('open_ticket')) ?></a>
        </div>
        <p class="small text-muted"><?= e(__('support_intro')) ?> <a href="<?= e(url('faq')) ?>"><?= e(__('faq_title')) ?></a></p>
        <?php if (!$tickets): ?>
            <div class="sb-empty"><i class="bi bi-headset"></i><p><?= e(__('no_tickets')) ?></p></div>
        <?php else: foreach ($tickets as $t): ?>
            <a class="sb-order-card" href="<?= e(url('account/support/' . $t['ticket_no'])) ?>">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <span><b>#<?= e($t['ticket_no']) ?></b> <span class="text-muted small"><?= e(dt($t['created_at'], 'd M Y')) ?></span></span>
                    <?= Support::badge($t['status']) ?>
                </div>
                <div class="fw-semibold mt-1"><?= e($t['subject']) ?></div>
                <div class="small text-muted"><?= e(__('tkc_' . $t['category'])) ?><?= $t['order_no'] ? ' · #' . e($t['order_no']) : '' ?> · <?= e(__('n_messages', ['n' => num((int) $t['replies'])])) ?> · <?= e(__('last_activity', ['date' => dt($t['last_reply_at'] ?? $t['created_at'], 'd M, h:i A')])) ?></div>
            </a>
        <?php endforeach; endif; ?>
    </section>
</div>
