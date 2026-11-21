<?php
/**
 * Generic report page. Vars: $key, $group (reports|accounting), $icon, $filters, $f, $report, $options
 */
$base = ($group === 'accounting' ? 'admin/accounting/' : 'admin/reports/') . $key;
$query = array_filter($_GET, static fn($v) => is_scalar($v) && $v !== '');
unset($query['format']);
$qs = static fn(array $extra = []) => ($q = http_build_query(array_merge($query, $extra))) !== '' ? '?' . $q : '';
$numeric = ['money', 'int', 'num', 'pct'];
$fmt = static function ($v, string $type): string {
    if ($v === null || $v === '') {
        return '';
    }
    return match ($type) {
        'money'    => money($v),
        'int'      => num(number_format((float) $v)),
        'num'      => num(number_format((float) $v, 1)),
        'pct'      => num(number_format((float) $v, 1)) . '%',
        'date'     => dt((string) $v, 'd M Y'),
        'datetime' => dt((string) $v, 'd M Y, h:i A'),
        default    => (string) $v,
    };
};
$has = static fn(string $flt) => in_array($flt, $filters, true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><i class="bi <?= e($icon) ?>"></i> <?= e($report['title']) ?></h1>
        <div class="small text-muted"><?= e($report['subtitle'] ?? '') ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
        <?php if (can($group . '.export')): ?>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?= e(__('export')) ?>">
                <a class="btn btn-outline-secondary" href="<?= e(url($base . '/export') . $qs(['format' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
                <a class="btn btn-outline-secondary" href="<?= e(url($base . '/export') . $qs(['format' => 'xlsx'])) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a class="btn btn-outline-secondary" href="<?= e(url($base . '/export') . $qs(['format' => 'pdf'])) ?>" title="<?= e(__('rp_pdf_english')) ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            </div>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> <?= e(__('print')) ?></button>
    </div>
</div>

<?php if ($group === 'accounting'): ?>
    <?= View::partial('admin/accounting/_nav', ['active' => $key]) ?>
<?php else: ?>
    <ul class="nav nav-pills small mb-3 d-print-none flex-nowrap overflow-auto">
        <li class="nav-item"><a class="nav-link text-nowrap" href="<?= e(url('admin/reports')) ?>"><i class="bi bi-grid"></i></a></li>
        <?php foreach (Reports::LIST as $k => [$g, $ic]): if ($g !== 'reports') { continue; } ?>
            <li class="nav-item"><a class="nav-link text-nowrap<?= $k === $key ? ' active' : '' ?>" href="<?= e(url('admin/reports/' . $k)) ?>"><?= e(__('rp_' . $k)) ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="get" class="card border-0 shadow-sm mb-3 d-print-none">
    <div class="card-body row g-2 align-items-end">
        <?php if ($has('range')): ?>
            <div class="col-12 d-flex flex-wrap gap-1">
                <?php foreach (Reports::presets() as $p => [$pf, $pt]): $on = $f['from'] === $pf && $f['to'] === $pt; ?>
                    <a class="btn btn-sm <?= $on ? 'btn-brand' : 'btn-outline-secondary' ?>" href="<?= e(url($base) . $qs(['from' => $pf, 'to' => $pt])) ?>"><?= e(__('rp_preset_' . $p)) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="rf"><?= e(__('from')) ?></label>
                <input class="form-control form-control-sm" type="date" id="rf" name="from" value="<?= e($f['from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="rt"><?= e(__('to')) ?></label>
                <input class="form-control form-control-sm" type="date" id="rt" name="to" value="<?= e($f['to']) ?>">
            </div>
        <?php endif; ?>
        <?php if ($has('group_by')): ?>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="gb"><?= e(__('rp_group_by')) ?></label>
                <select class="form-select form-select-sm" id="gb" name="group_by">
                    <option value=""><?= e(__('rp_auto')) ?></option>
                    <?php foreach (['day', 'week', 'month'] as $g): ?><option value="<?= $g ?>" <?= $f['group_by'] === $g ? 'selected' : '' ?>><?= e(__('rp_by_' . $g)) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($has('category')): ?>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="fc"><?= e(__('category')) ?></label>
                <select class="form-select form-select-sm" id="fc" name="category">
                    <option value=""><?= e(__('all')) ?></option>
                    <?php foreach ($options['categories'] as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['path']) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($has('seller')): ?>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="fs"><?= e(__('seller')) ?></label>
                <select class="form-select form-select-sm" id="fs" name="seller">
                    <option value=""><?= e(__('all')) ?></option>
                    <option value="inhouse" <?= $f['seller'] === 'inhouse' ? 'selected' : '' ?>><?= e(__('app_name')) ?> (<?= e(__('in_house')) ?>)</option>
                    <?php foreach ($options['vendors'] as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $f['seller'] === (string) $v['id'] ? 'selected' : '' ?>><?= e($v['shop_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($has('vendor')): ?>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="fv"><?= e(__('seller')) ?></label>
                <select class="form-select form-select-sm" id="fv" name="vendor">
                    <option value=""><?= e(__('all')) ?></option>
                    <?php foreach ($options['vendors'] as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $f['vendor'] === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['shop_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($has('courier')): ?>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="fco"><?= e(__('courier')) ?></label>
                <select class="form-select form-select-sm" id="fco" name="courier">
                    <option value=""><?= e(__('all')) ?></option>
                    <option value="own" <?= $f['courier'] === 'own' ? 'selected' : '' ?>><?= e(__('own_delivery')) ?></option>
                    <?php foreach ($options['couriers'] as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['courier'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($has('stock_state')): ?>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="fss"><?= e(__('status')) ?></label>
                <select class="form-select form-select-sm" id="fss" name="stock_state">
                    <option value=""><?= e(__('all')) ?></option>
                    <option value="low" <?= $f['stock_state'] === 'low' ? 'selected' : '' ?>><?= e(__('rp_stock_low')) ?></option>
                    <option value="out" <?= $f['stock_state'] === 'out' ? 'selected' : '' ?>><?= e(__('rp_stock_out')) ?></option>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($has('days')): ?>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="fd"><?= e(__('rp_expiring_within')) ?></label>
                <select class="form-select form-select-sm" id="fd" name="days">
                    <?php foreach ([30, 60, 90, 180, 365] as $d): ?><option value="<?= $d ?>" <?= $f['days'] === $d ? 'selected' : '' ?>><?= e(__('rp_n_days', ['n' => num($d)])) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php foreach (['payout_status' => ['po_st_', ['requested', 'approved', 'paid', 'rejected']], 'refund_status' => ['rf_', ['requested', 'approved', 'processed', 'rejected']], 'entry_type' => ['acc_', ['income', 'expense']]] as $flt => [$prefix, $vals]): if (!$has($flt)) { continue; } ?>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="f_<?= $flt ?>"><?= e(__($flt === 'entry_type' ? 'type' : 'status')) ?></label>
                <select class="form-select form-select-sm" id="f_<?= $flt ?>" name="<?= $flt ?>">
                    <option value=""><?= e(__('all')) ?></option>
                    <?php foreach ($vals as $v): ?><option value="<?= $v ?>" <?= $f[$flt] === $v ? 'selected' : '' ?>><?= e(__($prefix . $v)) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <?php if ($has('entry_category')): ?>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="fec"><?= e(__('category')) ?></label>
                <select class="form-select form-select-sm" id="fec" name="entry_category">
                    <option value=""><?= e(__('all')) ?></option>
                    <?php foreach ($options['entryCategories'] as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['entry_category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(__('acc_' . $c['type'])) ?>: <?= e(tr($c['name_bn'], $c['name_en'])) ?></option><?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-6 col-md-auto">
            <button class="btn btn-brand btn-sm w-100"><i class="bi bi-funnel"></i> <?= e(__('apply')) ?></button>
        </div>
    </div>
</form>

<?php if ($report['cards']): ?>
    <div class="row g-3 mb-3">
        <?php foreach ($report['cards'] as [$label, $value, $type]): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body py-2">
                    <div class="small text-muted"><?= e($label) ?></div>
                    <div class="fs-5 fw-semibold"><?= e($fmt($value, $type) !== '' ? $fmt($value, $type) : '—') ?></div>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($report['chart']) && !empty($report['chart']['labels'])): $ch = $report['chart']; ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div style="height: 260px"><canvas data-chart="<?= e(json_encode(['type' => $ch['type'] ?? 'bar', 'labels' => $ch['labels'], 'datasets' => $ch['datasets'], 'money' => true], JSON_UNESCAPED_UNICODE)) ?>" aria-label="<?= e($report['title']) ?>" role="img"></canvas></div>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($report['tables'] as $t): ?>
    <div class="card border-0 shadow-sm mb-3">
        <?php if (!empty($t['title'])): ?><div class="card-header bg-white fw-semibold"><?= e($t['title']) ?> <span class="text-muted small fw-normal">(<?= e(num(count($t['rows']))) ?>)</span></div><?php endif; ?>
        <div class="table-responsive report-table">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light"><tr>
                    <?php foreach ($t['columns'] as [, $label, $type]): ?><th class="<?= in_array($type, $numeric, true) ? 'text-end' : '' ?> text-nowrap"><?= e($label) ?></th><?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php if (!$t['rows']): ?><tr><td colspan="<?= count($t['columns']) ?>" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                <?php foreach ($t['rows'] as $r): ?>
                    <tr>
                        <?php foreach ($t['columns'] as [$c, , $type]): $v = $fmt($r[$c] ?? null, $type); ?>
                            <td class="<?= in_array($type, $numeric, true) ? 'text-end text-nowrap' : '' ?>"><?= $v !== '' ? e($v) : '<span class="text-muted">—</span>' ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (!empty($t['totals']) && $t['rows']): ?>
                    <tfoot class="table-light fw-semibold"><tr>
                        <?php foreach ($t['columns'] as [$c, , $type]): ?><td class="<?= in_array($type, $numeric, true) ? 'text-end text-nowrap' : '' ?>"><?= e($fmt($t['totals'][$c] ?? null, $type)) ?></td><?php endforeach; ?>
                    </tr></tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($report['notes'] as $n): ?>
    <p class="small text-muted mb-1"><i class="bi bi-info-circle"></i> <?= e($n) ?></p>
<?php endforeach; ?>

<?php if (!empty($report['chart'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="<?= e(asset('assets/js/charts.js')) ?>" defer></script>
<?php endif; ?>
