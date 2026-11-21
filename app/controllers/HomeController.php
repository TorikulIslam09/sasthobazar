<?php
declare(strict_types=1);

/** Storefront home: banner slider, categories, flash sale, featured / new / offer rows, brands. */
class HomeController extends Controller
{
    public function index(): void
    {
        $flash = Storefront::activeFlashSale();
        $flashItems = $flash ? Storefront::cards(['flash_sale' => (int) $flash['id']], 'discount', 12) : [];

        $sections = [
            'flash'    => $flashItems,
            'featured' => Storefront::cards(['featured' => 1], 'popular', 12),
            'offers'   => $flash ? [] : Storefront::cards(['discounted' => 1], 'discount', 12),
            'newest'   => Storefront::cards([], 'newest', 12),
        ];
        $ids = [];
        foreach ($sections as $rows) {
            $ids = array_merge($ids, array_column($rows, 'id'));
        }

        $this->view('shop/home', [
            'title'         => '',
            'banners'       => Storefront::banners('home_slider'),
            'midBanners'    => Storefront::banners('home_middle', 3),
            'bottomBanners' => Storefront::banners('home_bottom', 3),
            'categories'    => Storefront::homeCategories(),
            'flash'         => $flash,
            'flashProgress' => $flash ? Storefront::flashProgress((int) $flash['id']) : [],
            'sections'      => $sections,
            'brands'        => Storefront::popularBrands(12),
            'wished'        => ShopController::wishedAmong($ids),
        ]);
    }
}
