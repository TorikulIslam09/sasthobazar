<?php
/** A row of banners (home_middle / home_bottom / category_top / sidebar). Vars: $banners, optional $class */
if (empty($banners)) {
    return;
}
?>
<div class="<?= e($class ?? 'sb-banner-row') ?>">
    <?php foreach ($banners as $b): $href = Banner::href($b['link_url']); $title = Banner::title($b); $sub = Banner::subtitle($b); ?>
        <<?= $href ? 'a href="' . e($href) . '"' : 'div class="sb-bn"' ?> aria-label="<?= e($title !== '' ? $title : __('offers')) ?>">
            <picture>
                <?php if ($b['image_mobile']): ?><source media="(max-width: 767.98px)" srcset="<?= e(url($b['image_mobile'])) ?>"><?php endif; ?>
                <img src="<?= e(url($b['image'])) ?>" alt="<?= e($title) ?>" loading="lazy" decoding="async">
            </picture>
            <?php if ($title !== '' || $sub !== ''): ?>
                <span class="sb-bn-cap"><?php if ($title !== ''): ?><b><?= e($title) ?></b><?php endif; ?><?= e($sub) ?></span>
            <?php endif; ?>
        </<?= $href ? 'a' : 'div' ?>>
    <?php endforeach; ?>
</div>
