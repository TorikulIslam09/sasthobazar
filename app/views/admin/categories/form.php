<?php
/** Add / edit category. Vars: $category (null on create), $input, $errors, $parents (tree rows allowed as parent), $stats */
$action = $category ? url('admin/categories/' . $category['id']) : url('admin/categories');
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
$chk = static fn(string $k) => $v($k) === '1' ? 'checked' : '';
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url('admin/categories')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e($title) ?><?= $category ? ': ' . e(Category::label($category)) : '' ?>
</h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name_en"><?= e(__('name_en')) ?> *</label>
                        <input class="<?= $cls('name_en') ?>" id="name_en" name="name_en" maxlength="150" required value="<?= e($v('name_en')) ?>" data-slug-source="#slug">
                        <?= $err('name_en') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name_bn"><?= e(__('name_bn')) ?></label>
                        <input class="<?= $cls('name_bn') ?>" id="name_bn" name="name_bn" maxlength="150" value="<?= e($v('name_bn')) ?>">
                        <?= $err('name_bn') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="parent_id"><?= e(__('parent_category')) ?></label>
                        <select class="<?= $cls('parent_id', 'form-select') ?>" id="parent_id" name="parent_id">
                            <option value="">— <?= e(__('top_level')) ?> —</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= $v('parent_id') === (string) $p['id'] ? 'selected' : '' ?>>
                                    <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $p['depth']) . e(Category::label($p)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('parent_id') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="slug"><?= e(__('slug')) ?></label>
                        <input class="<?= $cls('slug') ?>" id="slug" name="slug" maxlength="180" value="<?= e($v('slug')) ?>" placeholder="<?= e(__('slug_auto')) ?>">
                        <?= $err('slug') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description"><?= e(__('description')) ?></label>
                        <textarea class="<?= $cls('description') ?>" id="description" name="description" rows="3" maxlength="5000"><?= e($v('description')) ?></textarea>
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
                        <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" <?= $chk('is_featured') ?>>
                        <label class="form-check-label" for="is_featured"><?= e(__('featured')) ?></label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="requires_prescription" name="requires_prescription" value="1" <?= $chk('requires_prescription') ?>>
                        <label class="form-check-label" for="requires_prescription"><?= e(__('rx_required')) ?></label>
                        <div class="form-text"><?= e(__('category_rx_help')) ?></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="sort_order"><?= e(__('sort_order')) ?></label>
                            <input class="<?= $cls('sort_order') ?>" type="number" id="sort_order" name="sort_order" value="<?= e($v('sort_order', '0')) ?>">
                            <?= $err('sort_order') ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="commission_rate"><?= e(__('commission_pct')) ?></label>
                            <input class="<?= $cls('commission_rate') ?>" inputmode="decimal" id="commission_rate" name="commission_rate" value="<?= e($v('commission_rate')) ?>" placeholder="<?= e(__('use_global')) ?>">
                            <?= $err('commission_rate') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <label class="form-label" for="icon"><?= e(__('icon')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi <?= e($v('icon') ?: 'bi-folder') ?>" data-icon-preview></i></span>
                        <input class="<?= $cls('icon') ?>" id="icon" name="icon" maxlength="100" value="<?= e($v('icon')) ?>" placeholder="bi-capsule">
                    </div>
                    <?= $err('icon') ?>
                    <div class="form-text"><?= e(__('icon_help')) ?></div>

                    <label class="form-label mt-3" for="image"><?= e(__('image')) ?></label>
                    <?php if ($category && $category['image']): ?>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="<?= e(url($category['image'])) ?>" alt="" class="rounded border" style="width:64px;height:64px;object-fit:cover">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                <label class="form-check-label small" for="remove_image"><?= e(__('remove_image')) ?></label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input class="<?= $cls('image') ?>" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-image-input data-max-mb="2" data-max-count="1">
                    <?= $err('image') ?>
                    <div class="form-text"><?= e(__('image_rules', ['mb' => 2])) ?></div>
                    <div class="image-preview d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>

            <?php if ($stats): ?>
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body small">
                        <div class="d-flex justify-content-between"><span class="text-muted"><?= e(__('products')) ?></span><strong><?= e(num($stats['products_count'])) ?></strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted"><?= e(__('subcategories')) ?></span><strong><?= e(num($stats['children_count'])) ?></strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted"><?= e(__('created_at')) ?></span><span><?= e(dt($category['created_at'])) ?></span></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3 form-actions">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <?php if (!$category): ?>
            <button class="btn btn-outline-primary" name="_after" value="new"><?= e(__('save_and_add_another')) ?></button>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/categories')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>

<?php if ($category && can('categories.delete')):
    $blocked = (int) $stats['children_count'] > 0 ? __('category_has_children', ['name' => $category['name_en'], 'n' => num($stats['children_count'])])
             : ((int) $stats['products_count'] > 0 ? __('category_has_products', ['name' => $category['name_en'], 'n' => num($stats['products_count'])]) : ''); ?>
    <div class="card border-danger-subtle mt-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small"><strong class="text-danger"><?= e(__('danger_zone')) ?></strong><br><span class="text-muted"><?= e($blocked ?: __('delete_permanent')) ?></span></div>
            <?= View::partial('partials/delete_button', [
                'action' => url('admin/categories/' . $category['id'] . '/delete'),
                'message' => __('confirm_delete_named', ['name' => Category::label($category)]),
                'blocked' => $blocked, 'label' => __('delete'),
            ]) ?>
        </div>
    </div>
<?php endif; ?>
