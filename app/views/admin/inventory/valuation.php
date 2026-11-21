<?php
/** Stock valuation (purchase vs sale value). Vars: $rows, $totals, $paginator, $filters, $vendors, $categories */
$t = $totals;
$profit = $t['sale_value'] - $t['cost_value'];
$marginPct = $t['sale_value'] > 0 ? $profit / $t['sale_value'] * 100 : 0;
$exportQs = http_build_query(array_filter($filters));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cash-coin"></i> <?= e(__('inv_valuation')) ?></h1>
    <?php if (can('inventory.export')): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/inventory/reports/valuation/export') . ($exportQs ? '?' . $exportQs : '')) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
    <?php endif; ?>
</div>
<?= View::partial('admin/inventory/_nav', ['active' => 'valuation']) ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="small text-muted"><?= e(__('inv_units_on_hand')) ?></div><div class="fs-5 fw-semibold"><?= e(num(number_format($t['qty']))) ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="small text-muted"><?= e(__('inv_purchase_value')) ?></div><div class="fs-5 fw-semibold"><?= e(money($t['cost_value'])) ?></div>
        <?php if ($t['qty_no_cost'] > 0): ?><div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> <?= e(__('inv_no_cost_units', ['n' => num(number_format($t['qty_no_cost']))])) ?></div><?php endif; ?>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="small text-muted"><?= e(__('inv_sale_value')) ?></div><div class="fs-5 fw-semibold"><?= e(money($t['sale_value'])) ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="small text-muted"><?= e(__('inv_potential_profit')) ?></div>
        <div class="fs-5 fw-semibold <?= $profit < 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($profit)) ?></div>
        <div class="small text-muted"><?= e(__('inv_margin')) ?> <?= e(num(round($marginPct, 1))) ?>%</div></div></div></div>
</div>

<form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-3"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('search_product')) ?>"></div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="category_id" aria-label="<?= e(__('category')) ?>">
                <option value=""><?= e(__('category')) ?>: <?= e(__('all')) ?></option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;', (int) $c['depth']) . e(Category::label($c)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="vendor" aria-label="<?= e(__('seller')) ?>">
                <option value=""><?= e(__('seller')) ?>: <?= e(__('all')) ?></option>
                <option value="inhouse" <?= $filters['vendor'] === 'inhouse' ? 'selected' : '' ?>><?= e(__('in_house')) ?></option>
                <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $filters['vendor'] === (string) $v['id'] ? 'selected' : '' ?>><?= e(Vendor::label($v)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="expired" aria-label="<?= e(__('inv_expired')) ?>">
                <option value=""><?= e(__('inv_include_expired')) ?></option>
                <option value="exclude" <?= $filters['expired'] === 'exclude' ? 'selected' : '' ?>><?= e(__('inv_exclude_expired')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="sort" aria-label="<?= e(__('sort')) ?>">
                <?php foreach (['value' => 'inv_sort_value', 'margin' => 'inv_sort_margin', 'qty' => 'inv_sort_qty', 'name' => 'sort_name'] as $k => $l): ?>
                    <option value="<?= e($k) ?>" <?= $filters['sort'] === $k ? 'selected' : '' ?>><?= e(__($l)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-1 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/inventory/reports/valuation')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('product')) ?></th>
                    <th class="text-end"><?= e(__('quantity')) ?></th>
                    <th class="text-end"><?= e(__('inv_avg_cost')) ?></th>
                    <th class="text-end"><?= e(__('inv_purchase_value')) ?></th>
                    <th class="text-end"><?= e(__('inv_sale_value')) ?></th>
                    <th class="text-end"><?= e(__('inv_potential_profit')) ?></th>
                    <th class="text-end"><?= e(__('inv_margin')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $r):
                $cv = (float) $r['cost_value']; $sv = (float) $r['sale_value']; $pr = $sv - $cv; $m = $sv > 0 ? $pr / $sv * 100 : 0; ?>
                <tr>
                    <td>
                        <a class="fw-semibold text-reset" href="<?= e(url('admin/inventory/batches?product_id=' . $r['id'])) ?>"><?= e(Product::label($r)) ?></a>
                        <div class="text-muted"><?= e($r['sku'] ?: '') ?> <?= e($r['category'] ? '· ' . $r['category'] : '') ?>
                            <?php if ((int) $r['qty_no_cost']): ?><span class="badge bg-warning-subtle text-warning-emphasis" title="<?= e(__('inv_no_cost_units', ['n' => num($r['qty_no_cost'])])) ?>"><?= e(__('inv_no_cost')) ?></span><?php endif; ?>
                            <?php if ((int) $r['qty_expired']): ?><span class="badge bg-danger-subtle text-danger"><?= e(__('inv_n_expired', ['n' => num($r['qty_expired'])])) ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td class="text-end"><?= e(num($r['qty'])) ?></td>
                    <td class="text-end"><?= e(money((int) $r['qty'] > 0 ? $cv / (int) $r['qty'] : 0)) ?></td>
                    <td class="text-end"><?= e(money($cv)) ?></td>
                    <td class="text-end"><?= e(money($sv)) ?></td>
                    <td class="text-end fw-semibold <?= $pr < 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($pr)) ?></td>
                    <td class="text-end <?= $m < 0 ? 'text-danger' : '' ?>"><?= e(num(round($m, 1))) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rows): ?>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td><?= e(__('inv_total_all_pages')) ?></td>
                        <td class="text-end"><?= e(num(number_format($t['qty']))) ?></td>
                        <td></td>
                        <td class="text-end"><?= e(money($t['cost_value'])) ?></td>
                        <td class="text-end"><?= e(money($t['sale_value'])) ?></td>
                        <td class="text-end"><?= e(money($profit)) ?></td>
                        <td class="text-end"><?= e(num(round($marginPct, 1))) ?>%</td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted"><?= e(__('inv_valuation_note')) ?></span>
        <?= $paginator->links() ?>
    </div>
</div>
