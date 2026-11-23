<?php
declare(strict_types=1);

/**
 * Editable storefront menus (Admin → Header, Menus & Colours) and header / theme settings helpers.
 * Locations: header (bar under the search), mobile (phone menu), footer_1 (Quick links), footer_2 (Information).
 */
final class Menu
{
    public const LOCATIONS = ['header', 'mobile', 'footer_1', 'footer_2'];

    private static ?array $cache = null;

    /** Active links of one location, in order (loaded once per request). */
    public static function items(string $location): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::getInstance()->fetchAll('SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order, id') as $r) {
                    self::$cache[$r['location']][] = $r;
                }
            } catch (Throwable $e) {
                self::$cache = []; // table not migrated yet: menus are simply empty
            }
        }
        return self::$cache[$location] ?? [];
    }

    public static function label(array $m): string
    {
        return tr((string) ($m['label_bn'] ?: $m['label_en']), (string) $m['label_en']);
    }

    /** Safe link: site path (products, /offers, page/about-us) or a full http(s) address. Anything else -> null. */
    public static function href(string $link): ?string
    {
        $link = trim($link);
        if ($link === '' || $link === '/') {
            return url('/');
        }
        if (preg_match('#^https?://[^\s<>"]+$#i', $link)) {
            return $link;
        }
        if (preg_match('#^(tel:\+?[0-9]+|mailto:[^\s<>"]+)$#i', $link)) {
            return $link;
        }
        if (preg_match('#^/?[A-Za-z0-9._~\-/?=&%\#+]*$#', $link) && !str_starts_with($link, '//')) {
            return url(ltrim($link, '/'));
        }
        return null;
    }

    public static function validLink(string $link): bool
    {
        return self::href($link) !== null;
    }

    /** Is this link the current page? (to highlight it in the header bar) */
    public static function isCurrent(string $link): bool
    {
        if (preg_match('#^(https?:|tel:|mailto:)#i', $link)) {
            return false;
        }
        $path = trim((string) parse_url('/' . ltrim($link, '/'), PHP_URL_PATH), '/');
        $cur = Helpers::currentPath();
        return $path === '' ? $cur === '' : ($cur === $path || str_starts_with($cur, $path . '/'));
    }

    /** Setting text in the current language, falling back to a translation key. */
    public static function text(string $key, string $fallbackKey, array $repl = []): string
    {
        $v = trim((string) setting($key . '_' . (lang() === 'bn' ? 'bn' : 'en'), ''));
        if ($v === '' && lang() === 'bn') {
            $v = trim((string) setting($key . '_en', ''));
        }
        return $v !== '' ? $v : __($fallbackKey, $repl);
    }

    /** Inline CSS that applies the brand colours chosen in admin (only valid #rrggbb values are used). */
    public static function themeCss(): string
    {
        $hex = static function (string $k, string $d): string {
            $v = (string) setting($k, $d);
            return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $d;
        };
        $brand = $hex('theme_brand', '#0e7673');
        $dark = $hex('theme_brand_dark', '#0a5a58');
        $accent = $hex('theme_accent', '#f0663a');
        if ($brand === '#0e7673' && $dark === '#0a5a58' && $accent === '#f0663a') {
            return '';
        }
        $rgb = implode(', ', array_map('hexdec', str_split(substr($brand, 1), 2)));
        $light = 'rgba(' . $rgb . ', .10)';
        return ".shop-body{--brand:{$brand};--brand-dark:{$dark};--brand-light:{$light};--accent:{$accent};"
            . "--bs-primary:{$brand};--bs-primary-rgb:{$rgb};--bs-link-color:{$brand};--bs-link-color-rgb:{$rgb};}"
            . ".shop-body .btn-primary{--bs-btn-bg:{$brand};--bs-btn-border-color:{$brand};--bs-btn-hover-bg:{$dark};--bs-btn-hover-border-color:{$dark};--bs-btn-active-bg:{$dark};}";
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
