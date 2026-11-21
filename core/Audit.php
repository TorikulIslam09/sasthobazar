<?php
/**
 * Audit - writes to `audit_logs` (who, what, which record, old/new values, IP, user agent, time).
 *
 * Model::create / updateById / deleteById call this automatically.
 * Call it manually for anything else:
 *   Audit::log('order.status', 'orders', $id, ['status' => 'pending'], ['status' => 'confirmed']);
 *
 * Secrets (password hashes, tokens, API keys) are masked before saving.
 */
declare(strict_types=1);

final class Audit
{
    private const HIDDEN = ['password', 'password_hash', 'password_confirmation', 'remember_token', 'api_key', 'api_secret', '_csrf'];

    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $old = null, ?array $new = null, ?int $userId = null): void
    {
        try {
            Database::getInstance()->insert('audit_logs', [
                'user_id'     => $userId ?? ($_SESSION['auth_id'] ?? null),
                'action'      => mb_substr($action, 0, 100),
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'old_values'  => $old !== null ? json_encode(self::mask($old), JSON_UNESCAPED_UNICODE) : null,
                'new_values'  => $new !== null ? json_encode(self::mask($new), JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => Helpers::clientIp(),
                'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? (IS_CLI ? 'cli' : '')), 0, 255),
            ]);
        } catch (Throwable $e) {
            // Logging must never break the request
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }

    /** Returns [oldChangedValues, newChangedValues] for fields in $new that differ from $old. */
    public static function diff(array $old, array $new): array
    {
        $o = [];
        $n = [];
        foreach ($new as $k => $v) {
            $before = $old[$k] ?? null;
            if (self::norm($before) !== self::norm($v)) {
                $o[$k] = $before;
                $n[$k] = $v;
            }
        }
        return [$o, $n];
    }

    private static function norm(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_float($v) || (is_string($v) && is_numeric($v) && str_contains($v, '.'))) {
            return rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
        }
        return is_scalar($v) ? (string) $v : json_encode($v);
    }

    private static function mask(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array((string) $k, self::HIDDEN, true)) {
                $data[$k] = '***';
            } elseif (is_array($v)) {
                $data[$k] = self::mask($v);
            }
        }
        return $data;
    }
}
