<?php /** Vendor sub-order detail. Vars: $s, $items, $history, $area, $next */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= e(url('vendor-panel/orders')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('my_orders')) ?></a>
        <h1 class="h4 mb-0"><?= e(__('sub_order')) ?> <?= e($s['sub_order_no']) ?> <?= OrderView::badge($s['status']) ?></h1>
        <span class="small text-muted"><?= e(dt($s['placed_at'])) ?> · <?= e(__('pm_' . $s['payment_method'])) ?></span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('vendor-panel/orders/' . (int) $s['id'] . '/slip')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('packing_slip')) ?></a>
</div>

<?php if (in_array($s['status'], ['pending', 'prescription_review'], true)): ?>
    <div class="alert alert-info small"><?= e(__('v_wait_confirm')) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><?= e(__('items')) ?></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('product')) ?></th><th class="text-center"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('price')) ?></th><th class="text-end"><?= e(__('total')) ?></th><th class="text-end"><?= e(__('commission')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): $allocs = json_decode((string) $it['batch_allocations'], true) ?: []; ?>
                        <tr class="<?= $it['status'] !== 'active' ? 'text-muted' : '' ?>">
                            <td><?= e($it['product_name']) ?><?= $it['variant_name'] ? ' — ' . e($it['variant_name']) : '' ?>
                                <?php if ((int) $it['requires_prescription']): ?><span class="badge text-bg-danger">Rx</span><?php endif; ?>
                                <div class="text-muted"><?= $it['sku'] ? 'SKU ' . e($it['sku']) : '' ?>
                                    <?php foreach ($allocs as $a): ?><span class="badge text-bg-light border"><?= e($a['batch_no']) ?> × <?= e(num($a['qty'])) ?></span> <?php endforeach; ?></div>
                                <?php foreach (Promo::itemParts($it) as $pp): ?><div class="text-success"><i class="bi bi-tag"></i> <?= e(Promo::partText($pp, true)) ?></div><?php endforeach; ?>
                                <?php if ($it['status'] !== 'active' || (int) $it['returned_qty']): ?><span class="badge text-bg-dark"><?= e($it['status'] === 'cancelled' ? __('os_cancelled') : __('returned_n', ['n' => num($it['returned_qty'])])) ?></span><?php endif; ?></td>
                            <td class="text-center"><?= e(num($it['quantity'])) ?></td>
                            <td class="text-end"><?= e(money($it['unit_price'])) ?></td>
                            <td class="text-end"><?= e(money($it['line_total'])) ?><?php if ((float) $it['discount_amount'] > 0): ?><div class="text-success">-<?= e(money($it['discount_amount'])) ?></div><?php endif; ?></td>
                            <td class="text-end"><?= e(num(number_format((float) $it['commission_rate'], 2))) ?>%<div class="text-muted"><?= e(money($it['commission_amount'])) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><td colspan="4" class="text-end"><?= e(__('v_sales')) ?></td><td class="text-end"><?= e(money((float) $s['subtotal'] - (float) $s['discount_total'])) ?></td></tr>
                        <?php $platformPaid = array_sum(array_map(static fn($i) => $i['status'] === 'cancelled' ? 0 : (float) $i['platform_funded'], $items)); if ($platformPaid > 0): ?>
                            <tr><td colspan="4" class="text-end"><?= e(__('v_platform_funded')) ?></td><td class="text-end text-success">+<?= e(money($platformPaid)) ?></td></tr>
                        <?php endif; ?>
                        <tr><td colspan="4" class="text-end"><?= e(__('v_commission_deducted')) ?></td><td class="text-end text-danger">-<?= e(money($s['commission_amount'])) ?></td></tr>
                        <tr class="fw-bold"><td colspan="4" class="text-end"><?= e(__('v_net_earning')) ?></td><td class="text-end"><?= e(money($s['vendor_earning'])) ?></td></tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted"><?= e(__('settle_' . $s['settlement_status'])) ?><?= $s['release_at'] && $s['settlement_status'] === 'pending' ? ' — ' . e(__('release_on', ['date' => dt($s['release_at'], 'd M Y')])) : '' ?></div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><?= e(__('history_notes')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach (array_reverse($history) as $h): ?>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span><?= $h['from_status'] ? e(__('os_' . $h['from_status'])) . ' → ' : '' ?><b><?= e(__('os_' . $h['to_status'])) ?></b><?= $h['note'] ? ' — ' . e($h['note']) : '' ?></span>
                        <span class="text-muted"><?= e(dt($h['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <?php if ($next): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><?= e(__('update_status')) ?></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('vendor-panel/orders/' . (int) $s['id'] . '/status')) ?>" data-confirm="<?= e(__('status_confirm')) ?>">
                        <?= csrf_field() ?>
                        <select class="form-select form-select-sm mb-2" name="status" aria-label="<?= e(__('status')) ?>">
                            <?php foreach ($next as $st): ?><option value="<?= $st ?>"><?= e(__('os_' . $st)) ?></option><?php endforeach; ?>
                        </select>
                        <input class="form-control form-control-sm mb-2" name="note" maxlength="255" placeholder="<?= e(__('v_status_note_ph')) ?>" aria-label="<?= e(__('note')) ?>">
                        <button class="btn btn-brand btn-sm w-100"><?= e(__('update_status')) ?></button>
                        <p class="small text-muted mt-2 mb-0"><?= e(__('v_status_help')) ?></p>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><?= e(__('ship_to')) ?></div>
            <div class="card-body small">
                <b><?= e($s['shipping_name']) ?></b> · <?= e($s['shipping_phone']) ?><br><?= e($s['shipping_address']) ?><br><?= e($area) ?>
                <?php if ($s['customer_note']): ?><div class="alert alert-light border mt-2 mb-0 py-1 px-2"><?= e(__('order_note')) ?>: <?= e($s['customer_note']) ?></div><?php endif; ?>
                <?php if ($s['tracking_note']): ?><div class="mt-2"><i class="bi bi-truck"></i> <?= e($s['tracking_note']) ?></div><?php endif; ?>
                <div class="mt-2 border-top pt-2"><span class="text-muted"><?= e(__('delivery_option')) ?>:</span> <?= e(__('dopt_' . $s['delivery_option'])) ?>
                    <?php if ($s['shipment_status']): ?> · <?= Shipment::badge((string) $s['shipment_status']) ?><?php endif; ?>
                    <?php if ($s['tracking_no']): ?><div><?= e($s['courier_name']) ?>: <code><?= e($s['tracking_no']) ?></code><?= (int) $s['is_mock'] ? ' <span class="badge text-bg-warning">mock</span>' : '' ?></div><?php endif; ?>
                    <?php if ($s['rider_name']): ?><div><?= e(__('delivery_man')) ?>: <?= e($s['rider_name']) ?> · <?= e($s['rider_phone']) ?></div><?php endif; ?>
                    <?php if (!$s['tracking_no'] && in_array($s['status'], ['confirmed', 'processing'], true)): ?><div class="text-muted"><?= e(__('v_courier_note')) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
