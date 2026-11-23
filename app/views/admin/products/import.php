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
                <?php if ($result['brands_created']): ?> · <?= e(__('import_new_brands', ['list' => implode(', ', $result['brands_created'])])) ?><?php endif; ?>
                <?php if (!empty($result['images_found'])): ?> · <?= e(__('imgimp_dry_found', ['n' => num($result['images_found'])])) ?><?php endif; ?>
                <?php if (!empty($result['categories_created'])): ?><div class="mt-1"><?= e(__('import_new_categories', ['n' => num(count($result['categories_created'])), 'list' => implode(', ', array_slice($result['categories_created'], 0, 15)) . (count($result['categories_created']) > 15 ? ' …' : '')])) ?></div><?php endif; ?></div>
        </div>
    <?php else: ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <strong><?= e(__('import_done', ['c' => num($result['created']), 'u' => num($result['updated'])])) ?></strong>
            <?php if ($result['brands_created']): ?><div class="small"><?= e(__('import_new_brands', ['list' => implode(', ', $result['brands_created'])])) ?></div><?php endif; ?>
            <?php if (!empty($result['categories_created'])): ?><div class="small"><i class="bi bi-diagram-3"></i> <?= e(__('import_new_categories', ['n' => num(count($result['categories_created'])), 'list' => implode(', ', array_slice($result['categories_created'], 0, 15)) . (count($result['categories_created']) > 15 ? ' …' : '')])) ?></div><?php endif; ?>
            <?php if (!empty($result['images_found'])): ?><div class="small"><i class="bi bi-images"></i> <?= e(__('imgimp_done', ['n' => num($result['images_added']), 'total' => num($result['images_found'])])) ?><?= Watermark::enabled() ? ' · ' . e(__('imgimp_watermarked')) : '' ?></div><?php endif; ?>
            <div class="mt-1"><a href="<?= e(url('admin/products?sort=updated')) ?>" class="alert-link"><?= e(__('view_products')) ?> →</a></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($result['image_warnings'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold text-warning-emphasis"><i class="bi bi-image"></i> <?= e(__('imgimp_problems')) ?></div>
            <div class="table-responsive" style="max-height: 300px">
                <table class="table table-sm mb-0 small"><tbody>
                    <?php foreach ($result['image_warnings'] as $w): ?><tr><td style="width:80px"><?= e(num($w['line'])) ?></td><td><?= e($w['message']) ?></td></tr><?php endforeach; ?>
                </tbody></table>
            </div>
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

                <label class="form-label mt-3" for="images_zip"><?= e(__('imgimp_zip')) ?></label>
                <input class="form-control" type="file" id="images_zip" name="images_zip" accept=".zip,application/zip">
                <div class="form-text"><?= e(__('imgimp_zip_help', ['mb' => num((int) ini_get('upload_max_filesize'))])) ?></div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="replace_images" name="replace_images" value="1" <?= $opt('replace_images') ?>>
                    <label class="form-check-label" for="replace_images"><?= e(__('imgimp_replace')) ?></label>
                </div>
                <?php if (!Watermark::enabled()): ?>
                    <div class="form-text"><i class="bi bi-droplet-half"></i> <?= e(__('imgimp_wm_off')) ?> <?php if (can('settings.view')): ?><a href="<?= e(url('admin/settings/watermark')) ?>"><?= e(__('set_watermark')) ?></a><?php endif; ?></div>
                <?php else: ?>
                    <div class="form-text text-success"><i class="bi bi-droplet-half"></i> <?= e(__('imgimp_wm_on')) ?></div>
                <?php endif; ?>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="dry_run" name="dry_run" value="1" <?= $result === null ? 'checked' : $opt('dry_run') ?>>
                    <label class="form-check-label" for="dry_run"><?= e(__('import_dry_run')) ?></label>
                    <div class="form-text mt-0"><?= e(__('import_dry_run_help')) ?></div>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="create_brands" name="create_brands" value="1" <?= $opt('create_brands') ?>>
                    <label class="form-check-label" for="create_brands"><?= e(__('import_create_brands')) ?></label>
                </div>
                <?php if (can('categories.create')): ?>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="create_categories" name="create_categories" value="1" <?= $opt('create_categories') ?>>
                    <label class="form-check-label" for="create_categories"><?= e(__('import_create_categories')) ?></label>
                    <div class="form-text mt-0"><?= e(__('import_create_categories_help')) ?></div>
                </div>
                <?php endif; ?>
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
                <div class="fw-semibold mt-3 mb-1"><i class="bi bi-images"></i> <?= e(__('imgimp_how')) ?></div>
                <ul class="ps-3 mb-0">
                    <li><?= e(__('imgimp_how_1')) ?> <code>NAPA-500.jpg|NAPA-500-back.jpg</code></li>
                    <li><?= e(__('imgimp_how_2')) ?></li>
                    <li><?= e(__('imgimp_how_3')) ?></li>
                    <li><?= e(__('imgimp_how_4')) ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>
