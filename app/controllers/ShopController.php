<?php
declare(strict_types=1);

/**
 * Customer catalog: product listing (all / category / brand / seller), product page, live search API.
 *
 * Listing query string:
 *   q          search words (name, Bangla name, generic, strength, brand, exact SKU/barcode)
 *   brand[]    brand ids          seller[]  vendor ids (0 = sold by SasthoBazar)
 *   min, max   price range        in_stock  1 = hide out-of-stock
 *   sort       relevance|popular|newest|price_asc|price_desc|discount|rating
 *   page       page number
 */
class ShopController extends Controller
{
    /** Wishlist flags for product cards (only for logged-in customers). */
    public static function wishedAmong(array $ids): array
    {
        return Auth::type() === 'customer' && $ids ? Wishlist::idsAmong((int) Auth::id(), $ids) : [];
    }

    /* ---------------- Listing ---------------- */

    public function index(): void
    {
        $this->listing(null);
    }

    public function category(string $slug): void
    {
        $cat = $this->db->fetch('SELECT * FROM categories WHERE slug = :s AND is_active = 1', ['s' => $slug]);
        if (!$cat) {
            $this->notFound();
        }
        $this->listing(['type' => 'category', 'row' => $cat]);
    }

    public function brand(string $slug): void
    {
        $brand = $this->db->fetch('SELECT * FROM brands WHERE slug = :s AND is_active = 1', ['s' => $slug]);
        if (!$brand) {
            $this->notFound();
        }
        $this->listing(['type' => 'brand', 'row' => $brand]);
    }

    public function seller(string $slug): void
    {
        $vendor = $this->db->fetch(
            "SELECT * FROM vendors WHERE slug = :s AND status = 'approved' AND deleted_at IS NULL",
            ['s' => $slug]
        );
        if (!$vendor) {
            $this->notFound();
        }
        $this->listing(['type' => 'seller', 'row' => $vendor]);
    }

    private function listing(?array $ctx): void
    {
        $q = mb_substr(Helpers::str('q'), 0, 100);
        $f = [
            'q'          => $q,
            'brand_ids'  => Storefront::intList($_GET['brand'] ?? []),
            'vendor_ids' => Storefront::intList($_GET['seller'] ?? [], true),
            'in_stock'   => Helpers::str('in_stock') === '1',
        ];
        $min = Helpers::str('min');
        $max = Helpers::str('max');
        $f['min_price'] = preg_match('/^\d{1,9}(\.\d{1,2})?$/', $min) ? (float) $min : '';
        $f['max_price'] = preg_match('/^\d{1,9}(\.\d{1,2})?$/', $max) ? (float) $max : '';
        if ($f['min_price'] !== '' && $f['max_price'] !== '' && $f['min_price'] > $f['max_price']) {
            [$f['min_price'], $f['max_price']] = [$f['max_price'], $f['min_price']];
        }

        $sort = Helpers::str('sort');
        if (!array_key_exists($sort, Storefront::SORTS) || ($sort === 'relevance' && $q === '')) {
            $sort = $q !== '' ? 'relevance' : 'popular';
        }

        $category = null;
        if ($ctx && $ctx['type'] === 'category') {
            $category = $ctx['row'];
            $f['category_ids'] = Category::descendantIds((int) $category['id']);
        } elseif ($ctx && $ctx['type'] === 'brand') {
            $f['brand_ids'] = [(int) $ctx['row']['id']];
        } elseif ($ctx && $ctx['type'] === 'seller') {
            $f['vendor_ids'] = [(int) $ctx['row']['id']];
        }

        $total = Storefront::count($f);
        $pager = new Paginator($total, Storefront::PER_PAGE);
        $products = $total > 0 ? Storefront::cards($f, $sort, Storefront::PER_PAGE, $pager->offset()) : [];

        $brandFacet = ($ctx['type'] ?? '') === 'brand' ? [] : Storefront::brandFacet($f);
        $vendorFacet = ($ctx['type'] ?? '') === 'seller' ? [] : Storefront::vendorFacet($f);
        // keep selected-but-now-empty brands visible so they can be unticked
        $missing = array_diff($f['brand_ids'], array_map('intval', array_column($brandFacet, 'id')));
        if ($missing && ($ctx['type'] ?? '') !== 'brand') {
            foreach ($missing as $id) {
                if ($b = $this->db->fetch('SELECT id, name, name_bn, slug FROM brands WHERE id = :id', ['id' => $id])) {
                    $brandFacet[] = $b + ['n' => 0];
                }
            }
        }

        if ($ctx) {
            $heading = match ($ctx['type']) {
                'category' => Category::label($ctx['row']),
                'brand'    => Brand::label($ctx['row']),
                'seller'   => Vendor::label($ctx['row']),
            };
        } else {
            $heading = $q !== '' ? __('shop_results_for', ['q' => $q]) : __('shop_all_products');
        }

        $this->view('shop/listing', [
            'topBanners'  => Storefront::banners('category_top', 2),
            'sideBanners' => Storefront::banners('sidebar', 2),
            'title'       => $heading,
            'heading'     => $heading,
            'ctx'         => $ctx,
            'f'           => $f,
            'q'           => $q,
            'sort'        => $sort,
            'products'    => $products,
            'pager'       => $pager,
            'brandFacet'  => $brandFacet,
            'vendorFacet' => $vendorFacet,
            'priceRange'  => Storefront::priceRange($f),
            'catNav'      => $this->categoryNav($category),
            'crumbs'      => $category ? $this->categoryCrumbs((int) $category['id']) : [],
            'wished'      => self::wishedAmong(array_column($products, 'id')),
        ]);
    }

