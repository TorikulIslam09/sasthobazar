<?php
declare(strict_types=1);

/**
 * Photos for the product CSV import ("images" column):
 *   NAPA-500.jpg | NAPA-500-back.jpg          → files inside the ZIP uploaded together with the CSV (matched by file name, any folder)
 *   https://supplier.example.com/napa.jpg      → downloaded (public http/https addresses only)
 * Several photos per product are separated by | (the first one becomes the main photo).
 * Every photo is checked like a normal upload (real image, size, dimensions, no hidden script) and gets the watermark.
 */
final class ProductImageImport
{
    public const MAX_PER_PRODUCT = 8;
    public const MAX_ZIP_ENTRIES = 6000;
    public const MAX_DOWNLOADS = 600;      // per import (time limit); split very big catalogues into several files
    private const EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private int $downloads = 0;
    private ?ZipArchive $zip = null;

    public function __construct(private ?string $zipPath = null)
    {
    }

    /**
     * Index of the photos inside a ZIP: lowercase file name => entry name.
     * @return array{0: array<string,string>, 1: ?string} [index, error]
     */
    public static function zipIndex(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            return [[], __('imgimp_no_zip_ext')];
        }
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::RDONLY) !== true) {
            return [[], __('imgimp_bad_zip')];
        }
        if ($z->numFiles > self::MAX_ZIP_ENTRIES) {
            $z->close();
            return [[], __('imgimp_zip_too_many', ['n' => num(self::MAX_ZIP_ENTRIES)])];
        }
        $index = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = (string) $z->getNameIndex($i);
            $base = basename(str_replace('\\', '/', $name));
            if ($base === '' || str_ends_with($name, '/') || str_starts_with($base, '.') || str_contains($name, '__MACOSX')) {
                continue;
            }
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (in_array($ext, self::EXT, true)) {
                $index[mb_strtolower($base)] ??= $name; // first one wins if the same name is in two folders
            }
        }
        $z->close();
        return [$index, null];
    }

    /**
     * Parse an "images" cell. Errors are returned as messages (the row is rejected in the dry run / import).
     * @return array{0: array<int, array{kind:string, ref:string}>, 1: string[]}
     */
    public static function parseCell(string $cell, ?array $zipIndex): array
    {
        $items = array_values(array_filter(array_map('trim', preg_split('/\s*[|;\n]\s*/', $cell) ?: []), static fn($v) => $v !== ''));
        $out = [];
        $errors = [];
        if (count($items) > self::MAX_PER_PRODUCT) {
            $errors[] = __('imgimp_too_many', ['n' => num(self::MAX_PER_PRODUCT)]);
            $items = array_slice($items, 0, self::MAX_PER_PRODUCT);
        }
        foreach ($items as $it) {
            if (preg_match('#^https?://#i', $it)) {
                if (!filter_var($it, FILTER_VALIDATE_URL) || strlen($it) > 1000) {
                    $errors[] = __('imgimp_bad_url', ['url' => mb_strimwidth($it, 0, 60, '…')]);
                    continue;
                }
                $out[] = ['kind' => 'url', 'ref' => $it];
                continue;
            }
            $base = mb_strtolower(basename(str_replace('\\', '/', $it)));
            if ($zipIndex === null) {
                $errors[] = __('imgimp_need_zip', ['name' => mb_strimwidth($it, 0, 60, '…')]);
            } elseif (!isset($zipIndex[$base])) {
                $errors[] = __('imgimp_not_in_zip', ['name' => mb_strimwidth($it, 0, 60, '…')]);
            } else {
                $out[] = ['kind' => 'zip', 'ref' => $zipIndex[$base]];
            }
        }
        return [$out, $errors];
    }

    /**
     * Store the photos for one product (after the product row is saved).
     * $replace: remove the product's current photos first (only when at least one new photo was stored).
     * @return array{0: int, 1: string[]} [photos added, warning messages]
     */
    public function attach(int $productId, array $images, string $alt, bool $replace): array
    {
        $db = Database::getInstance();
        $stored = [];
        $warnings = [];
        foreach ($images as $img) {
            $tmp = null;
            try {
                [$tmp, $label, $err] = $img['kind'] === 'zip' ? $this->fromZip($img['ref']) : $this->download($img['ref']);
                if ($err === null) {
                    $err = Upload::validateImageFile($tmp, $label, Upload::maxBytes());
                }
                if ($err !== null) {
                    $warnings[] = $err;
                    continue;
                }
                $path = Upload::storeImageFile($tmp, 'products');
                Watermark::applyToStored($path);
                $stored[] = $path;
            } catch (Throwable $e) {
                $warnings[] = __('imgimp_failed', ['name' => mb_strimwidth(basename($img['ref']), 0, 60, '…')]);
                error_log('[product image import] ' . $e->getMessage());
            } finally {
                if ($tmp !== null && is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
        if (!$stored) {
            return [0, $warnings];
        }
        $old = [];
        if ($replace) {
            $old = array_column($db->fetchAll('SELECT path FROM product_images WHERE product_id = :p', ['p' => $productId]), 'path');
            $db->delete('product_images', 'product_id = :p', ['p' => $productId]);
        }
        $sort = (int) $db->fetchColumn('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = :p', ['p' => $productId]);
        $hadPrimary = (bool) $db->fetchColumn('SELECT 1 FROM product_images WHERE product_id = :p AND is_primary = 1', ['p' => $productId]);
        $firstId = null;
        foreach ($stored as $i => $path) {
            $id = $db->insert('product_images', ['product_id' => $productId, 'path' => $path, 'alt' => mb_substr($alt, 0, 255), 'sort_order' => $sort + $i + 1, 'is_primary' => 0]);
            $firstId ??= $id;
        }
        Product::normalizePrimaryImage($productId, $hadPrimary ? null : $firstId); // first imported photo = main photo when there was none
        foreach ($old as $p) {
            Upload::delete($p);
            Watermark::forget($p);
        }
        return [count($stored), $warnings];
    }

    /** @return array{0: ?string, 1: string, 2: ?string} [temp file, label, error] */
    private function fromZip(string $entry): array
    {
        if ($this->zip === null) {
            $this->zip = new ZipArchive();
            if ($this->zipPath === null || $this->zip->open($this->zipPath, ZipArchive::RDONLY) !== true) {
                return [null, $entry, __('imgimp_bad_zip')];
            }
        }
        $label = basename($entry);
        $stat = $this->zip->statName($entry);
        if (!$stat || $stat['size'] > Upload::maxBytes()) {
            return [null, $label, __('upload_too_large', ['name' => $label, 'mb' => round(Upload::maxBytes() / 1048576, 1)])];
        }
        $in = $this->zip->getStream($entry); // stream by entry name: no files are written from paths inside the ZIP
        if (!$in) {
            return [null, $label, __('imgimp_failed', ['name' => $label])];
        }
        $tmp = (string) tempnam(sys_get_temp_dir(), 'sbimg');
        $out = fopen($tmp, 'wb');
        $bytes = 0;
        while (!feof($in)) {
            $chunk = (string) fread($in, 65536);
            $bytes += strlen($chunk);
            if ($bytes > Upload::maxBytes()) {
                fclose($in);
                fclose($out);
                @unlink($tmp);
                return [null, $label, __('upload_too_large', ['name' => $label, 'mb' => round(Upload::maxBytes() / 1048576, 1)])];
            }
            fwrite($out, $chunk);
        }
        fclose($in);
        fclose($out);
        return [$tmp, $label, null];
    }

    /**
     * Download a photo from a public address. Refuses local / private network addresses, follows at most
     * 3 redirects (each checked again), max size = upload limit, 20 s per photo.
     * @return array{0: ?string, 1: string, 2: ?string}
     */
    private function download(string $url): array
    {
        $label = mb_strimwidth(basename((string) parse_url($url, PHP_URL_PATH)) ?: $url, 0, 60, '…');
        if (++$this->downloads > self::MAX_DOWNLOADS) {
            return [null, $label, __('imgimp_download_limit', ['n' => num(self::MAX_DOWNLOADS)])];
        }
        if (!function_exists('curl_init')) {
            return [null, $label, __('imgimp_no_curl')];
        }
        $max = Upload::maxBytes();
        for ($hop = 0; $hop < 4; $hop++) {
            $p = parse_url($url);
            $scheme = strtolower((string) ($p['scheme'] ?? ''));
            $host = (string) ($p['host'] ?? '');
            if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
                return [null, $label, __('imgimp_bad_url', ['url' => $label])];
            }
            $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
            $ip = self::publicIp($host);
            if ($ip === null) {
                return [null, $label, __('imgimp_blocked_host', ['host' => $host])];
            }
            $tmp = (string) tempnam(sys_get_temp_dir(), 'sbimg');
            $fh = fopen($tmp, 'wb');
            $bytes = 0;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fh,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . $ip], // connect to the address we checked
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopImageImport/1.0)',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_NOPROGRESS     => false,
                CURLOPT_PROGRESSFUNCTION => static function ($c, $dlTotal, $dlNow) use ($max) {
                    return ($dlTotal > $max || $dlNow > $max) ? 1 : 0; // abort when too big
                },
            ]);
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            fclose($fh);
            if ($ok && in_array($code, [301, 302, 303, 307, 308], true) && $redirect !== '') {
                @unlink($tmp);
                $url = $redirect;
                continue;
            }
            if (!$ok || $code !== 200) {
                @unlink($tmp);
                return [null, $label, __('imgimp_download_failed', ['name' => $label, 'code' => $code ?: '-'])];
            }
            return [$tmp, $label, null];
        }
        return [null, $label, __('imgimp_download_failed', ['name' => $label, 'code' => 'redirects'])];
    }

    /** The host's address if it is a public internet address (not localhost / private / reserved), else null. */
    private static function publicIp(string $host): ?string
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null; // any private answer → refuse (DNS tricks)
            }
        }
        return $ips[0] ?? null;
    }

    public function __destruct()
    {
        if ($this->zip instanceof ZipArchive) {
            @$this->zip->close();
        }
    }
}
