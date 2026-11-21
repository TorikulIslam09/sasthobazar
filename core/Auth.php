<?php
/**
 * Auth - session based authentication + role/permission checks.
 *
 * user_type: super_admin | employee | vendor | customer
 *  - super_admin : every permission (can() is always true)
 *  - employee    : permissions from role_permissions of users.role_id
 *  - vendor      : vendor panel
 *  - customer    : storefront account
 *
 * Login protection (three layers):
 *  1. Per IP           : max 20 failed attempts / 15 min from one IP (any account)
 *  2. Per login + IP   : max 5 failed attempts / 15 min for one account from one IP
 *  3. Per account      : 5 consecutive failures lock the account for 15 min (users.locked_until)
 * Session id is regenerated on login and logout. Changing a password invalidates the user's other sessions.
 */
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;
    private static ?array $permissions = null;
    private static array|null|false $vendor = false;

    private const GUARD_TYPES = [
        'admin'    => ['super_admin', 'employee'],
        'vendor'   => ['vendor'],
        'customer' => ['customer'],
    ];

    private const IP_MAX = 20;
    private const LOGIN_MAX = 5;
    private const DECAY = 900; // 15 minutes

    /**
     * Attempt login by email OR phone.
     * @param string[] $allowedTypes user_types allowed on this login form
     * @return true|string  true on success, translated error message otherwise
     */
    public static function attempt(string $login, string $password, array $allowedTypes): bool|string
    {
        $db = Database::getInstance();
        $login = trim($login);
        $field = str_contains($login, '@') ? 'email' : 'phone';
        $login = $field === 'phone' ? self::normalizePhone($login) : strtolower($login);

        // ---- Rate limiting (before touching the users table) ----
        $ip = Helpers::clientIp();
        $ipKey = 'login:ip:' . $ip;
        $loginKey = 'login:id:' . $login . '|' . $ip;
        foreach ([[$ipKey, self::IP_MAX], [$loginKey, self::LOGIN_MAX]] as [$key, $max]) {
            if (RateLimiter::tooMany($key, $max)) {
                $mins = (int) ceil(RateLimiter::availableIn($key) / 60);
                Audit::log('login_throttled', 'user', null, null, ['login' => $login]);
                return __('auth_throttled', ['minutes' => max(1, $mins)]);
            }
        }
        $fail = static function () use ($ipKey, $loginKey): string {
            RateLimiter::hit($ipKey, self::DECAY);
            RateLimiter::hit($loginKey, self::DECAY);
            return __('auth_failed'); // same message for every failure (no user enumeration)
        };

        $user = $db->fetch(
            "SELECT * FROM users WHERE `{$field}` = :login AND deleted_at IS NULL LIMIT 1",
            ['login' => $login]
        );

        if (!$user) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // timing equalizer
            return $fail();
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $mins = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            return __('auth_locked', ['minutes' => $mins]);
        }

        if (!password_verify($password, $user['password_hash'])) {
            $fails = (int) $user['failed_logins'] + 1;
            $max = (int) config('security.max_login_attempts', 5);
            $lock = $fails >= $max ? date('Y-m-d H:i:s', time() + 60 * (int) config('security.lockout_minutes', 15)) : null;
            $db->update('users', ['failed_logins' => $lock ? 0 : $fails, 'locked_until' => $lock], 'id = :id', ['id' => $user['id']]);
            Audit::log($lock ? 'login_locked' : 'login_failed', 'users', (int) $user['id'], null, null, (int) $user['id']);
            return $fail();
        }

        if (!in_array($user['user_type'], $allowedTypes, true)) {
            return $fail();
        }

        if ($user['status'] !== 'active') {
            return __('auth_inactive');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $db->update('users', ['password_hash' => $user['password_hash']], 'id = :id', ['id' => $user['id']]);
        }

        RateLimiter::clear($loginKey);
        self::login($user);
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        CSRF::regenerate();
        $_SESSION['auth_id'] = (int) $user['id'];
        $_SESSION['auth_type'] = $user['user_type'];
        $_SESSION['auth_pw'] = self::passwordFingerprint($user['password_hash']);
        $_SESSION['auth_at'] = time();
        if (!empty($user['lang'])) {
            $_SESSION['lang'] = $user['lang'];
        }
        Database::getInstance()->update('users', [
            'failed_logins' => 0,
            'locked_until'  => null,
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => Helpers::clientIp(),
        ], 'id = :id', ['id' => $user['id']]);

        self::reset();
        Audit::log('login', 'users', (int) $user['id'], null, null, (int) $user['id']);
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'users', (int) self::id());
        }
        $lang = $_SESSION['lang'] ?? null;
        $_SESSION = [];
        session_regenerate_id(true);
        CSRF::regenerate();
        if ($lang) {
            $_SESSION['lang'] = $lang;
        }
        self::reset();
        self::$loaded = true;
    }

    /** Call after the current user's password changes so this session stays valid. */
    public static function refreshPasswordFingerprint(string $newHash): void
    {
        session_regenerate_id(true);
        $_SESSION['auth_pw'] = self::passwordFingerprint($newHash);
    }

    private static function passwordFingerprint(string $hash): string
    {
        return hash('sha256', $hash);
    }

    private static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
        self::$permissions = null;
        self::$vendor = false;
    }

    /** Logged-in user row (fresh from DB once per request) or null. */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (empty($_SESSION['auth_id'])) {
            return null;
        }
        $u = Database::getInstance()->fetch(
            "SELECT u.id, u.role_id, u.user_type, u.name, u.email, u.phone, u.avatar, u.status, u.lang, u.password_hash,
                    r.name AS role_name, r.name_bn AS role_name_bn, r.slug AS role_slug
               FROM users u LEFT JOIN roles r ON r.id = u.role_id
              WHERE u.id = :id AND u.deleted_at IS NULL",
            ['id' => (int) $_SESSION['auth_id']]
        );
        // Kick out if: deleted, disabled, or password changed elsewhere
        if (!$u || $u['status'] !== 'active'
            || !hash_equals((string) ($_SESSION['auth_pw'] ?? ''), self::passwordFingerprint($u['password_hash']))) {
            unset($_SESSION['auth_id'], $_SESSION['auth_type'], $_SESSION['auth_pw']);
            return null;
        }
        unset($u['password_hash']);
        self::$user = $u;
        return $u;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function type(): ?string
    {
        return self::user()['user_type'] ?? null;
    }

    public static function isSuperAdmin(): bool
    {
        return self::type() === 'super_admin';
    }

    public static function isStaff(): bool
    {
        return in_array(self::type(), ['super_admin', 'employee'], true);
    }

    public static function roleName(): string
    {
        $u = self::user();
        if (!$u) {
            return '';
        }
        return (lang() === 'bn' && !empty($u['role_name_bn'])) ? $u['role_name_bn'] : (string) ($u['role_name'] ?? $u['user_type']);
    }

    /** Permission keys like 'products.view'. Super admin => every permission. */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $u = self::user();
        if (!$u) {
            return self::$permissions = [];
        }
        $db = Database::getInstance();
        if ($u['user_type'] === 'super_admin') {
            $rows = $db->fetchAll('SELECT `key` FROM permissions');
        } elseif ($u['user_type'] === 'employee' && $u['role_id']) {
            $rows = $db->fetchAll(
                'SELECT p.`key` FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r',
                ['r' => (int) $u['role_id']]
            );
        } else {
            $rows = [];
        }
        return self::$permissions = array_column($rows, 'key');
    }

    /** can('products.edit'); also accepts 'a.view|b.view' (any). */
    public static function can(string $permission): bool
    {
        if (self::isSuperAdmin()) {
            return true;
        }
        if (self::type() !== 'employee') {
            return false;
        }
        $mine = self::permissions();
        foreach (explode('|', $permission) as $p) {
            if (in_array(trim($p), $mine, true)) {
                return true;
            }
        }
        return false;
    }

    /** True if the user has any permission in the given module (e.g. 'products'). */
    public static function canModule(string $module): bool
    {
        if (self::isSuperAdmin()) {
            return true;
        }
        foreach (self::permissions() as $p) {
            if (str_starts_with($p, $module . '.')) {
                return true;
            }
        }
        return false;
    }

    /** Vendor row of the logged-in vendor user. */
    public static function vendor(): ?array
    {
        if (self::$vendor !== false) {
            return self::$vendor;
        }
        if (self::type() !== 'vendor') {
            return self::$vendor = null;
        }
        return self::$vendor = Database::getInstance()->fetch('SELECT * FROM vendors WHERE user_id = :u', ['u' => self::id()]);
    }

    /* ---------------- Guards ---------------- */

    public static function requireGuard(string $guard): void
    {
        $types = self::GUARD_TYPES[$guard] ?? [];
        if (!self::check()) {
            if (Helpers::isAjax()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => __('login_required')], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? null;
            }
            Helpers::flash('warning', __('login_required'));
            Helpers::redirect(self::loginUrl($guard));
        }
        if (!in_array(self::type(), $types, true)) {
            self::wrongPanel($guard); // logged in, but with another kind of account
        }
    }

    /** Which panel a guard belongs to, from the user type. */
    public static function guardOf(?string $type): string
    {
        return match ($type) {
            'super_admin', 'employee' => 'admin',
            'vendor'                  => 'vendor',
            default                   => 'customer',
        };
    }

    /**
     * Logged in with an account of another panel (e.g. admin opening the vendor panel):
     * explain it and offer to log out and continue, instead of a bare 403.
     */
    public static function wrongPanel(string $guard): void
    {
        if (Helpers::isAjax() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            self::forbidden();
        }
        http_response_code(403);
        View::render('errors/wrong_panel', [
            'title'   => __('wrong_panel_title'),
            'guard'   => $guard,
            'current' => self::guardOf(self::type()),
            'next'    => self::loginUrl($guard),
        ], 'blank');
        exit;
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            self::forbidden();
        }
    }

    public static function forbidden(): void
    {
        http_response_code(403);
        if (Helpers::isAjax()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => __('forbidden')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        View::render('errors/403', ['title' => '403'], 'blank');
        exit;
    }

    public static function loginUrl(string $guard): string
    {
        return match ($guard) {
            'admin'  => 'admin/login',
            'vendor' => 'vendor-panel/login',
            default  => 'login',
        };
    }

    /** Where to send a logged-in user. */
    public static function homeUrl(): string
    {
        return match (self::type()) {
            'super_admin', 'employee' => 'admin',
            'vendor'                  => 'vendor-panel',
            default                   => '/',
        };
    }

    /** Pull and clear the URL the user tried to open before login (same-site paths only). */
    public static function intendedUrl(string $fallback): string
    {
        $u = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);
        if (is_string($u) && str_starts_with($u, '/') && !str_starts_with($u, '//') && !str_contains($u, '\\')) {
            return $u;
        }
        return Helpers::url($fallback);
    }

    /* ---------------- Utilities ---------------- */

    /** Normalise BD mobile numbers to 01XXXXXXXXX. */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', strtr($phone, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']));
        if (str_starts_with((string) $digits, '880')) {
            $digits = substr((string) $digits, 2);
        }
        return (string) $digits;
    }

    public static function isValidBdPhone(string $phone): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', self::normalizePhone($phone));
    }

    /** Kept for backward compatibility - use Audit::log(). */
    public static function audit(string $action, ?string $entityType = null, ?int $entityId = null, ?array $old = null, ?array $new = null, ?int $userId = null): void
    {
        Audit::log($action, $entityType, $entityId, $old, $new, $userId);
    }
}