    /** Sidebar category list: ancestors, then children of the current category (or its siblings, or top level). */
    private function categoryNav(?array $current): array
    {
        $all = array_filter(Category::allById(), static fn($c) => (int) $c['is_active'] === 1);
        $childrenOf = static function (?int $parent) use ($all): array {
            return array_values(array_filter($all, static fn($c) => ($c['parent_id'] === null ? null : (int) $c['parent_id']) === $parent));
        };
        if (!$current) {
            return ['ancestors' => [], 'current' => null, 'items' => $childrenOf(null)];
        }
        $id = (int) $current['id'];
        $items = $childrenOf($id);
        $ancestors = $this->categoryCrumbs($id);
        array_pop($ancestors); // drop the current one
        if (!$items) { // leaf: show siblings
            $items = $childrenOf($current['parent_id'] === null ? null : (int) $current['parent_id']);
        } else {
            $ancestors[] = $current;
        }
        return ['ancestors' => $ancestors, 'current' => $current, 'items' => $items];
    }

    /** Category chain from the top level down to $id (inclusive). */
    private function categoryCrumbs(int $id): array
    {
        $all = Category::allById();
        $chain = [];
        $guard = 0;
        while ($id && isset($all[$id]) && $guard++ < 20) {
            array_unshift($chain, $all[$id]);
            $id = $all[$id]['parent_id'] === null ? 0 : (int) $all[$id]['parent_id'];
        }
        return $chain;
    }

    /* ---------------- Live search suggestions ---------------- */

