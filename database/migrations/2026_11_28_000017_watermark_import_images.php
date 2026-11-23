<?php
/**
 * Product photo watermark (Admin → Settings → Watermark) + photos in the product CSV import.
 *  - settings (group watermark): watermark_enabled, watermark_type (text|logo), watermark_text, watermark_logo,
 *    watermark_position, watermark_opacity, watermark_size, watermark_color
 *  - originals of watermarked photos are kept in storage/private/originals (created on demand)
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $st = $pdo->prepare("INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES ('watermark', ?, ?, ?, ?, 0)");
    foreach ([
        ['watermark_enabled', '0', 'bool'], ['watermark_type', 'text', 'string'], ['watermark_text', 'SasthoBazar', 'string'],
        ['watermark_logo', '', 'string'], ['watermark_position', 'bottom_right', 'string'], ['watermark_opacity', '45', 'int'],
        ['watermark_size', '28', 'int'], ['watermark_color', '#0e7673', 'string'],
    ] as [$k, $v, $t]) {
        $st->execute([$k, $v, $t, $k]);
    }
};
