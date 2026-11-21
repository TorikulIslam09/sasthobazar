<?php
declare(strict_types=1);

/** Code 128 (set B) barcode as inline SVG - for shipping labels and manifests. No external library. */
final class Barcode
{
    /** Bar/space widths for Code 128 values 0..106 (106 = stop). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public static function code128Svg(string $text, int $height = 50, float $module = 1.6, bool $showText = true): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        $codes = [104]; // start B
        foreach (str_split($text) as $ch) {
            $codes[] = ord($ch) - 32;
        }
        $sum = 104;
        foreach (array_slice($codes, 1) as $i => $c) {
            $sum += $c * ($i + 1);
        }
        $codes[] = $sum % 103;
        $codes[] = 106;

        $x = 10 * $module; // quiet zone
        $rects = '';
        foreach ($codes as $c) {
            foreach (str_split(self::PATTERNS[$c]) as $i => $w) {
                $wpx = (int) $w * $module;
                if ($i % 2 === 0) {
                    $rects .= sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d"/>', $x, $wpx, $height);
                }
                $x += $wpx;
            }
        }
        $width = $x + 10 * $module;
        $total = $height + ($showText ? 16 : 0);
        $label = $showText ? sprintf('<text x="%.2f" y="%d" font-family="monospace" font-size="13" text-anchor="middle" fill="#000">%s</text>', $width / 2, $height + 13, htmlspecialchars($text, ENT_QUOTES)) : '';
        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%.0f" height="%d" viewBox="0 0 %.2f %d" role="img" aria-label="%s"><g fill="#000">%s</g>%s</svg>',
            $width, $total, $width, $total, htmlspecialchars($text, ENT_QUOTES), $rects, $label);
    }
}
