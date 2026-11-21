<?php
/**
 * RateLimiter - fixed-window counters stored in the `rate_limits` table (works across PHP processes).
 *
 *   $key = 'login:ip:' . $ip;
 *   if (RateLimiter::tooMany($key, 20)) { ... wait RateLimiter::availableIn($key) seconds ... }
 *   RateLimiter::hit($key, 900);   // count one attempt, window = 15 minutes
 *   RateLimiter::clear($key);      // e.g. after successful login
 *
 * Keys are SHA-256 hashed before storage (no emails/phones stored in clear text).
 */
declare(strict_types=1);

final class RateLimiter
{
    private static function k(string $key): string
    {
        return hash('sha256', $key);
    }

    /** Record one hit. Returns the number of attempts in the current window. */
    public static function hit(string $key, int $decaySeconds): int
    {
        $db = Database::getInstance();
        $db->query(
            'INSERT INTO rate_limits (`key`, attempts, reset_at)
                  VALUES (:k, 1, DATE_ADD(NOW(), INTERVAL :d SECOND))
             ON DUPLICATE KEY UPDATE
                  attempts = IF(reset_at <= NOW(), 1, attempts + 1),
                  reset_at = IF(reset_at <= NOW(), DATE_ADD(NOW(), INTERVAL :d2 SECOND), reset_at)',
            ['k' => self::k($key), 'd' => $decaySeconds, 'd2' => $decaySeconds]
        );
        // occasional garbage collection
        if (random_int(1, 100) === 1) {
            $db->query('DELETE FROM rate_limits WHERE reset_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
        return self::attempts($key);
    }

    public static function attempts(string $key): int
    {
        return (int) Database::getInstance()->fetchColumn(
            'SELECT attempts FROM rate_limits WHERE `key` = :k AND reset_at > NOW()',
            ['k' => self::k($key)]
        );
    }

    public static function tooMany(string $key, int $maxAttempts): bool
    {
        return self::attempts($key) >= $maxAttempts;
    }

    /** Seconds until the window resets (0 if not limited). */
    public static function availableIn(string $key): int
    {
        $s = Database::getInstance()->fetchColumn(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), reset_at) FROM rate_limits WHERE `key` = :k AND reset_at > NOW()',
            ['k' => self::k($key)]
        );
        return max(0, (int) $s);
    }

    public static function clear(string $key): void
    {
        Database::getInstance()->query('DELETE FROM rate_limits WHERE `key` = :k', ['k' => self::k($key)]);
    }
}
