<?php
/**
 * Admin order detail. Vars: $o, $items, $history, $timeline, $payments, $refunds, $prescription, $area, $customer
 */
$id = (int) $o['id'];
$next = Order::NEXT[$o['status']] ?? [];
if (!can('orders.cancel')) {
    $next = array_values(array_diff($next, ['cancelled']));
}
$openReturns = array_values(array_filter($refunds, static fn($r) => $r['status'] === 'requested'));
$approvedRefunds = array_values(array_filter($refunds, static fn($r) => $r['status'] === 'approved'));
$rxColor = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= e(url('admin/orders')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('orders')) ?></a>
        <h1 class="h4 mb-0"><?= e(__('order')) ?> #<?= e($o['order_no']) ?> <?= OrderView::badge($o['status']) ?>
            <?php if ($o['requires_prescription']): ?><span class="badge text-bg-danger">Rx</span><?php endif; ?></h1>
        <span class="small text-muted"><?= e(dt($o['placed_at'])) ?> · <?= e(__('source_' . $o['source'])) ?> · IP <?= e($o['ip_address']) ?></span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/orders/' . $id . '/invoice')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> <?= e(__('invoice')) ?></a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body"><?= View::partial('orders/_timeline', ['timeline' => $timeline]) ?></div>
        </div>

        <?php if ($o['requires_prescription']): ?>
            <div class="card border-0 shadow-sm mb-3 <?= $o['status'] === 'prescription_review' ? 'border-start border-4 border-info' : '' ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-medical"></i> <?= e(__('prescription')) ?></span>
                    <?php if ($prescription): ?><span class="badge text-bg-<?= $rxColor[$prescription['status']] ?>"><?= e(__('rx_status_' . $prescription['status'])) ?></span><?php endif; ?>
                </div>
                <div class="card-body small">
                    <?php if (!$prescription): ?>
                        <p class="text-danger mb-0"><?= e(__('rx_missing')) ?></p>
                    <?php else: $isPdf = $prescription['mime_type'] === 'application/pdf'; $fileUrl = url('admin/prescriptions/' . (int) $prescription['id'] . '/file'); ?>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener" class="rx-thumb">
                                <?php if ($isPdf): ?><i class="bi bi-file-earmark-pdf fs-1 text-danger"></i><?php else: ?><img src="<?= e($fileUrl) ?>" alt="<?= e(__('prescription')) ?>"><?php endif; ?>
                            </a>
                            <div>
                                <div><?= e($prescription['original_name']) ?> · <?= e(num(round((int) $prescription['file_size'] / 1024))) ?> KB</div>
                                <?php if ($prescription['patient_name']): ?><div><?= e(__('rx_patient_name')) ?>: <?= e($prescription['patient_name']) ?></div><?php endif; ?>
                                <div class="text-muted"><?= e(dt($prescription['created_at'])) ?></div>
                                <?php if ($prescription['reviewer']): ?><div class="text-muted"><?= e(__('reviewed_by')) ?>: <?= e($prescription['reviewer']) ?>, <?= e(dt($prescription['reviewed_at'])) ?></div><?php endif; ?>
                                <?php if ($prescription['review_note']): ?><div><?= e($prescription['review_note']) ?></div><?php endif; ?>
                                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('rx_open_file')) ?></a>
                            </div>
                        </div>
                        <?php if ($o['status'] === 'prescription_review' && can('prescriptions.verify')): ?>
                            <form method="post" action="<?= e(url('admin/orders/' . $id . '/prescription')) ?>" class="mt-3 d-flex flex-wrap gap-2 align-items-end">
                                <?= csrf_field() ?>
                                <div class="flex-grow-1">
                                    <label class="form-label small mb-1" for="rxNote"><?= e(__('rx_review_note')) ?></label>
                                    <input class="form-control form-control-sm" id="rxNote" name="note" maxlength="500">
                                </div>
                                <button class="btn btn-success btn-sm" name="decision" value="approve"><i class="bi bi-check2"></i> <?= e(__('rx_approve')) ?></button>
                                <button class="btn btn-outline-danger btn-sm" name="decision" value="reject" data-confirm-click="<?= e(__('rx_reject_confirm')) ?>"><i class="bi bi-x"></i> <?= e(__('rx_reject')) ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($splits): $multi = count($splits) > 1; ?>
            <div class="card border-0 shadow-sm mb-3" id="splits">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-diagram-2"></i> <?= e(__('sub_orders')) ?></span>
                    <span class="small text-muted"><?= e(__('sub_orders_help')) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light"><tr><th><?= e(__('sub_order')) ?></th><th><?= e(__('seller')) ?></th><th class="text-end"><?= e(__('subtotal')) ?></th><th class="text-end"><?= e(__('delivery_charge')) ?></th><th class="text-end"><?= e(__('commission')) ?></th><th class="text-end"><?= e(__('vendor_earning')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($splits as $sp):
                            $spNext = in_array($sp['status'], ['pending', 'prescription_review'], true) ? [] : (Order::NEXT[$sp['status']] ?? []);
                            if (!can('orders.cancel')) { $spNext = array_values(array_diff($spNext, ['cancelled'])); } ?>
                            <tr>
                                <td><b><?= e($sp['sub_order_no']) ?></b><div class="text-muted"><?= e(__('n_items', ['n' => num($sp['items'])])) ?></div>
                                    <?php if ($sp['shipment_id']): ?><div><a href="<?= e(url('admin/shipments/' . (int) $sp['shipment_id'])) ?>"><i class="bi bi-send"></i> <?= e(__('shipment')) ?></a> <?= Shipment::badge((string) $sp['shipment_status']) ?>
                                        <?php if ($sp['tracking_no']): ?><div class="text-muted font-monospace"><?= e($sp['courier_name']) ?> · <?= e($sp['tracking_no']) ?></div><?php endif; ?></div><?php endif; ?>
                                    <?php if ($sp['tracking_note']): ?><div class="text-muted"><i class="bi bi-truck"></i> <?= e($sp['tracking_note']) ?></div><?php endif; ?></td>
                                <td><?php if ($sp['vendor_id']): ?><a href="<?= e(url('admin/vendors/' . (int) $sp['vendor_id'])) ?>"><?= e(tr($sp['shop_name_bn'], $sp['shop_name'])) ?></a><div class="text-muted"><?= e($sp['vendor_phone']) ?></div><?php else: ?><?= e(__('app_name')) ?><?php endif; ?></td>
                                <td class="text-end"><?= e(money((float) $sp['subtotal'] - (float) $sp['discount_total'])) ?><?php if ((float) $sp['discount_total'] > 0): ?><div class="text-muted"><?= e(__('after_discount')) ?></div><?php endif; ?></td>
                                <td class="text-end"><?= e(money($sp['shipping_total'])) ?></td>
                                <td class="text-end"><?= $sp['vendor_id'] ? e(money($sp['commission_amount'])) : '—' ?></td>
                                <td class="text-end"><?= $sp['vendor_id'] ? e(money($sp['vendor_earning'])) . '<div class="text-muted">' . e(__('settle_' . $sp['settlement_status'])) . '</div>' : '—' ?></td>
                                <td>
                                    <?= OrderView::badge($sp['status']) ?>
                                    <?php if ($multi && $spNext && can('orders.update_status')): ?>
                                        <form method="post" action="<?= e(url('admin/orders/' . $id . '/splits/' . (int) $sp['id'] . '/status')) ?>" class="d-flex gap-1 mt-1" data-confirm="<?= e(__('status_confirm')) ?>">
                                            <?= csrf_field() ?>
                                            <select class="form-select form-select-sm" name="status" aria-label="<?= e(__('status')) ?>">
                                                <?php foreach ($spNext as $st): ?><option value="<?= $st ?>"><?= e(__('os_' . $st)) ?></option><?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="notify" value="1">
                                            <button class="btn btn-sm btn-outline-primary" title="<?= e(__('update_status')) ?>"><i class="bi bi-check2"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><?= e(__('items')) ?></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('product')) ?></th><th class="d-none d-md-table-cell"><?= e(__('seller')) ?></th><th class="text-end"><?= e(__('price')) ?></th><th class="text-center"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('total')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): $allocs = json_decode((string) $it['batch_allocations'], true) ?: []; ?>
                        <tr class="<?= $it['status'] !== 'active' ? 'text-muted' : '' ?>">
                            <td>
                                <?= e(OrderView::itemName($it)) ?> <?php if ((int) $it['requires_prescription']): ?><span class="badge text-bg-danger">Rx</span><?php endif; ?>
                                <div class="text-muted"><?= $it['sku'] ? 'SKU ' . e($it['sku']) . ' · ' : '' ?>
                                    <?php foreach ($allocs as $a): ?><span class="badge text-bg-light border"><?= e($a['batch_no']) ?> × <?= e(num($a['qty'])) ?><?= !empty($a['expiry']) ? ' · ' . e($a['expiry']) : '' ?></span> <?php endforeach; ?>
                                </div>
                                <?php foreach (Promo::itemParts($it) as $pp): ?><div class="text-success"><i class="bi bi-tag"></i> <?= e(Promo::partText($pp, false)) ?></div><?php endforeach; ?>
                                <?php if ($it['status'] !== 'active' || (int) $it['returned_qty']): ?><span class="badge text-bg-dark"><?= e($it['status'] === 'cancelled' ? __('os_cancelled') : __('returned_n', ['n' => num($it['returned_qty'])])) ?></span><?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell"><?= e($it['vendor_id'] ? tr($it['shop_name_bn'], $it['shop_name']) : __('app_name')) ?></td>
                            <td class="text-end"><?= e(money($it['unit_price'])) ?><?php if ((float) $it['mrp'] > (float) $it['unit_price']): ?><div class="text-muted"><s><?= e(money($it['mrp'])) ?></s></div><?php endif; ?></td>
                            <td class="text-center"><?= e(num($it['quantity'])) ?></td>
                            <td class="text-end"><?= e(money($it['line_total'])) ?><?php if ((float) $it['discount_amount'] > 0): ?><div class="text-success">-<?= e(money($it['discount_amount'])) ?></div><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><td colspan="4" class="text-end"><?= e(__('subtotal')) ?></td><td class="text-end"><?= e(money($o['subtotal'])) ?></td></tr>
                        <?php if ((float) ($o['promo_discount'] ?? 0) > 0): ?><tr><td colspan="4" class="text-end"><?= e(__('offer_discount')) ?></td><td class="text-end">-<?= e(money($o['promo_discount'])) ?></td></tr><?php endif; ?>
                        <?php if ((float) $o['coupon_discount'] > 0): ?><tr><td colspan="4" class="text-end"><?= e(__('coupon_discount')) ?> (<?= e($o['coupon_code']) ?>)</td><td class="text-end">-<?= e(money($o['coupon_discount'])) ?></td></tr><?php endif; ?>
                        <tr><td colspan="4" class="text-end"><?= e(__('delivery_charge')) ?></td><td class="text-end"><?= e(money($o['shipping_total'])) ?></td></tr>
                        <?php if ((float) $o['cod_charge'] > 0): ?><tr><td colspan="4" class="text-end"><?= e(__('cod_charge')) ?></td><td class="text-end"><?= e(money($o['cod_charge'])) ?></td></tr><?php endif; ?>
                        <?php if ((float) ($o['vat_total'] ?? 0) > 0): ?><tr><td colspan="4" class="text-end<?= Tax::orderVatOnTop($o) ? '' : ' text-muted' ?>"><?= e(Tax::rowLabel($o)) ?></td><td class="text-end<?= Tax::orderVatOnTop($o) ? '' : ' text-muted' ?>"><?= e(money($o['vat_total'])) ?></td></tr><?php endif; ?>
                        <tr class="fw-bold"><td colspan="4" class="text-end"><?= e(__('total')) ?></td><td class="text-end"><?= e(money($o['grand_total'])) ?></td></tr>
                        <tr><td colspan="4" class="text-end"><?= e(__('paid')) ?> / <?= e(__('refunded')) ?></td><td class="text-end"><?= e(money($o['paid_amount'])) ?> / <?= e(money($o['refunded_amount'])) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if ($refunds): ?>
            <div class="card border-0 shadow-sm mb-3" id="returns">
                <div class="card-header bg-white"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('returns_refunds')) ?></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light"><tr><th>No</th><th><?= e(__('product')) ?></th><th class="text-center"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('amount')) ?></th><th><?= e(__('refund_method')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($refunds as $r): ?>
                            <tr>
                                <td><?= e($r['refund_no']) ?><div class="text-muted"><?= e(dt($r['created_at'], 'd M')) ?></div></td>
                                <td><?= e($r['product_name'] ?? __('whole_order')) ?><div class="text-muted"><?= e($r['reason']) ?></div></td>
                                <td class="text-center"><?= e($r['quantity'] !== null ? num($r['quantity']) : '—') ?></td>
                                <td class="text-end"><?= e(money($r['amount'])) ?></td>
                                <td><?= e(__('rm_' . $r['method'])) ?><div class="text-muted"><?= e($r['account_details']) ?></div></td>
                                <td><?= e(__('rf_' . $r['status'])) ?><?= $r['reference_no'] ? '<div class="text-muted">' . e($r['reference_no']) . '</div>' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (can('refunds.approve') && ($openReturns || $approvedRefunds)): ?>
                    <div class="card-body border-top">
                        <?php if ($openReturns): ?>
                            <form method="post" action="<?= e(url('admin/orders/' . $id . '/return')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
                                <?= csrf_field() ?>
                                <div class="flex-grow-1"><label class="form-label small mb-1" for="rtNote"><?= e(__('note')) ?></label><input class="form-control form-control-sm" id="rtNote" name="note" maxlength="500"></div>
                                <div class="form-check small"><input class="form-check-input" type="checkbox" name="restock" value="1" id="restock" checked><label class="form-check-label" for="restock"><?= e(__('return_restock')) ?></label></div>
                                <button class="btn btn-success btn-sm" name="decision" value="approve"><?= e(__('return_approve')) ?></button>
                                <button class="btn btn-outline-danger btn-sm" name="decision" value="reject"><?= e(__('return_reject')) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($approvedRefunds): ?>
                            <form method="post" action="<?= e(url('admin/orders/' . $id . '/refunded')) ?>" class="d-flex flex-wrap gap-2 align-items-end mt-2"
                                  data-confirm="<?= e(__('refund_confirm', ['amount' => money(array_sum(array_column($approvedRefunds, 'amount')))])) ?>">
                                <?= csrf_field() ?>
                                <div class="flex-grow-1"><label class="form-label small mb-1" for="refNo"><?= e(__('refund_reference')) ?> *</label><input class="form-control form-control-sm" id="refNo" name="reference_no" maxlength="100" required></div>
                                <button class="btn btn-primary btn-sm"><i class="bi bi-cash"></i> <?= e(__('mark_refunded', ['amount' => money(array_sum(array_column($approvedRefunds, 'amount')))])) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3" id="history">
            <div class="card-header bg-white"><?= e(__('history_notes')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach (array_reverse($history) as $h): $isNote = $h['from_status'] === $h['to_status']; ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <span>
                                <?php if ($isNote): ?><i class="bi bi-chat-left-text text-muted"></i> <b><?= e(__('note')) ?></b>
                                <?php else: ?><?= $h['from_status'] ? e(__('os_' . $h['from_status'])) . ' → ' : '' ?><b><?= e(__('os_' . $h['to_status'])) ?></b><?php endif; ?>
                                <?php if ($h['sub_order_no']): ?><span class="badge text-bg-light border"><?= e($h['sub_order_no']) ?></span><?php endif; ?>
                                <?php if ($h['notify_customer']): ?><i class="bi bi-bell text-primary" title="<?= e(__('customer_notified')) ?>"></i><?php endif; ?>
                            </span>
                            <span class="text-muted"><?= e($h['user_name'] ?? __('system')) ?> · <?= e(dt($h['created_at'])) ?></span>
                        </div>
                        <?php if ($h['note']): ?><div class="mt-1"><?= e($h['note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (can('orders.edit')): ?>
                <div class="card-body border-top">
                    <form method="post" action="<?= e(url('admin/orders/' . $id . '/note')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="flex-grow-1"><label class="form-label small mb-1" for="noteTxt"><?= e(__('add_note')) ?></label><input class="form-control form-control-sm" id="noteTxt" name="note" maxlength="500" required></div>
                        <div class="form-check small"><input class="form-check-input" type="checkbox" name="notify" value="1" id="noteNotify"><label class="form-check-label" for="noteNotify"><?= e(__('show_to_customer')) ?></label></div>
                        <button class="btn btn-outline-primary btn-sm"><?= e(__('save')) ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (can('orders.update_status') && $next): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><?= e(__('change_status')) ?></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/orders/' . $id . '/status')) ?>" data-confirm="<?= e(__('status_confirm')) ?>">
                        <?= csrf_field() ?>
                        <select class="form-select form-select-sm mb-2" name="status" aria-label="<?= e(__('status')) ?>">
                            <?php foreach ($next as $s): ?><option value="<?= $s ?>"><?= e(__('os_' . $s)) ?></option><?php endforeach; ?>
                        </select>
                        <textarea class="form-control form-control-sm mb-2" name="note" rows="2" maxlength="500" placeholder="<?= e(__('status_note_ph')) ?>" aria-label="<?= e(__('note')) ?>"></textarea>
                        <div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="notify" value="1" id="stNotify" checked><label class="form-check-label" for="stNotify"><?= e(__('notify_customer')) ?></label></div>
                        <button class="btn btn-brand btn-sm w-100"><?= e(__('update_status')) ?></button>
                        <p class="small text-muted mt-2 mb-0"><?= e(__('cancel_restock_hint')) ?></p>
                    </form>
                </div>
            </div>
        <?php elseif ($o['status'] === 'prescription_review'): ?>
            <div class="alert alert-info small"><?= e(__('rx_confirm_hint')) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><?= e(__('customer')) ?></div>
            <div class="card-body small">
                <b><?= e($o['customer_name']) ?></b><br><?= e($o['customer_phone']) ?><?= $o['customer_email'] ? '<br>' . e($o['customer_email']) : '' ?>
                <?php if ($customer): ?>
                    <div class="text-muted mt-1"><?= e(__('customer_orders', ['n' => num($customer['orders_count']), 'c' => num($customer['cancelled_count'])])) ?></div>
                <?php endif; ?>
                <hr>
                <div class="text-muted small text-uppercase"><?= e(__('ship_to')) ?></div>
                <b><?= e($o['shipping_name']) ?></b> · <?= e($o['shipping_phone']) ?><br><?= e($o['shipping_address']) ?><br><?= e($area) ?><?= $o['postcode'] ? ' - ' . e($o['postcode']) : '' ?>
                <?php if ($o['customer_note']): ?><div class="alert alert-light border mt-2 mb-0 py-1 px-2"><?= e(__('order_note')) ?>: <?= e($o['customer_note']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between"><span><?= e(__('payment')) ?></span><?= OrderView::payBadge($o['payment_status']) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($payments as $p): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between"><b><?= e(__('pm_' . $p['method'])) ?></b><span><?= e(money($p['amount'])) ?></span></div>
                        <?php if ($p['transaction_id']): ?><div><?= e(__('trx_id')) ?>: <code class="user-select-all"><?= e($p['transaction_id']) ?></code></div><?php endif; ?>
                        <?php if ($p['payer_account']): ?><div><?= e(__('payer_number')) ?>: <?= e($p['payer_account']) ?></div><?php endif; ?>
                        <div class="text-muted"><?= e(__('pay_st_' . $p['status'])) ?><?= $p['received_by_name'] ? ' · ' . e($p['received_by_name']) : '' ?><?= $p['paid_at'] ? ' · ' . e(dt($p['paid_at'])) : '' ?></div>
                        <?php if ($p['status'] === 'pending' && $p['gateway'] && can('orders.edit')): ?>
                            <form method="post" action="<?= e(url('admin/orders/' . $id . '/payments/' . (int) $p['id'])) ?>" class="d-flex gap-1 mt-2">
                                <?= csrf_field() ?>
                                <button class="btn btn-success btn-sm flex-fill" name="decision" value="ok"><i class="bi bi-check2"></i> <?= e(__('pay_verify')) ?></button>
                                <button class="btn btn-outline-danger btn-sm flex-fill" name="decision" value="bad"><?= e(__('pay_reject')) ?></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
