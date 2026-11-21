<?php
/**
 * Product listing (all / search / category / brand / seller).
 * Vars: $heading, $ctx, $f, $q, $sort, $products, $pager, $brandFacet, $vendorFacet, $priceRange, $catNav, $crumbs, $wished
 */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$ctxType = $ctx['type'] ?? null;
$query = $_GET;
unset($query['page'], $query['lang']);

/** Current URL with some parameters changed (null = remove). */
$link = static function (array $changes, ?string $base = null) use ($path, $query): string {
    $qs = $query;
    foreach ($changes as $k => $v) {
        if ($v === null) {
            unset($qs[$k]);
        } else {
            $qs[$k] = $v;
        }
    }
    $s = http_build_query($qs);
    return ($base ?? $path) . ($s !== '' ? '?' . $s : '');
};
/** Category link keeps search, price, stock and sort; drops brand/seller (they depend on the category). */
$catLink = static function (?array $c) use ($link): string {
    return $link(['brand' => null, 'seller' => null], $c ? url('category/' . rawurlencode($c['slug'])) : url('products'));
};
$sortLabels = [
    'relevance'  => __('sort_relevance'),
    'popular'    => __('popular'),
    'newest'     => __('sort_newest'),
    'price_asc'  => __('sort_price_asc'),
    'price_desc' => __('sort_price_desc'),
    'discount'   => __('sort_discount'),
    'rating'     => __('sort_rating'),
];
if ($q === '') {
    unset($sortLabels['relevance']);
}

// Active filter chips
$chips = [];
if ($q !== '' && $ctxType !== null) {
    $chips[] = ['"' . $q . '"', $link(['q' => null])];
}
foreach ($brandFacet as $b) {
    if (in_array((int) $b['id'], $f['brand_ids'], true) && $ctxType !== 'brand') {
        $rest = array_values(array_diff($f['brand_ids'], [(int) $b['id']]));
        $chips[] = [Brand::label($b), $link(['brand' => $rest ?: null])];
    }
}
foreach ($vendorFacet as $v) {
    if (in_array((int) $v['id'], $f['vendor_ids'], true) && $ctxType !== 'seller') {
        $rest = array_values(array_diff($f['vendor_ids'], [(int) $v['id']]));
        $chips[] = [(int) $v['id'] === 0 ? __('app_name') : Vendor::label($v), $link(['seller' => $rest ?: null])];
    }
}
if ($f['min_price'] !== '' || $f['max_price'] !== '') {
    $chips[] = [($f['min_price'] !== '' ? money($f['min_price']) : '') . ' – ' . ($f['max_price'] !== '' ? money($f['max_price']) : ''), $link(['min' => null, 'max' => null])];
}
if ($f['in_stock']) {
    $chips[] = [__('in_stock_only'), $link(['in_stock' => null])];
}
?>
<nav class="sb-crumbs" aria-label="breadcrumb">
    <a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a>
    <?php if ($ctxType === 'category'): ?>
        <?php foreach ($crumbs as $i => $c): ?>
            › <?php if ($i < count($crumbs) - 1): ?><a href="<?= e($catLink($c)) ?>"><?= e(Category::label($c)) ?></a><?php else: ?><span><?= e(Category::label($c)) ?></span><?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        › <a href="<?= e(url('products')) ?>"><?= e(__('shop_all_products')) ?></a>
        <?php if ($ctxType || $q !== ''): ?> › <span><?= e($heading) ?></span><?php endif; ?>
    <?php endif; ?>
</nav>

<?php if ($ctxType === 'brand' || $ctxType === 'seller'): $r = $ctx['row']; $logo = $r['logo'] ?? null; ?>
    <div class="sb-block sb-ctx-head">
        <?php if ($logo): ?><img src="<?= e(url($logo)) ?>" alt="" class="sb-ctx-logo"><?php else: ?><span class="sb-ctx-logo sb-brand-initial"><?= e(mb_substr($heading, 0, 1)) ?></span><?php endif; ?>
        <div class="min-w-0">
            <h1 class="h5 mb-1"><?= e($heading) ?></h1>
            <?php if (!empty($r['description'])): ?><p class="text-muted small mb-0 text-truncate-2"><?= e($r['description']) ?></p><?php endif; ?>
            <?php if ($ctxType === 'brand' && !empty($r['country'])): ?><span class="small text-muted"><i class="bi bi-globe2"></i> <?= e($r['country']) ?></span><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?= View::partial('shop/_banners', ['banners' => $topBanners ?? []]) ?>