    public function suggest(): void
    {
        $key = 'suggest:' . Helpers::clientIp();
        if (RateLimiter::tooMany($key, 120)) {
            $this->json(['error' => __('too_many_requests')], 429);
        }
        RateLimiter::hit($key, 60);

        $q = mb_substr(Helpers::str('q'), 0, 100);
        $out = ['q' => $q, 'products' => [], 'categories' => [], 'brands' => [], 'all_url' => url('products?q=' . rawurlencode($q))];
        if (mb_strlen(trim($q)) < 2) {
            $this->json($out);
        }
        [$products, $cats, $brands] = Storefront::suggest($q);
        foreach ($products as $p) {
            $off = Storefront::offPercent($p);
            $out['products'][] = [
                'name'  => Storefront::name($p),
                'sub'   => Storefront::subline($p),
                'brand' => tr($p['brand_name_bn'], $p['brand_name']),
                'price' => money($p['eff_price']),
                'mrp'   => $off > 0 ? money($p['eff_mrp']) : null,
                'image' => $p['image'] ? url($p['image']) : null,
                'url'   => Storefront::url($p),
                'rx'    => (bool) $p['requires_prescription'],
                'out'   => !Storefront::inStock($p),
            ];
        }
        foreach ($cats as $c) {
            $out['categories'][] = ['name' => Category::label($c), 'url' => url('category/' . rawurlencode($c['slug']))];
        }
        foreach ($brands as $b) {
            $out['brands'][] = ['name' => Brand::label($b), 'url' => url('brand/' . rawurlencode($b['slug']))];
        }
        header('Cache-Control: private, max-age=60');
        $this->json($out);
    }

    /* ---------------- Product page ---------------- */

    public function product(string $slug): void
    {
        $p = Storefront::productBySlug($slug);
        if (!$p) {
            $this->notFound();
        }
        $id = (int) $p['id'];
        $flash = Storefront::productFlash($id);
        $variants = (int) $p['has_variants'] ? Storefront::activeVariants($id) : [];
        foreach ($variants as &$v) {
            $v['pricing'] = Storefront::priceFor($v, $flash, (int) $v['id'], $p);
        }
        unset($v);

        $selected = null;
        if ($variants) {
            $want = (int) Helpers::str('variant');
            foreach ($variants as $v) {
                if ((int) $v['id'] === $want) {
                    $selected = $v;
                }
            }
            $selected ??= $variants[0];
        }
        $pricing = $selected ? $selected['pricing'] : Storefront::priceFor($p, $flash, null, $p);

        // Reviews
        $reviewTotal = Review::countApproved($id);
        $rPage = max(1, (int) Helpers::str('rpage', '1'));
        $rPages = max(1, (int) ceil($reviewTotal / Review::PER_PAGE));
        $rPage = min($rPage, $rPages);
        $myReview = Auth::type() === 'customer' ? Review::byUser((int) Auth::id(), $id) : null;

        // Same generic + strength from other brands, then same category
        $alternatives = $p['generic_name']
            ? Storefront::cards(['generic' => [$p['generic_name'], $p['strength']], 'exclude_ids' => [$id]], 'price_asc', 12)
            : [];
        $related = $p['category_id']
            ? Storefront::cards([
                'category_ids' => [(int) $p['category_id']],
                'exclude_ids'  => array_merge([$id], array_map('intval', array_column($alternatives, 'id'))),
            ], 'popular', 12)
            : [];

        Storefront::countView($id);

        $this->view('shop/product', [
            'title'           => $p['meta_title'] ?: Storefront::name($p),
            'metaDescription' => $p['meta_description'] ?: ($p['short_description'] ?: Storefront::subline($p)),
            'p'               => $p,
            'images'          => Product::images($id),
            'variants'        => $variants,
            'selected'        => $selected,
            'pricing'         => $pricing,
            'crumbs'          => $p['category_id'] ? $this->categoryCrumbs((int) $p['category_id']) : [],
            'reviews'         => $reviewTotal ? Review::approved($id, Review::PER_PAGE, ($rPage - 1) * Review::PER_PAGE) : [],
            'reviewTotal'     => $reviewTotal,
            'reviewPage'      => $rPage,
            'reviewPages'     => $rPages,
            'breakdown'       => Review::breakdown($id),
            'myReview'        => $myReview,
            'errors'          => $_SESSION['_errors'] ?? [],
            'alternatives'    => $alternatives,
            'related'         => $related,
            'wished'          => self::wishedAmong(array_merge([$id], array_column($alternatives, 'id'), array_column($related, 'id'))),
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }
}
