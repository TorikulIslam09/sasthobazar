<?php
declare(strict_types=1);

/**
 * Page builder sections (Admin → Page Builder).
 * A section = a block of a given TYPE placed on a PAGE at a POSITION, with its own settings + style.
 * Text fields are bilingual (…_bn / …_en); rich text is cleaned with Html::clean(); images go to public/uploads/sections.
 * Rendering: Section::render($pageKey, $position) → HTML (views/sections/{type}.php inside views/sections/_wrap.php).
 */
final class Section
{
    /** Pages that can hold sections (CMS pages are added as page:{slug}). */
    public const PAGES = ['home', 'all', 'products', 'product', 'offers', 'cart', 'prescription'];

    /** Positions per page (others: top / bottom). */
    public const HOME_POSITIONS = ['top', 'after_slider', 'after_categories', 'bottom'];
    public const POSITIONS = ['top', 'bottom'];

    public const PADDINGS = ['none' => 0, 'sm' => 12, 'md' => 24, 'lg' => 48];

    /**
     * type => [icon, fields]. Field: name => [kind, extra]
     * kinds: text_i18n, textarea_i18n, html_i18n, link, image, select (extra = options), number (extra = [min, max, default]),
     *        checkbox, category, brand, ids, video, repeater (extra = subfields name => kind)
     */
    public static function types(): array
    {
        $btn = ['button_text' => ['text_i18n'], 'button_link' => ['link']];
        return [
            'heading_text' => ['bi-type', ['subtitle' => ['textarea_i18n'], 'body' => ['html_i18n'], 'align' => ['select', ['left', 'center']]] + $btn],
            'banner'       => ['bi-image', ['image' => ['image'], 'image_mobile' => ['image'], 'link' => ['link'], 'overlay' => ['textarea_i18n'],
                               'button_text' => ['text_i18n'], 'height' => ['select', ['auto', 'sm', 'md', 'lg']]]],
            'image_text'   => ['bi-layout-split', ['image' => ['image'], 'image_side' => ['select', ['left', 'right']], 'body' => ['html_i18n']] + $btn],
            'products'     => ['bi-grid-3x3-gap', ['source' => ['select', ['featured', 'newest', 'discounted', 'popular', 'category', 'brand', 'ids']],
                               'category' => ['category'], 'brand' => ['brand'], 'ids' => ['ids'], 'limit' => ['number', [2, 24, 8]],
                               'layout' => ['select', ['scroll', 'grid']], 'view_all' => ['link']]],
            'categories'   => ['bi-diagram-3', ['limit' => ['number', [2, 24, 12]]]],
            'brands'       => ['bi-award', ['limit' => ['number', [2, 24, 12]]]],
            'reviews'      => ['bi-star-half', ['limit' => ['number', [2, 12, 6]], 'min_rating' => ['select', ['4', '5', '3']], 'sort' => ['select', ['latest', 'top', 'photos']],
                               'layout' => ['select', ['scroll', 'grid']], 'show_summary' => ['checkbox'], 'show_product' => ['checkbox']]],
            'testimonials' => ['bi-chat-quote', ['items' => ['repeater', ['name' => 'text', 'text_bn' => 'textarea', 'text_en' => 'textarea', 'rating' => 'rating']]]],
            'features'     => ['bi-ui-checks-grid', ['columns' => ['select', ['3', '2', '4']],
                               'items' => ['repeater', ['icon' => 'icon', 'title_bn' => 'text', 'title_en' => 'text', 'text_bn' => 'textarea', 'text_en' => 'textarea', 'link' => 'link']]]],
            'faq'          => ['bi-question-circle', ['items' => ['repeater', ['q_bn' => 'text', 'q_en' => 'text', 'a_bn' => 'textarea', 'a_en' => 'textarea']]]],
            'cta'          => ['bi-megaphone', ['body' => ['textarea_i18n'], 'image' => ['image']] + $btn],
            'video'        => ['bi-play-btn', ['url' => ['video'], 'caption' => ['text_i18n']]],
            'track_order'  => ['bi-truck', ['subtitle' => ['textarea_i18n']]],
            'prescription_cta' => ['bi-file-earmark-medical', ['subtitle' => ['textarea_i18n']]],
            'html'         => ['bi-code-slash', ['body' => ['html_i18n']]],
            'spacer'       => ['bi-arrows-vertical', ['height' => ['number', [8, 200, 32]], 'divider' => ['checkbox']]],
        ];
    }

    public static function positionsFor(string $pageKey): array
    {
        return $pageKey === 'home' ? self::HOME_POSITIONS : self::POSITIONS;
    }

    public static function validPage(string $key): bool
    {
        if (in_array($key, self::PAGES, true)) {
            return true;
        }
        return (bool) preg_match('/^page:[a-z0-9-]{1,180}$/', $key)
            && (bool) Database::getInstance()->fetchColumn('SELECT 1 FROM pages WHERE slug = :s', ['s' => substr($key, 5)]);
    }

