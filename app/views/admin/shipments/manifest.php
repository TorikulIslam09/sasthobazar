<?php /** Courier hand-over manifest. Vars: $list, $date */
$byCourier = [];
foreach ($list as $sh) {
    $byCourier[$sh['courier_name'] ?? __('not_booked')][] = $sh;
}
?>
<style>
    .manifest { max-width: 1000px; margin: 0 auto; padding: 16px; font-size: 13px; }
    .manifest + .manifest { page-break-before: always; break-before: page; }
    .manifest svg { height: 34px; width: auto; }
    @media print { .no-print { display: none !important; } @page { size: A4 landscape; margin: 10mm; } }
</style>
<div class="no-print text-center my-3"><button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button></div>
<?php foreach ($byCourier as $courier => $rows): $cod = array_sum(array_map(static fn($r) => (float) $r['cod_amount'], $rows)); ?>
    <div class="manifest">
        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
            <div><div class="fs-5 fw-bold"><?= e(__('manifest')) ?> — <?= e($courier) ?></div><div><?= e(__('app_name')) ?> · <?= e(setting('contact_phone', '')) ?></div></div>
            <div class="text-end"><?= e(dt($date . ' 00:00:00', 'd M Y')) ?><br><?= e(__('manifest_totals', ['n' => num(count($rows)), 'cod' => money($cod)])) ?></div>
        </div>
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light"><tr><th>#</th><th><?= e(__('tracking')) ?></th><th><?= e(__('sub_order')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('ship_to')) ?></th><th class="text-end"><?= e(__('weight')) ?></th><th class="text-end">COD</th><th><?= e(__('signature')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $sh): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= $sh['tracking_no'] ? Barcode::code128Svg((string) $sh['tracking_no'], 28, 1, true) : '—' ?></td>
                    <td><?= e($sh['sub_order_no']) ?><br><small><?= e($sh['shop_name'] ?? __('app_name')) ?></small></td>
                    <td><?= e($sh['shipping_name']) ?><br><?= e($sh['shipping_phone']) ?></td>
                    <td><?= e($sh['area']) ?></td>
                    <td class="text-end"><?= e(number_format(max(0, (int) $sh['weight_grams']) / 1000, 2)) ?> kg</td>
                    <td class="text-end"><?= (float) $sh['cod_amount'] > 0 ? e(money($sh['cod_amount'], false)) : '—' ?></td>
                    <td style="width: 90px"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="6" class="text-end"><?= e(__('total')) ?></td><td class="text-end"><?= e(money($cod, false)) ?></td><td></td></tr></tfoot>
        </table>
        <div class="row mt-4">
            <div class="col-6"><?= e(__('handed_over_by')) ?>: ____________________</div>
            <div class="col-6 text-end"><?= e(__('received_by_courier')) ?>: ____________________</div>
        </div>
    </div>
<?php endforeach; ?>
