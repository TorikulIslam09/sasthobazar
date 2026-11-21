<?php /** Ticket (staff). Vars: $ticket, $replies, $staff, $refunds, $history, $orders */ $t = $ticket; $last = count($replies) - 1; $canReply = can('support.reply'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h5 mb-1"><a href="<?= e(url('admin/support')) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i></a> #<?= e($t['ticket_no']) ?> · <?= e($t['subject']) ?></h1>
        <div class="small text-muted"><?= e(dt($t['created_at'], 'd M Y, h:i A')) ?> · <?= e(__('tkc_' . $t['category'])) ?></div>
    </div>
    <div class="d-flex gap-2"><?= Support::priorityBadge($t['priority']) ?> <?= Support::badge($t['status']) ?></div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex flex-column gap-2">
                <?php foreach ($replies as $i => $r): $staffMsg = in_array($r['user_type'], ['super_admin', 'employee'], true); ?>
                    <div class="p-2 rounded border <?= (int) $r['is_internal'] ? 'bg-warning-subtle border-warning' : ($staffMsg ? 'bg-brand-subtle' : 'bg-white') ?>" <?= $i === $last ? 'id="last"' : '' ?>>
                        <div class="small mb-1"><b><?= e($r['name'] ?? '—') ?></b>
                            <span class="text-muted">· <?= e($staffMsg ? __('staff') : __('customer')) ?> · <?= e(dt($r['created_at'], 'd M, h:i A')) ?></span>
                            <?php if ((int) $r['is_internal']): ?><span class="badge text-bg-warning"><i class="bi bi-lock"></i> <?= e(__('internal_note')) ?></span><?php endif; ?>
                        </div>
                        <div class="small" style="white-space: pre-wrap"><?= e($r['message']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if ($canReply): ?>
            <form method="post" action="<?= e(url('admin/support/' . (int) $t['id'] . '/reply')) ?>" class="card border-0 shadow-sm">
                <?= csrf_field() ?>
                <div class="card-body">
                    <label class="form-label fw-semibold" for="msg"><?= e(__('reply')) ?></label>
                    <textarea class="form-control" id="msg" name="message" rows="5" maxlength="5000" required></textarea>
                    <div class="d-flex flex-wrap gap-3 align-items-center mt-2">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="internal" value="1" id="int"><label class="form-check-label small" for="int"><i class="bi bi-lock"></i> <?= e(__('internal_note_help')) ?></label></div>
                        <div class="d-flex align-items-center gap-2 ms-auto">
                            <label class="small text-muted" for="rst"><?= e(__('then_set_status')) ?></label>
                            <select class="form-select form-select-sm" id="rst" name="status" style="width: auto">
                                <option value=""><?= e(__('tk_auto')) ?></option>
                                <?php foreach (Support::STATUSES as $st): ?><option value="<?= $st ?>"><?= e(__('tk_' . $st)) ?></option><?php endforeach; ?>
                            </select>
                            <button class="btn btn-brand btn-sm"><i class="bi bi-send"></i> <?= e(__('send')) ?></button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('customer')) ?></div>
            <div class="card-body small">
                <div class="fw-semibold"><?= e($t['customer_name'] ?? '—') ?></div>
                <div><?= e($t['customer_phone'] ?? '') ?></div>
                <div class="text-muted"><?= e($t['customer_email'] ?? '') ?></div>
                <div class="text-muted mt-1"><?= e(__('n_orders_total', ['n' => num($orders)])) ?></div>
                <?php if ($t['order_no'] && can('orders.view')): ?><a class="btn btn-outline-secondary btn-sm mt-2" href="<?= e(url('admin/orders/' . (int) $t['order_id'])) ?>"><i class="bi bi-receipt"></i> #<?= e($t['order_no']) ?></a><?php endif; ?>
            </div>
        </div>
        <form method="post" action="<?= e(url('admin/support/' . (int) $t['id'])) ?>" class="card border-0 shadow-sm mb-3">
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><?= e(__('ticket_details')) ?></div>
            <div class="card-body row g-2 small">
                <div class="col-6"><label class="form-label small mb-1" for="s1"><?= e(__('status')) ?></label>
                    <select class="form-select form-select-sm" id="s1" name="status" <?= $canReply ? '' : 'disabled' ?>><?php foreach (Support::STATUSES as $st): ?><option value="<?= $st ?>" <?= $t['status'] === $st ? 'selected' : '' ?>><?= e(__('tk_' . $st)) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label small mb-1" for="p1"><?= e(__('priority')) ?></label>
                    <select class="form-select form-select-sm" id="p1" name="priority" <?= $canReply ? '' : 'disabled' ?>><?php foreach (Support::PRIORITIES as $p): ?><option value="<?= $p ?>" <?= $t['priority'] === $p ? 'selected' : '' ?>><?= e(__('tkp_' . $p)) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label small mb-1" for="c1"><?= e(__('category')) ?></label>
                    <select class="form-select form-select-sm" id="c1" name="category" <?= $canReply ? '' : 'disabled' ?>><?php foreach (Support::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $t['category'] === $c ? 'selected' : '' ?>><?= e(__('tkc_' . $c)) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label small mb-1" for="a1"><?= e(__('assigned_to')) ?></label>
                    <select class="form-select form-select-sm" id="a1" name="assigned_to" <?= can('support.assign') ? '' : 'disabled' ?>>
                        <option value=""><?= e(__('unassigned')) ?></option>
                        <?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $t['assigned_to'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <?php if ($canReply): ?><div class="card-footer bg-white"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
        </form>
        <?php if ($t['order_id']): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span><?= e(__('returns_refunds')) ?></span><?php if (can('refunds.view')): ?><a class="small" href="<?= e(url('admin/refunds?q=' . rawurlencode((string) $t['order_no']))) ?>"><?= e(__('open')) ?></a><?php endif; ?></div>
                <ul class="list-group list-group-flush small">
                    <?php if (!$refunds): ?><li class="list-group-item text-muted"><?= e(__('no_refunds_for_order')) ?></li><?php endif; ?>
                    <?php foreach ($refunds as $r): ?>
                        <li class="list-group-item d-flex justify-content-between gap-2"><span class="text-truncate"><?= e($r['refund_no']) ?> · <?= e($r['product_name'] ?? '') ?></span><span class="text-nowrap"><?= e(money($r['amount'])) ?> <span class="badge text-bg-light border"><?= e(__('rf_' . $r['status'])) ?></span></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($history): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><?= e(__('other_tickets')) ?></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($history as $h): ?><li class="list-group-item d-flex justify-content-between gap-2"><a class="text-truncate" href="<?= e(url('admin/support/' . (int) $h['id'])) ?>">#<?= e($h['ticket_no']) ?> <?= e($h['subject']) ?></a><?= Support::badge($h['status']) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
