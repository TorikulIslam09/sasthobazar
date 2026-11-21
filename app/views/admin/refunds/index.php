<?php /** Return / refund requests. Vars: $status, $rows, $pager, $counts */ ?>
<h1 class="h4 mb-3"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('refunds')) ?></h1>
<ul class="nav nav-pills mb-3 status-tabs">
    <?php foreach (['requested', 'approved', 'processed', 'rejected'] as $s): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="<?= e(url('admin/refunds?status=' . $s)) ?>"><?= e(__('rf_' . $s)) ?> <span class="badge bg-light text-dark"><?= e(num($counts[$s] ?? 0)) ?></span></a></li>
    <?php endforeach; ?>
</ul>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th>No</th><th><?= e(__('order')) ?></th><th><?= e(__('product')) ?></th><th class="text-center"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('amount')) ?></th><th><?= e(__('refund_method')) ?></th><th><?= e(__('created_at')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['refund_no']) ?></td>
                    <td><a href="<?= e(url('admin/orders/' . (int) $r['order_id'] . '#returns')) ?>"><?= e($r['order_no']) ?></a><div class="text-muted"><?= e($r['customer_name']) ?> · <?= e($r['customer_phone']) ?></div></td>
                    <td><?= e($r['product_name'] ?? __('whole_order')) ?><?= $r['variant_name'] ? ' — ' . e($r['variant_name']) : '' ?><div class="text-muted"><?= e($r['reason']) ?></div></td>
                    <td class="text-center"><?= e($r['quantity'] !== null ? num($r['quantity']) : '—') ?></td>
                    <td class="text-end"><?= e(money($r['amount'])) ?></td>
                    <td><?= e(__('rm_' . $r['method'])) ?><div class="text-muted"><?= e($r['account_details']) ?></div><?= $r['reference_no'] ? '<div>Ref: ' . e($r['reference_no']) . '</div>' : '' ?></td>
                    <td><?= e(dt($r['created_at'], 'd M, h:i A')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
