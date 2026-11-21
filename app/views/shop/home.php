<?php
/**
 * Storefront home.
 * Vars: $banners, $categories, $flash, $flashProgress, $sections[flash|featured|offers|newest], $brands, $wished
 */
$row = static function (array $items, array $wished, array $progress = []): string {
    $html = '';
    foreach ($items as $p) {
        $html .= View::partial('shop/_card', ['p' => $p, 'wished' => isset($wished[(int) $p['id']]), 'progress' => $progress[(int) $p['id']] ?? null]);
    }
    return $html;
};
// Default slides when no banner has been uploaded yet
$defaultSlides = [
    ['title' => __('slide1_title'), 'sub' => __('slide1_sub'), 'btn' => __('shop_now'), 'link' => url('products'), 'bg' => 'linear-gradient(120deg,#0e7673,#13a39e)'],
    ['title' => __('slide2_title'), 'sub' => __('slide2_sub'), 'btn' => __('best_offers'), 'link' => url('products?sort=discount'), 'bg' => 'linear-gradient(120deg,#f0663a,#f59e0b)'],
    ['title' => __('slide3_title'), 'sub' => __('free_delivery_over', ['amount' => money(setting('free_shipping_threshold', 0))]), 'btn' => __('shop_now'), 'link' => url('products?sort=popular'), 'bg' => 'linear-gradient(120deg,#0a5a58,#1d4e89)'],
];
$hasProducts = $sections['featured'] || $sections['newest'] || $sections['flash'] || $sections['offers'];
?>

<!-- Banner slider -->
<section class="sb-hero" data-slider aria-roledescription="carousel" aria-label="<?= e(__('offers')) ?>">
    <?php if ($banners): foreach ($banners as $i => $b): ?>
        <div class="sb-slide sb-slide-img<?= $i === 0 ? ' show' : '' ?>" aria-roledescription="slide">
            <?php if ($b['link_url']): ?><a href="<?= e(Banner::href($b['link_url'])) ?>" class="stretched-link" aria-label="<?= e(Banner::title($b) ?: __('view_details')) ?>"></a><?php endif; ?>
            <picture>
                <?php if ($b['image_mobile']): ?><source media="(max-width: 767.98px)" srcset="<?= e(url($b['image_mobile'])) ?>"><?php endif; ?>
                <img src="<?= e(url($b['image'])) ?>" alt="<?= e(Banner::title($b)) ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            </picture>
            <?php if (Banner::title($b) !== '' || Banner::subtitle($b) !== ''): ?>
                <div class="sb-slide-text">
                    <?php if (Banner::title($b) !== ''): ?><h2><?= e(Banner::title($b)) ?></h2><?php endif; ?>
                    <?php if (Banner::subtitle($b) !== ''): ?><p><?= e(Banner::subtitle($b)) ?></p><?php endif; ?>
                    <?php if ($b['button_text'] && $b['link_url']): ?><span class="sb-slide-btn"><?= e($b['button_text']) ?> <i class="bi bi-arrow-right"></i></span><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; else: foreach ($defaultSlides as $i => $s): ?>
        <div class="sb-slide<?= $i === 0 ? ' show' : '' ?>" style="background: <?= e($s['bg']) ?>" aria-roledescription="slide">
            <div class="sb-slide-text">
                <h2><?= e($s['title']) ?></h2>
                <p><?= e($s['sub']) ?></p>
                <a href="<?= e($s['link']) ?>" class="sb-slide-btn"><?= e($s['btn']) ?> <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    <?php endforeach; endif; ?>
    <?php $n = $banners ? count($banners) : count($defaultSlides); if ($n > 1): ?>
        <button type="button" class="sb-hero-nav prev" data-slide-prev aria-label="<?= e(__('previous')) ?>"><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="sb-hero-nav next" data-slide-next aria-label="<?= e(__('next')) ?>"><i class="bi bi-chevron-right"></i></button>
        <div class="sb-dots">
            <?php for ($i = 0; $i < $n; $i++): ?>
                <button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-slide-to="<?= $i ?>" aria-label="<?= e(num($i + 1)) ?>"></button>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Quick links -->
<div class="sb-chips">
    <a href="<?= e(url('offers')) ?>"><i class="bi bi-gift"></i> <?= e(__('todays_offers')) ?></a>
    <a href="<?= e(url('products?sort=discount')) ?>"><i class="bi bi-fire"></i> <?= e(__('best_offers')) ?></a>
    <a href="<?= e(url('products?sort=popular')) ?>"><i class="bi bi-star"></i> <?= e(__('popular')) ?></a>
    <a href="<?= e(url('products?sort=newest')) ?>"><i class="bi bi-stars"></i> <?= e(__('new_arrivals')) ?></a>
    <a href="<?= e(url('products?in_stock=1')) ?>"><i class="bi bi-check2-circle"></i> <?= e(__('in_stock_only')) ?></a>
</div>

<!-- Order by prescription -->
<?php if (Prescription::requestsEnabled()): ?>
<a href="<?= e(url('prescription')) ?>" class="sb-rx-cta">
    <span class="sb-rx-hero-ico"><i class="bi bi-file-earmark-medical"></i></span>
    <span class="flex-grow-1 min-w-0"><b><?= e(__('rx_cta_title')) ?></b><small><?= e(__('rx_cta_text')) ?></small></span>
    <span class="btn btn-light btn-sm fw-semibold"><?= e(__('upload_prescription')) ?></span>
