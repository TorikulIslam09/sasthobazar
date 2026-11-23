<?php /** Vars: $s, $set, $t */
$href = !empty($set['link']) ? Menu::href((string) $set['link']) : null;
$h = ['auto' => '', 'sm' => '180px', 'md' => '280px', 'lg' => '420px'][$set['height'] ?? 'auto'] ?? '';
$alt = Section::title($s) ?: strip_tags($t('overlay'));
?>
<div class="sb-sec-banner"<?= $h ? ' style="height:' . $h . '"' : '' ?>>
    <?php if ($href): ?><a href="<?= e($href) ?>" class="d-block h-100"><?php endif; ?>
    <picture>
        <?php if (!empty($set['image_mobile'])): ?><source media="(max-width: 767px)" srcset="<?= e(url($set['image_mobile'])) ?>"><?php endif; ?>
        <img src="<?= e(url($set['image'])) ?>" alt="<?= e($alt) ?>" loading="lazy" class="<?= $h ? 'sb-sec-cover' : 'img-fluid w-100' ?>">
    </picture>
    <?php if ($t('overlay') !== '' || $t('button_text') !== ''): ?>
        <div class="sb-sec-overlay">
            <?php if ($t('overlay') !== ''): ?><p><?= nl2br(e($t('overlay'))) ?></p><?php endif; ?>
            <?php if ($t('button_text') !== ''): ?><span class="btn btn-light btn-sm fw-semibold"><?= e($t('button_text')) ?></span><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($href): ?></a><?php endif; ?>
</div>