<div class="sb-shop">
    <!-- Filters: inline on large screens, slide-in panel on phones -->
    <aside class="offcanvas-lg offcanvas-start sb-side" tabindex="-1" id="sbFilters" aria-labelledby="sbFiltersLabel">
        <div class="offcanvas-header border-bottom">
            <h2 class="offcanvas-title h6 mb-0" id="sbFiltersLabel"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sbFilters" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-block">
            <form method="get" action="<?= e($path) ?>" id="filterForm">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">

                <?php if ($ctxType !== 'brand' && $ctxType !== 'seller'): ?>
                    <div class="sb-filter">
                        <h3><?= e(__('categories')) ?></h3>
                        <?php if ($catNav['current']): ?>
                            <a class="sb-cat-link" href="<?= e($catLink(null)) ?>"><i class="bi bi-chevron-left"></i> <?= e(__('all_categories')) ?></a>
                        <?php endif; ?>
                        <?php foreach ($catNav['ancestors'] as $a): ?>
                            <a class="sb-cat-link fw-semibold<?= (int) $a['id'] === (int) ($catNav['current']['id'] ?? 0) ? ' on' : '' ?>" href="<?= e($catLink($a)) ?>"><?= e(Category::label($a)) ?></a>
                        <?php endforeach; ?>
                        <div class="<?= $catNav['ancestors'] ? 'ps-2' : '' ?>">
                            <?php foreach ($catNav['items'] as $c): ?>
                                <a class="sb-cat-link<?= (int) $c['id'] === (int) ($catNav['current']['id'] ?? 0) ? ' on' : '' ?>" href="<?= e($catLink($c)) ?>">
                                    <span><i class="bi <?= e($c['icon'] ?: 'bi-dot') ?>"></i> <?= e(Category::label($c)) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="sb-filter">
                    <h3><?= e(__('price_range')) ?></h3>
                    <div class="d-flex gap-2 align-items-center">
                        <input class="form-control form-control-sm" type="number" name="min" min="0" step="1" inputmode="numeric"
                               value="<?= e($f['min_price'] !== '' ? (string) (float) $f['min_price'] : '') ?>"
                               placeholder="<?= e(__('min')) ?><?= $priceRange[0] > 0 ? ' ' . (int) floor($priceRange[0]) : '' ?>" aria-label="<?= e(__('min')) ?>">
                        <span>–</span>
                        <input class="form-control form-control-sm" type="number" name="max" min="0" step="1" inputmode="numeric"
                               value="<?= e($f['max_price'] !== '' ? (string) (float) $f['max_price'] : '') ?>"
                               placeholder="<?= e(__('max')) ?><?= $priceRange[1] > 0 ? ' ' . (int) ceil($priceRange[1]) : '' ?>" aria-label="<?= e(__('max')) ?>">
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="fInStock" <?= $f['in_stock'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fInStock"><?= e(__('in_stock_only')) ?></label>
                    </div>
                </div>

                <?php if ($brandFacet): ?>
                    <div class="sb-filter">
                        <h3><?= e(__('brands')) ?></h3>
                        <div class="sb-check-list">
                            <?php foreach ($brandFacet as $b): $bid = (int) $b['id']; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="brand[]" value="<?= $bid ?>" id="fb<?= $bid ?>" <?= in_array($bid, $f['brand_ids'], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label d-flex justify-content-between gap-2" for="fb<?= $bid ?>">
                                        <span class="text-truncate"><?= e(Brand::label($b)) ?></span><span class="text-muted"><?= e(num($b['n'])) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($vendorFacet && (count($vendorFacet) > 1 || $f['vendor_ids'])): ?>
                    <div class="sb-filter">
                        <h3><?= e(__('seller')) ?></h3>
                        <div class="sb-check-list">
                            <?php foreach ($vendorFacet as $v): $vid = (int) $v['id']; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="seller[]" value="<?= $vid ?>" id="fv<?= $vid ?>" <?= in_array($vid, $f['vendor_ids'], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label d-flex justify-content-between gap-2" for="fv<?= $vid ?>">
                                        <span class="text-truncate"><?= e($vid === 0 ? __('app_name') : Vendor::label($v)) ?></span><span class="text-muted"><?= e(num($v['n'])) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2 sb-filter-actions">
                    <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i> <?= e(__('apply')) ?></button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e($path . ($q !== '' ? '?q=' . rawurlencode($q) : '')) ?>"><?= e(__('reset')) ?></a>
                </div>
            </form>
            <?= View::partial('shop/_banners', ['banners' => $sideBanners ?? [], 'class' => 'sb-side-banners d-none d-lg-grid']) ?>
        </div>
    </aside>

    <section class="sb-block mb-0 min-w-0">
        <div class="sb-toolbar">
            <div class="min-w-0">
                <?php if ($ctxType !== 'brand' && $ctxType !== 'seller'): ?><h1 class="h5 mb-0 text-truncate"><?= e($heading) ?></h1><?php endif; ?>
                <span class="text-muted small"><?= e(__('n_products_found', ['n' => num($pager->total)])) ?></span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-outline-primary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sbFilters" aria-controls="sbFilters">
                    <i class="bi bi-funnel"></i> <?= e(__('filter')) ?><?php if ($chips): ?> <span class="badge bg-primary"><?= e(num(count($chips))) ?></span><?php endif; ?>
                </button>
                <form method="get" action="<?= e($path) ?>" class="d-flex align-items-center gap-1">
                    <?php foreach ($query as $k => $v): if ($k === 'sort') { continue; } foreach ((array) $v as $vv): if (!is_scalar($vv)) { continue; } ?>
                        <input type="hidden" name="<?= e(is_array($v) ? $k . '[]' : $k) ?>" value="<?= e($vv) ?>">
                    <?php endforeach; endforeach; ?>
                    <label class="small text-muted d-none d-sm-inline" for="sortSel"><?= e(__('sort')) ?></label>
                    <select class="form-select form-select-sm" name="sort" id="sortSel" data-autosubmit>
                        <?php foreach ($sortLabels as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-sm btn-primary"><?= e(__('apply')) ?></button></noscript>
                </form>
            </div>
        </div>

        <?php if ($chips): ?>
            <div class="sb-active-filters">
                <?php foreach ($chips as [$label, $href]): ?>
                    <a href="<?= e($href) ?>" title="<?= e(__('remove')) ?>"><?= e($label) ?> <i class="bi bi-x"></i></a>
                <?php endforeach; ?>
                <a href="<?= e($path . ($q !== '' && $ctxType === null ? '?q=' . rawurlencode($q) : '')) ?>" class="clear"><?= e(__('clear_all')) ?></a>
            </div>
        <?php endif; ?>

        <?php if ($products): ?>
            <div class="sb-grid">
                <?php foreach ($products as $p): ?>
                    <?= View::partial('shop/_card', ['p' => $p, 'wished' => isset($wished[(int) $p['id']])]) ?>
                <?php endforeach; ?>
            </div>
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3">
                <span class="small text-muted"><?= e(__('showing')) ?> <?= e(num($pager->from())) ?>–<?= e(num($pager->to())) ?> / <?= e(num($pager->total)) ?></span>
                <?= $pager->links() ?>
            </div>
        <?php else: ?>
            <div class="sb-empty">
                <i class="bi bi-search"></i>
                <p class="mb-1 fw-semibold"><?= e(__('no_products_found')) ?></p>
                <p class="small"><?= e(__('no_products_hint')) ?></p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('products')) ?>"><?= e(__('shop_all_products')) ?></a>
            </div>
        <?php endif; ?>
    </section>
</div>
