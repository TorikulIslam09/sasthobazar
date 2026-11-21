<?php
/** Offer form. Vars: $offer (null = new), $input, $errors, $categories, $brands, $vendors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$kind = $val('kind') ?: 'price';
$applies = $val('applies_to') ?: 'all';
$picked = static function (string $list) use ($input): array {
    $v = $input[$list] ?? ($input['applies_list'] ?? []);
    return array_map('intval', is_array($v) ? $v : []);
};
$action = $offer ? url('admin/offers/' . (int) $offer['id']) : url('admin/offers');
$pending = $offer && $offer['status'] === 'pending';
$canSave = can($offer ? 'offers.edit' : 'offers.create');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gift"></i> <?= e($title) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/offers')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
</div>

<?php if ($offer && $offer['requested_by']): ?>
    <div class="alert alert-<?= $pending ? 'warning' : ($offer['status'] === 'rejected' ? 'danger' : 'info') ?> small">
        <i class="bi bi-shop"></i> <?= e(__('offer_requested_by', ['shop' => tr($offer['shop_name_bn'], $offer['shop_name']), 'name' => $offer['requested_name'] ?? '—', 'date' => dt($offer['created_at'])])) ?>
        <?php if (!$pending && $offer['reviewed_at']): ?><br><?= e(__('offer_reviewed_by', ['status' => __('offer_status_' . $offer['status']), 'name' => $offer['reviewed_name'] ?? '—', 'date' => dt($offer['reviewed_at'])])) ?><?= $offer['review_note'] ? ' — ' . e($offer['review_note']) : '' ?><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($pending && can('offers.approve')): ?>
    <form method="post" action="<?= e(url('admin/offers/' . (int) $offer['id'] . '/review')) ?>" class="card border-warning shadow-sm mb-3">
        <?= csrf_field() ?>
        <div class="card-header bg-warning-subtle fw-semibold"><i class="bi bi-clipboard-check"></i> <?= e(__('review_request')) ?></div>
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small" for="rfb"><?= e(__('cost_bearer')) ?></label>
                <select class="form-select form-select-sm" id="rfb" name="funded_by">
                    <?php foreach (Promo::FUNDERS as $f): ?><option value="<?= $f ?>" <?= $offer['funded_by'] === $f ? 'selected' : '' ?>><?= e(__('funded_by_' . $f)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="rsh"><?= e(__('vendor_share_pct')) ?></label>
                <input class="form-control form-control-sm" id="rsh" name="vendor_share_percent" value="<?= e((string) ((float) $offer['vendor_share_percent'] + 0)) ?>" inputmode="decimal">
            </div>
            <div class="col-md-4">
                <label class="form-label small" for="rnote"><?= e(__('review_note')) ?></label>
                <input class="form-control form-control-sm" id="rnote" name="review_note" maxlength="255" placeholder="<?= e(__('review_note_ph')) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-success btn-sm flex-fill" name="decision" value="approve"><i class="bi bi-check2"></i> <?= e(__('approve')) ?></button>
                <button class="btn btn-outline-danger btn-sm flex-fill" name="decision" value="reject"><i class="bi bi-x"></i> <?= e(__('reject')) ?></button>
            </div>
        </div>
    </form>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card border-0 shadow-sm" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label" for="kind"><?= e(__('offer_type')) ?> *</label>
            <select class="<?= $cls('kind', 'form-select') ?>" id="kind" name="kind">
                <?php foreach (Promo::KINDS as $k): ?><option value="<?= $k ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e(__('offer_kind_' . $k)) ?></option><?php endforeach; ?>
            </select>
            <?= $err('kind') ?>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="ten"><?= e(__('title_en')) ?> *</label>
            <input class="<?= $cls('title_en') ?>" id="ten" name="title_en" value="<?= e($val('title_en')) ?>" maxlength="200" required>
            <?= $err('title_en') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tbn"><?= e(__('title_bn')) ?></label>
            <input class="<?= $cls('title_bn') ?>" id="tbn" name="title_bn" value="<?= e($val('title_bn')) ?>" maxlength="200" lang="bn">
            <?= $err('title_bn') ?>
        </div>
        <div class="col-12">
            <label class="form-label" for="desc"><?= e(__('description')) ?></label>
            <textarea class="<?= $cls('description') ?>" id="desc" name="description" rows="2" maxlength="1000"><?= e($val('description')) ?></textarea>
            <?= $err('description') ?>
        </div>

        <!-- discount -->
        <div class="col-6 col-md-3" data-show-when="kind:price first_order">
            <label class="form-label" for="type"><?= e(__('discount_type')) ?></label>
            <select class="form-select" id="type" name="type">
                <option value="percent" <?= $val('type') !== 'fixed' ? 'selected' : '' ?>><?= e(__('coupon_type_percent')) ?></option>
                <option value="fixed" <?= $val('type') === 'fixed' ? 'selected' : '' ?>><?= e(__('coupon_type_fixed')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3" data-show-when="kind:price first_order">
            <label class="form-label" for="value"><?= e(__('coupon_value')) ?> *</label>
            <input class="<?= $cls('value') ?>" id="value" name="value" value="<?= e($val('value')) ?>" inputmode="decimal">
            <div class="form-text" data-show-when="kind:price"><?= e(__('offer_value_help')) ?></div>
            <?= $err('value') ?>
        </div>
        <div class="col-6 col-md-3" data-show-when="kind:price first_order">
            <label class="form-label" for="maxd"><?= e(__('coupon_max_discount')) ?></label>
            <input class="<?= $cls('max_discount') ?>" id="maxd" name="max_discount" value="<?= e($val('max_discount')) ?>" inputmode="decimal">
            <div class="form-text"><?= e(__('max_discount_help')) ?></div>
            <?= $err('max_discount') ?>
        </div>
        <div class="col-6 col-md-3" data-show-when="kind:bogo">
            <label class="form-label" for="buy"><?= e(__('buy_qty')) ?> *</label>
            <input class="<?= $cls('buy_qty') ?>" id="buy" name="buy_qty" value="<?= e($val('buy_qty')) ?>" inputmode="numeric">
            <?= $err('buy_qty') ?>
        </div>
        <div class="col-6 col-md-3" data-show-when="kind:bogo">
            <label class="form-label" for="get"><?= e(__('get_qty_free')) ?> *</label>
            <input class="<?= $cls('get_qty') ?>" id="get" name="get_qty" value="<?= e($val('get_qty')) ?>" inputmode="numeric">
            <?= $err('get_qty') ?>
        </div>
        <div class="col-md-6 align-self-center small text-muted" data-show-when="kind:bogo"><i class="bi bi-info-circle"></i> <?= e(__('bogo_help')) ?></div>
        <div class="col-6 col-md-3" data-show-when="kind:first_order free_shipping">
            <label class="form-label" for="mino"><?= e(__('coupon_min_order')) ?><span data-show-when="kind:free_shipping"> *</span></label>
            <input class="<?= $cls('min_order_amount') ?>" id="mino" name="min_order_amount" value="<?= e($val('min_order_amount')) ?>" inputmode="decimal">
            <div class="form-text" data-show-when="kind:free_shipping"><?= e(__('free_ship_offer_help')) ?></div>
            <?= $err('min_order_amount') ?>
        </div>
        <div class="col-md-6 align-self-center small text-muted" data-show-when="kind:first_order"><i class="bi bi-info-circle"></i> <?= e(__('first_order_offer_help')) ?></div>

        <!-- scope -->
        <div class="col-md-4">
            <label class="form-label" for="apply"><?= e(__('coupon_applies')) ?></label>
            <select class="<?= $cls('applies_to', 'form-select') ?>" id="apply" name="applies_to">
                <?php foreach (Promo::APPLIES as $a): ?><option value="<?= $a ?>" <?= $applies === $a ? 'selected' : '' ?>><?= e(__('applies_' . $a)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8">
            <div data-show-when="applies_to:category">
                <label class="form-label" for="alc"><?= e(__('categories')) ?> *</label>
                <select class="<?= $cls('applies_categorys', 'form-select') ?>" id="alc" name="applies_categorys[]" multiple size="6">
                    <?php $sel = $applies === 'category' ? $picked('applies_categorys') : []; foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $sel, true) ? 'selected' : '' ?>><?= e($c['path']) ?></option><?php endforeach; ?>
                </select>
                <div class="form-text"><?= e(__('offer_category_help')) ?></div>
                <?= $err('applies_categorys') ?>
            </div>
            <div data-show-when="applies_to:brand">
                <label class="form-label" for="alb"><?= e(__('brands')) ?> *</label>
                <select class="<?= $cls('applies_brands', 'form-select') ?>" id="alb" name="applies_brands[]" multiple size="6">
                    <?php $sel = $applies === 'brand' ? $picked('applies_brands') : []; foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>" <?= in_array((int) $b['id'], $sel, true) ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
                </select>
                <?= $err('applies_brands') ?>
            </div>
            <div data-show-when="applies_to:vendor">
                <label class="form-label" for="alv"><?= e(__('vendors_label')) ?> *</label>
                <select class="<?= $cls('applies_vendors', 'form-select') ?>" id="alv" name="applies_vendors[]" multiple size="6">
                    <?php $sel = $applies === 'vendor' ? $picked('applies_vendors') : []; foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= in_array((int) $v['id'], $sel, true) ? 'selected' : '' ?>><?= e($v['shop_name']) ?></option><?php endforeach; ?>
                </select>
                <?= $err('applies_vendors') ?>
            </div>
            <div data-show-when="applies_to:product">
                <label class="form-label" for="alp"><?= e(__('products')) ?> *</label>
                <input class="<?= $cls('applies_products') ?>" id="alp" name="applies_products" value="<?= e($val('applies_products')) ?>" placeholder="12, 15, SKU-001">
                <div class="form-text"><?= e(__('coupon_products_help')) ?></div>
                <?= $err('applies_products') ?>
            </div>
        </div>

        <!-- owner / cost -->
        <div class="col-md-4">
            <label class="form-label" for="vend"><?= e(__('offer_owner')) ?></label>
            <select class="<?= $cls('vendor_id', 'form-select') ?>" id="vend" name="vendor_id">
                <option value=""><?= e(__('offer_owner_platform')) ?></option>
                <?php foreach ($vendors as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $val('vendor_id') === (string) $v['id'] ? 'selected' : '' ?>><?= e($v['shop_name']) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(__('offer_owner_help')) ?></div>
            <?= $err('vendor_id') ?>
        </div>
        <div class="col-6 col-md-4" data-show-when="kind:price bogo first_order">
            <label class="form-label" for="fb"><?= e(__('cost_bearer')) ?> *</label>
            <select class="form-select" id="fb" name="funded_by">
                <?php foreach (Promo::FUNDERS as $f): ?><option value="<?= $f ?>" <?= ($val('funded_by') ?: 'platform') === $f ? 'selected' : '' ?>><?= e(__('funded_by_' . $f)) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(__('cost_bearer_help')) ?></div>
        </div>
        <div class="col-6 col-md-2" data-show-when="funded_by:shared">
            <label class="form-label" for="vsp"><?= e(__('vendor_share_pct')) ?> *</label>
            <input class="<?= $cls('vendor_share_percent') ?>" id="vsp" name="vendor_share_percent" value="<?= e($val('vendor_share_percent')) ?>" inputmode="decimal">
            <?= $err('vendor_share_percent') ?>
        </div>
        <div class="col-md-4 align-self-center small text-muted" data-show-when="kind:free_shipping"><i class="bi bi-info-circle"></i> <?= e(__('free_ship_platform_note')) ?></div>

        <!-- schedule -->
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
        <div class="col-6 col-md-2">
            <label class="form-label" for="pri"><?= e(__('priority')) ?></label>
            <input class="<?= $cls('priority') ?>" id="pri" name="priority" value="<?= e($val('priority')) ?>" inputmode="numeric">
            <?= $err('priority') ?>
        </div>
        <div class="col-6 col-md-4 d-flex align-items-end">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" <?= $val('is_active') ? 'checked' : '' ?>><label class="form-check-label" for="ia"><?= e(__('active')) ?></label></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="bn"><?= e(__('offer_banner')) ?></label>
            <input class="<?= $cls('banner') ?>" type="file" id="bn" name="banner" accept="image/jpeg,image/png,image/webp,image/gif">
            <?= $err('banner') ?>
            <?php if (!empty($offer['banner'])): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <img src="<?= e(url($offer['banner'])) ?>" alt="" style="height: 48px; border-radius: 6px">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_banner" value="1" id="rb"><label class="form-check-label small" for="rb"><?= e(__('remove')) ?></label></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($canSave): ?>
        <div class="card-footer bg-white d-flex gap-2">
            <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(url('admin/offers')) ?>"><?= e(__('cancel')) ?></a>
        </div>
    <?php endif; ?>
</form>
