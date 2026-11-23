<?php
/** Section wrapper: card / plain, width, colours, spacing, device visibility. Vars: $s, $style, $inner, $title */
$width = ($style['width'] ?? 'boxed') === 'full' ? 'full' : 'boxed';
$card = !isset($style['card']) || !empty($style['card']);
$show = $style['show_on'] ?? 'all';
$vis = $show === 'desktop' ? ' d-none d-lg-block' : ($show === 'mobile' ? ' d-lg-none' : '');
$css = Section::wrapStyle($style);
?>
<section class="sb-sec sb-sec-<?= e($s['type']) ?> sb-sec-<?= $width ?><?= $card ? ' sb-block' : '' ?><?= $vis ?>" style="<?= e($css) ?>" id="section-<?= (int) $s['id'] ?>">
    <?php if ($title !== ''): ?><div class="sb-sec-head"><h2<?= !empty($style['color']) ? ' style="color:inherit"' : '' ?>><?= e($title) ?></h2></div><?php endif; ?>
    <?= $inner ?>
</section>
