<?php /** Vars: $set, $t */ $center = ($set['align'] ?? 'left') === 'center'; $href = Menu::href((string) ($set['button_link'] ?? '')); ?>
<div class="<?= $center ? 'text-center' : '' ?>">
    <?php if ($t('subtitle') !== ''): ?><p class="lead mb-2"><?= nl2br(e($t('subtitle'))) ?></p><?php endif; ?>
    <?php if ($t('body') !== ''): ?><div class="sb-prose"><?= Html::clean($t('body')) ?></div><?php endif; ?>
    <?php if ($t('button_text') !== '' && $href): ?><a class="btn btn-primary mt-2" href="<?= e($href) ?>"><?= e($t('button_text')) ?></a><?php endif; ?>
</div>
