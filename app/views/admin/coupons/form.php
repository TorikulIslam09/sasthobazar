<?php
/** Coupon form. Vars: $coupon (null = new), $input, $errors, $categories, $brands, $vendors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$applies = $val('applies_to') ?: 'all';
$picked = isset($input['applies_list']) && is_array($input['applies_list'])
    ? array_map('intval', $input['applies_list'])
    : array_map('intval', array_filter(explode(',', $val('applies_ids'))));
$productRefs = $input['applies_products'] ?? ($applies === 'product' ? $val('applies_ids') : '');
$action = $coupon ? url('admin/coupons/' . (int) $coupon['id']) : url('admin/coupons');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-ticket-perforated"></i> <?= e($title) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/coupons')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
</div>
<form method="post" action="<?= e($action) ?>" class="card border-0 shadow-sm" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label" for="code"><?= e(__('coupon_code')) ?> *</label>
            <input class="<?= $cls('code') ?> text-uppercase" id="code" name="code" value="<?= e($val('code')) ?>" maxlength="50" required>
            <div class="form-text"><?= e(__('coupon_code_help')) ?></div>
            <?= $err('code') ?>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="desc"><?= e(__('description')) ?></label>
            <input class="<?= $cls('description') ?>" id="desc" name="description" value="<?= e($val('description')) ?>" maxlength="255">
            <?= $err('description') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="type"><?= e(__('type')) ?> *</label>
            <select class="<?= $cls('type', 'form-select') ?>" id="type" name="type" data-coupon-type>
                <?php foreach (Coupon::TYPES as $t): ?><option value="<?= $t ?>" <?= $val('type') === $t ? 'selected' : '' ?>><?= e(__('coupon_type_' . $t)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3" data-show-type="percent fixed">
            <label class="form-label" for="value"><?= e(__('coupon_value')) ?> *</label>
            <input class="<?= $cls('value') ?>" id="value" name="value" value="<?= e($val('value')) ?>" inputmode="decimal">
            <?= $err('value') ?>
        </div>
        <div class="col-6 col-md-3" data-show-type="percent">
            <label class="form-label" for="maxd"><?= e(__('coupon_max_discount')) ?></label>
            <input class="<?= $cls('max_discount') ?>" id="maxd" name="max_discount" value="<?= e($val('max_discount')) ?>" inputmode="decimal">
            <?= $err('max_discount') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="mino"><?= e(__('coupon_min_order')) ?></label>
            <input class="<?= $cls('min_order_amount') ?>" id="mino" name="min_order_amount" value="<?= e($val('min_order_amount')) ?>" inputmode="decimal">
            <?= $err('min_order_amount') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="ul"><?= e(__('coupon_usage_limit')) ?></label>
            <input class="<?= $cls('usage_limit') ?>" id="ul" name="usage_limit" value="<?= e($val('usage_limit')) ?>" inputmode="numeric" placeholder="∞">
            <?= $err('usage_limit') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="upu"><?= e(__('coupon_per_user')) ?></label>
            <input class="<?= $cls('usage_limit_per_user') ?>" id="upu" name="usage_limit_per_user" value="<?= e($val('usage_limit_per_user')) ?>" inputmode="numeric" placeholder="∞">
            <?= $err('usage_limit_per_user') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="sa"><?= e(__('coupon_starts')) ?></label>
            <input class="<?= $cls('starts_at') ?>" type="datetime-local" id="sa" name="starts_at" value="<?= e($val('starts_at')) ?>">
            <?= $err('starts_at') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="ea"><?= e(__('coupon_ends')) ?></label>
            <input class="<?= $cls('ends_at') ?>" type="datetime-local" id="ea" name="ends_at" value="<?= e($val('ends_at')) ?>">
            <?= $err('ends_at') ?>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="apply"><?= e(__('coupon_applies')) ?></label>
            <select class="form-select" id="apply" name="applies_to" data-coupon-applies>
                <?php foreach (Coupon::APPLIES as $a): ?><option value="<?= $a ?>" <?= $applies === $a ? 'selected' : '' ?>><?= e(__('applies_' . $a)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8">
            <div data-show-applies="category">
                <label class="form-label" for="alc"><?= e(__('categories')) ?> *</label>
                <select class="<?= $cls('applies_list', 'form-select') ?>" id="alc" name="applies_list[]" multiple size="6" <?= $applies === 'category' ? '' : 'disabled' ?>>
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $picked, true) ? 'selected' : '' ?>><?= e($c['path']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div data-show-applies="brand">
                <label class="form-label" for="alb"><?= e(__('brands')) ?> *</label>
                <select class="<?= $cls('applies_list', 'form-select') ?>" id="alb" name="applies_list[]" multiple size="6" <?= $applies === 'brand' ? '' : 'disabled' ?>>
                    <?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>" <?= in_array((int) $b['id'], $picked, true) ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div data-show-applies="vendor">
                <label class="form-label" for="alv"><?= e(__('vendors_label')) ?> *</label>
                <select class="<?= $cls('applies_list', 'form-select') ?>" id="alv" name="applies_list[]" multiple size="6" <?= $applies === 'vendor' ? '' : 'disabled' ?>>
                    <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= in_array((int) $v['id'], $picked, true) ? 'selected' : '' ?>><?= e($v['shop_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div data-show-applies="product">
                <label class="form-label" for="alp"><?= e(__('products')) ?> *</label>
                <input class="<?= $cls('applies_products') ?>" id="alp" name="applies_products" value="<?= e((string) $productRefs) ?>" placeholder="12, 15, SKU-001" <?= $applies === 'product' ? '' : 'disabled' ?>>
                <div class="form-text"><?= e(__('coupon_products_help')) ?></div>
                <?= $err('applies_products') ?>
            </div>
            <?= $err('applies_list') ?>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="vend"><?= e(__('coupon_vendor_funded')) ?></label>
            <select class="<?= $cls('vendor_id', 'form-select') ?>" id="vend" name="vendor_id">
                <option value=""><?= e(__('app_name')) ?></option>
                <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $val('vendor_id') === (string) $v['id'] ? 'selected' : '' ?>><?= e($v['shop_name']) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(__('coupon_vendor_help')) ?></div>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="cfb"><?= e(__('cost_bearer')) ?></label>
            <select class="<?= $cls('funded_by', 'form-select') ?>" id="cfb" name="funded_by">
                <?php foreach (Promo::FUNDERS as $f): ?><option value="<?= $f ?>" <?= ($val('funded_by') ?: 'platform') === $f ? 'selected' : '' ?>><?= e(__('funded_by_' . $f)) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(__('cost_bearer_help')) ?></div>
        </div>
        <div class="col-6 col-md-2" data-show-when="funded_by:shared">
            <label class="form-label" for="cvs"><?= e(__('vendor_share_pct')) ?> *</label>
            <input class="<?= $cls('vendor_share_percent') ?>" id="cvs" name="vendor_share_percent" value="<?= e($val('vendor_share_percent')) ?>" inputmode="decimal">
            <?= $err('vendor_share_percent') ?>
        </div>
        <div class="col-md-3 d-flex flex-column justify-content-center gap-1">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="first_order_only" value="1" id="fo" <?= $val('first_order_only') ? 'checked' : '' ?>><label class="form-check-label" for="fo"><?= e(__('first_order_only')) ?></label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" <?= $val('is_active') ? 'checked' : '' ?>><label class="form-check-label" for="ia"><?= e(__('active')) ?></label></div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/coupons')) ?>"><?= e(__('cancel')) ?></a>
        <?php if ($coupon): ?><span class="ms-auto small text-muted align-self-center"><?= e(__('coupon_used_n', ['n' => num($coupon['used_count'])])) ?></span><?php endif; ?>
    </div>
</form>
