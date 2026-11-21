<?php
/** Income & expense entries. Vars: $f, $report (Reports 'entries'), $categories */
$table = $report['tables'][1];
$query = array_filter($_GET, static fn($v) => is_scalar($v) && $v !== '');
$qs = static fn(array $extra = []) => '?' . http_build_query(array_merge($query, $extra));
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><i class="bi bi-journal-plus"></i> <?= e(__('acc_entries')) ?></h1>
        <div class="small text-muted"><?= e($report['subtitle']) ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
        <?php if (can('accounting.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/accounting/entries/create?type=expense')) ?>"><i class="bi bi-dash-circle"></i> <?= e(__('acc_add_expense')) ?></a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/accounting/entries/create?type=income')) ?>"><i class="bi bi-plus-circle"></i> <?= e(__('acc_add_income')) ?></a>
        <?php endif; ?>
        <?php if (can('accounting.edit')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/accounting/categories')) ?>"><i class="bi bi-tags"></i> <?= e(__('acc_categories')) ?></a><?php endif; ?>
        <?php if (can('accounting.export')): ?>
            <div class="btn-group btn-group-sm">
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/accounting/entries/export') . $qs(['format' => 'csv'])) ?>">CSV</a>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/accounting/entries/export') . $qs(['format' => 'xlsx'])) ?>">Excel</a>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/accounting/entries/export') . $qs(['format' => 'pdf'])) ?>">PDF</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= View::partial('admin/accounting/_nav', ['active' => 'entries']) ?>

<form method="get" class="card border-0 shadow-sm mb-3 d-print-none">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 d-flex flex-wrap gap-1">
            <?php foreach (Reports::presets() as $p => [$pf, $pt]): ?>
                <a class="btn btn-sm <?= $f['from'] === $pf && $f['to'] === $pt ? 'btn-brand' : 'btn-outline-secondary' ?>" href="<?= e(url('admin/accounting/entries') . $qs(['from' => $pf, 'to' => $pt])) ?>"><?= e(__('rp_preset_' . $p)) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="ef"><?= e(__('from')) ?></label><input class="form-control form-control-sm" type="date" id="ef" name="from" value="<?= e($f['from']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="et"><?= e(__('to')) ?></label><input class="form-control form-control-sm" type="date" id="et" name="to" value="<?= e($f['to']) ?>"></div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="ety"><?= e(__('type')) ?></label>
            <select class="form-select form-select-sm" id="ety" name="entry_type">
                <option value=""><?= e(__('all')) ?></option>
                <option value="income" <?= $f['entry_type'] === 'income' ? 'selected' : '' ?>><?= e(__('acc_income')) ?></option>
                <option value="expense" <?= $f['entry_type'] === 'expense' ? 'selected' : '' ?>><?= e(__('acc_expense')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="ec"><?= e(__('category')) ?></label>
            <select class="form-select form-select-sm" id="ec" name="entry_category">
                <option value=""><?= e(__('all')) ?></option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['entry_category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(__('acc_' . $c['type'])) ?>: <?= e(tr($c['name_bn'], $c['name_en'])) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-brand btn-sm"><i class="bi bi-funnel"></i> <?= e(__('apply')) ?></button></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ($report['cards'] as $i => [$label, $value]): ?>
        <div class="col-4">
            <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body py-2">
                <div class="small text-muted"><?= e($label) ?></div>
                <div class="fs-5 fw-semibold <?= $i === 0 ? 'text-success' : ($i === 1 ? 'text-danger' : ($value < 0 ? 'text-danger' : '')) ?>"><?= e(money($value)) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light"><tr>
                <th><?= e(__('date')) ?></th><th><?= e(__('acc_entry_no')) ?></th><th><?= e(__('category')) ?></th><th><?= e(__('description')) ?></th>
                <th><?= e(__('rp_method')) ?></th><th class="text-end"><?= e(__('acc_income')) ?></th><th class="text-end"><?= e(__('acc_expense')) ?></th><th class="text-end d-print-none"></th>
            </tr></thead>
            <tbody>
            <?php if (!$table['rows']): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('acc_no_entries')) ?></td></tr><?php endif; ?>
            <?php foreach ($table['rows'] as $r): ?>
                <tr>
                    <td class="text-nowrap"><?= e(dt($r['entry_date'], 'd M Y')) ?></td>
                    <td class="text-nowrap"><?= e($r['entry_no']) ?></td>
                    <td><?= e($r['category']) ?></td>
                    <td><?= e($r['description'] ?? '') ?><?php if ($r['reference']): ?><div class="text-muted"><?= e(__('reference')) ?>: <?= e($r['reference']) ?></div><?php endif; ?></td>
                    <td><?= e($r['method']) ?></td>
                    <td class="text-end text-success text-nowrap"><?= $r['income'] !== null ? e(money($r['income'])) : '' ?></td>
                    <td class="text-end text-danger text-nowrap"><?= $r['expense'] !== null ? e(money($r['expense'])) : '' ?></td>
                    <td class="text-end text-nowrap d-print-none">
                        <?php if (can('accounting.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/accounting/entries/' . (int) $r['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php if (can('accounting.delete')): ?><?= View::partial('partials/delete_button', ['action' => url('admin/accounting/entries/' . (int) $r['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $r['entry_no']])]) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($table['rows']): ?>
                <tfoot class="table-light fw-semibold"><tr><td colspan="5"><?= e(__('total')) ?></td>
                    <td class="text-end text-nowrap"><?= e(money($table['totals']['income'])) ?></td><td class="text-end text-nowrap"><?= e(money($table['totals']['expense'])) ?></td><td class="d-print-none"></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
