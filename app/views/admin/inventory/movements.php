<?php
/** Stock ledger. Vars: $rows, $paginator, $filters, $vendors, $product (when filtered by product) */
$types = array_merge(Inventory::IN_TYPES, Inventory::OUT_TYPES, ['adjustment']);
$exportQs = http_build_query(array_filter($filters));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-text"></i> <?= e(__('inv_movements')) ?><?= $product ? ': ' . e(Product::label($product)) : '' ?></h1>
    <?php if (can('inventory.export')): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/inventory/movements/export') . ($exportQs ? '?' . $exportQs : '')) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
    <?php endif; ?>
</div>
<?= View::partial('admin/inventory/_nav', ['active' => 'movements']) ?>

<form class="card border-0 shadow-sm mb-3" method="get">
    <?php foreach (['product_id', 'batch_id'] as $h): if ($filters[$h]): ?><input type="hidden" name="<?= $h ?>" value="<?= (int) $filters[$h] ?>"><?php endif; endforeach; ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-3"><input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('inv_search_ledger')) ?>"></div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="type" aria-label="<?= e(__('type')) ?>">
                <option value=""><?= e(__('type')) ?>: <?= e(__('all')) ?></option>
                <?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(__('mv_' . $t)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <select class="form-select form-select-sm" name="vendor" aria-label="<?= e(__('seller')) ?>">
                <option value=""><?= e(__('seller')) ?>: <?= e(__('all')) ?></option>
                <option value="inhouse" <?= $filters['vendor'] === 'inhouse' ? 'selected' : '' ?>><?= e(__('in_house')) ?></option>
                <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $filters['vendor'] === (string) $v['id'] ? 'selected' : '' ?>><?= e(Vendor::label($v)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2"><input class="form-control form-control-sm" type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="<?= e(__('from_date')) ?>" title="<?= e(__('from_date')) ?>"></div>
        <div class="col-6 col-md-3 col-lg-2"><input class="form-control form-control-sm" type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="<?= e(__('to_date')) ?>" title="<?= e(__('to_date')) ?>"></div>
        <div class="col-12 col-lg-1 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill" title="<?= e(__('filter')) ?>"><i class="bi bi-funnel"></i></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/inventory/movements')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <?= View::partial('admin/inventory/_movement_table', ['rows' => $rows]) ?>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted"><?= e(__('showing', ['from' => num($paginator->from()), 'to' => num($paginator->to()), 'total' => num($paginator->total)])) ?></span>
        <?= $paginator->links() ?>
    </div>
</div>