</a>
<?php endif; ?>

<!-- Flash sale -->
<?php if ($flash && $sections['flash']): ?>
    <section class="sb-block sb-flash">
        <div class="sb-sec-head">
            <h2><i class="bi bi-lightning-charge-fill"></i> <?= e(tr($flash['title_bn'], $flash['title_en'])) ?></h2>
            <a href="<?= e(url('offers')) ?>" class="ms-auto me-2 small"><?= e(__('view_all')) ?> <i class="bi bi-arrow-right"></i></a>
            <div class="sb-countdown" data-countdown="<?= e(date('c', strtotime($flash['ends_at']))) ?>" aria-label="<?= e(__('flash_ends_in')) ?>">
                <span class="lbl"><?= e(__('flash_ends_in')) ?></span>
                <span data-cd-days hidden><b data-cd="d">0</b><?= e(__('days_short')) ?> </span><b data-cd="h">00</b>:<b data-cd="m">00</b>:<b data-cd="s">00</b>
            </div>
        </div>
        <div class="sb-row"><?= $row($sections['flash'], $wished, $flashProgress) ?></div>
    </section>
<?php endif; ?>

<?= View::partial('shop/_banners', ['banners' => $midBanners]) ?>

<!-- Categories -->
<?php if ($categories): ?>
    <section class="sb-block">
        <div class="sb-sec-head">
            <h2><?= e(__('shop_by_category')) ?></h2>
            <a href="<?= e(url('products')) ?>"><?= e(__('view_all')) ?> <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="sb-cat-grid">
            <?php foreach ($categories as $c): ?>
                <a href="<?= e(url('category/' . rawurlencode($c['slug']))) ?>" class="sb-cat">
                    <span class="sb-cat-icon">
                        <?php if ($c['image']): ?><img src="<?= e(url($c['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi <?= e($c['icon'] ?: 'bi-grid') ?>"></i><?php endif; ?>
                    </span>
                    <span><?= e(Category::label($c)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php
$blocks = [
    'featured' => [__('featured_products'), url('products?sort=popular')],
    'offers'   => [__('best_offers'), url('products?sort=discount')],
    'newest'   => [__('new_arrivals'), url('products?sort=newest')],
];
foreach ($blocks as $key => [$heading, $more]):
    if (!$sections[$key]) {
        continue;
    } ?>
    <section class="sb-block">
        <div class="sb-sec-head">
            <h2><?= e($heading) ?></h2>
            <a href="<?= e($more) ?>"><?= e(__('view_all')) ?> <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="sb-row"><?= $row($sections[$key], $wished) ?></div>
    </section>
<?php endforeach; ?>

<?php if (!$hasProducts): ?>
    <section class="sb-block sb-empty">
        <i class="bi bi-box-seam"></i>
        <p class="mb-0"><?= e(__('shop_no_products_yet')) ?></p>
    </section>
<?php endif; ?>

<?= View::partial('shop/_banners', ['banners' => $bottomBanners]) ?>

<!-- Brands -->
<?php if ($brands): ?>
    <section class="sb-block">
        <div class="sb-sec-head"><h2><?= e(__('popular_brands')) ?></h2></div>
        <div class="sb-brand-grid">
            <?php foreach ($brands as $b): ?>
                <a href="<?= e(url('brand/' . rawurlencode($b['slug']))) ?>" class="sb-brand">
                    <?php if ($b['logo']): ?><img src="<?= e(url($b['logo'])) ?>" alt="" loading="lazy"><?php else: ?><span class="sb-brand-initial"><?= e(mb_substr(Brand::label($b), 0, 1)) ?></span><?php endif; ?>
                    <span><?= e(Brand::label($b)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- How to order + why us (from the old home page) -->
<section class="sb-block">
    <div class="sb-sec-head"><h2><?= e(__('order_in_3_steps')) ?></h2></div>
    <div class="sb-steps">
        <div><b><?= e(__('step1_title')) ?></b><p><?= e(__('step1_text')) ?></p></div>
        <div><b><?= e(__('step2_title')) ?></b><p><?= e(__('step2_text')) ?></p></div>
        <div><b><?= e(__('step3_title')) ?></b><p><?= e(__('step3_text')) ?></p></div>
    </div>
</section>
<section class="sb-block">
    <div class="sb-sec-head"><h2><?= e(__('why_us', ['name' => __('app_name')])) ?></h2></div>
    <div class="sb-why">
        <div><h3><i class="bi bi-patch-check"></i> <?= e(__('why1_title')) ?></h3><p><?= e(__('why1_text')) ?></p></div>
        <div><h3><i class="bi bi-person-badge"></i> <?= e(__('why2_title')) ?></h3><p><?= e(__('why2_text')) ?></p></div>
        <div><h3><i class="bi bi-truck"></i> <?= e(__('why3_title')) ?></h3><p><?= e(__('free_delivery_over', ['amount' => money(setting('free_shipping_threshold', 0))])) ?></p></div>
        <div><h3><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('why4_title')) ?></h3><p><?= e(__('why4_text', ['n' => num((int) setting('return_window_days', 7))])) ?></p></div>
    </div>
</section>
