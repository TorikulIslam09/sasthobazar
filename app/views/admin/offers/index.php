<?php /** Offers list. Vars: $offers, $pager, $status, $kind, $q, $pending */
$tab = static function (string $label, array $params, bool $on) {
    return '<a class="nav-link' . ($on ? ' active' : '') . '" href="' . e(url('admin/offers' . ($params ? '?' . http_build_query($params) : ''))) . '">' . $label . '</a>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gift"></i> <?= e(__('offers')) ?></h1>
    <?php if (can('offers.create')): ?>
        <div class="dropdown">
            <button class="btn btn-brand btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg"></i> <?= e(__('add_offer')) ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach (Promo::KINDS as $k): ?>
                    <li><a class="dropdown-item" href="<?= e(url('admin/offers/create?kind=' . $k)) ?>"><i class="bi <?= ['price' => 'bi-percent', 'bogo' => 'bi-gift', 'first_order' => 'bi-stars', 'free_shipping' => 'bi-truck'][$k] ?>"></i> <?= e(__('offer_kind_' . $k)) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3 small">
    <li class="nav-item"><?= $tab(e(__('all')), array_filter(['kind' => $kind]), $status === '') ?></li>
    <li class="nav-item"><?= $tab(e(__('offer_status_pending')) . ($pending ? ' <span class="badge text-bg-warning">' . e(num($pending)) . '</span>' : ''), array_filter(['status' => 'pending', 'kind' => $kind]), $status === 'pending') ?></li>
    <li class="nav-item"><?= $tab(e(__('offer_status_approved')), array_filter(['status' => 'approved', 'kind' => $kind]), $status === 'approved') ?></li>
    <li class="nav-item"><?= $tab(e(__('offer_status_rejected')), array_filter(['status' => 'rejected', 'kind' => $kind]), $status === 'rejected') ?></li>
</ul>

<form class="mb-3 d-flex flex-wrap gap-2" method="get">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input class="form-control form-control-sm" style="max-width: 260px" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('search')) ?>" aria-label="<?= e(__('search')) ?>">
    <select class="form-select form-select-sm" style="max-width: 200px" name="kind" aria-label="<?= e(__('type')) ?>">
        <option value=""><?= e(__('all_types')) ?></option>
        <?php foreach (Promo::KINDS as $k): ?><option value="<?= $k ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e(__('offer_kind_' . $k)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr>
                <th><?= e(__('offer')) ?></th><th><?= e(__('discount')) ?></th><th><?= e(__('coupon_applies')) ?></th>
                <th><?= e(__('cost_bearer')) ?></th><th><?= e(__('coupon_validity')) ?></th><th><?= e(__('status')) ?></th><th class="text-end"><?= e(__('actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$offers): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($offers as $o): [$stLabel, $stColor] = OfferForm::state($o); $id = (int) $o['id']; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e(Promo::title($o)) ?></div>
                        <span class="badge text-bg-light border"><?= e(__('offer_kind_' . $o['kind'])) ?></span>
                        <?php if ($o['vendor_id']): ?><span class="text-muted"><i class="bi bi-shop"></i> <?= e(tr($o['shop_name_bn'], $o['shop_name'])) ?></span><?php endif; ?>
                        <?php if ($o['requested_by']): ?><span class="badge text-bg-info"><?= e(__('vendor_request')) ?></span><?php endif; ?>
                    </td>
                    <td><?= e(Promo::label($o)) ?>
                        <?php if ($o['kind'] === 'first_order' && (float) $o['min_order_amount'] > 0): ?><div class="text-muted"><?= e(__('coupon_min_short', ['amount' => money($o['min_order_amount'])])) ?></div><?php endif; ?>
                        <?php if ($o['max_discount'] !== null && $o['type'] === 'percent'): ?><div class="text-muted"><?= e(__('coupon_max', ['amount' => money($o['max_discount'])])) ?></div><?php endif; ?>
                    </td>
                    <td><?= e(OfferForm::scopeText($o)) ?></td>
                    <td><?= e(OfferForm::fundedText($o['funded_by'], $o['vendor_share_percent'])) ?></td>
                    <td class="text-nowrap"><?= $o['starts_at'] ? e(dt($o['starts_at'], 'd M y')) : '—' ?> → <?= $o['ends_at'] ? e(dt($o['ends_at'], 'd M y')) : '∞' ?></td>
                    <td><span class="badge text-bg-<?= $stColor ?>"><?= e($stLabel) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ($o['status'] === 'approved' && can('offers.edit')): ?>
                            <form method="post" action="<?= e(url('admin/offers/' . $id . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" title="<?= e(__((int) $o['is_active'] ? 'pause' : 'resume')) ?>"><i class="bi <?= (int) $o['is_active'] ? 'bi-pause' : 'bi-play' ?>"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if (can('offers.edit') || ($o['status'] === 'pending' && can('offers.approve'))): ?>
                            <a class="btn btn-sm <?= $o['status'] === 'pending' ? 'btn-warning' : 'btn-outline-secondary' ?>" href="<?= e(url('admin/offers/' . $id . '/edit')) ?>" title="<?= e(__($o['status'] === 'pending' ? 'review' : 'edit')) ?>"><i class="bi <?= $o['status'] === 'pending' ? 'bi-clipboard-check' : 'bi-pencil' ?>"></i></a>
                        <?php endif; ?>
                        <?php if (can('offers.delete')): ?>
                            <?= View::partial('partials/delete_button', ['action' => url('admin/offers/' . $id . '/delete'), 'message' => __('confirm_delete_named', ['name' => $o['title_en']])]) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
<p class="small text-muted mt-3"><i class="bi bi-info-circle"></i> <?= e(__('offer_stacking_help')) ?></p>
