<?php
/** Accounting tabs. Vars: $active */
$tabs = [
    'overview'   => ['admin/accounting', 'bi-speedometer2', __('acc_overview')],
    'pnl'        => ['admin/accounting/pnl', 'bi-calculator', __('pl_title')],
    'entries'    => ['admin/accounting/entries', 'bi-journal-plus', __('acc_entries')],
    'ledger'     => ['admin/accounting/ledger', 'bi-journal-text', __('rp_ledger')],
    'payouts'    => ['admin/accounting/payouts', 'bi-cash-coin', __('rp_payouts')],
    'cod'        => ['admin/accounting/cod', 'bi-cash-stack', __('rp_cod')],
    'refunds'    => ['admin/accounting/refunds', 'bi-arrow-counterclockwise', __('rp_refunds')],
    'vat'        => ['admin/accounting/vat', 'bi-receipt-cutoff', __('rp_vat')],
    'tax'        => ['admin/accounting/tax', 'bi-sliders', __('acc_tax_settings')],
];
?>
<ul class="nav nav-pills small mb-3 d-print-none flex-nowrap overflow-auto">
    <?php foreach ($tabs as $k => [$path, $icon, $label]): ?>
        <li class="nav-item"><a class="nav-link text-nowrap<?= $k === $active ? ' active' : '' ?>" href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
