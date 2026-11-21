<?php
/**
 * Storefront layout (look of the old index.html): announcement bar, sticky header with live search,
 * category tabs, footer, and a bottom navigation bar on phones. $content = rendered view.
 */
$user = Auth::user();
$isCustomer = Auth::type() === 'customer';
$wishCount = $isCustomer ? Wishlist::countFor((int) $user['id']) : 0;
$cartCount = Cart::count();
$navCats = Category::topLevel();
$current = Helpers::currentPath();
$q = isset($q) && is_string($q) ? $q : '';
$siteName = __('app_name');
$siteLogo = (string) setting('site_logo', '');
$footerPages = Database::getInstance()->fetchAll('SELECT slug, title_en, title_bn FROM pages WHERE is_active = 1 AND show_in_footer = 1 ORDER BY sort_order, id LIMIT 8');
$isActive = static fn(string $path): bool => $path === '' ? $current === '' : ($current === $path || str_starts_with($current, $path . '/'));
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= View::partial('partials/head', ['title' => $title ?? '', 'metaDescription' => $metaDescription ?? '']) ?>
<link href="<?= e(asset('assets/css/shop.css')) ?>" rel="stylesheet">
</head>
<body class="shop-body lang-<?= e(lang()) ?>"
      data-login-url="<?= e(url('login')) ?>"
      data-wish-url="<?= e(url('wishlist/toggle')) ?>"
      data-cart-url="<?= e(url('cart/add')) ?>"
      data-lang="<?= e(lang()) ?>">

<div class="sb-announce">
    <div class="container d-flex justify-content-between align-items-center gap-2">
        <span class="text-truncate"><i class="bi bi-telephone"></i> <?= e(__('hotline')) ?>:
            <a href="tel:<?= e(setting('contact_phone', '')) ?>"><?= e(num((string) setting('contact_phone', ''))) ?></a>
            <span class="d-none d-md-inline">&nbsp;·&nbsp;<?= e(__('free_delivery_over', ['amount' => money(setting('free_shipping_threshold', 0))])) ?></span>
        </span>
        <?= View::partial('partials/lang_switch', ['class' => 'sb-lang']) ?>
    </div>
</div>

