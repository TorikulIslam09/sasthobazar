<?php /** Vars: $d[embed], $t, $s */ ?>
<div class="ratio ratio-16x9 rounded-3 overflow-hidden">
    <iframe src="<?= e($d['embed']) ?>" title="<?= e(Section::title($s) ?: 'Video') ?>" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
</div>
<?php if ($t('caption') !== ''): ?><p class="small text-muted text-center mt-2 mb-0"><?= e($t('caption')) ?></p><?php endif; ?>
