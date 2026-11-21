<?php
declare(strict_types=1);

/**
 * Storefront banners and the popup.
 * Positions: home_slider (hero carousel), home_middle / home_bottom (home banner rows), category_top (listing pages),
 * sidebar (listing filter column), popup (shown once per day per visitor, after popup_delay seconds).
 * lang = all / en / bn limits a banner to one storefront language. Only active banners inside their schedule show.
 */
class Banner extends Model
{
    protected static string $table = 'banners';

    public const POSITIONS = ['home_slider', 'home_middle', 'home_bottom', 'category_top', 'sidebar', 'popup'];
    public const LANGS = ['all', 'en', 'bn'];

    /** Link for an href: full URLs and absolute paths as they are, "products?x=1" relative to the site. */
    public static function href(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $link) || str_starts_with($link, '/')) {
            return $link;
        }
        return url($link);
    }

    public static function title(array $b): string
    {
        return tr($b['title_bn'] ?? null, (string) ($b['title'] ?? ''));
    }

    public static function subtitle(array $b): string
    {
        return tr($b['subtitle_bn'] ?? null, (string) ($b['subtitle'] ?? ''));
    }

    /** The popup to show now (highest sort first), or null. */
    public static function popup(): ?array
    {
        return Storefront::banners('popup', 1)[0] ?? null;
    }

    /** Stable key for "already seen" (changes when the popup is edited). */
    public static function popupKey(array $b): string
    {
        return 'sbPopup' . (int) $b['id'] . '-' . substr(md5((string) ($b['updated_at'] ?? $b['created_at'] ?? '')), 0, 6);
    }
}
