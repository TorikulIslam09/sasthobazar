<?php /** Vendor payouts. Vars: $rows, $pager, $status, $counts */ ?>
<h1 class="h4 mb-3"><i class="bi bi-cash-coin"></i> <?= e(__('vendor_payouts')) ?></h1>
<ul class="nav nav-pills mb-3 status-tabs">
    <?php foreach (array_keys(Payout::BADGES) as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="<?= e(url('admin/payouts?status=' . $s)) ?>"><?= e(__('po_st_' . $s)) ?>
            <span class="badge bg-light text-dark"><?= e(num((int) ($counts[$s]['n'] ?? 0))) ?></span></a></li>
    <?php endforeach; ?>
</ul>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light"><tr><th>#</th><th><?= e(__('vendors_label')) ?></th><th class="text-end"><?= e(__('amount')) ?></th><th><?= e(__('payout_method')) ?></th><th><?= e(__('created_at')) ?></th><th style="min-width: 300px"><?= e(__('actions')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $p): $open = in_array($p['status'], ['requested', 'approved'], true); ?>
                <tr>
                    <td><?= e(num($p['id'])) ?></td>
                    <td><a href="<?= e(url('admin/vendors/' . (int) $p['vendor_id'])) ?>"><?= e($p['shop_name']) ?></a><div class="text-muted"><?= e($p['phone']) ?> · <?= e(__('wallet_available')) ?> <?= e(money((float) $p['balance'])) ?></div></td>
                    <td class="text-end fw-semibold"><?= e(money($p['amount'])) ?></td>
                    <td><?= e(__('mfs_' . $p['method'])) ?><div class="text-muted user-select-all"><?= e($p['account_details']) ?></div><?= $p['vendor_note'] ? '<div class="text-muted">' . e($p['vendor_note']) . '</div>' : '' ?></td>
                    <td><?= e(dt($p['requested_at'], 'd M, h:i A')) ?><?php if ($p['processed_at']): ?><div class="text-muted"><?= e($p['processed_by_name']) ?> · <?= e(dt($p['processed_at'], 'd M')) ?></div><?php endif; ?></td>
                    <td>
                        <?php if ($open && can('vendor_payouts.approve')): ?>
                            <form method="post" action="<?= e(url('admin/payouts/' . (int) $p['id'])) ?>" class="d-flex flex-wrap gap-1">
                                <?= csrf_field() ?>
                                <input class="form-control form-control-sm" style="max-width: 150px" name="reference_no" maxlength="100" placeholder="<?= e(__('reference')) ?>" aria-label="<?= e(__('reference')) ?>">
                                <input class="form-control form-control-sm" style="max-width: 150px" name="note" maxlength="255" placeholder="<?= e(__('note')) ?>" aria-label="<?= e(__('note')) ?>">
                                <button class="btn btn-success btn-sm" name="action" value="paid" data-confirm-click="<?= e(__('payout_paid_confirm', ['amount' => money($p['amount'])])) ?>"><?= e(__('payout_mark_paid')) ?></button>
                                <?php if ($p['status'] === 'requested'): ?><button class="btn btn-outline-primary btn-sm" name="action" value="approve"><?= e(__('approve')) ?></button><?php endif; ?>
                                <button class="btn btn-outline-danger btn-sm" name="action" value="reject" data-confirm-click="<?= e(__('payout_reject_confirm')) ?>"><?= e(__('payout_reject')) ?></button>
                            </form>
                        <?php else: ?>
                            <span class="badge text-bg-<?= Payout::BADGES[$p['status']] ?>"><?= e(__('po_st_' . $p['status'])) ?></span>
                            <?php if ($p['reference_no']): ?> <code><?= e($p['reference_no']) ?></code><?php endif; ?>
                            <?php if ($p['admin_note']): ?><div class="text-muted"><?= e($p['admin_note']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
