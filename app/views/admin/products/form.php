<?php
/**
 * Add / edit product.
 * Vars: $product (null on create), $input, $variants (form rows), $errors, $images (existing),
 *       $categories (tree), $brands, $vendors, $statuses (allowed for this user)
 */
$action = $product ? url('admin/products/' . $product['id']) : url('admin/products');
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k, string $base = 'form-control') => $base . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
$chk = static fn(string $k) => $v($k) === '1' ? 'checked' : '';
$verr = static fn(int $i, string $f) => $errors['variants.' . $i . '.' . $f] ?? null;
$vcls = static fn(int $i, string $f, string $base = 'form-control form-control-sm') => $base . (isset($errors['variants.' . $i . '.' . $f]) ? ' is-invalid' : '');
$maxMb = (int) config('uploads.max_size_mb', 5);
$postMax = (static function (): int {
    $v = trim((string) ini_get('post_max_size'));
    $n = (int) $v;
    return match (strtoupper(substr($v, -1))) { 'G' => $n * 1024, 'M' => $n, 'K' => max(1, intdiv($n, 1024)), default => max(1, intdiv($n, 1048576)) };
})();
$remainingSlots = Product::MAX_IMAGES - count($images);
$selectedPrimary = Helpers::str('primary_image');
$fieldLabels = [
    'name_en' => __('product_name_en'), 'name_bn' => __('product_name_bn'), 'generic_name' => __('generic_name'),
    'strength' => __('strength'), 'unit' => __('unit'), 'dosage_form' => __('dosage_form'), 'pack_size' => __('pack_size'),
    'sku' => 'SKU', 'barcode' => __('barcode'), 'short_description' => __('short_description'), 'description' => __('description'),
    'usage_instructions' => __('usage_instructions'), 'indications' => __('indications'), 'side_effects' => __('side_effects'),
    'price' => __('price_mrp'), 'sale_price' => __('sale_price'), 'purchase_price' => __('purchase_price'), 'vat_percent' => __('vat_percent'),
    'variants' => __('variants'), 'new_images' => __('images'), 'images' => __('images'), 'slug' => __('slug'),
    'meta_title' => __('meta_title'), 'meta_description' => __('meta_description'), 'meta_keywords' => __('meta_keywords'),
    'status' => __('status'), 'rejection_reason' => __('rejection_reason'), 'product_type' => __('product_type'),
    'category_id' => __('category'), 'brand_id' => __('brand_company'), 'vendor_id' => __('seller'),
    'min_order_qty' => __('min_order_qty'), 'max_order_qty' => __('max_order_qty'),
    'low_stock_threshold' => __('low_stock_alert'), 'weight_grams' => __('weight_grams'),
];