    /** Page key of the current storefront URL, or null where sections are not shown (checkout, account, login ...). */
    public static function currentPageKey(): ?string
    {
        $p = Helpers::currentPath();
        if ($p === '') {
            return 'home';
        }
        if (preg_match('#^page/([a-z0-9-]+)$#', $p, $m)) {
            return 'page:' . $m[1];
        }
        if ($p === 'products' || preg_match('#^(category|brand|seller)/#', $p)) {
            return 'products';
        }
        if (str_starts_with($p, 'product/')) {
            return 'product';
        }
        foreach (['offers', 'cart', 'prescription'] as $k) {
            if ($p === $k) {
                return $k;
            }
        }
        if (preg_match('#^(faq|blog|track)(/|$)#', $p)) {
            return 'other';
        }
        return null;
    }

    private static ?array $loaded = null;

    /** Active sections (within schedule) for a page + "all pages", grouped by page_key|position. */
    private static function load(string $pageKey): array
    {
        if (self::$loaded === null || !array_key_exists($pageKey, self::$loaded)) {
            $rows = [];
            try {
                $rows = Database::getInstance()->fetchAll(
                    'SELECT * FROM page_sections WHERE is_active = 1 AND page_key IN (:k, :a)
                        AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
                   ORDER BY sort_order, id',
                    ['k' => $pageKey, 'a' => 'all']
                );
            } catch (Throwable $e) {
                $rows = []; // not migrated yet
            }
            $by = [];
            foreach ($rows as $r) {
                $by[$r['page_key'] . '|' . $r['position']][] = $r;
            }
            self::$loaded[$pageKey] = $by;
        }
        return self::$loaded[$pageKey];
    }

    /**
     * HTML of the sections of a page at a position. For top / bottom the "all pages" sections are added
     * (top: all-pages first, bottom: all-pages last, so they sit right above the footer).
     */
    public static function render(?string $pageKey, string $position): string
    {
        if ($pageKey === null) {
            return '';
        }
        $by = self::load($pageKey);
        $list = $by[$pageKey . '|' . $position] ?? [];
        if ($pageKey !== 'all' && in_array($position, ['top', 'bottom'], true)) {
            $global = $by['all|' . $position] ?? [];
            $list = $position === 'top' ? array_merge($global, $list) : array_merge($list, $global);
        }
        $html = '';
        foreach ($list as $s) {
            try {
                $html .= self::renderOne($s);
            } catch (Throwable $e) {
                error_log('[section ' . $s['id'] . '] ' . $e->getMessage()); // one broken section never breaks the page
            }
        }
        return $html;
    }

    public static function hasAny(string $pageKey): bool
    {
        foreach (self::load($pageKey) as $k => $rows) {
            if (str_starts_with($k, $pageKey . '|') && $rows) {
                return true;
            }
        }
        return false;
    }

    public static function renderOne(array $s): string
    {
        if (!isset(self::types()[$s['type']])) {
            return '';
        }
        $set = json_decode((string) $s['settings'], true) ?: [];
        $style = json_decode((string) $s['style'], true) ?: [];
        $data = self::data($s['type'], $set);
        if ($data === false) {
            return ''; // nothing to show (e.g. no products / reviews yet)
        }
        $inner = View::partial('sections/' . $s['type'], ['s' => $s, 'set' => $set, 'd' => $data, 't' => self::textFn($set)]);
        if (trim($inner) === '') {
            return '';
        }
        return View::partial('sections/_wrap', ['s' => $s, 'style' => $style, 'inner' => $inner, 'title' => self::title($s)]);
    }

    public static function title(array $s): string
    {
        return tr((string) ($s['title_bn'] ?: $s['title_en']), (string) $s['title_en']);
    }

    /** $t('body') → the Bangla or English value of a bilingual field. */
    public static function textFn(array $set): Closure
    {
        return static function (string $name, ?array $from = null) use ($set): string {
            $src = $from ?? $set;
            $bn = (string) ($src[$name . '_bn'] ?? '');
            $en = (string) ($src[$name . '_en'] ?? '');
            return lang() === 'bn' ? ($bn !== '' ? $bn : $en) : ($en !== '' ? $en : $bn);
        };
    }

