<?php /** Returns on the vendor's sub-orders. Vars: $rows */ ?>
<h1 class="h4 mb-1"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('returns_refunds')) ?></h1>
<p class="small text-muted"><?= e(__('v_returns_help')) ?></p>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light"><tr><th>No</th><th><?= e(__('sub_order')) ?></th><th><?= e(__('product')) ?></th><th class="text-center"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('amount')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['refund_no']) ?><div class="text-muted"><?= e(dt($r['created_at'], 'd M')) ?></div></td>
                    <td><a href="<?= e(url('vendor-panel/orders/' . (int) $r['split_id'])) ?>"><?= e($r['sub_order_no']) ?></a></td>
                    <td><?= e($r['product_name'] ?? __('whole_order')) ?><?= $r['variant_name'] ? ' — ' . e($r['variant_name']) : '' ?><div class="text-muted"><?= e($r['reason']) ?></div></td>
                    <td class="text-center"><?= e($r['quantity'] !== null ? num($r['quantity']) : '—') ?></td>
                    <td class="text-end"><?= e(money($r['amount'])) ?></td>
                    <td><?= e(__('rf_' . $r['status'])) ?><?= $r['admin_note'] ? '<div class="text-muted">' . e($r['admin_note']) . '</div>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
