<?php
/**
 * Product card. Vars: $p (Storefront::cards row), $wished (bool),
 * optional $progress [sold, limit|null] for flash sale items.
 */
$off = Storefront::offPercent($p);
$inStock = Storefront::inStock($p);
$link = Storefront::url($p);
$name = Storefront::name($p);
$sub = Storefront::subline($p);
$brand = tr($p['brand_name_bn'] ?? null, $p['brand_name'] ?? null);
$variant = tr($p['variant_bn'] ?? null, $p['variant_en'] ?? null);
$wished = !empty($wished);
?>
<div class="sb-card" data-product="<?= (int) $p['id'] ?>">
    <?php if ($off > 0): ?>
        <span class="sb-off<?= $p['is_flash'] ? ' sb-off-flash' : '' ?>"><?php if ($p['is_flash']): ?><i class="bi bi-lightning-charge-fill"></i><?php endif; ?><?= e(__('pct_off', ['n' => num($off)])) ?></span>
    <?php endif; ?>
    <button type="button" class="sb-wish<?= $wished ? ' on' : '' ?>" data-wish="<?= (int) $p['id'] ?>"
            aria-pressed="<?= $wished ? 'true' : 'false' ?>" title="<?= e(__('wishlist')) ?>" aria-label="<?= e(__('wishlist')) ?>: <?= e($name) ?>">
        <i class="bi <?= $wished ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
    </button>
    <a href="<?= e($link) ?>" class="sb-card-img" tabindex="-1" aria-hidden="true">
        <?php if ($p['image']): ?>
            <img src="<?= e(url($p['image'])) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <i class="bi bi-capsule"></i>
        <?php endif; ?>
    </a>
    <a href="<?= e($link) ?>" class="sb-card-name"><?= e($name) ?></a>
    <div class="sb-card-sub" title="<?= e($sub) ?>"><?= e($sub !== '' ? $sub : $brand) ?></div>
    <div class="sb-card-unit">
        <?= e($variant !== '' ? $variant : trim(($p['pack_size'] ?? '') . ' ' . __('unit_' . $p['unit']))) ?>
        <?php if ((int) $p['requires_prescription']): ?><span class="sb-rx" title="<?= e(__('rx_required')) ?>">Rx</span><?php endif; ?>
    </div>
    <div class="sb-price">
        <b><?= e(money($p['eff_price'])) ?></b>
        <?php if ($off > 0): ?><s><?= e(money($p['eff_mrp'])) ?></s><?php endif; ?>
    </div>
    <?php if (!empty($p['offer_label']) || !empty($p['bogo_label'])): ?>
        <div class="sb-card-tag<?= !empty($p['bogo_label']) ? ' bogo' : '' ?>"><i class="bi <?= !empty($p['bogo_label']) ? 'bi-gift' : 'bi-tag' ?>"></i> <?= e($p['bogo_label'] ?: $p['offer_label']) ?></div>
    <?php endif; ?>
    <?php if ((int) $p['rating_count'] > 0): ?>
        <div class="sb-stars-sm"><i class="bi bi-star-fill"></i> <?= e(num(number_format((float) $p['rating_avg'], 1))) ?> <span>(<?= e(num($p['rating_count'])) ?>)</span></div>
    <?php endif; ?>
    <?php if (!empty($progress) && $progress[1]): $pct = min(100, (int) round($progress[0] / max(1, $progress[1]) * 100)); ?>
        <div class="sb-progress" title="<?= e(__('flash_sold_n', ['n' => num($progress[0])])) ?>">
            <div style="width: <?= $pct ?>%"></div>
        </div>
        <div class="sb-progress-lbl"><?= e(__('flash_sold_n', ['n' => num($progress[0])])) ?></div>
    <?php endif; ?>
    <div class="sb-card-foot">
        <?php if ($inStock && !(int) $p['has_variants']): ?>
            <button type="button" class="sb-card-btn w-100 border-0" data-add-cart="<?= (int) $p['id'] ?>"><i class="bi bi-cart-plus"></i> <?= e(__('add_to_cart')) ?></button>
        <?php elseif ($inStock): ?>
            <a href="<?= e($link) ?>" class="sb-card-btn"><?= e(__('choose_option')) ?></a>
        <?php else: ?>
            <span class="sb-card-btn is-out"><?= e(__('out_of_stock')) ?></span>
        <?php endif; ?>
    </div>
</div>
