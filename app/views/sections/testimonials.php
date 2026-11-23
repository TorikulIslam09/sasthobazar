<?php /** Vars: $d (items), $t */ ?>
<div class="sb-rv-row">
    <?php foreach ($d as $it): $rating = max(0, min(5, (int) ($it['rating'] ?? 5))); $txt = $t('text', $it); if ($txt === '') { continue; } ?>
        <figure class="sb-rv-card">
            <?php if ($rating): ?><div class="sb-rv-stars"><?= str_repeat('<i class="bi bi-star-fill"></i>', $rating) . str_repeat('<i class="bi bi-star"></i>', 5 - $rating) ?></div><?php endif; ?>
            <blockquote class="mb-2">“<?= e($txt) ?>”</blockquote>
            <?php if (!empty($it['name'])): ?><figcaption class="small fw-semibold">— <?= e($it['name']) ?></figcaption><?php endif; ?>
        </figure>
    <?php endforeach; ?>
</div>
