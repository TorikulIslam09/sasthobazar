<?php
declare(strict_types=1);

/**
 * SasthoBazar watermark on product photos (Admin → Settings → Watermark).
 *  - Text (default "SasthoBazar", Inter font) or your own logo (transparent PNG)
 *  - Position (corners, centre) or tiled diagonally, opacity, size (% of the photo width), text colour
 *  - The untouched original of every product photo is kept privately in storage/private/originals/...,
 *    so changing the settings later re-applies the watermark to the ORIGINAL (never a second watermark on top).
 * Needs the GD extension (with FreeType for text). When GD is missing, photos are saved without a watermark.
 */
final class Watermark
{
    public const POSITIONS = ['bottom_right', 'bottom_left', 'top_right', 'top_left', 'center', 'tiled'];
    public const FONT = APP_PATH . '/resources/fonts/Inter.ttf';

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    public static function enabled(): bool
    {
        return (bool) setting('watermark_enabled', false) && self::available();
    }

    /** Current settings, cleaned. */
    public static function config(): array
    {
        $pos = (string) setting('watermark_position', 'bottom_right');
        $color = (string) setting('watermark_color', '#0e7673');
        return [
            'type'     => setting('watermark_type', 'text') === 'logo' && self::logoPath() ? 'logo' : 'text',
            'text'     => mb_substr(trim((string) setting('watermark_text', '')) ?: 'SasthoBazar', 0, 40),
            'position' => in_array($pos, self::POSITIONS, true) ? $pos : 'bottom_right',
            'opacity'  => max(5, min(100, (int) setting('watermark_opacity', 45))),
            'size'     => max(5, min(80, (int) setting('watermark_size', 28))),
            'color'    => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#0e7673',
        ];
    }

    /** Absolute path of the uploaded watermark logo, or null. */
    public static function logoPath(): ?string
    {
        $rel = (string) setting('watermark_logo', '');
        if ($rel === '' || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
            return null;
        }
        $abs = ROOT_PATH . '/public/' . $rel;
        return is_file($abs) ? $abs : null;
    }

    /** Private copy of a product photo's original, e.g. storage/private/originals/uploads/products/2026/11/x.jpg */
    public static function originalPath(string $rel): string
    {
        return STORAGE_PATH . '/private/originals/' . $rel;
    }

    /**
     * Watermark a stored product photo (path relative to public/). Keeps the original first.
     * Safe to call again: the watermark is always drawn on the kept original.
     * @return bool true when a watermark was drawn
     */
    public static function applyToStored(string $rel): bool
    {
        if (!self::enabled() || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
            return false;
        }
        $abs = ROOT_PATH . '/public/' . $rel;
        if (!is_file($abs)) {
            return false;
        }
        // only real photos (JPG / PNG / WEBP); SVG drawings and GIFs are left as they are
        if (!in_array((@getimagesize($abs)['mime'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return false;
        }
        $orig = self::originalPath($rel);
        if (!is_file($orig)) {
            if (!is_dir(dirname($orig)) && !@mkdir(dirname($orig), 0770, true) && !is_dir(dirname($orig))) {
                return false;
            }
            if (!@copy($abs, $orig)) {
                return false;
            }
        }
        return self::render($orig, $abs, self::config());
    }

    /** Put back the original (no watermark) of a stored photo. */
    public static function restoreOriginal(string $rel): bool
    {
        $orig = self::originalPath($rel);
        $abs = ROOT_PATH . '/public/' . $rel;
        return is_file($orig) && !str_contains($rel, '..') && @copy($orig, $abs);
    }

    /** Remove the kept original when a product photo is deleted. */
    public static function forget(string $rel): void
    {
        if (str_starts_with($rel, 'uploads/') && !str_contains($rel, '..')) {
            $orig = self::originalPath($rel);
            if (is_file($orig)) {
                @unlink($orig);
            }
        }
    }

    /** Draw the watermark from $src onto a copy saved at $dest (same format). */
    public static function render(string $src, string $dest, array $cfg): bool
    {
        if (!self::available()) {
            return false;
        }
        $info = @getimagesize($src);
        if (!$info) {
            return false;
        }
        $mime = $info['mime'];
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default      => false, // GIF (may be animated): left as is
        };
        if (!$img) {
            return false;
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);
        $w = imagesx($img);
        $h = imagesy($img);

        $mark = $cfg['type'] === 'logo' ? self::logoLayer($cfg, $w) : self::textLayer($cfg, $w);
        if (!$mark) {
            imagedestroy($img);
            return false;
        }
        $mw = imagesx($mark);
        $mh = imagesy($mark);
        $pad = (int) max(8, round(min($w, $h) * 0.03));
        if ($cfg['position'] === 'tiled') {
            // diagonal rows across the whole photo
            $stepX = (int) ($mw * 1.6);
            $stepY = (int) max($mh * 3, $h / 4);
            for ($y = -$mh, $row = 0; $y < $h + $mh; $y += $stepY, $row++) {
                for ($x = ($row % 2) ? -(int) ($stepX / 2) : 0; $x < $w; $x += $stepX) {
                    imagecopy($img, $mark, $x, $y, 0, 0, $mw, $mh);
                }
            }
        } else {
            [$x, $y] = match ($cfg['position']) {
                'bottom_left' => [$pad, $h - $mh - $pad],
                'top_right'   => [$w - $mw - $pad, $pad],
                'top_left'    => [$pad, $pad],
                'center'      => [(int) (($w - $mw) / 2), (int) (($h - $mh) / 2)],
                default       => [$w - $mw - $pad, $h - $mh - $pad],
            };
            imagecopy($img, $mark, $x, $y, 0, 0, $mw, $mh);
        }
        imagedestroy($mark);

        $tmp = $dest . '.tmp' . bin2hex(random_bytes(3));
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $tmp, 88),
            'image/png'  => imagepng($img, $tmp, 6),
            'image/webp' => imagewebp($img, $tmp, 85),
        };
        imagedestroy($img);
        if (!$ok || !@rename($tmp, $dest)) {
            @unlink($tmp);
            return false;
        }
        @chmod($dest, 0644);
        return true;
    }

