<?php /** Shipping labels (100 x 150 mm, one per page). Var: $list */ ?>
<style>
    .label { width: 100mm; min-height: 145mm; margin: 6mm auto; border: 1.5px solid #000; padding: 4mm; font-size: 12px; color: #000; background: #fff; display: flex; flex-direction: column; gap: 3mm; }
    .label + .label { page-break-before: always; break-before: page; }
    .label .row1 { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #000; padding-bottom: 2mm; }
    .label .big { font-size: 18px; font-weight: 700; }
    .label .box { border: 1px solid #000; padding: 2mm; }
    .label .cod { font-size: 22px; font-weight: 800; text-align: center; border: 2px solid #000; padding: 2mm; }
    .label svg { max-width: 100%; height: auto; }
    @media print { @page { size: 100mm 150mm; margin: 0; } body { margin: 0; } .no-print { display: none !important; } .label { margin: 0; border: 0; } }
</style>
<div class="no-print text-center my-3"><button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button></div>
<?php foreach ($list as $sh): $code = $sh['tracking_no'] ?: ('SH' . str_pad((string) $sh['id'], 8, '0', STR_PAD_LEFT)); ?>
    <div class="label">
        <div class="row1">
            <span class="big"><?= e(__('app_name')) ?></span>
            <span class="text-end"><b><?= e($sh['courier_name'] ?? __('dopt_' . $sh['delivery_option'])) ?></b><br><?= e(strtoupper(__('dopt_' . $sh['delivery_option']))) ?></span>
        </div>
        <div class="text-center"><?= Barcode::code128Svg($code, 55, 1.5) ?></div>
        <div class="box"><b><?= e(__('ship_to')) ?>:</b> <span class="big"><?= e($sh['shipping_name']) ?></span><br>
            <b><?= e($sh['shipping_phone']) ?></b><br><?= e($sh['shipping_address']) ?><br><?= e($sh['area']) ?><?= $sh['postcode'] ? ' - ' . e($sh['postcode']) : '' ?></div>
        <div class="box"><b><?= e(__('ship_from')) ?>:</b> <?= e($sh['shop_name'] ?? __('app_name')) ?> · <?= e($sh['pickup_phone'] ?: ($sh['vendor_phone'] ?? setting('contact_phone', ''))) ?><br><?= e($sh['vendor_address'] ?? (setting('store_pickup_address', '') ?: setting('contact_address', ''))) ?></div>
        <div class="d-flex justify-content-between">
            <span><?= e(__('sub_order')) ?>: <b><?= e($sh['sub_order_no']) ?></b></span>
            <span><?= e(num((int) $sh['items'])) ?> <?= e(__('items')) ?> · <?= e(number_format(max(0, (int) $sh['weight_grams']) / 1000, 2)) ?> kg</span>
        </div>
        <div class="cod"><?= (float) $sh['cod_amount'] > 0 ? 'COD ' . e(money($sh['cod_amount'], false)) : e(__('label_paid')) ?></div>
        <?php if ($sh['customer_note']): ?><div class="small"><?= e(__('order_note')) ?>: <?= e(mb_strimwidth((string) $sh['customer_note'], 0, 120, '…')) ?></div><?php endif; ?>
        <div class="small text-end mt-auto"><?= e(date('d M Y')) ?> · #<?= (int) $sh['id'] ?></div>
    </div>
<?php endforeach; ?>
