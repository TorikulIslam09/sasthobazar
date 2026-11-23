<?php /** Vars: $d (categories) */ ?>
<div class="sb-cat-grid">
    <?php foreach ($d as $c): ?>
        <a href="<?= e(url('category/' . rawurlencode($c['slug']))) ?>" class="sb-cat">
            <span class="sb-cat-icon"><?php if ($c['image']): ?><img src="<?= e(url($c['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi <?= e($c['icon'] ?: 'bi-grid') ?>"></i><?php endif; ?></span>
            <span><?= e(Category::label($c)) ?></span>
        </a>
    <?php endforeach; ?>
</div>
