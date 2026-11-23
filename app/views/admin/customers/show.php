<?php
/** Customer page. Vars: $u, $d (stats, orders, addresses, rx, tickets, wishlist, reviews), $open (running orders), $errors */
$id = (int) $u['id'];
$s = $d['stats'];
$stColor = ['active' => 'success', 'inactive' => 'secondary', 'banned' => 'danger'];
$canEdit = can('customers.edit');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= e(url('admin/customers')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('customers')) ?></a>
        <h1 class="h4 mb-0"><?= e($u['name']) ?> <span class="badge text-bg-<?= $stColor[$u['status']] ?? 'light' ?>"><?= e(__('cst_' . $u['status'])) ?></span></h1>
        <span class="small text-muted"><?= e(__('joined')) ?> <?= e(dt($u['created_at'], 'd M Y')) ?><?= $u['last_login_at'] ? ' · ' . e(__('last_login')) . ' ' . e(dt($u['last_login_at'], 'd M Y, h:i A')) : '' ?></span>
    </div>
</div>

<div class="row g-2 mb-3">
    <?php foreach ([[__('orders'), num((int) $s['orders'])], [__('os_delivered'), num((int) $s['delivered'])], [__('os_cancelled'), num((int) $s['cancelled'])],
                    [__('cust_spent'), money($s['spent'])], [__('refunded'), money($s['refunded'])], [__('cust_last_order'), $s['last_order'] ? dt($s['last_order'], 'd M Y') : '—']] as [$lbl, $v]): ?>
        <div class="col-6 col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2"><div class="small text-muted"><?= e($lbl) ?></div><div class="fw-bold"><?= e($v) ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('orders')) ?></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <tbody>
                    <?php if (!$d['orders']): ?><tr><td class="text-muted text-center py-3"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                    <?php foreach ($d['orders'] as $o): ?>
                        <tr>
                            <td><?php if (can('orders.view')): ?><a href="<?= e(url('admin/orders/' . (int) $o['id'])) ?>">#<?= e($o['order_no']) ?></a><?php else: ?>#<?= e($o['order_no']) ?><?php endif; ?></td>
                            <td><?= e(dt($o['placed_at'], 'd M Y')) ?></td>
                            <td><?= OrderView::badge($o['status']) ?></td>
                            <td><?= e(__('pm_' . $o['payment_method'])) ?> <?= OrderView::payBadge($o['payment_status']) ?></td>
                            <td class="text-end fw-semibold"><?= e(money($o['grand_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ((int) $s['orders'] > count($d['orders']) && can('orders.view')): ?><div class="card-footer bg-white small"><a href="<?= e(url('admin/orders?q=' . rawurlencode((string) $u['phone']))) ?>"><?= e(__('view_all')) ?> (<?= e(num((int) $s['orders'])) ?>)</a></div><?php endif; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><?= e(__('prescriptions')) ?></div>
                    <ul class="list-group list-group-flush small">
                        <?php if (!$d['rx']): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
                        <?php foreach ($d['rx'] as $p): ?>
                            <li class="list-group-item d-flex justify-content-between gap-2">
                                <span><?php if (can('prescriptions.view')): ?><a href="<?= e(url($p['request_type'] === 'order_request' ? 'admin/prescriptions/' . (int) $p['id'] : 'admin/prescriptions/' . (int) $p['id'] . '/file')) ?>" <?= $p['request_type'] === 'order_request' ? '' : 'target="_blank" rel="noopener"' ?>><?= e($p['group_code'] ?: mb_strimwidth((string) $p['original_name'], 0, 24, '…')) ?></a><?php else: ?><?= e($p['group_code'] ?: '#' . $p['id']) ?><?php endif; ?>
                                    <div class="text-muted"><?= e(dt($p['created_at'], 'd M Y')) ?></div></span>
                                <span class="badge align-self-start text-bg-<?= $p['status'] === 'approved' ? 'success' : ($p['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= e(__('rx_status_' . $p['status'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><?= e(__('support_tickets')) ?></div>
                    <ul class="list-group list-group-flush small">
                        <?php if (!$d['tickets']): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
                        <?php foreach ($d['tickets'] as $t): ?>
                            <li class="list-group-item"><?php if (can('support.view')): ?><a href="<?= e(url('admin/support/' . (int) $t['id'])) ?>"><?= e($t['ticket_no']) ?></a><?php else: ?><?= e($t['ticket_no']) ?><?php endif; ?>
                                — <?= e(mb_strimwidth((string) $t['subject'], 0, 40, '…')) ?> <span class="badge text-bg-light"><?= e(__('tk_' . $t['status'])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3 small">
            <div class="card-header bg-white fw-semibold"><?= e(__('cust_contact')) ?></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><i class="bi bi-phone"></i> <a href="tel:<?= e((string) $u['phone']) ?>"><?= e((string) $u['phone']) ?></a>
                    <?= $u['phone_verified_at'] ? '<span class="badge text-bg-success">' . e(__('verified')) . '</span>' : '<span class="badge text-bg-light">' . e(__('cust_not_verified')) . '</span>' ?></li>
                <li class="list-group-item"><i class="bi bi-envelope"></i> <?= e($u['email'] ?: '—') ?>
                    <?php if ($u['email']): ?><?= $u['email_verified_at'] ? '<span class="badge text-bg-success">' . e(__('verified')) . '</span>' : '<span class="badge text-bg-light">' . e(__('cust_not_verified')) . '</span>' ?><?php endif; ?></li>
                <li class="list-group-item"><i class="bi bi-translate"></i> <?= e($u['lang'] === 'en' ? 'English' : 'বাংলা') ?> · <i class="bi bi-heart"></i> <?= e(num($d['wishlist'])) ?> · <i class="bi bi-star"></i> <?= e(num($d['reviews'])) ?></li>
            </ul>
        </div>

        <div class="card border-0 shadow-sm mb-3 small">
            <div class="card-header bg-white fw-semibold"><?= e(__('my_addresses')) ?></div>
            <ul class="list-group list-group-flush">
                <?php if (!$d['addresses']): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
                <?php foreach ($d['addresses'] as $a): ?>
                    <li class="list-group-item"><b><?= e($a['recipient_name']) ?></b> · <?= e($a['phone']) ?><?= (int) $a['is_default'] ? ' <span class="badge text-bg-light">' . e(__('default')) . '</span>' : '' ?>
                        <div class="text-muted"><?= e($a['address_line']) ?>, <?= e(OrderView::area($a)) ?></div></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($canEdit): ?>
            <form method="post" action="<?= e(url('admin/customers/' . $id . '/status')) ?>" class="card border-0 shadow-sm mb-3 small">
                <?= csrf_field() ?>
                <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-lock"></i> <?= e(__('cust_account_status')) ?></div>
                <div class="card-body">
                    <select class="form-select form-select-sm mb-2" name="status" aria-label="<?= e(__('status')) ?>">
                        <?php foreach (Customer::STATUSES as $st): ?><option value="<?= $st ?>" <?= $u['status'] === $st ? 'selected' : '' ?>><?= e(__('cst_' . $st)) ?></option><?php endforeach; ?>
                    </select>
                    <input class="form-control form-control-sm mb-2" name="reason" maxlength="255" placeholder="<?= e(__('cust_reason_ph')) ?>" aria-label="<?= e(__('reason')) ?>">
                    <div class="form-text mt-0 mb-2"><?= e(__('cust_status_help')) ?></div>
                    <button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button>
                </div>
            </form>
            <form method="post" action="<?= e(url('admin/customers/' . $id . '/password')) ?>" class="card border-0 shadow-sm mb-3 small" id="password">
                <?= csrf_field() ?>
                <div class="card-header bg-white fw-semibold"><i class="bi bi-key"></i> <?= e(__('cust_new_password')) ?></div>
                <div class="card-body">
                    <input class="form-control form-control-sm mb-1<?= isset($errors['password']) ? ' is-invalid' : '' ?>" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-label="<?= e(__('password')) ?>">
                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>
                    <div class="form-text mt-0 mb-2"><?= e(__('cust_password_help')) ?></div>
                    <button class="btn btn-outline-secondary btn-sm" data-confirm-click="<?= e(__('cust_password_confirm')) ?>"><?= e(__('save')) ?></button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (can('customers.delete')): ?>
            <div class="card border-danger mb-3 small" id="delete">
                <div class="card-header bg-white text-danger fw-semibold"><i class="bi bi-trash"></i> <?= e(__('cust_delete')) ?></div>
                <div class="card-body">
                    <p class="mb-2"><?= e(__('cust_delete_help')) ?></p>
                    <?php if ($open): ?>
                        <div class="alert alert-warning mb-0 py-2"><?= e(__('cust_delete_open', ['n' => num($open)])) ?></div>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('admin/customers/' . $id . '/delete')) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label mb-1" for="cdp"><?= e(__('cust_type_phone', ['phone' => (string) $u['phone']])) ?></label>
                            <input class="form-control form-control-sm mb-2" id="cdp" name="confirm_phone" autocomplete="off" inputmode="tel" required>
                            <button class="btn btn-danger btn-sm" data-confirm-click="<?= e(__('cust_delete_confirm')) ?>"><i class="bi bi-trash"></i> <?= e(__('cust_delete')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
