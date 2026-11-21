<?php
/**
 * SasthoBazar bootstrap - loaded by every entry point (public, admin, vendor-panel, install, CLI scripts).
 */
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', ROOT_PATH . '/core');
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/views');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('IS_CLI', PHP_SAPI === 'cli');

/* ---------- Autoloader: core/, app/controllers/, app/models/ ---------- */
spl_autoload_register(static function (string $class): void {
    $class = basename(str_replace('\\', '/', $class));
    foreach ([CORE_PATH, APP_PATH . '/controllers', APP_PATH . '/models'] as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

require_once CORE_PATH . '/Helpers.php';

/* ---------- Config ---------- */
$GLOBALS['__config'] = require CONFIG_PATH . '/config.php';
$GLOBALS['__config']['db'] = require CONFIG_PATH . '/database.php';
$GLOBALS['__config']['modules'] = require CONFIG_PATH . '/modules.php';

date_default_timezone_set(config('app.timezone', 'Asia/Dhaka'));
mb_internal_encoding('UTF-8');

/* ---------- HTTPS + host ---------- */
define('IS_HTTPS', !IS_CLI && (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    // behind a trusted proxy / load balancer (Cloudflare, Nginx in front of Apache): set app.trust_proxy = true
    || ((bool) config('app.trust_proxy', false) && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
));
$host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
define('IS_LOCAL', IS_CLI || in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local'));

/* ---------- Errors ---------- */
// Error details are shown only on a local machine (or when app.debug_anywhere is true) - never on a live site by mistake.
$debug = (bool) config('app.debug', false) && (IS_LOCAL || (bool) config('app.debug_anywhere', false));
error_reporting(E_ALL);
ini_set('display_errors', ($debug || IS_CLI) ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0775, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(static function (Throwable $e) use ($debug): void {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (IS_CLI) {
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    if ($debug) {
        echo '<pre style="padding:16px;background:#fff3f3;color:#900;white-space:pre-wrap">'
            . e(get_class($e) . ': ' . $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString())
            . '</pre>';
    } else {
        $ref = strtoupper(substr(sha1($e->getFile() . $e->getLine() . microtime()), 0, 8));
        error_log('[ref ' . $ref . '] ' . get_class($e) . ' (see the line above)');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>500</title></head>'
            . '<body style="font-family:system-ui,sans-serif;background:#f2f5f5;color:#1f2a2a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">'
            . '<div style="text-align:center;padding:24px"><h1 style="font-size:48px;margin:0;color:#0e7673">500</h1>'
            . '<p>Something went wrong. Please try again in a moment.<br>কিছু একটা সমস্যা হয়েছে, একটু পরে আবার চেষ্টা করুন।</p>'
            . '<p style="color:#6b7a7a;font-size:13px">Ref: ' . $ref . '</p><p><a href="' . (defined('BASE_PATH') ? BASE_PATH : '') . '/" style="color:#0e7673">Home / হোম</a></p></div></body></html>';
    }
    exit;
});

/* ---------- Base path (URL prefix of the project root, e.g. "/sasthobazar") ---------- */
define('BASE_PATH', IS_CLI ? '' : detect_base_path());

/* ---------- Installed? ---------- */
define('IS_INSTALLED', is_file(CONFIG_PATH . '/installed.lock'));
if (!IS_INSTALLED && !IS_CLI && !defined('SKIP_INSTALL_CHECK')) {
    header('Location: ' . BASE_PATH . '/install/');
    exit;
}

if (IS_CLI) {
    $_SESSION = [];
    return; // no session / headers for command-line scripts
}

/* ---------- Session ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(config('session.name', 'SBSESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'secure'   => IS_HTTPS || (bool) config('session.secure', false), // never sent over plain HTTP on an HTTPS site
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_start();

    // Idle timeout
    $now = time();
    if (isset($_SESSION['_last_activity']) && ($now - $_SESSION['_last_activity']) > (int) config('session.lifetime', 7200)) {
        $lang = $_SESSION['lang'] ?? null;
        session_unset();
        session_regenerate_id(true);
        if ($lang) {
            $_SESSION['lang'] = $lang;
        }
    }
    $_SESSION['_last_activity'] = $now;
}

/* ---------- Language (?lang=en / ?lang=bn persists in session) ---------- */
if (isset($_GET['lang']) && in_array($_GET['lang'], config('app.locales', ['bn', 'en']), true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

/* ---------- Security headers ---------- */
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    // Scripts / styles only from this site and the two CDNs the pages use. Inline scripts are still needed by a few pages.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; "
        . "img-src 'self' data: https:; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    if (IS_HTTPS) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('Content-Type: text/html; charset=UTF-8');
}
