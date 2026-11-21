<?php
/** Commission rates. Vars: $global, $categories, $vendors, $products, $earned */
$edit = can('commissions.edit');
$rateInput = static fn(string $name, $value, string $ph) => '<input class="form-control form-control-sm text-end" style="max-width:110px" name="' . e($name) . '" value="' . e($value ?? '') . '" placeholder="' . e($ph) . '" inputmode="decimal"' . ($edit ? '' : ' disabled') . '>';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="bi bi-percent"></i> <?= e(__('commissions')) ?></h1>
    <span class="small text-muted"><?= e(__('commission_earned_total', ['amount' => money($earned['total'])])) ?></span>
</div>
<div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('commission_priority')) ?> <?= e(__('commission_when')) ?></div>

<div class="row g-3">
    <div class="col-lg-4">
        <form method="post" action="<?= e(url('admin/commissions')) ?>" class="card border-0 shadow-sm mb-3" id="global">
            <?= csrf_field() ?><input type="hidden" name="section" value="global">
            <div class="card-header bg-white fw-semibold">5. <?= e(__('cscope_global')) ?></div>
            <div class="card-body d-flex gap-2 align-items-center">
                <input class="form-control" name="global" value="<?= e(number_format($global, 2, '.', '')) ?>" inputmode="decimal" aria-label="<?= e(__('cscope_global')) ?>" <?= $edit ? '' : 'disabled' ?>> <span>%</span>
                <?php if ($edit): ?><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button><?php endif; ?>
            </div>
        </form>

        <div class="card border-0 shadow-sm mb-3" id="vendorTypes">
            <div class="card-header bg-white fw-semibold">4. <?= e(__('cscope_vendor_type')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php foreach (VendorType::active() as $t): ?>
                    <li class="list-group-item d-flex justify-content-between"><span><i class="bi <?= e($t['icon'] ?: 'bi-shop') ?>"></i> <?= e(VendorType::label($t)) ?></span>
                        <span><?= $t['commission_rate'] !== null ? e(num($t['commission_rate'])) . '%' : '<span class="text-muted">—</span>' ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php if (can('vendor_types.view')): ?><div class="card-footer bg-white"><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/vendor-types')) ?>"><i class="bi bi-pencil"></i> <?= e(__('vendor_types')) ?></a></div><?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3" id="product">
            <div class="card-header bg-white fw-semibold">1. <?= e(__('cscope_product')) ?></div>
            <?php if ($edit): ?>
                <form method="post" action="<?= e(url('admin/commissions')) ?>" class="card-body border-bottom row g-2">
                    <?= csrf_field() ?><input type="hidden" name="section" value="product">
                    <div class="col-7"><input class="form-control form-control-sm" name="product" placeholder="<?= e(__('product_id_or_sku')) ?>" aria-label="<?= e(__('product')) ?>" required></div>
                    <div class="col-3"><input class="form-control form-control-sm" name="product_rate" placeholder="%" inputmode="decimal" aria-label="%"></div>
                    <div class="col-2"><button class="btn btn-brand btn-sm w-100"><i class="bi bi-check2"></i></button></div>
                    <div class="col-12 form-text"><?= e(__('product_rate_help')) ?></div>
                </form>
            <?php endif; ?>
            <ul class="list-group list-group-flush small">
                <?php if (!$products): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2"><span class="text-truncate">#<?= (int) $p['id'] ?> <?= e($p['name_en']) ?><div class="text-muted"><?= e($p['shop_name'] ?? __('app_name')) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?></div></span><b><?= e(num($p['commission_rate'])) ?>%</b></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <form method="post" action="<?= e(url('admin/commissions')) ?>" class="card border-0 shadow-sm" id="categories">
            <?= csrf_field() ?><input type="hidden" name="section" value="categories">
            <div class="card-header bg-white fw-semibold">2. <?= e(__('cscope_category')) ?></div>
            <div class="table-responsive" style="max-height: 560px">
                <table class="table table-sm align-middle mb-0 small">
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td style="padding-left: <?= 8 + (int) $c['depth'] * 16 ?>px"><?= e(Category::label($c)) ?><?= (int) $c['is_active'] ? '' : ' <span class="badge text-bg-light border">' . e(__('inactive')) . '</span>' ?></td>
                            <td class="text-end"><?= $rateInput('rate[' . (int) $c['id'] . ']', $c['commission_rate'], $c['depth'] > 0 ? __('inherit') : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($edit): ?><div class="card-footer bg-white"><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button> <span class="small text-muted"><?= e(__('category_rate_help')) ?></span></div><?php endif; ?>
        </form>
    </div>

    <div class="col-lg-4">
        <form method="post" action="<?= e(url('admin/commissions')) ?>" class="card border-0 shadow-sm" id="vendors">
            <?= csrf_field() ?><input type="hidden" name="section" value="vendors">
            <div class="card-header bg-white fw-semibold">3. <?= e(__('cscope_vendor')) ?></div>
            <div class="table-responsive" style="max-height: 560px">
                <table class="table table-sm align-middle mb-0 small">
                    <tbody>
                    <?php if (!$vendors): ?><tr><td class="text-muted"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                    <?php foreach ($vendors as $v): ?>
                        <tr>
                            <td><a href="<?= e(url('admin/vendors/' . (int) $v['id'])) ?>"><?= e($v['shop_name']) ?></a> <span class="badge text-bg-<?= VendorAccount::BADGES[$v['status']] ?>"><?= e(__('vs_' . $v['status'])) ?></span></td>
                            <td class="text-end"><?= $rateInput('rate[' . (int) $v['id'] . ']', $v['commission_rate'], '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($edit && $vendors): ?><div class="card-footer bg-white"><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button></div><?php endif; ?>
        </form>
    </div>
</div>
