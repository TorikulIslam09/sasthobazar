<?php
/** Accounting overview. Vars: $month, $last (P&L arrays), $trend, $stats, $recent */
$change = static function (float $now, float $before): string {
    if ($before == 0.0) {
        return '';
    }
    $p = ($now - $before) / abs($before) * 100;
    return '<span class="small ' . ($p >= 0 ? 'text-success' : 'text-danger') . '"><i class="bi bi-arrow-' . ($p >= 0 ? 'up' : 'down') . '"></i> '
        . e(num(number_format(abs($p), 1))) . '%</span>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><i class="bi bi-journal-bookmark"></i> <?= e(__('accounting')) ?></h1>
        <div class="small text-muted"><?= e(__('acc_this_month_vs_last')) ?></div>
    </div>
    <?php if (can('accounting.create')): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/accounting/entries/create?type=expense')) ?>"><i class="bi bi-dash-circle"></i> <?= e(__('acc_add_expense')) ?></a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/accounting/entries/create?type=income')) ?>"><i class="bi bi-plus-circle"></i> <?= e(__('acc_add_income')) ?></a>
        </div>
    <?php endif; ?>
</div>
<?= View::partial('admin/accounting/_nav', ['active' => 'overview']) ?>

<div class="row g-3 mb-3">
    <?php foreach (['total_income' => 'pl_total_income', 'gross_profit' => 'pl_gross_profit', 'total_expenses' => 'pl_total_expenses', 'net_profit' => 'pl_net_profit'] as $k => $label): ?>
        <div class="col-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted"><?= e(__($label)) ?></div>
                <div class="fs-5 fw-semibold <?= $k === 'net_profit' && $month[$k] < 0 ? 'text-danger' : '' ?>"><?= e(money($month[$k])) ?></div>
                <div class="small text-muted"><?= e(__('acc_last_month')) ?>: <?= e(money($last[$k])) ?> <?= $change((float) $month[$k], (float) $last[$k]) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= e(__('acc_six_months')) ?></div>
            <div class="card-body"><div style="height: 260px">
                <canvas data-chart="<?= e(json_encode(['type' => 'bar', 'money' => true, 'labels' => array_column($trend, 'label'), 'datasets' => [
                    ['label' => __('pl_total_income'), 'data' => array_column($trend, 'income'), 'kind' => 'bar'],
                    ['label' => __('pl_net_profit'), 'data' => array_column($trend, 'net'), 'kind' => 'bar'],
                ]], JSON_UNESCAPED_UNICODE)) ?>" role="img" aria-label="<?= e(__('acc_six_months')) ?>"></canvas>
            </div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= e(__('acc_money_owed')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach (['cod_held' => ['admin/accounting/cod', 'bi-cash-stack'], 'refunds_due' => ['admin/refunds?status=approved', 'bi-arrow-counterclockwise'],
                                'payouts_open' => ['admin/payouts', 'bi-cash-coin'], 'seller_owed' => ['admin/accounting/payouts', 'bi-shop']] as $k => [$link, $icon]): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="<?= e(url($link)) ?>" class="text-decoration-none"><i class="bi <?= $icon ?>"></i> <?= e(__('acc_' . $k)) ?></a>
                        <strong><?= e(money($stats[$k])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-body small text-muted"><?= e(__('acc_money_owed_help')) ?></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= e(__('acc_recent_entries')) ?></span>
        <a class="small" href="<?= e(url('admin/accounting/entries')) ?>"><?= e(__('view_all')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('date')) ?></th><th><?= e(__('acc_entry_no')) ?></th><th><?= e(__('category')) ?></th><th><?= e(__('description')) ?></th><th class="text-end"><?= e(__('amount')) ?></th></tr></thead>
            <tbody>
            <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('acc_no_entries')) ?></td></tr><?php endif; ?>
            <?php foreach ($recent as $e): ?>
                <tr>
                    <td class="text-nowrap"><?= e(dt($e['entry_date'], 'd M Y')) ?></td>
                    <td><?= e($e['entry_no']) ?></td>
                    <td><?= e(tr($e['name_bn'], $e['name_en'])) ?></td>
                    <td class="text-truncate" style="max-width: 280px"><?= e($e['description'] ?? '') ?></td>
                    <td class="text-end text-nowrap <?= $e['type'] === 'income' ? 'text-success' : 'text-danger' ?>"><?= $e['type'] === 'income' ? '+' : '−' ?><?= e(money($e['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= e(asset('assets/js/charts.js')) ?>" defer></script>
