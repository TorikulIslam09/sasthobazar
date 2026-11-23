<?php /** Vars: $d (brands) */ ?>
<div class="sb-brand-grid">
    <?php foreach ($d as $b): ?>
        <a href="<?= e(url('brand/' . rawurlencode($b['slug']))) ?>" class="sb-brand">
            <?php if ($b['logo']): ?><img src="<?= e(url($b['logo'])) ?>" alt="" loading="lazy"><?php else: ?><span class="sb-brand-initial"><?= e(mb_substr(Brand::label($b), 0, 1)) ?></span><?php endif; ?>
            <span><?= e(Brand::label($b)) ?></span>
        </a>
    <?php endforeach; ?>
</div>
