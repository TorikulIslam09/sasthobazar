<?php
/**
 * Product detail.
 * Vars: $p, $images, $variants (each with 'pricing'), $selected, $pricing, $crumbs, $reviews, $reviewTotal,
 *       $reviewPage, $reviewPages, $breakdown, $myReview, $errors, $alternatives, $related, $wished
 */
$name = Storefront::name($p);
$link = Storefront::url($p);
$isWished = isset($wished[(int) $p['id']]);
$brand = tr($p['brand_name_bn'], $p['brand_name']);
$seller = $p['vendor_id'] ? tr($p['shop_name_bn'], $p['shop_name']) : __('app_name');
$threshold = (int) $p['low_stock_threshold'];

/** [state, text] for a stock quantity. */
$stock = static function (int $qty) use ($p, $threshold): array {
    if (!(int) $p['track_stock']) {
        return ['in', __('in_stock')];
    }
    if ($qty <= 0) {
        return ['out', __('out_of_stock')];
    }
    if ($qty <= $threshold) {
        return ['low', __('only_n_left', ['n' => num($qty)])];
    }
    return ['in', __('in_stock')];
};
[$stockState, $stockText] = $stock((int) ($selected['stock_qty'] ?? $p['stock_qty']));
$flash = $pricing['flash'];

$maxQty = (int) ($p['max_order_qty'] ?: 99);
$minQty = max(1, (int) $p['min_order_qty']);
$info = array_filter([
    'description'        => [__('description'), $p['description'] ?: $p['short_description']],
    'usage_instructions' => [__('usage_instructions'), $p['usage_instructions'] ?? null],
    'indications'        => [__('indications'), $p['indications']],
    'side_effects'       => [__('side_effects'), $p['side_effects']],
], static fn($x) => trim((string) $x[1]) !== '');

$avg = (float) $p['rating_avg'];
$stars = static function (float $r): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $r >= $i ? 'bi-star-fill' : ($r >= $i - 0.5 ? 'bi-star-half' : 'bi-star');
        $html .= '<i class="bi ' . $cls . '"></i>';
    }
    return $html;
};
$cardRow = static function (array $items) use ($wished): string {
    $html = '';
    foreach ($items as $it) {
        $html .= View::partial('shop/_card', ['p' => $it, 'wished' => isset($wished[(int) $it['id']])]);
    }
    return $html;
};
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
?>
<nav class="sb-crumbs" aria-label="breadcrumb">
    <a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a>
    <?php foreach ($crumbs as $c): ?> › <a href="<?= e(url('category/' . rawurlencode($c['slug']))) ?>"><?= e(Category::label($c)) ?></a><?php endforeach; ?>
    › <span><?= e($name) ?></span>
</nav>

