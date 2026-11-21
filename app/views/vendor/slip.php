<?php /** Packing slip for one sub-order (print layout). Vars: $vendor, $s, $items, $area */ ?>
<style>.slip { max-width: 760px; margin: 0 auto; padding: 20px 16px; font-size: 14px; } @media print { .no-print { display: none !important; } }</style>
<div class="no-print text-center my-3"><button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button></div>
<div class="slip">
    <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
        <div><b><?= e($vendor['shop_name']) ?></b><br><span class="small"><?= e($vendor['address']) ?> · <?= e($vendor['pickup_phone'] ?: $vendor['phone']) ?></span></div>
        <div class="text-end"><b><?= e(__('packing_slip')) ?></b><br><?= e($s['sub_order_no']) ?><br><span class="small"><?= e(dt($s['placed_at'], 'd M Y')) ?></span></div>
    </div>
    <div class="mb-3"><span class="text-muted small text-uppercase"><?= e(__('ship_to')) ?></span><br><b><?= e($s['shipping_name']) ?></b> · <?= e($s['shipping_phone']) ?><br><?= e($s['shipping_address']) ?><br><?= e($area) ?></div>
    <table class="table table-bordered">
        <thead class="table-light"><tr><th>#</th><th><?= e(__('product')) ?></th><th class="text-center"><?= e(__('quantity')) ?></th><th><?= e(__('inv_batch_no')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($items as $i => $it): $allocs = json_decode((string) $it['batch_allocations'], true) ?: []; ?>
            <tr><td><?= e(num($i + 1)) ?></td><td><?= e($it['product_name']) ?><?= $it['variant_name'] ? ' — ' . e($it['variant_name']) : '' ?></td><td class="text-center"><?= e(num($it['quantity'])) ?></td>
                <td><?= e(implode(', ', array_map(static fn($a) => $a['batch_no'] . ' × ' . $a['qty'], $allocs))) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="small"><?= e(__('pm_' . $s['payment_method'])) ?><?= $s['payment_method'] === 'cod' ? ' — ' . e(__('slip_cod_note')) : '' ?></p>
</div>
