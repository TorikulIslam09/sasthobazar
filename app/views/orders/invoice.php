<?php
/** Printable invoice(s), used by customers and admin. Var: $orders = [['o'=>, 'items'=>, 'area'=>, 'payments'=>], ...] */
?>
<style>
    .invoice { max-width: 820px; margin: 0 auto; padding: 24px 16px; font-size: 14px; }
    .invoice + .invoice { page-break-before: always; break-before: page; }
    .invoice table td, .invoice table th { padding: 6px 8px; }
    .inv-head { border-bottom: 3px solid #0e7673; padding-bottom: 12px; margin-bottom: 16px; }
    .inv-brand { color: #0e7673; font-weight: 700; font-size: 22px; }
    .inv-brand .bi-heart-pulse-fill { color: #e11d48; }
    @media print { .no-print { display: none !important; } .invoice { padding: 0; } @page { margin: 12mm; } }
</style>
<div class="no-print text-center my-3">
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button>
</div>
<?php foreach ($orders as ['o' => $o, 'items' => $items, 'area' => $area, 'payments' => $payments]): ?>
    <div class="invoice">
        <div class="inv-head d-flex justify-content-between flex-wrap gap-2">
            <div>
                <div class="inv-brand"><i class="bi bi-heart-pulse-fill"></i> <?= e(__('app_name')) ?></div>
                <div class="small text-muted"><?= e(setting('contact_address', '')) ?> · <?= e(setting('contact_phone', '')) ?> · <?= e(setting('contact_email', '')) ?></div>
                <?php if (Tax::regNo() !== ''): ?><div class="small text-muted"><?= e(__('vat_reg_no')) ?>: <?= e(Tax::regNo()) ?></div><?php endif; ?>
            </div>
            <div class="text-end">
                <div class="fs-5 fw-bold"><?= e(__('invoice')) ?></div>
                <div>#<?= e($o['order_no']) ?></div>
                <div class="small"><?= e(dt($o['placed_at'], 'd M Y, h:i A')) ?></div>
                <div class="small"><?= e(__('status')) ?>: <?= e(__('os_' . $o['status'])) ?></div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-6">
                <div class="text-muted small text-uppercase"><?= e(__('bill_to')) ?></div>
                <b><?= e($o['customer_name']) ?></b><br><?= e($o['customer_phone']) ?><?= $o['customer_email'] ? '<br>' . e($o['customer_email']) : '' ?>
            </div>
            <div class="col-6">
                <div class="text-muted small text-uppercase"><?= e(__('ship_to')) ?></div>
                <b><?= e($o['shipping_name']) ?></b> · <?= e($o['shipping_phone']) ?><br><?= e($o['shipping_address']) ?><br><?= e($area) ?><?= $o['postcode'] ? ' - ' . e($o['postcode']) : '' ?>
            </div>
        </div>
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr><th>#</th><th><?= e(__('product')) ?></th><th class="text-end"><?= e(__('price')) ?></th><th class="text-center"><?= e(__('quantity')) ?></th><th class="text-end"><?= e(__('total')) ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $it): ?>
                    <tr>
                        <td><?= e(num($i + 1)) ?></td>
                        <td><?= e(OrderView::itemName($it)) ?><?= (int) $it['requires_prescription'] ? ' <small>(Rx)</small>' : '' ?>
                            <?php if ($it['status'] !== 'active'): ?><small class="text-danger">— <?= e(__($it['status'] === 'cancelled' ? 'os_cancelled' : 'os_returned')) ?></small><?php endif; ?></td>
                        <td class="text-end"><?= e(money($it['unit_price'])) ?></td>
                        <td class="text-center"><?= e(num($it['quantity'])) ?></td>
                        <td class="text-end"><?= e(money($it['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="text-end"><?= e(__('subtotal')) ?></td><td class="text-end"><?= e(money($o['subtotal'])) ?></td></tr>
                <?php if ((float) ($o['promo_discount'] ?? 0) > 0): ?><tr><td colspan="4" class="text-end"><?= e(__('offer_discount')) ?></td><td class="text-end">-<?= e(money($o['promo_discount'])) ?></td></tr><?php endif; ?>
                <?php if ((float) $o['coupon_discount'] > 0): ?><tr><td colspan="4" class="text-end"><?= e(__('coupon_discount')) ?> (<?= e($o['coupon_code']) ?>)</td><td class="text-end">-<?= e(money($o['coupon_discount'])) ?></td></tr><?php endif; ?>
                <tr><td colspan="4" class="text-end"><?= e(__('delivery_charge')) ?></td><td class="text-end"><?= e(money($o['shipping_total'])) ?></td></tr>
                <?php if ((float) $o['cod_charge'] > 0): ?><tr><td colspan="4" class="text-end"><?= e(__('cod_charge')) ?></td><td class="text-end"><?= e(money($o['cod_charge'])) ?></td></tr><?php endif; ?>
                <?php if ((float) ($o['vat_total'] ?? 0) > 0): ?><tr><td colspan="4" class="text-end<?= Tax::orderVatOnTop($o) ? '' : ' text-muted' ?>"><?= e(Tax::rowLabel($o)) ?></td><td class="text-end<?= Tax::orderVatOnTop($o) ? '' : ' text-muted' ?>"><?= e(money($o['vat_total'])) ?></td></tr><?php endif; ?>
                <tr class="fw-bold"><td colspan="4" class="text-end"><?= e(__('total')) ?></td><td class="text-end"><?= e(money($o['grand_total'])) ?></td></tr>
                <tr><td colspan="4" class="text-end"><?= e(__('paid')) ?></td><td class="text-end"><?= e(money($o['paid_amount'])) ?></td></tr>
                <?php if ((float) $o['refunded_amount'] > 0): ?><tr><td colspan="4" class="text-end"><?= e(__('refunded')) ?></td><td class="text-end">-<?= e(money($o['refunded_amount'])) ?></td></tr><?php endif; ?>
                <tr class="fw-bold"><td colspan="4" class="text-end"><?= e(__('amount_due')) ?></td><td class="text-end"><?= e(money(max(0, (float) $o['grand_total'] - (float) $o['paid_amount']))) ?></td></tr>
            </tfoot>
        </table>
        <div class="small">
            <?= e(__('payment')) ?>: <b><?= e(__('pm_' . $o['payment_method'])) ?></b> (<?= e(__('ps_' . $o['payment_status'])) ?>)
            <?php foreach ($payments as $p): if ($p['transaction_id']): ?> · <?= e(__('trx_id')) ?>: <?= e($p['transaction_id']) ?><?php endif; endforeach; ?>
        </div>
        <p class="small text-muted mt-4 mb-0 text-center"><?= e(__('invoice_footer')) ?></p>
    </div>
<?php endforeach; ?>