<header class="sb-header">
    <div class="container">
        <div class="sb-head-row">
            <button class="sb-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sbCatMenu" aria-controls="sbCatMenu" aria-label="<?= e(__('categories')) ?>">
                <i class="bi bi-list"></i>
            </button>
            <a href="<?= e(url('/')) ?>" class="sb-logo">
                <?php if ($siteLogo !== ''): ?><img src="<?= e(url($siteLogo)) ?>" alt="<?= e($siteName) ?>" class="sb-logo-img"><?php else: ?><span class="sb-logo-mark"><i class="bi bi-heart-pulse-fill"></i></span><span><?= e($siteName) ?></span><?php endif; ?>
            </a>

            <form class="sb-search" role="search" action="<?= e(url('products')) ?>" method="get" autocomplete="off" data-suggest-url="<?= e(url('api/search')) ?>">
                <label class="visually-hidden" for="sbSearch"><?= e(__('search')) ?></label>
                <input id="sbSearch" name="q" type="search" value="<?= e($q) ?>" maxlength="100"
                       placeholder="<?= e(__('search_placeholder')) ?>"
                       role="combobox" aria-expanded="false" aria-controls="sbSuggest" aria-autocomplete="list">
                <button type="submit" aria-label="<?= e(__('search')) ?>"><i class="bi bi-search"></i><span class="d-none d-md-inline ms-1"><?= e(__('search')) ?></span></button>
                <div class="sb-suggest" id="sbSuggest" role="listbox" hidden></div>
            </form>

            <a href="<?= e(url('prescription')) ?>" class="sb-rx-btn" title="<?= e(__('upload_prescription')) ?>"><i class="bi bi-file-earmark-medical"></i><span class="d-none d-md-inline"> <?= e(__('upload_prescription')) ?></span></a>
            <a href="<?= e(url('offers')) ?>" class="sb-flash-btn d-none d-lg-inline-flex"><i class="bi bi-lightning-charge-fill"></i> <?= e(__('todays_offers')) ?></a>

            <div class="sb-head-links">
                <a href="<?= e(url('account/wishlist')) ?>" class="d-none d-lg-flex" title="<?= e(__('wishlist')) ?>">
                    <i class="bi bi-heart"></i><span class="lbl"><?= e(__('wishlist')) ?></span>
                    <span class="sb-count" data-wish-count <?= $wishCount ? '' : 'hidden' ?>><?= e(num($wishCount)) ?></span>
                </a>
                <a href="<?= e(url('cart')) ?>" title="<?= e(__('cart')) ?>">
                    <i class="bi bi-cart3"></i><span class="lbl"><?= e(__('cart')) ?></span>
                    <span class="sb-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= e(num($cartCount)) ?></span>
                </a>
                <?php if (!$user): ?>
                    <a href="<?= e(url('login')) ?>" title="<?= e(__('login')) ?>"><i class="bi bi-person"></i><span class="lbl"><?= e(__('login')) ?></span></a>
                <?php else: ?>
                    <div class="dropdown">
                        <a href="#" data-bs-toggle="dropdown" aria-expanded="false" title="<?= e(__('my_account')) ?>">
                            <i class="bi bi-person-check"></i><span class="lbl text-truncate"><?= e(explode(' ', trim($user['name']))[0]) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <?php if ($isCustomer): ?>
                                <li><a class="dropdown-item" href="<?= e(url('account')) ?>"><i class="bi bi-person"></i> <?= e(__('my_account')) ?></a></li>
                                <li><a class="dropdown-item" href="<?= e(url('account/orders')) ?>"><i class="bi bi-bag"></i> <?= e(__('my_orders')) ?></a></li>
                                <li><a class="dropdown-item" href="<?= e(url('account/addresses')) ?>"><i class="bi bi-geo-alt"></i> <?= e(__('my_addresses')) ?></a></li>
                                <li><a class="dropdown-item" href="<?= e(url('account/wishlist')) ?>"><i class="bi bi-heart"></i> <?= e(__('wishlist')) ?></a></li>
                                <li><a class="dropdown-item" href="<?= e(url('account/support')) ?>"><i class="bi bi-headset"></i> <?= e(__('my_tickets')) ?></a></li>
                            <?php elseif (Auth::isStaff()): ?>
                                <li><a class="dropdown-item" href="<?= e(url('admin')) ?>"><i class="bi bi-speedometer2"></i> <?= e(__('admin_panel')) ?></a></li>
                            <?php elseif (Auth::type() === 'vendor'): ?>
                                <li><a class="dropdown-item" href="<?= e(url('vendor-panel')) ?>"><i class="bi bi-shop"></i> <?= e(__('vendor_panel')) ?></a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?>
                                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> <?= e(__('logout')) ?></button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <nav class="sb-tabs" aria-label="<?= e(__('categories')) ?>">
            <a href="<?= e(url('/')) ?>" class="<?= $isActive('') ? 'active' : '' ?>"><?= e(__('home')) ?></a>
            <a href="<?= e(url('products')) ?>" class="<?= $isActive('products') ? 'active' : '' ?>"><?= e(__('shop_all_products')) ?></a>
            <?php foreach (array_slice($navCats, 0, 8) as $c): $path = 'category/' . $c['slug']; ?>
                <a href="<?= e(url('category/' . rawurlencode($c['slug']))) ?>" class="<?= $isActive($path) ? 'active' : '' ?>"><?= e(Category::label($c)) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<!-- Category menu (phones) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sbCatMenu" aria-labelledby="sbCatMenuLabel">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h6 mb-0" id="sbCatMenuLabel"><i class="bi bi-grid"></i> <?= e(__('categories')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-2">
        <a class="sb-menu-link" href="<?= e(url('products')) ?>"><i class="bi bi-grid-3x3-gap"></i> <?= e(__('shop_all_products')) ?></a>
        <a class="sb-menu-link" href="<?= e(url('offers')) ?>"><i class="bi bi-lightning-charge"></i> <?= e(__('todays_offers')) ?></a>
        <a class="sb-menu-link" href="<?= e(url('prescription')) ?>"><i class="bi bi-file-earmark-medical"></i> <?= e(__('upload_prescription')) ?></a>
        <hr class="my-2">
        <?php foreach ($navCats as $c): ?>
            <a class="sb-menu-link" href="<?= e(url('category/' . rawurlencode($c['slug']))) ?>"><i class="bi <?= e($c['icon'] ?: 'bi-grid') ?>"></i> <?= e(Category::label($c)) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<main class="container sb-main">
    <?= View::partial('partials/flash') ?>
    <?= $content ?>
</main>

<footer class="sb-footer">
    <div class="container">
        <div class="sb-f-grid">
            <div class="sb-f-about">
                <a href="<?= e(url('/')) ?>" class="sb-logo sb-logo-light"><span class="sb-logo-mark"><i class="bi bi-heart-pulse-fill"></i></span><span><?= e($siteName) ?></span></a>
                <p class="mt-2 mb-2"><?= e(setting(lang() === 'bn' ? 'site_tagline' : 'site_tagline_en', '') ?: __('tagline')) ?></p>
                <div class="sb-social">
                    <?php if (setting('facebook_url')): ?><a href="<?= e(setting('facebook_url')) ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a><?php endif; ?>
                    <?php if (setting('youtube_url')): ?><a href="<?= e(setting('youtube_url')) ?>" target="_blank" rel="noopener" aria-label="YouTube"><i class="bi bi-youtube"></i></a><?php endif; ?>
                    <?php if (setting('instagram_url')): ?><a href="<?= e(setting('instagram_url')) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a><?php endif; ?>
                    <?php if ($wa = preg_replace('/\D/', '', (string) setting('whatsapp_number', ''))): ?><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
                </div>
            </div>
            <div>
                <h5><?= e(__('quick_links')) ?></h5>
                <ul>
                    <li><a href="<?= e(url('products')) ?>"><?= e(__('shop_all_products')) ?></a></li>
                    <li><a href="<?= e(url('offers')) ?>"><?= e(__('todays_offers')) ?></a></li>
                    <li><a href="<?= e(url('products?sort=newest')) ?>"><?= e(__('new_arrivals')) ?></a></li>
                    <li><a href="<?= e(url('track')) ?>"><?= e(__('track_order')) ?></a></li>
                    <li><a href="<?= e(url('prescription')) ?>"><?= e(__('upload_prescription')) ?></a></li>
                </ul>
            </div>
            <div>
                <h5><?= e(__('information')) ?></h5>
                <ul>
                    <?php foreach ($footerPages as $fp): ?><li><a href="<?= e(url('page/' . $fp['slug'])) ?>"><?= e(tr($fp['title_bn'], $fp['title_en'])) ?></a></li><?php endforeach; ?>
                    <li><a href="<?= e(url('faq')) ?>"><?= e(__('faq_title')) ?></a></li>
                    <li><a href="<?= e(url('blog')) ?>"><?= e(__('blog_title')) ?></a></li>
                </ul>
            </div>
            <div>
                <h5><?= e(__('categories')) ?></h5>
                <ul>
                    <?php foreach (array_slice($navCats, 0, 5) as $c): ?>
                        <li><a href="<?= e(url('category/' . rawurlencode($c['slug']))) ?>"><?= e(Category::label($c)) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h5><?= e(__('my_account')) ?></h5>
                <ul>
                    <?php if ($isCustomer): ?>
                        <li><a href="<?= e(url('account')) ?>"><?= e(__('my_profile')) ?></a></li>
                        <li><a href="<?= e(url('account/orders')) ?>"><?= e(__('my_orders')) ?></a></li>
                        <li><a href="<?= e(url('account/addresses')) ?>"><?= e(__('my_addresses')) ?></a></li>
                        <li><a href="<?= e(url('account/wishlist')) ?>"><?= e(__('wishlist')) ?></a></li>
                        <li><a href="<?= e(url('account/support')) ?>"><?= e(__('my_tickets')) ?></a></li>
                    <?php else: ?>
                        <li><a href="<?= e(url('login')) ?>"><?= e(__('login')) ?></a></li>
                        <li><a href="<?= e(url('register')) ?>"><?= e(__('register')) ?></a></li>
                    <?php endif; ?>
                    <li><a href="<?= e(url('vendor-panel/login')) ?>"><?= e(__('vendor_login')) ?></a></li>
                </ul>
            </div>
            <div>
                <h5><?= e(__('contact_us')) ?></h5>
                <ul>
                    <li><i class="bi bi-telephone"></i> <a href="tel:<?= e(setting('contact_phone', '')) ?>"><?= e(num((string) setting('contact_phone', ''))) ?></a></li>
                    <li><i class="bi bi-envelope"></i> <a href="mailto:<?= e(setting('contact_email', '')) ?>"><?= e(setting('contact_email', '')) ?></a></li>
                    <li><i class="bi bi-geo-alt"></i> <?= e(setting('contact_address', '')) ?></li>
                </ul>
            </div>
        </div>
        <div class="sb-pay">
            <?php if (setting('cod_enabled', true)): ?><span><?= e(__('pay_cod')) ?></span><?php endif; ?>
            <?php if (setting('bkash_enabled')): ?><span>bKash</span><?php endif; ?>
            <?php if (setting('nagad_enabled')): ?><span>Nagad</span><?php endif; ?>
            <?php if (setting('sslcommerz_enabled')): ?><span><?= e(__('pay_card')) ?></span><?php endif; ?>
        </div>
        <div class="sb-copy">© <?= e(num(date('Y'))) ?> <?= e($siteName) ?> — <?= e(__('all_rights')) ?></div>
    </div>
</footer>

<!-- Bottom navigation (phones) -->
<nav class="sb-bottom-nav d-lg-none" aria-label="<?= e(__('menu')) ?>">
    <a href="<?= e(url('/')) ?>" class="<?= $isActive('') ? 'active' : '' ?>"><i class="bi bi-house"></i><span><?= e(__('home')) ?></span></a>
    <a href="#" data-bs-toggle="offcanvas" data-bs-target="#sbCatMenu" role="button"><i class="bi bi-grid"></i><span><?= e(__('categories')) ?></span></a>
    <a href="<?= e(url('cart')) ?>" class="<?= $isActive('cart') || $isActive('checkout') ? 'active' : '' ?>">
        <i class="bi bi-cart3"></i><span><?= e(__('cart')) ?></span>
        <b class="sb-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= e(num($cartCount)) ?></b>
    </a>
    <a href="<?= e(url('account/wishlist')) ?>" class="<?= $isActive('account/wishlist') ? 'active' : '' ?>">
        <i class="bi bi-heart"></i><span><?= e(__('wishlist')) ?></span>
        <b class="sb-count" data-wish-count <?= $wishCount ? '' : 'hidden' ?>><?= e(num($wishCount)) ?></b>
    </a>
    <a href="<?= e(url($user ? ($isCustomer ? 'account' : Auth::homeUrl()) : 'login')) ?>" class="<?= ($isActive('account') && !$isActive('account/wishlist')) || $isActive('login') ? 'active' : '' ?>">
        <i class="bi bi-person"></i><span><?= e($user ? __('my_account') : __('login')) ?></span>
    </a>
</nav>

<div class="sb-toast" id="sbToast" role="status" aria-live="polite"></div>
<?php
// Promotional popup: not on cart / checkout, once a day per visitor (key changes when the popup is edited)
$reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$popup = !preg_match('#/(cart|checkout|login|register|account)(/|$)#', $reqPath) ? Banner::popup() : null;
if ($popup): $pHref = Banner::href($popup['link_url']); ?>
    <div class="sb-popup" id="sbPopup" hidden role="dialog" aria-modal="true" aria-labelledby="sbPopupTitle"
         data-popup-key="<?= e(Banner::popupKey($popup)) ?>" data-popup-delay="<?= (int) $popup['popup_delay'] ?>">
        <div class="sb-popup-box">
            <button type="button" class="sb-popup-x" data-popup-close aria-label="<?= e(__('close')) ?>">&times;</button>
            <?php if ($popup['image']): ?>
                <?php if ($pHref): ?><a href="<?= e($pHref) ?>" data-popup-close-go><?php endif; ?>
                <img src="<?= e(url($popup['image'])) ?>" alt="<?= e(Banner::title($popup)) ?>" loading="lazy">
                <?php if ($pHref): ?></a><?php endif; ?>
            <?php endif; ?>
            <?php if (Banner::title($popup) !== '' || Banner::subtitle($popup) !== '' || ($pHref && $popup['button_text'])): ?>
                <div class="sb-popup-body">
                    <?php if (Banner::title($popup) !== ''): ?><h3 id="sbPopupTitle"><?= e(Banner::title($popup)) ?></h3><?php endif; ?>
                    <?php if (Banner::subtitle($popup) !== ''): ?><p><?= e(Banner::subtitle($popup)) ?></p><?php endif; ?>
                    <?php if ($pHref): ?><a class="btn btn-primary" href="<?= e($pHref) ?>" data-popup-close-go><?= e($popup['button_text'] ?: __('shop_now')) ?></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<script src="<?= e(asset('assets/js/shop.js')) ?>"></script>
</body>
</html>
