<?php /** Call to action. Vars: $set, $t */ $href = Menu::href((string) ($set['button_link'] ?? '')); ?>
<div class="sb-cta d-flex flex-wrap align-items-center gap-3">
    <?php if (!empty($set['image'])): ?><img src="<?= e(url($set['image'])) ?>" alt="" class="sb-cta-img" loading="lazy"><?php endif; ?>
    <div class="flex-grow-1"><?php if ($t('body') !== ''): ?><p class="mb-0"><?= nl2br(e($t('body'))) ?></p><?php endif; ?></div>
    <?php if ($t('button_text') !== '' && $href): ?><a class="btn btn-primary btn-lg" href="<?= e($href) ?>"><?= e($t('button_text')) ?></a><?php endif; ?>
</div>
