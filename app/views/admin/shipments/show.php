<?php
/** Shipment detail. Vars: $sh, $events, $couriers, $items, $area, $riders */
$id = (int) $sh['id'];
$canBook = can('shipments.create') && in_array($sh['status'], ['pending', 'cancelled'], true) && $sh['delivery_option'] !== 'pickup'
    && in_array($sh['split_status'], ['confirmed', 'processing', 'packed'], true);
$final = in_array($sh['status'], Shipment::FINAL, true);
$track = Shipment::trackingUrl($sh);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= e(url('admin/shipments')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('shipments')) ?></a>
        <h1 class="h4 mb-0"><?= e(__('shipment')) ?> #<?= $id ?> <?= Shipment::badge($sh['status']) ?>
            <span class="badge text-bg-<?= $sh['delivery_option'] === 'express' ? 'warning' : ($sh['delivery_option'] === 'pickup' ? 'info' : 'light border') ?>"><?= e(__('dopt_' . $sh['delivery_option'])) ?></span></h1>
        <span class="small text-muted"><a href="<?= e(url('admin/orders/' . (int) $sh['order_id'] . '#splits')) ?>"><?= e($sh['order_no']) ?></a> · <?= e($sh['sub_order_no']) ?> · <?= e($sh['shop_name'] ?? __('app_name')) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/shipments/labels?ids=' . $id)) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('print_label')) ?></a>
    </div>
</div>

