<?php
/** Profit & loss statement. Vars: $f, $pl */
$query = array_filter(['from' => $f['from'], 'to' => $f['to']]);
$line = static function (string $label, float $amount, bool $negative = false, string $cls = '', ?string $link = null): string {
    $text = $link ? '<a href="' . e($link) . '" class="text-decoration-none">' . e($label) . '</a>' : e($label);
    return '<tr class="' . $cls . '"><td class="ps-4">' . $text . '</td><td class="text-end text-nowrap">' . ($negative && $amount != 0.0 ? '(' . e(money($amount)) . ')' : e(money($amount))) . '</td></tr>';
};
$range = '?from=' . $f['from'] . '&to=' . $f['to'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><i class="bi bi-calculator"></i> <?= e(__('pl_title')) ?></h1>
        <div class="small text-muted"><?= e(__('rp_range', ['from' => dt($f['from'], 'd M Y'), 'to' => dt($f['to'], 'd M Y')])) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
        <?php if (can('accounting.export')): ?>
            <div class="btn-group btn-group-sm">
                <?php foreach (['csv' => ['bi-filetype-csv', 'CSV'], 'xlsx' => ['bi-file-earmark-excel', 'Excel'], 'pdf' => ['bi-file-earmark-pdf', 'PDF']] as $fmtKey => [$ic, $lbl]): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('admin/accounting/pnl?' . http_build_query($query + ['format' => $fmtKey]))) ?>"><i class="bi <?= $ic ?>"></i> <?= $lbl ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button>
    </div>
</div>
<?= View::partial('admin/accounting/_nav', ['active' => 'pnl']) ?>

<form method="get" class="card border-0 shadow-sm mb-3 d-print-none">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 d-flex flex-wrap gap-1">
            <?php foreach (Reports::presets() as $p => [$pf, $pt]): ?>
                <a class="btn btn-sm <?= $f['from'] === $pf && $f['to'] === $pt ? 'btn-brand' : 'btn-outline-secondary' ?>" href="<?= e(url('admin/accounting/pnl?from=' . $pf . '&to=' . $pt)) ?>"><?= e(__('rp_preset_' . $p)) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="pf"><?= e(__('from')) ?></label><input class="form-control form-control-sm" type="date" id="pf" name="from" value="<?= e($f['from']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="pt"><?= e(__('to')) ?></label><input class="form-control form-control-sm" type="date" id="pt" name="to" value="<?= e($f['to']) ?>"></div>
        <div class="col-auto"><button class="btn btn-brand btn-sm"><i class="bi bi-funnel"></i> <?= e(__('apply')) ?></button></div>
    </div>
</form>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <table class="table table-sm align-middle mb-0 pl-table">
                <tbody>
                    <tr class="table-light fw-semibold"><td colspan="2"><?= e(__('pl_income')) ?></td></tr>
                    <?= $line(__('pl_inhouse_sales'), $pl['income'][0][1], false, '', url('admin/reports/products' . $range . '&seller=inhouse')) ?>
                    <?= $line(__('pl_commission'), $pl['income'][1][1], false, '', url('admin/accounting/ledger' . $range)) ?>
                    <?= $line(__('pl_delivery_income'), $pl['income'][2][1]) ?>
                    <?php foreach ($pl['other_income'] as [$label, $v, $cid]): ?><?= $line($label, $v, false, '', url('admin/accounting/entries' . $range . '&entry_category=' . $cid)) ?><?php endforeach; ?>
                    <tr class="fw-semibold"><td><?= e(__('pl_total_income')) ?></td><td class="text-end"><?= e(money($pl['total_income'])) ?></td></tr>

                    <tr class="table-light fw-semibold"><td colspan="2"><?= e(__('pl_direct_costs')) ?></td></tr>
                    <?= $line(__('pl_cogs'), $pl['costs'][0][1], true) ?>
                    <?= $line(__('pl_promotions'), $pl['costs'][1][1], true) ?>
                    <?= $line(__('pl_courier'), $pl['costs'][2][1], true, '', url('admin/accounting/cod' . $range)) ?>
                    <?= $line(__('pl_gateway'), $pl['costs'][3][1], true) ?>
                    <tr class="fw-semibold"><td><?= e(__('pl_gross_profit')) ?></td><td class="text-end <?= $pl['gross_profit'] < 0 ? 'text-danger' : '' ?>"><?= e(money($pl['gross_profit'])) ?></td></tr>

                    <tr class="table-light fw-semibold"><td colspan="2"><?= e(__('pl_expenses')) ?></td></tr>
                    <?php if (!$pl['expenses']): ?><tr><td class="ps-4 text-muted" colspan="2"><?= e(__('acc_no_entries')) ?></td></tr><?php endif; ?>
                    <?php foreach ($pl['expenses'] as [$label, $v, $cid]): ?><?= $line($label, $v, true, '', url('admin/accounting/entries' . $range . '&entry_category=' . $cid)) ?><?php endforeach; ?>
                    <tr class="fw-semibold"><td><?= e(__('pl_total_expenses')) ?></td><td class="text-end">(<?= e(money($pl['total_expenses'])) ?>)</td></tr>

                    <tr class="table-<?= $pl['net_profit'] >= 0 ? 'success' : 'danger' ?> fw-bold fs-6"><td><?= e(__($pl['net_profit'] >= 0 ? 'pl_net_profit' : 'pl_net_loss')) ?></td>
                        <td class="text-end"><?= e(money($pl['net_profit'])) ?><?php if ($pl['margin'] !== null): ?><div class="small fw-normal"><?= e(__('rp_margin')) ?> <?= e(num(number_format($pl['margin'], 1))) ?>%</div><?php endif; ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php if ($pl['no_cost_units'] > 0): ?>
            <div class="alert alert-warning small mt-3"><i class="bi bi-exclamation-triangle"></i> <?= e(__('pl_missing_cost', ['n' => num($pl['no_cost_units'])])) ?></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('pl_memo')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($pl['memo'] as [$k, $v]): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2"><span><?= e(__($k)) ?></span><strong class="text-nowrap"><?= e(money($v)) ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="small text-muted"><i class="bi bi-info-circle"></i> <?= e(__('pl_note')) ?></div>
    </div>
</div>