<div class="sb-block">
    <div class="sb-pd">
        <!-- Gallery -->
        <div class="sb-gallery">
            <div class="sb-gallery-main">
                <span class="sb-off sb-off-lg<?= $flash ? ' sb-off-flash' : '' ?>" data-pd-offbadge <?= $pricing['off'] > 0 ? '' : 'hidden' ?>><?= $pricing['off'] > 0 ? e(__('pct_off', ['n' => num($pricing['off'])])) : '' ?></span>
                <button type="button" class="sb-wish sb-wish-lg<?= $isWished ? ' on' : '' ?>" data-wish="<?= (int) $p['id'] ?>" aria-pressed="<?= $isWished ? 'true' : 'false' ?>" aria-label="<?= e(__('wishlist')) ?>">
                    <i class="bi <?= $isWished ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                </button>
                <?php if ($images): ?>
                    <img src="<?= e(url($images[0]['path'])) ?>" alt="<?= e($images[0]['alt'] ?: $name) ?>" id="pdMainImg" fetchpriority="high">
                <?php else: ?>
                    <i class="bi bi-capsule sb-gallery-ph"></i>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="sb-thumbs" role="list">
                    <?php foreach ($images as $i => $img): ?>
                        <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-gallery-thumb="<?= e(url($img['path'])) ?>" data-alt="<?= e($img['alt'] ?: $name) ?>" aria-label="<?= e(__('image')) ?> <?= e(num($i + 1)) ?>">
                            <img src="<?= e(url($img['path'])) ?>" alt="" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Buy box -->
        <div class="sb-pd-info">
            <h1><?= e($name) ?></h1>
            <?php if ($p['generic_name']): ?>
                <p class="sb-pd-generic">
                    <a href="<?= e(url('products?q=' . rawurlencode($p['generic_name']))) ?>"><?= e($p['generic_name']) ?></a>
                    <?php if ($p['strength']): ?><span class="sb-pd-strength"><?= e($p['strength']) ?></span><?php endif; ?>
                </p>
            <?php endif; ?>
            <p class="sb-pd-meta">
                <?php if ($p['dosage_form']): ?><span><i class="bi bi-capsule"></i> <?= e($p['dosage_form']) ?></span><?php endif; ?>
                <?php if ($p['pack_size']): ?><span><i class="bi bi-box"></i> <?= e($p['pack_size']) ?></span><?php endif; ?>
                <?php if ($brand !== ''): ?><span><i class="bi bi-award"></i> <a href="<?= e(url('brand/' . rawurlencode($p['brand_slug']))) ?>"><?= e($brand) ?></a></span><?php endif; ?>
                <?php if ((int) $p['requires_prescription']): ?><span class="sb-rx"><i class="bi bi-file-medical"></i> <?= e(__('rx_required')) ?></span><?php endif; ?>
            </p>
            <a href="#reviews" class="sb-pd-rating">
                <span class="sb-stars"><?= $stars($avg) ?></span>
                <?php if ((int) $p['rating_count'] > 0): ?>
                    <b><?= e(num(number_format($avg, 1))) ?></b> <span class="text-muted">(<?= e(__('n_reviews', ['n' => num($p['rating_count'])])) ?>)</span>
                <?php else: ?>
                    <span class="text-muted"><?= e(__('no_reviews_yet')) ?></span>
                <?php endif; ?>
            </a>

            <?php if ($flash): ?>
                <div class="sb-pd-flash">
                    <span><i class="bi bi-lightning-charge-fill"></i> <?= e(__('flash_sale')) ?></span>
                    <span class="sb-countdown sb-countdown-sm" data-countdown="<?= e(date('c', strtotime($flash['ends_at']))) ?>">
                        <span class="lbl"><?= e(__('flash_ends_in')) ?></span><span data-cd-days hidden><b data-cd="d">0</b><?= e(__('days_short')) ?> </span><b data-cd="h">00</b>:<b data-cd="m">00</b>:<b data-cd="s">00</b>
                    </span>
                    <?php if (!empty($flash['per_order'])): ?><small><?= e(__('flash_max_per_order', ['n' => num($flash['per_order'])])) ?></small><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($pricing['offer'])): $po = $pricing['offer']; ?>
                <div class="sb-pd-offer">
                    <i class="bi bi-tag-fill"></i>
                    <span><b><?= e(Promo::label($po)) ?></b> · <?= e(Promo::title($po)) ?><?php if ($po['ends_at']): ?> <small class="text-muted">(<?= e(__('offer_until', ['date' => dt($po['ends_at'], 'd M, h:i A')])) ?>)</small><?php endif; ?></span>
                </div>
            <?php elseif (!empty($pricing['bogo'])): $pb = $pricing['bogo']; ?>
                <div class="sb-pd-offer bogo">
                    <i class="bi bi-gift-fill"></i>
                    <span><b><?= e(Promo::label($pb)) ?></b> · <?= e(__('bogo_hint', ['n' => num((int) $pb['buy_qty'] + (int) $pb['get_qty']), 'get' => num((int) $pb['get_qty'])])) ?></span>
                </div>
            <?php endif; ?>

            <div class="sb-pd-price">
                <b data-pd-price><?= e(money($pricing['price'])) ?></b>
                <s data-pd-mrp <?= $pricing['off'] > 0 ? '' : 'hidden' ?>><?= e(money($pricing['mrp'])) ?></s>
                <span class="sb-save" data-pd-save <?= $pricing['off'] > 0 ? '' : 'hidden' ?>><?= e(__('you_save', ['amount' => money($pricing['mrp'] - $pricing['price'])])) ?></span>
            </div>

            <?php if ($variants): ?>
                <div class="sb-variants" role="radiogroup" aria-label="<?= e(__('variant')) ?>">
                    <?php foreach ($variants as $v): $vp = $v['pricing']; [$vs, $vt] = $stock((int) $v['stock_qty']); ?>
                        <label>
                            <input type="radio" name="variant" value="<?= (int) $v['id'] ?>" <?= (int) $v['id'] === (int) $selected['id'] ? 'checked' : '' ?>
                                   data-price="<?= e(money($vp['price'])) ?>"
                                   data-mrp="<?= e(money($vp['mrp'])) ?>"
                                   data-off="<?= $vp['off'] > 0 ? e(__('pct_off', ['n' => num($vp['off'])])) : '' ?>"
                                   data-save="<?= $vp['off'] > 0 ? e(__('you_save', ['amount' => money($vp['mrp'] - $vp['price'])])) : '' ?>"
                                   data-stock="<?= e($vs) ?>" data-stock-text="<?= e($vt) ?>">
                            <span><?= e(tr($v['name_bn'], $v['name_en'])) ?><small><?= e(money($vp['price'])) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="sb-stock sb-stock-<?= e($stockState) ?>" data-pd-stock>
                <i class="bi <?= $stockState === 'out' ? 'bi-x-circle' : ($stockState === 'low' ? 'bi-exclamation-triangle' : 'bi-check-circle') ?>"></i>
                <span><?= e($stockText) ?></span>
            </p>

            <form method="post" action="<?= e(url('cart/add')) ?>" class="sb-buy" id="buyForm" data-add-form>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="variant_id" value="<?= (int) ($selected['id'] ?? 0) ?>" data-variant-input>
                <div class="sb-qty" data-qty>
                    <button type="button" data-qty-step="-1" aria-label="−">−</button>
                    <input type="number" name="qty" value="<?= $minQty ?>" min="<?= $minQty ?>" max="<?= $maxQty ?>" aria-label="<?= e(__('quantity')) ?>" inputmode="numeric">
                    <button type="button" data-qty-step="1" aria-label="+">+</button>
                </div>
                <button class="btn btn-primary sb-buy-btn" data-buy-btn <?= $stockState === 'out' ? 'disabled' : '' ?>><i class="bi bi-cart-plus"></i> <?= e(__('add_to_cart')) ?></button>
                <button class="btn btn-outline-primary sb-buy-btn" name="buy_now" value="1" data-buy-btn <?= $stockState === 'out' ? 'disabled' : '' ?>><i class="bi bi-lightning"></i> <?= e(__('buy_now')) ?></button>
            </form>
            <?php if ((int) $p['requires_prescription']): ?>
                <p class="small sb-rx-note mt-2 mb-0"><i class="bi bi-file-medical"></i> <?= e(__('rx_note')) ?>
                    <a href="<?= e(url('prescription')) ?>" class="fw-semibold ms-1"><?= e(__('rx_order_short')) ?> →</a></p>
            <?php endif; ?>

            <div class="sb-seller">
                <i class="bi bi-shop"></i> <?= e(__('sold_by')) ?>:
                <?php if ($p['vendor_id'] && $p['vendor_slug']): ?>
                    <a href="<?= e(url('seller/' . rawurlencode($p['vendor_slug']))) ?>"><?= e($seller) ?></a>
                <?php else: ?>
                    <b><?= e($seller) ?></b>
                <?php endif; ?>
            </div>

            <div class="sb-feat">
                <div><i class="bi bi-patch-check"></i> <?= e(__('feat_genuine')) ?></div>
                <div><i class="bi bi-truck"></i> <?= e(__('feat_delivery')) ?></div>
                <div><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('feat_return', ['n' => num((int) setting('return_window_days', 7))])) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($info): ?>
    <div class="sb-block sb-info">
        <?php if (count($info) > 1): ?>
            <nav class="sb-info-nav">
                <?php foreach ($info as $key => [$label]): ?><a href="#pd-<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <?php foreach ($info as $key => [$label, $text]): ?>
            <section id="pd-<?= e($key) ?>">
                <h2><?= e($label) ?></h2>
                <div class="sb-text"><?= nl2br(e($text)) ?></div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($alternatives): ?>
    <section class="sb-block">
        <div class="sb-sec-head">
            <h2><?= e(__('alternatives')) ?></h2>
            <a href="<?= e(url('products?q=' . rawurlencode($p['generic_name']))) ?>"><?= e(__('view_all')) ?> <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="sb-row"><?= $cardRow($alternatives) ?></div>
    </section>
