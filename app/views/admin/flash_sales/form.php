<?php
/** Flash sale form + items. Vars: $sale (null = new), $items, $input, $itemOld, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$iv = static fn(string $f) => (string) ($itemOld[$f] ?? '');
$action = $sale ? url('admin/flash-sales/' . (int) $sale['id']) : url('admin/flash-sales');
$canEdit = can($sale ? 'flash_sales.edit' : 'flash_sales.create');
$running = $sale && (int) $sale['is_active'] && $sale['starts_at'] <= date('Y-m-d H:i:s') && $sale['ends_at'] > date('Y-m-d H:i:s');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-lightning-charge"></i> <?= e($title) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/flash-sales')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
</div>
<?php if ($running): ?>
    <div class="alert alert-success small d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-broadcast"></i> <?= e(__('flash_sale_live')) ?></span>
        <span class="sb-countdown-mini" data-no-reload data-countdown="<?= e(date('c', strtotime($sale['ends_at']))) ?>"><?= e(__('flash_ends_in')) ?> <span data-cd-days hidden><b data-cd="d">0</b><?= e(__('days_short')) ?> </span><b data-cd="h">00</b>:<b data-cd="m">00</b>:<b data-cd="s">00</b></span>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card border-0 shadow-sm mb-4" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label" for="ten"><?= e(__('title_en')) ?> *</label>
            <input class="<?= $cls('title_en') ?>" id="ten" name="title_en" value="<?= e($val('title_en')) ?>" maxlength="200" required>
            <?= $err('title_en') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="tbn"><?= e(__('title_bn')) ?></label>
            <input class="<?= $cls('title_bn') ?>" id="tbn" name="title_bn" value="<?= e($val('title_bn')) ?>" maxlength="200" lang="bn">
            <?= $err('title_bn') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="sa"><?= e(__('coupon_starts')) ?> *</label>
            <input class="<?= $cls('starts_at') ?>" type="datetime-local" id="sa" name="starts_at" value="<?= e($val('starts_at')) ?>" required>
            <?= $err('starts_at') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="ea"><?= e(__('coupon_ends')) ?> *</label>
            <input class="<?= $cls('ends_at') ?>" type="datetime-local" id="ea" name="ends_at" value="<?= e($val('ends_at')) ?>" required>
            <?= $err('ends_at') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="fb"><?= e(__('cost_bearer')) ?> *</label>
            <select class="<?= $cls('funded_by', 'form-select') ?>" id="fb" name="funded_by">
                <?php foreach (Promo::FUNDERS as $f): ?><option value="<?= $f ?>" <?= $val('funded_by') === $f ? 'selected' : '' ?>><?= e(__('funded_by_' . $f)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3" data-show-when="funded_by:shared">
            <label class="form-label" for="vsp"><?= e(__('vendor_share_pct')) ?> *</label>
            <input class="<?= $cls('vendor_share_percent') ?>" id="vsp" name="vendor_share_percent" value="<?= e($val('vendor_share_percent')) ?>" inputmode="decimal">
            <?= $err('vendor_share_percent') ?>
        </div>
        <div class="col-12 small text-muted"><i class="bi bi-info-circle"></i> <?= e(__('flash_cost_help')) ?></div>
        <div class="col-md-6">
            <label class="form-label" for="bn"><?= e(__('offer_banner')) ?></label>
            <input class="<?= $cls('banner') ?>" type="file" id="bn" name="banner" accept="image/jpeg,image/png,image/webp,image/gif">
            <?= $err('banner') ?>
            <?php if (!empty($sale['banner'])): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <img src="<?= e(url($sale['banner'])) ?>" alt="" style="height: 48px; border-radius: 6px">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_banner" value="1" id="rb"><label class="form-check-label small" for="rb"><?= e(__('remove')) ?></label></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" <?= $val('is_active') ? 'checked' : '' ?>><label class="form-check-label" for="ia"><?= e(__('active')) ?></label></div>
        </div>
    </div>
    <?php if ($canEdit): ?>
        <div class="card-footer bg-white d-flex gap-2">
            <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
            <a class="btn btn-outline-secondary" href="<?= e(url('admin/flash-sales')) ?>"><?= e(__('cancel')) ?></a>
        </div>
    <?php endif; ?>
</form>

<?php if ($sale): $sid = (int) $sale['id']; ?>
    <div class="card border-0 shadow-sm" id="items">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span><i class="bi bi-box-seam"></i> <?= e(__('flash_items')) ?></span><span class="text-muted small"><?= e(num(count($items))) ?></span></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead class="table-light"><tr>
                    <th><?= e(__('product')) ?></th><th class="text-end"><?= e(__('regular_price')) ?></th><th style="width: 130px"><?= e(__('sale_price')) ?></th>
                    <th style="width: 110px"><?= e(__('flash_stock_cap')) ?></th><th style="width: 110px"><?= e(__('flash_per_order')) ?></th><th class="text-center"><?= e(__('sold')) ?></th><th class="text-end"></th>
                </tr></thead>
                <tbody>
                <?php if (!$items): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('flash_no_items')) ?></td></tr><?php endif; ?>
                <?php foreach ($items as $it): $regular = (float) ($it['variant_id'] ? $it['v_price'] : $it['price']); $fid = 'fi' . (int) $it['id']; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e(tr($it['name_bn'], $it['name_en'])) ?><?= $it['variant_en'] ? ' — ' . e($it['variant_en']) : '' ?></div>
                            <div class="text-muted"><?= e($it['variant_sku'] ?: ($it['sku'] ?: '#' . $it['product_id'])) ?><?= $it['shop_name'] ? ' · ' . e($it['shop_name']) : '' ?></div>
                        </td>
                        <td class="text-end"><?= e(money($regular)) ?></td>
                        <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="sale_price" value="<?= e((string) ((float) $it['sale_price'] + 0)) ?>" inputmode="decimal" aria-label="<?= e(__('sale_price')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                            <div class="text-success"><?= e(__('pct_off', ['n' => num($regular > 0 ? (int) floor(($regular - (float) $it['sale_price']) / $regular * 100) : 0)])) ?></div></td>
                        <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="quantity_limit" value="<?= e((string) ($it['quantity_limit'] ?? '')) ?>" placeholder="∞" inputmode="numeric" aria-label="<?= e(__('flash_stock_cap')) ?>" <?= $canEdit ? '' : 'disabled' ?>></td>
                        <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="per_user_limit" value="<?= e((string) ($it['per_user_limit'] ?? '')) ?>" placeholder="∞" inputmode="numeric" aria-label="<?= e(__('flash_per_order')) ?>" <?= $canEdit ? '' : 'disabled' ?>></td>
                        <td class="text-center"><?= e(num($it['sold_qty'])) ?><?php if ($it['quantity_limit'] !== null): ?><div class="progress mt-1" style="height: 4px"><div class="progress-bar bg-danger" style="width: <?= min(100, (int) round((int) $it['sold_qty'] / max(1, (int) $it['quantity_limit']) * 100)) ?>%"></div></div><?php endif; ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($canEdit): ?>
                                <form id="<?= $fid ?>" method="post" action="<?= e(url('admin/flash-sales/' . $sid . '/items/' . (int) $it['id'])) ?>" class="d-inline"><?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-primary" title="<?= e(__('save')) ?>"><i class="bi bi-check2"></i></button>
                                </form>
                                <form method="post" action="<?= e(url('admin/flash-sales/' . $sid . '/items/' . (int) $it['id'] . '/delete')) ?>" class="d-inline" data-confirm="<?= e(__('confirm_remove_item')) ?>"><?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" title="<?= e(__('remove')) ?>"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= e(url('admin/flash-sales/' . $sid . '/items')) ?>" class="card-footer bg-white row g-2 align-items-end mx-0" novalidate>
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label small" for="pref"><?= e(__('product_id_or_sku')) ?> *</label>
                    <input class="<?= $cls('product_ref', 'form-control form-control-sm') ?>" id="pref" name="product_ref" value="<?= e($iv('product_ref')) ?>" placeholder="15 / SKU-001">
                    <?= $err('product_ref') ?>
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small" for="sp"><?= e(__('sale_price')) ?> *</label>
                    <input class="<?= $cls('sale_price', 'form-control form-control-sm') ?>" id="sp" name="sale_price" value="<?= e($iv('sale_price')) ?>" inputmode="decimal">
                    <?= $err('sale_price') ?>
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small" for="ql"><?= e(__('flash_stock_cap')) ?></label>
                    <input class="<?= $cls('quantity_limit', 'form-control form-control-sm') ?>" id="ql" name="quantity_limit" value="<?= e($iv('quantity_limit')) ?>" placeholder="∞" inputmode="numeric">
                    <?= $err('quantity_limit') ?>
                </div>
                <div class="col-4 col-md-2">
                    <label class="form-label small" for="pul"><?= e(__('flash_per_order')) ?></label>
                    <input class="<?= $cls('per_user_limit', 'form-control form-control-sm') ?>" id="pul" name="per_user_limit" value="<?= e($iv('per_user_limit')) ?>" placeholder="∞" inputmode="numeric">
                    <?= $err('per_user_limit') ?>
                </div>
                <div class="col-md-2"><button class="btn btn-brand btn-sm w-100"><i class="bi bi-plus-lg"></i> <?= e(__('add')) ?></button></div>
                <div class="col-12 form-text"><?= e(__('flash_item_help')) ?></div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
