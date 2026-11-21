<?php /** Ticket conversation (customer). Vars: $ticket, $replies */ $t = $ticket; $last = count($replies) - 1; ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account/support')) ?>"><?= e(__('my_tickets')) ?></a> › <span>#<?= e($t['ticket_no']) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => 'support']) ?>
    <section class="sb-block min-w-0">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div>
                <h1 class="h5 mb-1"><?= e($t['subject']) ?></h1>
                <div class="small text-muted">#<?= e($t['ticket_no']) ?> · <?= e(__('tkc_' . $t['category'])) ?><?php if ($t['order_no']): ?> · <a href="<?= e(url('account/orders/' . $t['order_no'])) ?>">#<?= e($t['order_no']) ?></a><?php endif; ?> · <?= e(dt($t['created_at'], 'd M Y, h:i A')) ?></div>
            </div>
            <div><?= Support::badge($t['status']) ?></div>
        </div>
        <div class="d-flex flex-column gap-2 my-3">
            <?php foreach ($replies as $i => $r): $staff = in_array($r['user_type'], ['super_admin', 'employee'], true); ?>
                <div class="sb-ticket-msg<?= $staff ? ' staff' : '' ?>" <?= $i === $last ? 'id="last"' : '' ?>>
                    <div class="small mb-1"><b><?= e($staff ? __('support_team') : __('you')) ?></b> <span class="text-muted">· <?= e(dt($r['created_at'], 'd M, h:i A')) ?></span></div>
                    <div style="white-space: pre-wrap"><?= e($r['message']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($t['status'] !== 'closed'): ?>
            <form method="post" action="<?= e(url('account/support/' . $t['ticket_no'] . '/reply')) ?>">
                <?= csrf_field() ?>
                <label class="form-label" for="msg"><?= e(__('your_reply')) ?></label>
                <textarea class="form-control" id="msg" name="message" rows="4" maxlength="3000" required></textarea>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <button class="btn btn-primary"><i class="bi bi-send"></i> <?= e(__('send')) ?></button>
                </div>
            </form>
            <form method="post" action="<?= e(url('account/support/' . $t['ticket_no'] . '/close')) ?>" class="mt-2" data-confirm="<?= e(__('ticket_close_confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-link btn-sm text-muted p-0"><i class="bi bi-check2-circle"></i> <?= e(__('ticket_close_solved')) ?></button>
            </form>
        <?php else: ?>
            <div class="alert alert-light border small mb-0"><?= e(__('ticket_closed_msg')) ?> <a href="<?= e(url('account/support/new')) ?>"><?= e(__('open_ticket')) ?></a></div>
        <?php endif; ?>
    </section>
</div>