<?php endif; ?>

<!-- Reviews -->
<section class="sb-block" id="reviews">
    <div class="sb-sec-head"><h2><?= e(__('reviews')) ?></h2></div>
    <div class="sb-reviews">
        <div class="sb-review-summary">
            <div class="sb-review-avg">
                <b><?= e(num(number_format($avg, 1))) ?></b>
                <span class="sb-stars"><?= $stars($avg) ?></span>
                <span class="small text-muted"><?= e(__('n_reviews', ['n' => num($reviewTotal)])) ?></span>
            </div>
            <div class="sb-review-bars">
                <?php foreach ($breakdown as $star => $n): $pct = $reviewTotal ? (int) round($n / $reviewTotal * 100) : 0; ?>
                    <div><span><?= e(num($star)) ?> <i class="bi bi-star-fill"></i></span><div class="sb-bar"><div style="width: <?= $pct ?>%"></div></div><span><?= e(num($n)) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sb-review-list">
            <?php if (Auth::type() === 'customer'): ?>
                <?php if ($myReview): ?>
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-check2-circle text-success"></i>
                        <?= e(__($myReview['status'] === 'approved' ? 'review_yours' : 'review_yours_pending')) ?>
                    </div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('product/' . (int) $p['id'] . '/review')) ?>" class="sb-review-form" novalidate>
                        <?= csrf_field() ?>
                        <h3 class="h6"><?= e(__('write_review')) ?></h3>
                        <fieldset class="mb-2">
                            <legend class="form-label mb-0"><?= e(__('your_rating')) ?> *</legend>
                            <div class="sb-star-input">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" value="<?= $i ?>" id="rate<?= $i ?>" <?= old('rating') === (string) $i ? 'checked' : '' ?> required>
                                    <label for="rate<?= $i ?>" title="<?= e(num($i)) ?>"><i class="bi bi-star-fill"></i><span class="visually-hidden"><?= e(num($i)) ?></span></label>
                                <?php endfor; ?>
                            </div>
                            <?= $err('rating') ?>
                        </fieldset>
                        <div class="mb-2">
                            <label class="form-label" for="rvTitle"><?= e(__('review_title')) ?></label>
                            <input class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" id="rvTitle" name="title" maxlength="200" value="<?= e(old('title')) ?>">
                            <?= $err('title') ?>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="rvComment"><?= e(__('review_comment')) ?></label>
                            <textarea class="form-control<?= isset($errors['comment']) ? ' is-invalid' : '' ?>" id="rvComment" name="comment" rows="3" maxlength="2000"><?= e(old('comment')) ?></textarea>
                            <?= $err('comment') ?>
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> <?= e(__('submit_review')) ?></button>
                    </form>
                <?php endif; ?>
            <?php elseif (!Auth::check()): ?>
                <p class="small"><a href="<?= e(url('login')) ?>"><?= e(__('login')) ?></a> <?= e(__('login_to_review')) ?></p>
            <?php endif; ?>

            <?php if (!$reviews): ?>
                <p class="text-muted small mb-0"><?= e(__('no_reviews_yet')) ?></p>
            <?php endif; ?>
            <?php foreach ($reviews as $r): ?>
                <article class="sb-review">
                    <div class="d-flex justify-content-between gap-2 flex-wrap">
                        <span class="sb-stars"><?= $stars((float) $r['rating']) ?></span>
                        <span class="small text-muted"><?= e(dt($r['created_at'], 'd M Y')) ?></span>
                    </div>
                    <?php if ($r['title']): ?><h3 class="h6 mt-1 mb-1"><?= e($r['title']) ?></h3><?php endif; ?>
                    <?php if ($r['comment']): ?><p class="mb-1"><?= nl2br(e($r['comment'])) ?></p><?php endif; ?>
                    <div class="small text-muted">
                        <?= e(mb_substr($r['user_name'], 0, 1) . str_repeat('*', 3)) ?>
                        <?php if ((int) $r['is_verified_purchase']): ?><span class="sb-verified"><i class="bi bi-patch-check-fill"></i> <?= e(__('verified_purchase')) ?></span><?php endif; ?>
                    </div>
                    <?php if ($r['vendor_reply']): ?>
                        <div class="sb-reply"><b><?= e(__('seller_reply')) ?>:</b> <?= nl2br(e($r['vendor_reply'])) ?></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if ($reviewPages > 1): ?>
                <nav class="d-flex gap-2 justify-content-center mt-3" aria-label="<?= e(__('reviews')) ?>">
                    <?php if ($reviewPage > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e($link . '?rpage=' . ($reviewPage - 1) . '#reviews') ?>">&laquo; <?= e(__('previous')) ?></a><?php endif; ?>
                    <span class="small align-self-center text-muted"><?= e(num($reviewPage)) ?> / <?= e(num($reviewPages)) ?></span>
                    <?php if ($reviewPage < $reviewPages): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e($link . '?rpage=' . ($reviewPage + 1) . '#reviews') ?>"><?= e(__('next')) ?> &raquo;</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($related): ?>
    <section class="sb-block">
        <div class="sb-sec-head">
            <h2><?= e(__('related_products')) ?></h2>
            <?php if ($crumbs): ?><a href="<?= e(url('category/' . rawurlencode(end($crumbs)['slug']))) ?>"><?= e(__('view_all')) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        </div>
        <div class="sb-row"><?= $cardRow($related) ?></div>
    </section>
<?php endif; ?>
