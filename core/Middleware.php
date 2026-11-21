<?php
/**
 * Middleware - runs before a route handler and blocks unauthorized requests.
 *
 * Available middleware (used through Router options):
 *   guard => 'guest'          only logged-out users (logged-in users are sent to their home)
 *   guard => 'admin|vendor|customer'   must be logged in with that user type (else login page / 403)
 *   'can:products.edit'       employee must have the permission (super admin always passes)
 *   'can:a.view|b.view'       any one of the permissions
 *   'super_admin'             super admin only
 *
 * Unauthorized => 403 page (or JSON {"error": ...} for AJAX requests).
 */
declare(strict_types=1);

final class Middleware
{
    public static function run(array $opts): void
    {
        $guard = $opts['guard'] ?? null;
        if ($guard === 'guest') {
            self::guest();
        } elseif ($guard !== null) {
            Auth::requireGuard($guard);
        }

        foreach ($opts['middleware'] ?? [] as $spec) {
            [$name, $arg] = array_pad(explode(':', (string) $spec, 2), 2, '');
            match ($name) {
                'can'         => self::can($arg),
                'super_admin' => self::superAdmin(),
                'guest'       => self::guest(),
                'auth'        => Auth::requireGuard($arg !== '' ? $arg : 'admin'),
                default       => throw new RuntimeException('Unknown middleware: ' . $name),
            };
        }
    }

    public static function guest(): void
    {
        if (!Auth::check()) {
            return;
        }
        // login / register page of another panel (e.g. admin opening the vendor login): explain instead of bouncing back
        $path = Helpers::currentPath();
        $panel = str_starts_with($path, 'vendor-panel') ? 'vendor' : (str_starts_with($path, 'admin') ? 'admin' : 'customer');
        if ($panel !== Auth::guardOf(Auth::type()) && $panel !== 'customer') {
            Auth::wrongPanel($panel);
        }
        // a signed-in customer opening /login or /register: to checkout when the cart has items, else to the account
        if (Auth::type() === 'customer') {
            Helpers::redirect(Cart::count() > 0 ? 'checkout' : 'account');
        }
        Helpers::redirect(Auth::homeUrl());
    }

    /** 'a.view' or 'a.view|b.view' (any). */
    public static function can(string $permissions): void
    {
        if (!Auth::check()) {
            Auth::requireGuard('admin');
        }
        foreach (explode('|', $permissions) as $p) {
            if (Auth::can(trim($p))) {
                return;
            }
        }
        Auth::forbidden();
    }

    public static function superAdmin(): void
    {
        if (!Auth::isSuperAdmin()) {
            Auth::forbidden();
        }
    }
}
