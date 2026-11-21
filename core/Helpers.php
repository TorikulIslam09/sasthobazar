<?php
/**
 * Helpers - static utility class + short global functions used in views/controllers.
 * escape, redirect, flash messages, money format, url, translation, settings, old input, CSV export.
 */
declare(strict_types=1);

final class Helpers
{
    private static array $lang = [];
    private static ?array $settings = null;

    /** HTML-escape any value for safe output. */
    public static function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Redirect to an app path ('admin/roles'), a based path ('/sasthobazar/admin') or a full URL. Exits. */
    public static function redirect(string $to, int $code = 302): void
    {
        $alreadyBased = BASE_PATH !== '' && ($to === BASE_PATH || str_starts_with($to, BASE_PATH . '/'));
        if (!preg_match('#^https?://#i', $to) && !$alreadyBased) {
            $to = self::url($to);
        }
        header('Location: ' . $to, true, $code);
        exit;
    }

    public static function back(): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Only redirect back to our own host (avoid open redirect)
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
            header('Location: ' . $ref, true, 302);
            exit;
        }
        self::redirect('/');
    }

    /* ---------------- Flash messages ---------------- */

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    /** Returns all flash messages and clears them. ['success' => [...], 'error' => [...]] */
    public static function getFlashes(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function setOld(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_csrf'],
              $input['mail_password'], $input['sms_api_key'], $input['db_pass'], $input['admin_pass'], $input['admin_pass_confirmation']);
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, string $default = ''): string
    {
        $v = $_SESSION['_old'][$key] ?? $default;
        return is_scalar($v) ? (string) $v : $default;
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old']);
    }

    /* ---------------- Money / numbers ---------------- */

    /** Format BDT: money(1234.5) => "৳1,234.50" (Bengali digits when UI is Bangla). */
    public static function money(float|int|string|null $amount, ?bool $bnDigits = null, bool $symbol = true): string
    {
        $amount = (float) ($amount ?? 0);
        $neg = $amount < 0;
        $str = self::bdNumberFormat(abs($amount), 2);
        if ($bnDigits ?? (self::lang() === 'bn')) {
            $str = self::bnDigits($str);
        }
        return ($neg ? '-' : '') . ($symbol ? '৳' : '') . $str;
    }

    /** South-Asian grouping: 12,34,567.00 */
    public static function bdNumberFormat(float $n, int $decimals = 2): string
    {
        $fixed = number_format($n, $decimals, '.', '');
        [$int, $dec] = array_pad(explode('.', $fixed), 2, '');
        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = substr($int, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest . ',' . $last3;
        }
        return $decimals > 0 ? $int . '.' . $dec : $int;
    }

    public static function bnDigits(string|int|float $s): string
    {
        return strtr((string) $s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }

    /** Number in the current UI language's digits. */
    public static function num(string|int|float $n): string
    {
        return self::lang() === 'bn' ? self::bnDigits($n) : (string) $n;
    }

    /** Date/time for display, e.g. "20 Nov 2026, 09:15 PM" (Bangla digits in bn UI). */
    public static function dt(?string $datetime, string $format = 'd M Y, h:i A'): string
    {
        if (!$datetime) {
            return '—';
        }
        $ts = strtotime($datetime);
        return $ts ? self::num(date($format, $ts)) : '—';
    }

    /* ---------------- URLs / request ---------------- */

    public static function url(string $path = ''): string
    {
        return BASE_PATH . '/' . ltrim($path, '/');
    }

    /** Full URL with scheme and host (for emails / SMS). Uses the current host, else config app.url. */
    public static function absoluteUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $rel = str_starts_with($path, '/') ? $path : self::url($path);
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '' && preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $scheme . '://' . $host . $rel;
        }
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        return preg_replace('#^(https?://[^/]+).*$#', '$1', $base) . $rel;
    }

    public static function asset(string $path): string
    {
        $file = ROOT_PATH . '/public/' . ltrim($path, '/');
        $v = is_file($file) ? '?v=' . filemtime($file) : '';
        return self::url($path) . $v;
    }

    /** Request path relative to the project root, e.g. "admin/roles/3/edit". */
    public static function currentPath(): string
    {
        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
            $path = substr($path, strlen(BASE_PATH));
        }
        return trim($path, '/');
    }

    public static function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** String-only query/body value (arrays are ignored). */
    public static function str(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    /**
     * Visitor IP. Only the real connection address is trusted, unless config app.trust_proxy is true
     * (site behind Cloudflare / a reverse proxy) - then CF-Connecting-IP / X-Forwarded-For is used.
     */
    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? (defined('IS_CLI') && IS_CLI ? '127.0.0.1' : '0.0.0.0'));
        if ((bool) config('app.trust_proxy', false)) {
            $fwd = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
            if ($fwd === '' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $fwd = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            }
            if ($fwd !== '' && filter_var($fwd, FILTER_VALIDATE_IP)) {
                $ip = $fwd;
            }
        }
        return substr($ip, 0, 45);
    }

    /* ---------------- Language ---------------- */

    public static function lang(): string
    {
        return $_SESSION['lang'] ?? config('app.locale', 'bn');
    }

    public static function trans(string $key, array $replace = []): string
    {
        $lang = self::lang();
        if (!isset(self::$lang[$lang])) {
            $file = APP_PATH . '/lang/' . $lang . '.php';
            self::$lang[$lang] = is_file($file) ? require $file : [];
        }
        $text = self::$lang[$lang][$key] ?? $key;
        if ($key === 'app_name') { // site name from Settings → General
            $site = trim((string) self::setting($lang === 'bn' ? 'site_name_bn' : 'site_name', ''));
            $text = $site !== '' ? $site : $text;
        }
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return $text;
    }

    /** Pick Bangla or English value: tr('ওষুধ', 'Medicine') */
    public static function tr(?string $bn, ?string $en): string
    {
        return (string) (self::lang() === 'bn' ? ($bn ?: $en) : ($en ?: $bn));
    }

    /* ---------------- Settings (DB table `settings`) ---------------- */

    public static function setting(string $key, mixed $default = null): mixed
    {
        if (self::$settings === null) {
            self::$settings = [];
            try {
                foreach (Database::getInstance()->fetchAll('SELECT `key`, `value`, `type` FROM settings') as $row) {
                    self::$settings[$row['key']] = self::castSetting($row['value'], $row['type']);
                }
            } catch (Throwable) {
                // DB not ready (e.g. during install) - fall back to defaults
            }
        }
        return self::$settings[$key] ?? $default;
    }

    public static function clearSettingsCache(): void
    {
        self::$settings = null;
    }

    private static function castSetting(?string $v, string $type): mixed
    {
        return match ($type) {
            'int'     => (int) $v,
            'decimal' => (float) $v,
            'bool'    => in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true),
            'json'    => json_decode((string) $v, true),
            default   => $v,
        };
    }

    /* ---------------- CSV export ---------------- */

    /**
     * Stream a CSV download (UTF-8 with BOM so Excel shows Bangla correctly). Exits.
     * @param iterable $rows each row = array of scalars
     */
    public static function csvDownload(string $filename, array $header, iterable $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($rows as $row) {
            // Neutralise spreadsheet formula injection (=, +, -, @ at cell start)
            fputcsv($out, array_map(static function ($v) {
                $v = (string) ($v ?? '');
                return ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && !is_numeric($v)) ? "'" . $v : $v;
            }, $row));
        }
        fclose($out);
        exit;
    }

    /* ---------------- Misc ---------------- */

    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\x{0980}-\x{09FF}]+/u', '-', $text);
        return trim((string) $text, '-') ?: bin2hex(random_bytes(4));
    }
}

