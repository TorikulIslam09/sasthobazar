<?php
declare(strict_types=1);

/** Storefront offers page: running flash sale (countdown), next flash sale, running offers, products on offer. */
class OfferController extends Controller
{
    public function index(): void
    {
        $flash = Storefront::activeFlashSale();
        $flashItems = $flash ? Storefront::cards(['flash_sale' => (int) $flash['id']], 'discount', 24) : [];
        $next = $this->db->fetch('SELECT * FROM flash_sales WHERE is_active = 1 AND starts_at > NOW() ORDER BY starts_at, id LIMIT 1');

        $offers = [];
        $productIds = [];
        foreach (Promo::active() as $kind => $rows) {
            foreach ($rows as $o) {
                $o['link'] = $this->linkFor($o);
                $offers[] = $o;
                if ($o['applies_to'] === 'product' && in_array($kind, ['price', 'bogo'], true)) {
                    $productIds = array_merge($productIds, $o['ids']);
                }
            }
        }
        $offerProducts = $productIds ? Storefront::cards(['ids' => $productIds], 'popular', 24) : [];
        $discounted = Storefront::cards(['discounted' => 1, 'exclude_ids' => array_column(array_merge($flashItems, $offerProducts), 'id')], 'discount', 12);
        $ids = array_column(array_merge($flashItems, $offerProducts, $discounted), 'id');

        $this->view('shop/offers', [
            'title'         => __('todays_offers'),
            'flash'         => $flash,
            'flashItems'    => $flashItems,
            'flashProgress' => $flash ? Storefront::flashProgress((int) $flash['id']) : [],
            'next'          => $next,
            'offers'        => $offers,
            'offerProducts' => $offerProducts,
            'discounted'    => $discounted,
            'banners'       => Storefront::banners('category_top', 2),
            'wished'        => ShopController::wishedAmong($ids),
        ]);
    }

    /** Where "Shop now" goes for an offer. */
    private function linkFor(array $o): ?string
    {
        $first = $o['ids'][0] ?? null;
        return match ($o['applies_to']) {
            'product'  => '#offer-products',
            'category' => $first && ($slug = $this->db->fetchColumn('SELECT slug FROM categories WHERE id = :id AND is_active = 1', ['id' => $first])) ? url('category/' . rawurlencode($slug)) : url('products'),
            'brand'    => $first && ($slug = $this->db->fetchColumn('SELECT slug FROM brands WHERE id = :id', ['id' => $first])) ? url('brand/' . rawurlencode($slug)) : url('products'),
            'vendor'   => ($vid = $first ?? $o['vendor_id']) && ($slug = $this->db->fetchColumn("SELECT slug FROM vendors WHERE id = :id AND status = 'approved'", ['id' => $vid])) ? url('seller/' . rawurlencode($slug)) : url('products'),
            default    => in_array($o['kind'], ['first_order', 'free_shipping'], true) ? null : url('products?sort=discount'),
        };
    }
}
