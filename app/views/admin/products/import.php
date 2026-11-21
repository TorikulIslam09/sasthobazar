<?php
/** Product CSV import. Vars: $result (null | ProductCsv::import() result | ['fatal' => msg]), $options */
$opt = static fn(string $k) => !empty($options[$k]) ? 'checked' : '';
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url('admin/products')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e(__('import_products')) ?>
</h1>

<?php if ($result): ?>
    <?php if (!empty($result['fatal'])): ?>
        <div class="alert alert-danger"><i class="bi bi-x-octagon"></i> <?= e($result['fatal']) ?></div>
    <?php elseif (!empty($result['errors'])): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-octagon"></i> <strong><?= e(__('import_failed_rows', ['n' => num($result['errors_total']), 'total' => num($result['total'])])) ?></strong>
            <div class="small"><?= e(__('import_nothing_saved')) ?></div>
        </div>
    <?php elseif ($result['dry_run']): ?>
        <div class="alert alert-info">
            <i class="bi bi-clipboard-check"></i> <strong><?= e(__('import_dry_ok')) ?></strong>
            <div class="small"><?= e(__('import_would', ['c' => num($result['created']), 'u' => num($result['updated'])])) ?>
                <?php if ($result['brands_created']): ?> · <?= e(__('import_new_brands', ['list' => implode(', ', $result['brands_created'])])) ?><?php endif; ?></div>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <strong><?= e(__('import_done', ['c' => num($result['created']), 'u' => num($result['updated'])])) ?></strong>
            <?php if ($result['brands_created']): ?><div class="small"><?= e(__('import_new_brands', ['list' => implode(', ', $result['brands_created'])])) ?></div><?php endif; ?>
            <div class="mt-1"><a href="<?= e(url('admin/products?sort=updated')) ?>" class="alert-link"><?= e(__('view_products')) ?> →</a></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($result['warnings'])): ?>
        <div class="alert alert-warning small"><ul class="mb-0"><?php foreach (array_unique($result['warnings']) as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if (!empty($result['errors'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold text-danger"><?= e(__('import_errors')) ?></div>
            <div class="table-responsive" style="max-height: 420px">
                <table class="table table-sm table-striped mb-0 small">
                    <thead class="table-light sticky-top"><tr><th style="width:80px"><?= e(__('line')) ?></th><th style="width:180px"><?= e(__('column')) ?></th><th><?= e(__('problem')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($result['errors'] as $er): ?>
                        <tr><td><?= e(num($er['line'])) ?></td><td class="font-monospace"><?= e($er['field']) ?></td><td><?= e($er['message']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($result['errors_total'] > count($result['errors'])): ?>
                <div class="card-footer bg-white small text-muted"><?= e(__('import_more_errors', ['n' => num($result['errors_total'] - count($result['errors']))])) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="<?= e(url('admin/products/import')) ?>" enctype="multipart/form-data" class="card border-0 shadow-sm" novalidate>
            <?= csrf_field() ?>
            <div class="card-body">
                <label class="form-label" for="file"><?= e(__('csv_file')) ?> *</label>
                <input class="form-control" type="file" id="file" name="file" accept=".csv,text/csv,text/plain" required data-csv-input data-max-mb="<?= ProductCsv::MAX_BYTES / 1048576 ?>">
                <div class="invalid-feedback" data-csv-error></div>
                <div class="form-text"><?= e(__('csv_rules', ['mb' => ProductCsv::MAX_BYTES / 1048576, 'n' => num(ProductCsv::MAX_ROWS)])) ?></div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="dry_run" name="dry_run" value="1" <?= $result === null ? 'checked' : $opt('dry_run') ?>>
                    <label class="form-check-label" for="dry_run"><?= e(__('import_dry_run')) ?></label>
                    <div class="form-text mt-0"><?= e(__('import_dry_run_help')) ?></div>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="create_brands" name="create_brands" value="1" <?= $opt('create_brands') ?>>
                    <label class="form-check-label" for="create_brands"><?= e(__('import_create_brands')) ?></label>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <button class="btn btn-brand"><i class="bi bi-upload"></i> <?= e(__('import_start')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/products/import/template')) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> <?= e(__('download_template')) ?></a>
            </div>
        </form>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-question-circle"></i> <?= e(__('how_import_works')) ?></div>
            <div class="card-body small">
                <ul class="ps-3 mb-3">
                    <li><?= e(__('import_help_1')) ?></li>
                    <li><?= e(__('import_help_2')) ?></li>
                    <li><?= e(__('import_help_3')) ?></li>
                    <li><?= e(__('import_help_4')) ?></li>
                    <li><?= e(__('import_help_5')) ?></li>
                </ul>
                <div class="fw-semibold mb-1"><?= e(__('columns')) ?></div>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach (ProductCsv::COLUMNS as $c): ?>
                        <code class="badge bg-light text-dark border fw-normal <?= in_array($c, ['name_en', 'price', 'category'], true) ? 'border-danger' : '' ?>"><?= e($c) ?></code>
                    <?php endforeach; ?>
                </div>
                <div class="form-text"><?= e(__('import_required_cols')) ?></div>
            </div>
        </div>
    </div>
</div>