<?php if ($sh['split_status'] === 'pending' || $sh['split_status'] === 'prescription_review'): ?>
    <div class="alert alert-info small"><?= e(__('shipment_wait_confirm')) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body small row g-2">
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('ship_from')) ?></span><?= e($sh['shop_name'] ?? __('app_name')) ?><br><?= e($sh['vendor_address'] ?? setting('store_pickup_address', '')) ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('ship_to')) ?></span><b><?= e($sh['shipping_name']) ?></b> · <?= e($sh['shipping_phone']) ?><br><?= e($sh['shipping_address']) ?><br><?= e($area) ?></div>
                <div class="col-sm-4"><span class="text-muted d-block"><?= e(__('zone')) ?></span><?= e(tr($sh['zone_name_bn'], $sh['zone_name']) ?: '—') ?></div>
                <div class="col-sm-4"><span class="text-muted d-block"><?= e(__('weight')) ?></span><?= e(num((int) $sh['weight_grams'])) ?> g · <?= e(__('n_items', ['n' => num((int) $sh['items'])])) ?></div>
                <div class="col-sm-4"><span class="text-muted d-block"><?= e(__('customer_paid_delivery')) ?></span><?= e(money($sh['shipping_fee'])) ?><?= (float) $sh['cod_charge'] > 0 ? ' + COD ' . e(money($sh['cod_charge'])) : '' ?></div>
                <div class="col-sm-4"><span class="text-muted d-block">COD</span><?= (float) $sh['cod_amount'] > 0 ? e(money($sh['cod_amount'])) : '—' ?> <span class="badge text-bg-<?= Shipment::COD_BADGES[$sh['cod_status']] ?> border"><?= e(__('cod_' . $sh['cod_status'])) ?></span></div>
                <div class="col-sm-4"><span class="text-muted d-block"><?= e(__('courier')) ?></span><?= e($sh['courier_name'] ?? '—') ?><?php if ($sh['tracking_no']): ?><br><code class="user-select-all"><?= e($sh['tracking_no']) ?></code><?php if ((int) $sh['is_mock']): ?> <span class="badge text-bg-warning">mock</span><?php endif; ?><?php endif; ?>
                    <?php if ($track): ?><br><a href="<?= e($track) ?>" target="_blank" rel="noopener"><?= e(__('track_on_courier')) ?></a><?php endif; ?></div>
                <div class="col-sm-4"><span class="text-muted d-block"><?= e(__('courier_charge')) ?></span><?= e(money($sh['delivery_charge'])) ?></div>
                <?php if ($sh['rider_name']): ?><div class="col-sm-6"><span class="text-muted d-block"><?= e(__('delivery_man')) ?></span><?= e($sh['rider_name']) ?> · <?= e($sh['rider_phone']) ?></div><?php endif; ?>
                <div class="col-12"><span class="text-muted d-block"><?= e(__('items')) ?></span><?= e(implode(', ', array_map(static fn($i) => $i['product_name'] . ($i['variant_name'] ? ' ' . $i['variant_name'] : '') . ' × ' . $i['quantity'], $items))) ?></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= e(__('tracking_history')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($events as $ev): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <span><b><?= e(__('sh_' . $ev['status'])) ?></b> <span class="badge text-bg-light border"><?= e(__('src_' . $ev['source'])) ?></span></span>
                            <span class="text-muted"><?= e($ev['user_name'] ?? '') ?> <?= e(dt($ev['created_at'])) ?></span>
                        </div>
                        <?php if ($ev['note']): ?><div class="text-muted"><?= e($ev['note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if ($sh['delivery_option'] === 'pickup' && !$final && can('shipments.edit') && in_array($sh['split_status'], ['confirmed', 'processing', 'packed', 'shipped'], true)): ?>
            <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                <p class="small mb-2"><?= e(__('pickup_help')) ?></p>
                <form method="post" action="<?= e(url('admin/shipments/' . $id . '/picked-up')) ?>" data-confirm="<?= e(__('pickup_confirm')) ?>"><?= csrf_field() ?>
                    <button class="btn btn-success btn-sm w-100"><i class="bi bi-bag-check"></i> <?= e(__('pickup_collected')) ?></button></form>
            </div></div>
        <?php endif; ?>

        <?php if ($canBook): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= e(__('book_courier')) ?></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/shipments/' . $id . '/book')) ?>" class="small">
                        <?= csrf_field() ?>
                        <select class="form-select form-select-sm mb-2" name="courier" data-courier-pick aria-label="<?= e(__('courier')) ?>">
                            <?php foreach ($couriers as $c): $ad = isset(Courier::ADAPTERS[$c['code']]) ? Courier::adapter($c) : null; ?>
                                <option value="<?= e($c['code']) ?>"><?= e($c['name']) ?><?= $ad && $c['code'] !== 'self' && $ad->isMock() ? ' (mock)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div data-rider-fields hidden>
                            <select class="form-select form-select-sm mb-1" name="rider_user_id" aria-label="<?= e(__('delivery_man')) ?>">
                                <option value=""><?= e(__('rider_pick_staff')) ?></option>
                                <?php foreach ($riders as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?> · <?= e($u['phone']) ?></option><?php endforeach; ?>
                            </select>
                            <div class="row g-1 mb-2">
                                <div class="col-6"><input class="form-control form-control-sm" name="rider_name" maxlength="150" placeholder="<?= e(__('rider_name')) ?>" aria-label="<?= e(__('rider_name')) ?>"></div>
                                <div class="col-6"><input class="form-control form-control-sm" name="rider_phone" maxlength="20" placeholder="01XXXXXXXXX" aria-label="<?= e(__('phone')) ?>"></div>
                            </div>
                        </div>
                        <button class="btn btn-brand btn-sm w-100"><i class="bi bi-send"></i> <?= e(__('book_courier')) ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$final && $sh['status'] !== 'pending' && $sh['status'] !== 'cancelled' && can('shipments.edit')): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= e(__('update_status')) ?></div>
                <div class="card-body small">
                    <?php if ($sh['courier_code'] !== 'self' && $sh['courier_id']): ?>
                        <form method="post" action="<?= e(url('admin/shipments/' . $id . '/sync')) ?>" class="mb-2"><?= csrf_field() ?>
                            <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-arrow-repeat"></i> <?= e(__('sync_courier')) ?></button>
                            <?php if ($sh['last_synced_at']): ?><div class="text-muted mt-1"><?= e(__('last_sync', ['t' => dt($sh['last_synced_at'])])) ?></div><?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('admin/shipments/' . $id . '/status')) ?>" data-confirm="<?= e(__('status_confirm')) ?>">
                        <?= csrf_field() ?>
                        <select class="form-select form-select-sm mb-2" name="status" aria-label="<?= e(__('status')) ?>">
                            <?php foreach (Shipment::MANUAL as $st): ?><option value="<?= $st ?>"><?= e(__('sh_' . $st)) ?></option><?php endforeach; ?>
                        </select>
                        <input class="form-control form-control-sm mb-2" name="note" maxlength="255" placeholder="<?= e(__('note')) ?>" aria-label="<?= e(__('note')) ?>">
                        <button class="btn btn-brand btn-sm w-100"><?= e(__('update_status')) ?></button>
                        <p class="text-muted mt-2 mb-0"><?= e(__('sh_manual_help')) ?></p>
                    </form>
                    <?php if ($sh['status'] === 'booked'): ?>
                        <form method="post" action="<?= e(url('admin/shipments/' . $id . '/cancel')) ?>" class="mt-2" data-confirm="<?= e(__('cancel_booking_confirm')) ?>" data-confirm-danger="1"><?= csrf_field() ?>
                            <button class="btn btn-outline-danger btn-sm w-100"><?= e(__('cancel_booking')) ?></button></form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pick = document.querySelector('[data-courier-pick]'), box = document.querySelector('[data-rider-fields]');
    if (!pick || !box) return;
    var sync = function () { box.hidden = pick.value !== 'self'; };
    pick.addEventListener('change', sync); sync();
});
</script>
