<?php
declare(strict_types=1);

/**
 * Private uploads (image or PDF) stored OUTSIDE the web root: storage/private/{dir}/{Y}/{m}/{random}.{ext}.
 * Only streamed through a controller after an access check. Used for vendor documents.
 */
final class PrivateFile
{
    public const TYPES = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @return string|null translated error or null when fine */
    public static function validate(array $f, int $maxBytes = self::MAX_BYTES): ?string
    {
        $name = mb_substr(basename((string) ($f['name'] ?? '')), 0, 80);
        $mb = round($maxBytes / 1048576, 1);
        if (in_array($f['error'] ?? -1, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return __('upload_too_large', ['name' => $name, 'mb' => $mb]);
        }
        if (($f['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            return __('upload_failed', ['name' => $name]);
        }
        if ((int) $f['size'] <= 0 || (int) $f['size'] > $maxBytes) {
            return __('upload_too_large', ['name' => $name, 'mb' => $mb]);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        if (!isset(self::TYPES[$mime])) {
            return __('rx_bad_type', ['name' => $name]);
        }
        $raw = (string) file_get_contents($f['tmp_name']);
        if ($mime === 'application/pdf') {
            if (!str_starts_with($raw, '%PDF-') || preg_match('#/(JavaScript|JS|Launch|EmbeddedFile)\b#', $raw)) {
                return __('rx_bad_type', ['name' => $name]);
            }
        } else {
            $info = @getimagesize($f['tmp_name']);
            if ($info === false || ($info['mime'] ?? '') !== $mime || $info[0] > Upload::MAX_DIMENSION || $info[1] > Upload::MAX_DIMENSION) {
                return __('rx_bad_type', ['name' => $name]);
            }
        }
        if (preg_match('/<\?(php|=)|<script/i', $raw)) {
            return __('rx_bad_type', ['name' => $name]);
        }
        return null;
    }

    /** Store a validated upload. @return array{path: string, mime: string, name: string, size: int} */
    public static function store(array $f, string $dir): array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $dir)) {
            throw new InvalidArgumentException('Bad private dir');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $ext = self::TYPES[$mime] ?? null;
        if ($ext === null) {
            throw new RuntimeException('Unsupported file type');
        }
        $rel = $dir . '/' . date('Y') . '/' . date('m');
        $abs = STORAGE_PATH . '/private/' . $rel;
        if (!is_dir($abs) && !mkdir($abs, 0775, true) && !is_dir($abs)) {
            throw new RuntimeException('Cannot create directory');
        }
        $file = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $abs . '/' . $file)) {
            throw new RuntimeException('Cannot store file');
        }
        @chmod($abs . '/' . $file, 0640);
        return ['path' => $rel . '/' . $file, 'mime' => $mime, 'name' => mb_substr(basename((string) $f['name']), 0, 150), 'size' => (int) $f['size']];
    }

    public static function absPath(string $rel): ?string
    {
        if (!preg_match('#^[a-z0-9-]+/\d{4}/\d{2}/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#', $rel)) {
            return null;
        }
        return STORAGE_PATH . '/private/' . $rel;
    }

    public static function delete(?string $rel): void
    {
        $abs = $rel ? self::absPath($rel) : null;
        if ($abs && is_file($abs)) {
            @unlink($abs);
        }
    }

    /** Stream inline and exit. */
    public static function stream(string $rel, string $downloadName): void
    {
        $abs = self::absPath($rel);
        if (!$abs || !is_file($abs)) {
            http_response_code(404);
            exit;
        }
        $ext = pathinfo($abs, PATHINFO_EXTENSION);
        $mime = array_search($ext, self::TYPES, true) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $downloadName) . '.' . $ext . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        if ($mime !== 'application/pdf') {
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        }
        readfile($abs);
        exit;
    }
}
