<?php
/** Vendor offer request form. Vars: $vendor, $offer (null = new), $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$kind = $val('kind') === 'bogo' ? 'bogo' : 'price';
$action = $offer ? url('vendor-panel/offers/' . (int) $offer['id']) : url('vendor-panel/offers');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gift"></i> <?= e($title) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('vendor-panel/offers')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
</div>
<?php if ($offer && $offer['status'] === 'rejected'): ?>
    <div class="alert alert-danger small"><i class="bi bi-x-circle"></i> <?= e(__('offer_was_rejected')) ?><?= $offer['review_note'] ? ': ' . e($offer['review_note']) : '' ?></div>
<?php endif; ?>
<form method="post" action="<?= e($action) ?>" class="card border-0 shadow-sm" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label" for="kind"><?= e(__('offer_type')) ?> *</label>
            <select class="<?= $cls('kind', 'form-select') ?>" id="kind" name="kind">
                <?php foreach (OfferForm::VENDOR_KINDS as $k): ?><option value="<?= $k ?>" <?= $kind === $k ? 'selected' : '' ?>><?= e(__('offer_kind_' . $k)) ?></option><?php endforeach; ?>
            </select>
            <?= $err('kind') ?>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="ten"><?= e(__('title_en')) ?> *</label>
            <input class="<?= $cls('title_en') ?>" id="ten" name="title_en" value="<?= e($val('title_en')) ?>" maxlength="200" required placeholder="<?= e(__('offer_title_ph')) ?>">
            <?= $err('title_en') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tbn"><?= e(__('title_bn')) ?></label>
            <input class="<?= $cls('title_bn') ?>" id="tbn" name="title_bn" value="<?= e($val('title_bn')) ?>" maxlength="200" lang="bn">
            <?= $err('title_bn') ?>
        </div>

        <div class="col-6 col-md-3" data-show-when="kind:price">
            <label class="form-label" for="type"><?= e(__('discount_type')) ?></label>
            <select class="form-select" id="type" name="type">
                <option value="percent" <?= $val('type') !== 'fixed' ? 'selected' : '' ?>><?= e(__('coupon_type_percent')) ?></option>
                <option value="fixed" <?= $val('type') === 'fixed' ? 'selected' : '' ?>><?= e(__('coupon_type_fixed')) ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3" data-show-when="kind:price">
            <label class="form-label" for="value"><?= e(__('coupon_value')) ?> *</label>
            <input class="<?= $cls('value') ?>" id="value" name="value" value="<?= e($val('value')) ?>" inputmode="decimal">
            <div class="form-text"><?= e(__('offer_value_help')) ?></div>
            <?= $err('value') ?>
        </div>
        <div class="col-6 col-md-3" data-show-when="kind:price">
            <label class="form-label" for="maxd"><?= e(__('coupon_max_discount')) ?></label>
            <input class="<?= $cls('max_discount') ?>" id="maxd" name="max_discount" value="<?= e($val('max_discount')) ?>" inputmode="decimal">
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

        <div class="col-md-4">
            <label class="form-label" for="apply"><?= e(__('coupon_applies')) ?></label>
            <select class="<?= $cls('applies_to', 'form-select') ?>" id="apply" name="applies_to">
                <option value="vendor" <?= $val('applies_to') !== 'product' ? 'selected' : '' ?>><?= e(__('all_shop_products')) ?></option>
                <option value="product" <?= $val('applies_to') === 'product' ? 'selected' : '' ?>><?= e(__('selected_products')) ?></option>
            </select>
        </div>
        <div class="col-md-8" data-show-when="applies_to:product">
            <label class="form-label" for="alp"><?= e(__('products')) ?> *</label>
            <input class="<?= $cls('applies_products') ?>" id="alp" name="applies_products" value="<?= e($val('applies_products')) ?>" placeholder="12, 15, SKU-001">
            <div class="form-text"><?= e(__('vendor_offer_products_help')) ?> <a href="<?= e(url('vendor-panel/products')) ?>" target="_blank"><?= e(__('my_products')) ?></a></div>
            <?= $err('applies_products') ?>
        </div>

        <div class="col-6 col-md-4">
            <label class="form-label" for="fb"><?= e(__('cost_bearer')) ?></label>
            <select class="form-select" id="fb" name="funded_by">
                <?php foreach (['vendor', 'shared', 'platform'] as $f): ?><option value="<?= $f ?>" <?= ($val('funded_by') ?: 'vendor') === $f ? 'selected' : '' ?>><?= e(__('funded_by_' . $f)) ?></option><?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(__('vendor_cost_help')) ?></div>
        </div>
        <div class="col-6 col-md-2" data-show-when="funded_by:shared">
            <label class="form-label" for="vsp"><?= e(__('vendor_share_pct')) ?> *</label>
            <input class="<?= $cls('vendor_share_percent') ?>" id="vsp" name="vendor_share_percent" value="<?= e($val('vendor_share_percent')) ?>" inputmode="decimal">
            <?= $err('vendor_share_percent') ?>
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
        <div class="col-12">
            <label class="form-label" for="desc"><?= e(__('note_for_admin')) ?></label>
            <textarea class="<?= $cls('description') ?>" id="desc" name="description" rows="2" maxlength="1000"><?= e($val('description')) ?></textarea>
            <?= $err('description') ?>
        </div>
        <input type="hidden" name="is_active" value="1">
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-brand"><i class="bi bi-send"></i> <?= e(__('send_for_approval')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('vendor-panel/offers')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
