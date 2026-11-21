<?php
/**
 * CSRF protection. One token per session; every POST is verified automatically by Router::dispatch().
 */
declare(strict_types=1);

final class CSRF
{
    private const KEY = '_csrf_token';
    public const FIELD = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . self::token() . '">';
    }

    public static function meta(): string
    {
        return '<meta name="csrf-token" content="' . self::token() . '">';
    }

    public static function verify(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION[self::KEY]) && hash_equals($_SESSION[self::KEY], $token);
    }

    /** Check POST field or X-CSRF-TOKEN header (for AJAX). */
    public static function verifyRequest(): bool
    {
        $token = $_POST[self::FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return self::verify($token);
    }

    public static function regenerate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