/* =================== Global shortcuts =================== */

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** URL path of the project root, e.g. "/sasthobazar" (empty string when at web root). */
function detect_base_path(): string
{
    $configured = config('app.base_path');
    if (is_string($configured)) {
        return rtrim($configured, '/');
    }
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $root = str_replace('\\', '/', (string) realpath(ROOT_PATH));
    if ($docRoot !== '' && stripos($root, $docRoot) === 0) {
        $base = substr($root, strlen(rtrim($docRoot, '/')));
        return rtrim('/' . ltrim($base, '/'), '/');
    }
    return '';
}

function e(mixed $value): string { return Helpers::escape($value); }
function url(string $path = ''): string { return Helpers::url($path); }
function asset(string $path): string { return Helpers::asset($path); }
function redirect(string $to, int $code = 302): void { Helpers::redirect($to, $code); }
function back(): void { Helpers::back(); }
function flash(string $type, string $message): void { Helpers::flash($type, $message); }
function money(float|int|string|null $amount, ?bool $bnDigits = null): string { return Helpers::money($amount, $bnDigits); }
function bn_digits(string|int|float $s): string { return Helpers::bnDigits($s); }
/** Bengali digits ০-৯ -> 0-9 (customers often type numbers / codes with a Bangla keyboard). */
function bn_to_en_digits(string $s): string { return strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']); }
function num(string|int|float $n): string { return Helpers::num($n); }
function dt(?string $datetime, string $format = 'd M Y, h:i A'): string { return Helpers::dt($datetime, $format); }
function __(string $key, array $replace = []): string { return Helpers::trans($key, $replace); }
function tr(?string $bn, ?string $en): string { return Helpers::tr($bn, $en); }
function lang(): string { return Helpers::lang(); }
function setting(string $key, mixed $default = null): mixed { return Helpers::setting($key, $default); }
function old(string $key, string $default = ''): string { return Helpers::old($key, $default); }
function csrf_field(): string { return CSRF::field(); }
function csrf_token(): string { return CSRF::token(); }
/** Permission check for views/controllers: can('products.edit') or can('a.view|b.view') */
function can(string $permission): bool { return Auth::can($permission); }