    /** Data a type needs (false = hide the section). */
    private static function data(string $type, array $set): mixed
    {
        $limit = static fn(int $d) => max(1, min(24, (int) ($set['limit'] ?? $d)));
        switch ($type) {
            case 'products':
                $f = [];
                $sort = 'popular';
                switch ($set['source'] ?? 'featured') {
                    case 'featured': $f['featured'] = 1; break;
                    case 'newest': $sort = 'newest'; break;
                    case 'discounted': $f['discounted'] = 1; $sort = 'discount'; break;
                    case 'category':
                        $id = (int) ($set['category'] ?? 0);
                        if (!$id) { return false; }
                        $f['category_ids'] = self::categoryWithChildren($id);
                        break;
                    case 'brand':
                        if (!(int) ($set['brand'] ?? 0)) { return false; }
                        $f['brand_ids'] = [(int) $set['brand']];
                        break;
                    case 'ids':
                        $ids = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($set['ids'] ?? '')))));
                        if (!$ids) { return false; }
                        $f['ids'] = $ids;
                        break;
                }
                $items = Storefront::cards($f, $sort, $limit(8));
                return $items ? ['items' => $items, 'wished' => ShopController::wishedAmong(array_column($items, 'id'))] : false;
            case 'categories':
                $c = Storefront::homeCategories($limit(12));
                return $c ?: false;
            case 'brands':
                $b = Storefront::popularBrands($limit(12));
                return $b ?: false;
            case 'reviews':
                $min = in_array((string) ($set['min_rating'] ?? '4'), ['3', '4', '5'], true) ? (int) $set['min_rating'] : 4;
                $order = [
                    'top'    => 'r.rating DESC, r.is_verified_purchase DESC, r.created_at DESC',
                    'photos' => "(r.images IS NOT NULL AND r.images NOT IN ('', '[]')) DESC, r.created_at DESC",
                ][$set['sort'] ?? 'latest'] ?? 'r.created_at DESC';
                $visible = "r.status = 'approved' AND p.deleted_at IS NULL AND p.status = 'active'";
                $db = Database::getInstance();
                $r = $db->fetchAll(
                    "SELECT r.id, r.rating, r.title, r.comment, r.images, r.created_at, r.is_verified_purchase, r.vendor_reply,
                            u.name AS customer, u.avatar, p.slug, p.name_en, p.name_bn,
                            (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS product_image
                       FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
                      WHERE {$visible} AND r.rating >= :m AND r.comment IS NOT NULL AND r.comment <> ''
                   ORDER BY {$order} LIMIT " . $limit(6),
                    ['m' => $min]
                );
                if (!$r) {
                    return false;
                }
                // summary of ALL approved reviews (honest numbers, not only the ones shown)
                $sum = $db->fetch("SELECT COUNT(*) AS n, AVG(r.rating) AS avg, SUM(r.rating >= 4) AS happy FROM reviews r JOIN products p ON p.id = r.product_id WHERE {$visible}");
                $dist = array_column($db->fetchAll("SELECT r.rating, COUNT(*) AS n FROM reviews r JOIN products p ON p.id = r.product_id WHERE {$visible} GROUP BY r.rating"), 'n', 'rating');
                return ['rows' => $r, 'total' => (int) $sum['n'], 'avg' => round((float) $sum['avg'], 1),
                        'happy' => $sum['n'] ? (int) round(100 * (int) $sum['happy'] / (int) $sum['n']) : 0, 'dist' => $dist];
            case 'testimonials':
            case 'features':
            case 'faq':
                $items = array_values(array_filter((array) ($set['items'] ?? []), 'is_array'));
                return $items ?: false;
            case 'video':
                $embed = self::videoEmbed((string) ($set['url'] ?? ''));
                return $embed ? ['embed' => $embed] : false;
            case 'banner':
            case 'image_text':
                return !empty($set['image']) || $type === 'image_text' ? [] : false;
            case 'prescription_cta':
                return Prescription::requestsEnabled() ? [] : false;
        }
        return [];
    }

    private static function categoryWithChildren(int $id): array
    {
        $cats = Category::allById();
        $out = [$id];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($cats as $c) {
                if ($c['parent_id'] !== null && in_array((int) $c['parent_id'], $out, true) && !in_array((int) $c['id'], $out, true)) {
                    $out[] = (int) $c['id'];
                    $changed = true;
                }
            }
        }
        return $out;
    }

    /** YouTube / Vimeo link → privacy-friendly embed URL, or null. */
    public static function videoEmbed(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        if (preg_match('#^https?://(?:www\.)?vimeo\.com/(\d{5,12})#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }

    /** Inline style for the wrapper, built only from validated values. */
    public static function wrapStyle(array $style): string
    {
        $css = [];
        foreach (['bg' => 'background', 'color' => 'color'] as $k => $prop) {
            if (!empty($style[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $style[$k])) {
                $css[] = $prop . ':' . $style[$k];
            }
        }
        $pad = self::PADDINGS[$style['padding'] ?? 'md'] ?? 24;
        $css[] = 'padding-top:' . $pad . 'px;padding-bottom:' . $pad . 'px';
        return implode(';', $css);
    }

    public static function flush(): void
    {
        self::$loaded = null;
    }
}