/** One variant row (also used for the JS <template> with $i = '__i__'). */
$variantRow = static function ($i, array $r) use ($vcls, $verr): string {
    $n = is_int($i) ? $i : -1; // -1 = JS template row (never has errors)
    ob_start(); ?>
    <tr data-variant-row>
        <td>
            <input type="hidden" name="variants[<?= e($i) ?>][id]" value="<?= e($r['id'] ?? '') ?>">
            <input class="<?= $vcls($n, 'name_en') ?>" name="variants[<?= e($i) ?>][name_en]" value="<?= e($r['name_en'] ?? '') ?>" maxlength="150" placeholder="<?= e(__('variant_name_ph')) ?>" aria-label="<?= e(__('name_en')) ?>">
            <?php if ($m = $verr($n, 'name_en')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
            <input class="<?= $vcls($n, 'name_bn') ?> mt-1" name="variants[<?= e($i) ?>][name_bn]" value="<?= e($r['name_bn'] ?? '') ?>" maxlength="150" placeholder="<?= e(__('name_bn')) ?>" aria-label="<?= e(__('name_bn')) ?>">
        </td>
        <td>
            <input class="<?= $vcls($n, 'sku') ?> font-monospace" name="variants[<?= e($i) ?>][sku]" value="<?= e($r['sku'] ?? '') ?>" maxlength="100" placeholder="SKU" aria-label="SKU">
            <?php if ($m = $verr($n, 'sku')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
            <input class="<?= $vcls($n, 'barcode') ?> mt-1 font-monospace" name="variants[<?= e($i) ?>][barcode]" value="<?= e($r['barcode'] ?? '') ?>" maxlength="100" placeholder="<?= e(__('barcode')) ?>" aria-label="<?= e(__('barcode')) ?>">
            <?php if ($m = $verr($n, 'barcode')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
        </td>
        <td style="width:90px">
            <input class="<?= $vcls($n, 'unit_multiplier') ?>" type="number" min="1" name="variants[<?= e($i) ?>][unit_multiplier]" value="<?= e(($r['unit_multiplier'] ?? '') !== '' ? $r['unit_multiplier'] : '1') ?>" aria-label="<?= e(__('unit_multiplier')) ?>">
            <?php if ($m = $verr($n, 'unit_multiplier')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
        </td>
        <td style="width:110px">
            <input class="<?= $vcls($n, 'price') ?>" inputmode="decimal" name="variants[<?= e($i) ?>][price]" value="<?= e($r['price'] ?? '') ?>" placeholder="0.00" aria-label="<?= e(__('price_mrp')) ?>">
            <?php if ($m = $verr($n, 'price')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
        </td>
        <td style="width:110px">
            <input class="<?= $vcls($n, 'sale_price') ?>" inputmode="decimal" name="variants[<?= e($i) ?>][sale_price]" value="<?= e($r['sale_price'] ?? '') ?>" placeholder="—" aria-label="<?= e(__('sale_price')) ?>">
            <?php if ($m = $verr($n, 'sale_price')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
        </td>
        <td style="width:110px">
            <input class="<?= $vcls($n, 'purchase_price') ?>" inputmode="decimal" name="variants[<?= e($i) ?>][purchase_price]" value="<?= e($r['purchase_price'] ?? '') ?>" placeholder="—" aria-label="<?= e(__('purchase_price')) ?>">
            <?php if ($m = $verr($n, 'purchase_price')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; ?>
        </td>
        <td class="text-center">
            <input class="form-check-input" type="radio" name="variants_default" value="<?= e($i) ?>" <?= !empty($r['is_default']) ? 'checked' : '' ?> aria-label="<?= e(__('default')) ?>">
        </td>
        <td class="text-center">
            <input class="form-check-input" type="checkbox" name="variants[<?= e($i) ?>][is_active]" value="1" <?= (!isset($r['is_active']) || $r['is_active'] === '1') ? 'checked' : '' ?> aria-label="<?= e(__('active')) ?>">
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-variant-remove title="<?= e(__('remove')) ?>"><i class="bi bi-x-lg"></i></button>
        </td>
    </tr>
    <?php return (string) ob_get_clean();
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">
        <a href="<?= e(url('admin/products')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
        <?= e($title) ?>
    </h1>
    <?php if ($product): ?>
        <div class="small text-muted">#<?= (int) $product['id'] ?> · <?= e(__('updated')) ?>: <?= e(dt($product['updated_at'] ?? $product['created_at'])) ?></div>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <div class="fw-semibold"><?= e(__('form_has_errors')) ?></div>
        <ul class="mb-0 small">
            <?php foreach ($errors as $f => $m): if (str_starts_with($f, 'variants.')) continue; ?>
                <li><?= e($fieldLabels[$f] ?? $f) ?>: <?= e($m) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($product && $product['status'] === 'rejected' && $product['rejection_reason']): ?>
    <div class="alert alert-warning"><i class="bi bi-x-octagon"></i> <strong><?= e(__('status_rejected')) ?>:</strong> <?= e($product['rejection_reason']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" id="productForm" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <!-- ================= Main column ================= -->
        <div class="col-xl-8">

            <!-- Basic info -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle"></i> <?= e(__('basic_info')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name_en"><?= e(__('product_name_en')) ?> *</label>
                        <input class="<?= $cls('name_en') ?>" id="name_en" name="name_en" maxlength="255" required value="<?= e($v('name_en')) ?>" data-slug-source="#slug" placeholder="Napa 500 mg Tablet">
                        <?= $err('name_en') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name_bn"><?= e(__('product_name_bn')) ?></label>
                        <input class="<?= $cls('name_bn') ?>" id="name_bn" name="name_bn" maxlength="255" value="<?= e($v('name_bn')) ?>">
                        <?= $err('name_bn') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="generic_name"><?= e(__('generic_name')) ?> <span class="text-danger small" data-medicine-only>*</span></label>
                        <input class="<?= $cls('generic_name') ?>" id="generic_name" name="generic_name" maxlength="255" value="<?= e($v('generic_name')) ?>" placeholder="Paracetamol">
                        <?= $err('generic_name') ?>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="strength"><?= e(__('strength')) ?></label>
                        <input class="<?= $cls('strength') ?>" id="strength" name="strength" maxlength="100" value="<?= e($v('strength')) ?>" placeholder="500 mg">
                        <?= $err('strength') ?>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="unit"><?= e(__('unit')) ?> *</label>
                        <input class="<?= $cls('unit') ?>" id="unit" name="unit" maxlength="30" required value="<?= e($v('unit', 'pcs')) ?>" list="unitList">
                        <datalist id="unitList"><?php foreach (Product::UNITS as $u): ?><option value="<?= e($u) ?>"><?php endforeach; ?></datalist>
                        <?= $err('unit') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label" for="dosage_form"><?= e(__('dosage_form')) ?></label>
                        <input class="<?= $cls('dosage_form') ?>" id="dosage_form" name="dosage_form" maxlength="100" value="<?= e($v('dosage_form')) ?>" list="dosageList">
                        <datalist id="dosageList"><?php foreach (Product::DOSAGE_FORMS as $d): ?><option value="<?= e($d) ?>"><?php endforeach; ?></datalist>
                        <?= $err('dosage_form') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label" for="pack_size"><?= e(__('pack_size')) ?></label>
                        <input class="<?= $cls('pack_size') ?>" id="pack_size" name="pack_size" maxlength="100" value="<?= e($v('pack_size')) ?>" placeholder="10x10">
                        <?= $err('pack_size') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label" for="sku">SKU</label>
                        <input class="<?= $cls('sku') ?> font-monospace" id="sku" name="sku" maxlength="100" value="<?= e($v('sku')) ?>">
                        <?= $err('sku') ?>
                    </div>
                    <div class="col-md-4 col-6">
                        <label class="form-label" for="barcode"><?= e(__('barcode')) ?></label>
                        <input class="<?= $cls('barcode') ?> font-monospace" id="barcode" name="barcode" maxlength="100" value="<?= e($v('barcode')) ?>">
                        <?= $err('barcode') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="short_description"><?= e(__('short_description')) ?></label>
                        <input class="<?= $cls('short_description') ?>" id="short_description" name="short_description" maxlength="500" value="<?= e($v('short_description')) ?>" data-counter="500">
                        <?= $err('short_description') ?>
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white p-0">
                    <ul class="nav nav-tabs card-header-tabs m-0 px-2 pt-2" role="tablist">
                        <?php foreach (['description' => 'description', 'usage_instructions' => 'usage_instructions', 'indications' => 'indications', 'side_effects' => 'side_effects'] as $k => $label): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $k === 'description' ? 'active' : '' ?> <?= isset($errors[$k]) ? 'text-danger' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab_<?= e($k) ?>" type="button" role="tab"><?= e(__($label)) ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-body tab-content">
                    <?php foreach (['description' => 60000, 'usage_instructions' => 20000, 'indications' => 20000, 'side_effects' => 20000] as $k => $max): ?>
                        <div class="tab-pane fade <?= $k === 'description' ? 'show active' : '' ?>" id="tab_<?= e($k) ?>" role="tabpanel">
                            <textarea class="<?= $cls($k) ?>" id="<?= e($k) ?>" name="<?= e($k) ?>" rows="7" maxlength="<?= $max ?>" aria-label="<?= e(__($k)) ?>" placeholder="<?= e(__('ph_' . $k)) ?>"><?= e($v($k)) ?></textarea>
                            <?= $err($k) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pricing -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-stack"></i> <?= e(__('pricing')) ?></div>
                <div class="card-body row g-3" data-price-group>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="price"><?= e(__('price_mrp')) ?> *</label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input class="<?= $cls('price') ?>" inputmode="decimal" id="price" name="price" required value="<?= e($v('price')) ?>" placeholder="0.00" data-price="mrp">
                        </div>
                        <?= $err('price') ?>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="sale_price"><?= e(__('sale_price')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input class="<?= $cls('sale_price') ?>" inputmode="decimal" id="sale_price" name="sale_price" value="<?= e($v('sale_price')) ?>" placeholder="—" data-price="sale">
                        </div>
                        <?= $err('sale_price') ?>
                        <div class="form-text" data-price-discount></div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="purchase_price"><?= e(__('purchase_price')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input class="<?= $cls('purchase_price') ?>" inputmode="decimal" id="purchase_price" name="purchase_price" value="<?= e($v('purchase_price')) ?>" placeholder="—" data-price="cost">
                        </div>
                        <?= $err('purchase_price') ?>
                        <div class="form-text" data-price-margin></div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label" for="vat_percent"><?= e(__('vat_percent')) ?></label>
                        <div class="input-group">
                            <input class="<?= $cls('vat_percent') ?>" inputmode="decimal" id="vat_percent" name="vat_percent" value="<?= e($v('vat_percent', '0')) ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <?= $err('vat_percent') ?>
                    </div>
                    <div class="col-12 form-text mt-0"><?= e(__('pricing_help')) ?></div>
                </div>
            </div>

            <!-- Variants -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-boxes"></i> <?= e(__('variants')) ?> <span class="badge bg-light text-dark border" data-variant-count><?= e(num(count($variants))) ?></span></span>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-variant-add data-max="<?= Product::MAX_VARIANTS ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_variant')) ?></button>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2"><?= e(__('variants_help')) ?></p>
                    <?php if (isset($errors['variants'])): ?><div class="alert alert-danger py-2 small"><?= e($errors['variants']) ?></div><?php endif; ?>
                    <div class="table-responsive <?= $variants ? '' : 'd-none' ?>" data-variant-table>
                        <table class="table table-sm align-top mb-0 variant-table">
                            <thead class="table-light small">
                                <tr>
                                    <th style="min-width:170px"><?= e(__('name')) ?> *</th>
                                    <th style="min-width:130px">SKU / <?= e(__('barcode')) ?></th>
                                    <th title="<?= e(__('unit_multiplier_help')) ?>"><?= e(__('unit_multiplier')) ?> <i class="bi bi-question-circle"></i></th>
                                    <th><?= e(__('price_mrp')) ?> *</th>
                                    <th><?= e(__('sale_price')) ?></th>
                                    <th><?= e(__('purchase_price')) ?></th>
                                    <th class="text-center"><?= e(__('default')) ?></th>
                                    <th class="text-center"><?= e(__('active')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-variant-body>
                                <?php foreach (array_values($variants) as $i => $row): ?>
                                    <?= $variantRow($i, $row) ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <template id="variantTemplate"><?= $variantRow('__i__', ['is_active' => '1']) ?></template>
                </div>
            </div>

            <!-- Images -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-images"></i> <?= e(__('images')) ?>
                    <span class="badge bg-light text-dark border"><?= e(num(count($images))) ?>/<?= e(num(Product::MAX_IMAGES)) ?></span></div>
                <div class="card-body">
                    <?php if ($images): ?>
                        <div class="row g-2 mb-3">
                            <?php foreach ($images as $img):
                                $st = $input['_images'][(int) $img['id']] ?? null;
                                $isPrimary = $selectedPrimary !== '' ? $selectedPrimary === 'e' . $img['id'] : (int) $img['is_primary'] === 1; ?>
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="border rounded p-2 h-100 image-card <?= ($st['delete'] ?? false) ? 'opacity-50' : '' ?>">
                                        <a href="<?= e(url($img['path'])) ?>" target="_blank" rel="noopener">
                                            <img src="<?= e(url($img['path'])) ?>" alt="<?= e($img['alt']) ?>" class="w-100 rounded bg-light" style="aspect-ratio:1;object-fit:contain">
                                        </a>
                                        <div class="form-check mt-2 small">
                                            <input class="form-check-input" type="radio" name="primary_image" id="pi<?= (int) $img['id'] ?>" value="e<?= (int) $img['id'] ?>" <?= $isPrimary ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pi<?= (int) $img['id'] ?>"><?= e(__('primary_image')) ?></label>
                                        </div>
                                        <input class="form-control form-control-sm mt-1" name="images[<?= (int) $img['id'] ?>][alt]" maxlength="255" value="<?= e($st['alt'] ?? $img['alt']) ?>" placeholder="<?= e(__('alt_text')) ?>" aria-label="<?= e(__('alt_text')) ?>">
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <input class="form-control form-control-sm" type="number" min="0" style="width:70px" name="images[<?= (int) $img['id'] ?>][sort]" value="<?= e($st['sort'] ?? $img['sort_order']) ?>" title="<?= e(__('sort_order')) ?>" aria-label="<?= e(__('sort_order')) ?>">
                                            <div class="form-check small mb-0">
                                                <input class="form-check-input" type="checkbox" id="del<?= (int) $img['id'] ?>" name="images[<?= (int) $img['id'] ?>][delete]" value="1" <?= ($st['delete'] ?? false) ? 'checked' : '' ?> data-image-delete>
                                                <label class="form-check-label text-danger" for="del<?= (int) $img['id'] ?>"><?= e(__('delete')) ?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <label class="form-label" for="new_images"><?= e(__('upload_images')) ?></label>
                    <input class="<?= $cls('new_images') ?>" type="file" id="new_images" name="new_images[]" multiple
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           data-image-input data-max-mb="<?= $maxMb ?>" data-max-count="<?= max(0, $remainingSlots) ?>" data-post-max-mb="<?= $postMax ?>" data-primary-radio="primary_image"
                           <?= $remainingSlots <= 0 ? 'disabled' : '' ?>>
                    <?= $err('new_images') ?>
                    <div class="form-text"><?= e(__('images_rules', ['mb' => $maxMb, 'n' => Product::MAX_IMAGES, 'px' => num(Upload::MAX_DIMENSION)])) ?></div>
                    <div class="image-preview d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>

            <!-- SEO -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-search"></i> <?= e(__('seo')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="slug"><?= e(__('slug')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text small text-muted d-none d-md-flex"><?= e(url('product/')) ?></span>
                            <input class="<?= $cls('slug') ?>" id="slug" name="slug" maxlength="250" value="<?= e($v('slug')) ?>" placeholder="<?= e(__('slug_auto')) ?>">
                        </div>
                        <?= $err('slug') ?>
                    </div>
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
                    <div class="col-12">
                        <label class="form-label" for="meta_keywords"><?= e(__('meta_keywords')) ?></label>
                        <input class="<?= $cls('meta_keywords') ?>" id="meta_keywords" name="meta_keywords" maxlength="255" value="<?= e($v('meta_keywords')) ?>" placeholder="paracetamol, fever, napa">
                        <?= $err('meta_keywords') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= Side column ================= -->
        <div class="col-xl-4">
            <div class="sticky-xl-top" style="top: 70px">
                <!-- Publish -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-send"></i> <?= e(__('publish')) ?></div>
                    <div class="card-body">
                        <label class="form-label" for="status"><?= e(__('status')) ?> *</label>
                        <select class="<?= $cls('status', 'form-select') ?>" id="status" name="status" data-status-select>
                            <?php foreach (Product::STATUSES as $s): if (!in_array($s, $statuses, true)) continue; ?>
                                <option value="<?= e($s) ?>" <?= $v('status', 'draft') === $s ? 'selected' : '' ?>><?= e(__('status_' . $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('status') ?>
                        <?php if (!can('products.approve')): ?><div class="form-text"><?= e(__('status_approve_help')) ?></div><?php endif; ?>
                        <div class="mt-2 <?= $v('status') === 'rejected' ? '' : 'd-none' ?>" data-rejection>
                            <label class="form-label" for="rejection_reason"><?= e(__('rejection_reason')) ?> *</label>
                            <input class="<?= $cls('rejection_reason') ?>" id="rejection_reason" name="rejection_reason" maxlength="255" value="<?= e($v('rejection_reason')) ?>">
                            <?= $err('rejection_reason') ?>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" <?= $chk('is_featured') ?>>
                            <label class="form-check-label" for="is_featured"><?= e(__('featured')) ?></label>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex flex-wrap gap-2">
                        <button class="btn btn-brand flex-fill"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
                        <?php if (!$product): ?>
                            <button class="btn btn-outline-primary" name="_after" value="new" title="<?= e(__('save_and_add_another')) ?>"><i class="bi bi-plus-lg"></i></button>
                        <?php endif; ?>
                        <a class="btn btn-outline-secondary" href="<?= e(url('admin/products')) ?>"><?= e(__('cancel')) ?></a>
                    </div>
                </div>

                <!-- Organization -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3"></i> <?= e(__('organization')) ?></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="product_type"><?= e(__('product_type')) ?> *</label>
                            <select class="<?= $cls('product_type', 'form-select') ?>" id="product_type" name="product_type" data-product-type>
                                <?php foreach (Product::TYPES as $t): ?>
                                    <option value="<?= e($t) ?>" <?= $v('product_type') === $t ? 'selected' : '' ?>><?= e(__('type_' . $t)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('product_type') ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="category_id"><?= e(__('category')) ?> *</label>
                            <select class="<?= $cls('category_id', 'form-select') ?>" id="category_id" name="category_id" required data-rx-source="#requires_prescription">
                                <option value=""><?= e(__('select')) ?></option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" data-rx="<?= (int) $c['requires_prescription'] ?>" <?= $v('category_id') === (string) $c['id'] ? 'selected' : '' ?> <?= (int) $c['is_active'] ? '' : 'class="text-muted"' ?>>
                                        <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $c['depth']) . e(Category::label($c)) ?><?= (int) $c['is_active'] ? '' : ' (' . e(__('inactive')) . ')' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('category_id') ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="brand_id"><?= e(__('brand_company')) ?></label>
                            <select class="<?= $cls('brand_id', 'form-select') ?>" id="brand_id" name="brand_id">
                                <option value="">—</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>" <?= $v('brand_id') === (string) $b['id'] ? 'selected' : '' ?>><?= e(Brand::label($b)) ?><?= (int) $b['is_active'] ? '' : ' (' . e(__('inactive')) . ')' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('brand_id') ?>
                            <?php if (can('brands.create')): ?>
                                <div class="form-text"><a href="<?= e(url('admin/brands/create')) ?>" target="_blank" rel="noopener"><i class="bi bi-plus"></i> <?= e(__('add_brand')) ?></a></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="vendor_id"><?= e(__('seller')) ?></label>
                            <select class="<?= $cls('vendor_id', 'form-select') ?>" id="vendor_id" name="vendor_id">
                                <option value=""><?= e(__('in_house')) ?></option>
                                <?php foreach ($vendors as $vd): ?>
                                    <option value="<?= (int) $vd['id'] ?>" <?= $v('vendor_id') === (string) $vd['id'] ? 'selected' : '' ?>><?= e(Vendor::label($vd)) ?><?= $vd['status'] !== 'approved' ? ' (' . e($vd['status']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('vendor_id') ?>
                        </div>
                    </div>
                </div>

                <!-- Rx & inventory rules -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders"></i> <?= e(__('rules')) ?></div>
                    <div class="card-body">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="requires_prescription" name="requires_prescription" value="1" <?= $chk('requires_prescription') ?>>
                            <label class="form-check-label fw-semibold" for="requires_prescription"><?= e(__('rx_required')) ?></label>
                        </div>
                        <div class="form-text mb-3 d-none text-warning-emphasis" data-rx-hint><i class="bi bi-exclamation-triangle"></i> <?= e(__('rx_category_hint')) ?></div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="track_stock" name="track_stock" value="1" <?= $chk('track_stock') ?>>
                            <label class="form-check-label" for="track_stock"><?= e(__('track_stock')) ?></label>
                            <?php if ($product): ?><div class="form-text"><?= e(__('current_stock')) ?>: <strong><?= e(num($product['stock_qty'])) ?></strong></div><?php endif; ?>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small" for="min_order_qty"><?= e(__('min_order_qty')) ?> *</label>
                                <input class="<?= $cls('min_order_qty') ?> form-control-sm" type="number" min="1" id="min_order_qty" name="min_order_qty" value="<?= e($v('min_order_qty', '1')) ?>">
                                <?= $err('min_order_qty') ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="max_order_qty"><?= e(__('max_order_qty')) ?></label>
                                <input class="<?= $cls('max_order_qty') ?> form-control-sm" type="number" min="1" id="max_order_qty" name="max_order_qty" value="<?= e($v('max_order_qty')) ?>" placeholder="∞">
                                <?= $err('max_order_qty') ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="low_stock_threshold"><?= e(__('low_stock_alert')) ?></label>
                                <input class="<?= $cls('low_stock_threshold') ?> form-control-sm" type="number" min="0" id="low_stock_threshold" name="low_stock_threshold" value="<?= e($v('low_stock_threshold', '10')) ?>">
                                <?= $err('low_stock_threshold') ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="weight_grams"><?= e(__('weight_grams')) ?></label>
                                <input class="<?= $cls('weight_grams') ?> form-control-sm" type="number" min="0" id="weight_grams" name="weight_grams" value="<?= e($v('weight_grams', '0')) ?>">
                                <?= $err('weight_grams') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($product && can('products.delete')): ?>
                    <div class="card border-danger-subtle mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center gap-2">
                            <span class="small text-muted"><?= e(__('delete_product_help')) ?></span>
                            <button type="submit" form="deleteProductForm" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> <?= e(__('delete')) ?></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?php if ($product && can('products.delete')): ?>
    <form method="post" id="deleteProductForm" action="<?= e(url('admin/products/' . $product['id'] . '/delete')) ?>" data-confirm="<?= e(__('confirm_delete_product', ['name' => $product['name_en']])) ?>" data-confirm-danger="1">
        <?= csrf_field() ?>
    </form>
<?php endif; ?>
