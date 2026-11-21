<?php
/** Offers page. Vars: $flash, $flashItems, $flashProgress, $next, $offers, $offerProducts, $discounted, $banners, $wished */
$wishedIds = $wished;
$row = static function (array $items, array $progress = []) use ($wishedIds): string {
    $out = '';
    foreach ($items as $p) {
        $out .= View::partial('shop/_card', ['p' => $p, 'wished' => isset($wishedIds[(int) $p['id']]), 'progress' => $progress[(int) $p['id']] ?? null]);
    }
    return $out;
};
$icon = ['price' => 'bi-percent', 'bogo' => 'bi-gift', 'first_order' => 'bi-stars', 'free_shipping' => 'bi-truck'];
$cd = static fn(string $at, string $label) => '<span class="sb-countdown" data-countdown="' . e(date('c', strtotime($at))) . '"><span class="lbl">' . e($label) . '</span>'
    . '<span data-cd-days hidden><b data-cd="d">0</b>' . e(__('days_short')) . ' </span><b data-cd="h">00</b>:<b data-cd="m">00</b>:<b data-cd="s">00</b></span>';
?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <span><?= e(__('todays_offers')) ?></span></nav>

<?= View::partial('shop/_banners', ['banners' => $banners]) ?>

<?php if ($flash): ?>
    <section class="sb-block sb-flash">
        <div class="sb-deals-head">
            <h1><i class="bi bi-lightning-charge-fill"></i> <?= e(tr($flash['title_bn'], $flash['title_en'])) ?></h1>
            <?= $cd($flash['ends_at'], __('flash_ends_in')) ?>
        </div>
        <?php if ($flash['banner']): ?><img src="<?= e(url($flash['banner'])) ?>" alt="" class="w-100 rounded-3 mb-3" loading="lazy"><?php endif; ?>
        <?php if ($flashItems): ?>
            <div class="sb-grid"><?= $row($flashItems, $flashProgress) ?></div>
        <?php else: ?>
            <p class="text-muted mb-0"><?= e(__('flash_sold_out')) ?></p>
        <?php endif; ?>
    </section>
<?php else: ?>
    <div class="sb-deals-head"><h1><i class="bi bi-gift"></i> <?= e(__('todays_offers')) ?></h1></div>
<?php endif; ?>

<?php if ($next): ?>
    <div class="sb-promo-hint justify-content-between flex-wrap">
        <span><i class="bi bi-alarm"></i> <b><?= e(tr($next['title_bn'], $next['title_en'])) ?></b> — <?= e(__('flash_starts_on', ['date' => dt($next['starts_at'], 'd M, h:i A')])) ?></span>
        <?= $cd($next['starts_at'], __('starts_in')) ?>
    </div>
<?php endif; ?>

<?php if ($offers): ?>
    <section class="sb-block">
        <div class="sb-sec-head"><h2><?= e(__('running_offers')) ?></h2></div>
        <div class="sb-offer-list">
            <?php foreach ($offers as $o): ?>
                <div class="sb-offer-item">
                    <div class="d-flex justify-content-between gap-2">
                        <b><i class="bi <?= $icon[$o['kind']] ?>"></i> <?= e(Promo::label($o)) ?></b>
                        <?php if ($o['link']): ?><a href="<?= e($o['link']) ?>" class="small text-nowrap"><?= e(__('shop_now')) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                    </div>
                    <div class="fw-semibold mt-1"><?= e(Promo::title($o)) ?></div>
                    <?php if ($o['description']): ?><div class="small text-muted"><?= e($o['description']) ?></div><?php endif; ?>
                    <div class="small text-muted mt-1">
                        <?php if ($o['kind'] === 'first_order'): ?><?= e(__('first_order_auto')) ?><?php if ((float) $o['min_order_amount'] > 0): ?> · <?= e(__('coupon_min_short', ['amount' => money($o['min_order_amount'])])) ?><?php endif; ?><?php endif; ?>
                        <?php if ($o['ends_at']): ?><div><i class="bi bi-clock"></i> <?= e(__('offer_until', ['date' => dt($o['ends_at'], 'd M, h:i A')])) ?></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($offerProducts): ?>
    <section class="sb-block" id="offer-products">
        <div class="sb-sec-head"><h2><?= e(__('products_on_offer')) ?></h2></div>
        <div class="sb-grid"><?= $row($offerProducts) ?></div>
    </section>
<?php endif; ?>

<?php if ($discounted): ?>
    <section class="sb-block">
        <div class="sb-sec-head">
            <h2><?= e(__('best_offers')) ?></h2>
            <a href="<?= e(url('products?sort=discount')) ?>"><?= e(__('view_all')) ?> <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="sb-grid"><?= $row($discounted) ?></div>
    </section>
<?php endif; ?>

<?php if (!$flash && !$offers && !$discounted): ?>
    <section class="sb-block sb-empty"><i class="bi bi-gift"></i><p class="mb-0"><?= e(__('no_offers_now')) ?></p></section>
<?php endif; ?>
