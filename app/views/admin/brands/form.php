<?php
/** Add / edit brand. Vars: $brand (null on create), $input, $errors, $products (count) */
$action = $brand ? url('admin/brands/' . $brand['id']) : url('admin/brands');
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
$chk = static fn(string $k) => $v($k) === '1' ? 'checked' : '';
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url('admin/brands')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e($title) ?><?= $brand ? ': ' . e($brand['name']) : '' ?>
</h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name"><?= e(__('name_en')) ?> *</label>
                        <input class="<?= $cls('name') ?>" id="name" name="name" maxlength="150" required value="<?= e($v('name')) ?>" data-slug-source="#slug">
                        <?= $err('name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name_bn"><?= e(__('name_bn')) ?></label>
                        <input class="<?= $cls('name_bn') ?>" id="name_bn" name="name_bn" maxlength="150" value="<?= e($v('name_bn')) ?>">
                        <?= $err('name_bn') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="slug"><?= e(__('slug')) ?></label>
                        <input class="<?= $cls('slug') ?>" id="slug" name="slug" maxlength="180" value="<?= e($v('slug')) ?>" placeholder="<?= e(__('slug_auto')) ?>">
                        <?= $err('slug') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="country"><?= e(__('country')) ?></label>
                        <input class="<?= $cls('country') ?>" id="country" name="country" maxlength="80" value="<?= e($v('country')) ?>" list="countryList">
                        <datalist id="countryList">
                            <?php foreach (['Bangladesh', 'India', 'USA', 'UK', 'Germany', 'Switzerland', 'France', 'Japan', 'China', 'Malaysia', 'Thailand'] as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                        </datalist>
                        <?= $err('country') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="website"><?= e(__('website')) ?></label>
                        <input class="<?= $cls('website') ?>" type="url" id="website" name="website" maxlength="255" value="<?= e($v('website')) ?>" placeholder="https://">
                        <?= $err('website') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description"><?= e(__('description')) ?></label>
                        <textarea class="<?= $cls('description') ?>" id="description" name="description" rows="4" maxlength="5000"><?= e($v('description')) ?></textarea>
                        <?= $err('description') ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-search"></i> <?= e(__('seo')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="meta_title"><?= e(__('meta_title')) ?></label>
                        <input class="<?= $cls('meta_title') ?>" id="meta_title" name="meta_title" maxlength="255" value="<?= e($v('meta_title')) ?>" data-counter="70">
                        <?= $err('meta_title') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="meta_description"><?= e(__('meta_description')) ?></label>
                        <textarea class="<?= $cls('meta_description') ?>" id="meta_description" name="meta_description" rows="2" maxlength="500" data-counter="160"><?= e($v('meta_description')) ?></textarea>
                        <?= $err('meta_description') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= $chk('is_active') ?>>
                        <label class="form-check-label" for="is_active"><?= e(__('active')) ?></label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_manufacturer" name="is_manufacturer" value="1" <?= $chk('is_manufacturer') ?>>
                        <label class="form-check-label" for="is_manufacturer"><?= e(__('is_manufacturer')) ?></label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" <?= $chk('is_featured') ?>>
                        <label class="form-check-label" for="is_featured"><?= e(__('featured')) ?></label>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <label class="form-label" for="logo"><?= e(__('logo')) ?></label>
                    <?php if ($brand && $brand['logo']): ?>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="<?= e(url($brand['logo'])) ?>" alt="" class="rounded border bg-white" style="width:64px;height:64px;object-fit:contain">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                                <label class="form-check-label small" for="remove_logo"><?= e(__('remove_image')) ?></label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input class="<?= $cls('logo') ?>" type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp,image/gif" data-image-input data-max-mb="2" data-max-count="1">
                    <?= $err('logo') ?>
                    <div class="form-text"><?= e(__('image_rules', ['mb' => 2])) ?></div>
                    <div class="image-preview d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3 form-actions">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <?php if (!$brand): ?>
            <button class="btn btn-outline-primary" name="_after" value="new"><?= e(__('save_and_add_another')) ?></button>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/brands')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>

<?php if ($brand && can('brands.delete')): ?>
    <div class="card border-danger-subtle mt-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small"><strong class="text-danger"><?= e(__('danger_zone')) ?></strong><br>
                <span class="text-muted"><?= e($products > 0 ? __('brand_has_products', ['name' => $brand['name'], 'n' => num($products)]) : __('delete_permanent')) ?></span></div>
            <?= View::partial('partials/delete_button', [
                'action' => url('admin/brands/' . $brand['id'] . '/delete'),
                'message' => __('confirm_delete_named', ['name' => $brand['name']]),
                'blocked' => $products > 0 ? __('brand_has_products', ['name' => $brand['name'], 'n' => num($products)]) : '',
                'label' => __('delete'),
            ]) ?>
        </div>
    </div>
<?php endif; ?>
