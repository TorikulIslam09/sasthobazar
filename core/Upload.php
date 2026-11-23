<?php
/**
 * Upload - validated image uploads into public/uploads/{dir}/{Y}/{m}/.
 *
 *   foreach (Upload::files('images') as $f) {
 *       if ($err = Upload::validateImage($f)) { ... }
 *   }
 *   $path = Upload::storeImage($f, 'products');   // "uploads/products/2026/11/3f9c...e1.webp"
 *   echo url($path);
 *
 * Checks: PHP upload error, real upload (is_uploaded_file), size limit, MIME by content (finfo),
 * real image (getimagesize, dimension limits), no embedded PHP. File names are random; the
 * extension comes from the detected type, never from the client. public/uploads/.htaccess blocks scripts.
 */
declare(strict_types=1);

final class Upload
{
    public const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    public const MAX_DIMENSION = 6000;

    /**
     * Normalise $_FILES[$field] (single or multiple input) into a list of file arrays.
     * Empty file inputs (UPLOAD_ERR_NO_FILE) are skipped.
     */
    public static function files(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || !isset($f['error'])) {
            return [];
        }
        $out = [];
        if (is_array($f['error'])) {
            foreach (array_keys($f['error']) as $i) {
                if (is_array($f['error'][$i])) {
                    continue; // nested arrays are not supported
                }
                $out[] = [
                    'name'     => (string) ($f['name'][$i] ?? ''),
                    'type'     => (string) ($f['type'][$i] ?? ''),
                    'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''),
                    'error'    => (int) $f['error'][$i],
                    'size'     => (int) ($f['size'][$i] ?? 0),
                ];
            }
        } else {
            $out[] = [
                'name'     => (string) $f['name'],
                'type'     => (string) $f['type'],
                'tmp_name' => (string) $f['tmp_name'],
                'error'    => (int) $f['error'],
                'size'     => (int) $f['size'],
            ];
        }
        return array_values(array_filter($out, static fn($x) => $x['error'] !== UPLOAD_ERR_NO_FILE));
    }

    public static function maxBytes(): int
    {
        return (int) config('uploads.max_size_mb', 5) * 1024 * 1024;
    }

    /** @return string|null translated error message, or null when the image is acceptable */
    public static function validateImage(array $f, ?int $maxBytes = null): ?string
    {
        $maxBytes ??= self::maxBytes();
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
        if (!isset(self::IMAGE_TYPES[$mime])) {
            return __('upload_bad_type', ['name' => $name]);
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) {
            return __('upload_bad_type', ['name' => $name]);
        }
        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            return __('upload_too_big_dim', ['name' => $name, 'px' => self::MAX_DIMENSION]);
        }
        // Reject polyglot files (valid image header + embedded script)
        $raw = (string) file_get_contents($f['tmp_name']);
        if (preg_match('/<\?(php|=)|<script/i', $raw)) {
            return __('upload_bad_type', ['name' => $name]);
        }
        return null;
    }

    /**
     * Move an already-validated image. Returns path relative to public/, e.g. "uploads/brands/2026/11/ab12.png".
     * @throws RuntimeException when the file cannot be stored
     */
    public static function storeImage(array $f, string $dir): string
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $dir)) {
            throw new InvalidArgumentException('Invalid upload dir');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $ext = self::IMAGE_TYPES[$mime] ?? null;
        if ($ext === null) {
            throw new RuntimeException('Unsupported image type');
        }
        $rel = 'uploads/' . $dir . '/' . date('Y') . '/' . date('m');
        $abs = ROOT_PATH . '/public/' . $rel;
        if (!is_dir($abs) && !mkdir($abs, 0775, true) && !is_dir($abs)) {
            throw new RuntimeException('Cannot create upload directory');
        }
        $file = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $abs . '/' . $file)) {
            throw new RuntimeException('Cannot store uploaded file');
        }
        @chmod($abs . '/' . $file, 0644);
        return $rel . '/' . $file;
    }

    /** Delete a stored upload. Only paths inside public/uploads are touched. */
    public static function delete(?string $relPath): void
    {
        if (!$relPath || !str_starts_with($relPath, 'uploads/') || str_contains($relPath, '..')) {
            return;
        }
        $abs = ROOT_PATH . '/public/' . $relPath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    /**
     * Same checks as validateImage() for a file that did NOT come from a browser upload
     * (photo inside an import ZIP, or downloaded from a link). $name is only used in messages.
     */
    public static function validateImageFile(string $path, string $name, ?int $maxBytes = null): ?string
    {
        $maxBytes ??= self::maxBytes();
        $name = mb_substr(basename($name), 0, 80);
        $size = is_file($path) ? (int) filesize($path) : 0;
        if ($size <= 0) {
            return __('upload_failed', ['name' => $name]);
        }
        if ($size > $maxBytes) {
            return __('upload_too_large', ['name' => $name, 'mb' => round($maxBytes / 1048576, 1)]);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (!isset(self::IMAGE_TYPES[$mime])) {
            return __('upload_bad_type', ['name' => $name]);
        }
        $info = @getimagesize($path);
        if ($info === false || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) {
            return __('upload_bad_type', ['name' => $name]);
        }
        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            return __('upload_too_big_dim', ['name' => $name, 'px' => self::MAX_DIMENSION]);
        }
        if (preg_match('/<\?(php|=)|<script/i', (string) file_get_contents($path))) {
            return __('upload_bad_type', ['name' => $name]);
        }
        return null;
    }

    /** Copy an already-validated local image file into public/uploads/{dir}/{Y}/{m}/ (random name). */
    public static function storeImageFile(string $path, string $dir): string
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $dir)) {
            throw new InvalidArgumentException('Invalid upload dir');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        $ext = self::IMAGE_TYPES[$mime] ?? null;
        if ($ext === null) {
            throw new RuntimeException('Unsupported image type');
        }
        $rel = 'uploads/' . $dir . '/' . date('Y') . '/' . date('m');
        $abs = ROOT_PATH . '/public/' . $rel;
        if (!is_dir($abs) && !mkdir($abs, 0775, true) && !is_dir($abs)) {
            throw new RuntimeException('Cannot create upload directory');
        }
        $file = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!copy($path, $abs . '/' . $file)) {
            throw new RuntimeException('Cannot store image');
        }
        @chmod($abs . '/' . $file, 0644);
        return $rel . '/' . $file;
    }

    /** True if the request body was dropped because it exceeded post_max_size. */
    public static function postTooLarge(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !empty($_POST) || !empty($_FILES)) {
            return false;
        }
        // Only when PHP really dropped the body because of post_max_size (JSON bodies also leave $_POST empty)
        $limit = trim((string) ini_get('post_max_size'));
        $bytes = (int) $limit;
        $unit = strtolower(substr($limit, -1));
        $bytes *= match ($unit) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
        return $bytes > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $bytes;
    }
}