    /** Transparent layer with the text (soft shadow under it so it reads on light and dark photos). */
    private static function textLayer(array $cfg, int $photoW): ?GdImage
    {
        if (!function_exists('imagettftext') || !is_file(self::FONT)) {
            return null;
        }
        $targetW = max(40, (int) round($photoW * $cfg['size'] / 100));
        // find the font size that makes the text about $targetW wide
        $box = imagettfbbox(100, 0, self::FONT, $cfg['text']);
        $textW100 = max(1, abs($box[2] - $box[0]));
        $fontSize = max(6, 100 * $targetW / $textW100);
        $box = imagettfbbox($fontSize, 0, self::FONT, $cfg['text']);
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[7] - $box[1]);
        $shadow = (int) max(1, round($fontSize / 18));
        $lw = (int) ceil($tw + $shadow * 2 + 4);
        $lh = (int) ceil($th + $shadow * 2 + 4);
        $layer = imagecreatetruecolor($lw, $lh);
        imagealphablending($layer, false);
        imagefill($layer, 0, 0, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagealphablending($layer, true);
        // GD alpha: 0 = solid, 127 = invisible
        $alpha = (int) round(127 * (1 - $cfg['opacity'] / 100));
        $shadowAlpha = (int) min(127, $alpha + (127 - $alpha) * 0.45);
        [$r, $g, $b] = array_map('hexdec', str_split(substr($cfg['color'], 1), 2));
        $dark = ($r * 299 + $g * 587 + $b * 114) / 1000 > 140; // light text → dark shadow
        $sc = $dark ? [0, 0, 0] : [255, 255, 255];
        $baseX = (int) (2 - $box[0]);   // bbox: [6],[7] = upper-left corner relative to the baseline
        $baseY = (int) (2 - $box[7]);
        imagettftext($layer, $fontSize, 0, $baseX + $shadow, $baseY + $shadow, imagecolorallocatealpha($layer, $sc[0], $sc[1], $sc[2], $shadowAlpha), self::FONT, $cfg['text']);
        imagettftext($layer, $fontSize, 0, $baseX, $baseY, imagecolorallocatealpha($layer, $r, $g, $b, $alpha), self::FONT, $cfg['text']);
        return $layer;
    }

    /** Transparent layer with the logo, scaled to the chosen width, with the chosen opacity. */
    private static function logoLayer(array $cfg, int $photoW): ?GdImage
    {
        $path = self::logoPath();
        $info = $path ? @getimagesize($path) : false;
        if (!$info) {
            return null;
        }
        $logo = match ($info['mime']) {
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/jpeg' => @imagecreatefromjpeg($path),
            default      => false,
        };
        if (!$logo) {
            return null;
        }
        $targetW = max(30, (int) round($photoW * $cfg['size'] / 100));
        $targetH = max(1, (int) round(imagesy($logo) * $targetW / max(1, imagesx($logo))));
        $layer = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagefill($layer, 0, 0, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagecopyresampled($layer, $logo, 0, 0, 0, 0, $targetW, $targetH, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
        // apply opacity to every pixel's alpha
        $factor = $cfg['opacity'] / 100;
        if ($factor < 1) {
            for ($x = 0; $x < $targetW; $x++) {
                for ($y = 0; $y < $targetH; $y++) {
                    $c = imagecolorat($layer, $x, $y);
                    $a = ($c >> 24) & 0x7F;
                    $na = (int) round(127 - (127 - $a) * $factor);
                    imagesetpixel($layer, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
                }
            }
        }
        imagealphablending($layer, true);
        return $layer;
    }
}
