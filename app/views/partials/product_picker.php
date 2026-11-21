<?php
/**
 * Product search picker (AJAX, see app.js [data-product-picker]).
 * Vars: $name (hidden product_id field name), $variantName (variant select name),
 *       $product (selected product row with 'variants', optional), $variantId (selected, optional),
 *       $error / $variantError (messages, optional), $id (input id, optional), $required (bool)
 * Fires a "product:selected" event on the wrapper with the chosen product (incl. variants, cost_price).
 */
$product = $product ?? null;
$variantId = (string) ($variantId ?? '');
$variants = $product['variants'] ?? [];
$label = $product ? trim($product['name_en'] . ' ' . (($product['strength'] ?? '') && !str_contains($product['name_en'], (string) $product['strength']) ? $product['strength'] : '')) : '';
?>
<div class="product-picker position-relative" data-product-picker data-lookup="<?= e(url('admin/inventory/lookup/products')) ?>">
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($product['id'] ?? '') ?>" data-picker-id>
    <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input class="form-control <?= !empty($error) ? 'is-invalid' : '' ?>" type="search" autocomplete="off" <?= isset($id) ? 'id="' . e($id) . '"' : '' ?>
               value="<?= e($label) ?>" placeholder="<?= e(__('inv_search_product')) ?>" data-picker-input <?= !empty($required) ? 'required' : '' ?>
               aria-label="<?= e(__('product')) ?>">
    </div>
    <?php if (!empty($error)): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
    <div class="list-group position-absolute w-100 shadow-sm d-none picker-results" data-picker-results role="listbox"></div>
    <div class="small text-muted mt-1" data-picker-meta><?php if ($product): ?>SKU: <?= e($product['sku'] ?? '—') ?> · <?= e(__('stock')) ?>: <?= e(num($product['stock_qty'] ?? 0)) ?><?php endif; ?></div>
    <select class="form-select form-select-sm mt-1 <?= $variants ? '' : 'd-none' ?> <?= !empty($variantError) ? 'is-invalid' : '' ?>" name="<?= e($variantName) ?>" data-picker-variant aria-label="<?= e(__('variant')) ?>">
        <option value=""><?= e(__('inv_choose_variant')) ?></option>
        <?php foreach ($variants as $v): ?>
            <option value="<?= (int) $v['id'] ?>" <?= $variantId === (string) $v['id'] ? 'selected' : '' ?>><?= e($v['name_en'] . ($v['sku'] ? ' · ' . $v['sku'] : '')) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!empty($variantError)): ?><div class="invalid-feedback d-block"><?= e($variantError) ?></div><?php endif; ?>
</div>
