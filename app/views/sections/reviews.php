<?php
/**
 * Customer reviews (smart): rating summary + cards with author photo / initial, verified badge, time ago,
 * stars, "read more", review photos, product thumbnail, seller reply. Swipe row with arrows, or grid.
 * Vars: $s, $set, $d [rows, total, avg, happy, dist]
 */
$showSummary = !isset($set['show_summary']) || !empty($set['show_summary']);
$showProduct = !isset($set['show_product']) || !empty($set['show_product']);
$grid = ($set['layout'] ?? 'scroll') === 'grid';
$rid = 'rv' . (int) $s['id'];
// short public name: "Rahim Uddin" -> "Rahim U."
$short = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [''];
    return $parts[0] . (count($parts) > 1 ? ' ' . mb_substr((string) end($parts), 0, 1) . '.' : '');
};
// stable colour per person for the initial circle
$palette = ['#0e7673', '#7c3aed', '#db2777', '#ea580c', '#2563eb', '#16a34a', '#b45309', '#0891b2'];
$stars = static function (float $n): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="bi ' . ($n >= $i ? 'bi-star-fill' : ($n >= $i - 0.5 ? 'bi-star-half' : 'bi-star')) . '"></i>';
    }
    return $out;
};
?>
<div class="sb-rv">
    <?php if ($showSummary && $d['total'] > 0): ?>
        <div class="sb-rv-summary">
            <div class="sb-rv-score">
                <b><?= e(num(number_format($d['avg'], 1))) ?></b>
                <div class="sb-rv-stars" aria-label="<?= e(__('rating_n_of_5', ['n' => num(number_format($d['avg'], 1))])) ?>"><?= $stars($d['avg']) ?></div>
                <small><?= e(__('rv_based_on', ['n' => num($d['total'])])) ?></small>
            </div>
            <div class="sb-rv-bars d-none d-sm-block" aria-hidden="true">
                <?php for ($i = 5; $i >= 1; $i--): $n = (int) ($d['dist'][$i] ?? 0); $pct = $d['total'] ? round(100 * $n / $d['total']) : 0; ?>
                    <div><span><?= e(num($i)) ?> <i class="bi bi-star-fill"></i></span><span class="sb-rv-bar"><span style="width:<?= (int) $pct ?>%"></span></span><span><?= e(num($n)) ?></span></div>
                <?php endfor; ?>
            </div>
            <div class="sb-rv-happy">
                <i class="bi bi-hand-thumbs-up-fill"></i>
                <div><b><?= e(num($d['happy'])) ?>%</b><small><?= e(__('rv_recommend')) ?></small></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="sb-rv-track-wrap">
        <?php if (!$grid): ?>
            <button type="button" class="sb-rv-nav prev" data-rv-prev="<?= $rid ?>" aria-label="<?= e(__('rv_prev')) ?>"><i class="bi bi-chevron-left"></i></button>
        <?php endif; ?>
        <div class="<?= $grid ? 'sb-rv-grid' : 'sb-rv-row' ?>" id="<?= $rid ?>">
            <?php foreach ($d['rows'] as $r):
                $name = $short((string) $r['customer']);
                $color = $palette[crc32((string) $r['customer']) % count($palette)];
                $photos = array_slice(array_values(array_filter((array) (json_decode((string) $r['images'], true) ?: []), static fn($p) => is_string($p) && str_starts_with($p, 'uploads/'))), 0, 3);
                $long = mb_strlen((string) $r['comment']) > 160; ?>
                <figure class="sb-rv-card">
                    <figcaption class="sb-rv-author">
                        <?php if (!empty($r['avatar'])): ?>
                            <img class="sb-rv-avatar" src="<?= e(url($r['avatar'])) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="sb-rv-avatar sb-rv-initial" style="background:<?= $color ?>" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
                        <?php endif; ?>
                        <span class="min-w-0">
                            <b class="d-block text-truncate"><?= e($name) ?>
                                <?php if ((int) $r['is_verified_purchase']): ?><i class="bi bi-patch-check-fill text-success" title="<?= e(__('verified_purchase')) ?>"></i><?php endif; ?></b>
                            <small class="text-muted"><?= e(Helpers::ago($r['created_at'])) ?><?php if ((int) $r['is_verified_purchase']): ?> · <?= e(__('verified_purchase')) ?><?php endif; ?></small>
                        </span>
                    </figcaption>
                    <div class="sb-rv-stars" aria-label="<?= e(__('rating_n_of_5', ['n' => num((int) $r['rating'])])) ?>"><?= $stars((float) $r['rating']) ?></div>
                    <?php if ($r['title']): ?><b class="d-block mb-1"><?= e($r['title']) ?></b><?php endif; ?>
                    <blockquote class="sb-rv-text<?= $long ? ' clamp' : '' ?>"><?= nl2br(e((string) $r['comment'])) ?></blockquote>
                    <?php if ($long): ?><button type="button" class="sb-rv-more" data-rv-more><?= e(__('rv_read_more')) ?></button><?php endif; ?>
                    <?php if ($photos): ?>
                        <div class="sb-rv-photos"><?php foreach ($photos as $ph): ?><a href="<?= e(url($ph)) ?>" target="_blank" rel="noopener"><img src="<?= e(url($ph)) ?>" alt="" loading="lazy"></a><?php endforeach; ?></div>
                    <?php endif; ?>
                    <?php if ($r['vendor_reply']): ?><div class="sb-rv-reply"><i class="bi bi-reply-fill"></i> <?= e(mb_strimwidth((string) $r['vendor_reply'], 0, 140, '…')) ?></div><?php endif; ?>
                    <?php if ($showProduct): ?>
                        <a class="sb-rv-product" href="<?= e(url('product/' . rawurlencode($r['slug']))) ?>">
                            <span class="sb-rv-pimg"><?php if ($r['product_image']): ?><img src="<?= e(url($r['product_image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-capsule"></i><?php endif; ?></span>
                            <span class="text-truncate"><?= e(tr($r['name_bn'], $r['name_en'])) ?></span>
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </a>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php if (!$grid): ?>
            <button type="button" class="sb-rv-nav next" data-rv-next="<?= $rid ?>" aria-label="<?= e(__('rv_next')) ?>"><i class="bi bi-chevron-right"></i></button>
        <?php endif; ?>
    </div>
</div>
