<?php /** Vars: $set, $t */ $right = ($set['image_side'] ?? 'left') === 'right'; $href = Menu::href((string) ($set['button_link'] ?? '')); ?>
<div class="row g-4 align-items-center<?= $right ? ' flex-md-row-reverse' : '' ?>">
    <?php if (!empty($set['image'])): ?><div class="col-md-5"><img src="<?= e(url($set['image'])) ?>" alt="" class="img-fluid rounded-3 w-100" loading="lazy"></div><?php endif; ?>
    <div class="<?= !empty($set['image']) ? 'col-md-7' : 'col-12' ?>">
        <?php if ($t('body') !== ''): ?><div class="sb-prose"><?= Html::clean($t('body')) ?></div><?php endif; ?>
        <?php if ($t('button_text') !== '' && $href): ?><a class="btn btn-primary mt-2" href="<?= e($href) ?>"><?= e($t('button_text')) ?></a><?php endif; ?>
    </div>
</div>
