<?php /** Coupons list. Vars: $q, $coupons, $pager */
$now = date('Y-m-d H:i:s');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-ticket-perforated"></i> <?= e(__('coupons')) ?></h1>
    <?php if (can('coupons.create')): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/coupons/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_coupon')) ?></a><?php endif; ?>
</div>
<form class="mb-3 d-flex gap-2" method="get" style="max-width: 420px">
    <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('search')) ?>" aria-label="<?= e(__('search')) ?>">
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('coupon_code')) ?></th><th><?= e(__('coupon_discount')) ?></th><th><?= e(__('coupon_applies')) ?></th><th><?= e(__('coupon_usage')) ?></th><th><?= e(__('coupon_validity')) ?></th><th><?= e(__('status')) ?></th><th class="text-end"><?= e(__('actions')) ?></th></tr></thead>
            <tbody>
            <?php if (!$coupons): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($coupons as $c):
                $state = !$c['is_active'] ? ['inactive', 'secondary'] : (($c['ends_at'] && $c['ends_at'] <= $now) ? ['coupon_expired_lbl', 'danger'] : (($c['starts_at'] && $c['starts_at'] > $now) ? ['coupon_scheduled', 'info'] : ['active', 'success'])); ?>
                <tr>
                    <td><code class="fs-6"><?= e($c['code']) ?></code><div class="text-muted"><?= e($c['description']) ?></div></td>
                    <td><?= e(Coupon::summary($c)) ?><?php if ((float) $c['min_order_amount'] > 0): ?><div class="text-muted"><?= e(__('coupon_min_short', ['amount' => money($c['min_order_amount'])])) ?></div><?php endif; ?></td>
                    <td><?= e(__('applies_' . $c['applies_to'])) ?><?= $c['first_order_only'] ? '<div class="text-muted">' . e(__('first_order_only')) . '</div>' : '' ?><div class="text-muted"><i class="bi bi-wallet2"></i> <?= e(OfferForm::fundedText($c['funded_by'], $c['vendor_share_percent'])) ?></div></td>
                    <td><?= e(num($c['used_count'])) ?> / <?= $c['usage_limit'] !== null ? e(num($c['usage_limit'])) : '∞' ?></td>
                    <td><?= $c['starts_at'] ? e(dt($c['starts_at'], 'd M y')) : '—' ?> → <?= $c['ends_at'] ? e(dt($c['ends_at'], 'd M y')) : '∞' ?></td>
                    <td><span class="badge text-bg-<?= $state[1] ?>"><?= e(__($state[0])) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('coupons.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/coupons/' . (int) $c['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php if (can('coupons.delete')): ?>
                            <?= View::partial('partials/delete_button', ['action' => url('admin/coupons/' . (int) $c['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $c['code']])]) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
