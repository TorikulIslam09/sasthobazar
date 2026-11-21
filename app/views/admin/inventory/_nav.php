<?php
/** Inventory section tabs. Var: $active (key) */
$tabs = [
    'dashboard' => ['admin/inventory', 'bi-speedometer2', __('inv_dashboard'), 'inventory.view'],
    'batches'   => ['admin/inventory/batches', 'bi-layers', __('inv_batches'), 'inventory.view'],
    'stock_in'  => ['admin/inventory/stock-in', 'bi-box-arrow-in-down', __('inv_stock_in'), 'inventory.adjust'],
    'stock_out' => ['admin/inventory/stock-out', 'bi-box-arrow-up', __('inv_stock_out'), 'inventory.adjust'],
    'movements' => ['admin/inventory/movements', 'bi-journal-text', __('inv_movements'), 'inventory.view'],
    'valuation' => ['admin/inventory/reports/valuation', 'bi-cash-coin', __('inv_valuation'), 'inventory.view'],
    'vendors'   => ['admin/inventory/reports/vendors', 'bi-shop', __('inv_vendor_report'), 'inventory.view'],
];
?>
<ul class="nav nav-pills nav-pills-sm flex-nowrap overflow-auto mb-3 status-tabs">
    <?php foreach ($tabs as $key => [$href, $icon, $label, $perm]): if (!can($perm)) continue; ?>
        <li class="nav-item"><a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
