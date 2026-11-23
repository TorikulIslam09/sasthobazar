<?php /** Icon boxes. Vars: $set, $d (items), $t */ $cols = in_array((string) ($set['columns'] ?? '3'), ['2', '3', '4'], true) ? (int) $set['columns'] : 3; ?>
<div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-lg-<?= $cols ?>">
    <?php foreach ($d as $it): $href = !empty($it['link']) ? Menu::href((string) $it['link']) : null; ?>
        <div class="col"><div class="sb-feat h-100">
            <?php if (!empty($it['icon']) && preg_match('/^bi-[a-z0-9-]+$/', (string) $it['icon'])): ?><i class="bi <?= e($it['icon']) ?>"></i><?php endif; ?>
            <?php if ($t('title', $it) !== ''): ?><h3 class="h6 fw-bold mb-1"><?php if ($href): ?><a href="<?= e($href) ?>" class="stretched-link text-reset"><?php endif; ?><?= e($t('title', $it)) ?><?php if ($href): ?></a><?php endif; ?></h3><?php endif; ?>
            <?php if ($t('text', $it) !== ''): ?><p class="small mb-0 opacity-75"><?= nl2br(e($t('text', $it))) ?></p><?php endif; ?>
        </div></div>
    <?php endforeach; ?>
</div>
