<?php
declare(strict_types=1);

class Notification extends Model
{
    protected static string $table = 'notifications';
    protected static bool $audited = false;

    public static function unreadCount(int $userId): int
    {
        return (int) static::db()->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL',
            ['u' => $userId]
        );
    }

    public static function latest(int $userId, int $limit = 5): array
    {
        return static::db()->fetchAll(
            "SELECT id, type, title, message, link, read_at, created_at
               FROM notifications WHERE user_id = :u ORDER BY created_at DESC, id DESC LIMIT {$limit}",
            ['u' => $userId]
        );
    }

    public static function markAllRead(int $userId): int
    {
        return static::db()->update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'user_id = :u AND read_at IS NULL', ['u' => $userId]);
    }

    /**
     * Create an in-app notification for a user. With $event (Notifier::EVENTS) it may also go out by SMS / email,
     * depending on Settings → Notifications.
     */
    public static function send(int $userId, string $type, string $title, ?string $message = null, ?string $link = null, ?string $event = null): int
    {
        $id = static::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => mb_substr($title, 0, 200),
            'message' => $message !== null ? mb_substr($message, 0, 1000) : null,
            'link'    => $link,
        ]);
        if ($event !== null) {
            try {
                Notifier::user($userId, $event, $title, $message, $link);
            } catch (Throwable $e) {
                error_log('Notifier: ' . $e->getMessage()); // never break the action that notified
            }
        }
        return $id;
    }

    /**
     * Notify every active staff member who has one of the permissions (super admins always).
     * @param string[] $permissions e.g. ['orders.view']
     */
    public static function toStaff(array $permissions, string $type, string $title, ?string $message = null, ?string $link = null): int
    {
        $in = implode(',', array_fill(0, count($permissions), '?'));
        $staff = static::db()->fetchAll(
            "SELECT DISTINCT u.id FROM users u
              WHERE u.status = 'active' AND u.deleted_at IS NULL AND (u.user_type = 'super_admin' OR (u.user_type = 'employee' AND EXISTS (
                    SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
                     WHERE rp.role_id = u.role_id AND p.`key` IN ({$in}))))
              LIMIT 100",
            array_values($permissions)
        );
        foreach ($staff as $s) {
            self::send((int) $s['id'], $type, $title, $message, $link);
        }
        return count($staff);
    }

    /** Latest notifications newer than $sinceId (for the bell's AJAX polling). */
    public static function since(int $userId, int $sinceId, int $limit = 10): array
    {
        return static::db()->fetchAll(
            "SELECT id, type, title, message, link, read_at, created_at FROM notifications
              WHERE user_id = :u AND id > :s ORDER BY id DESC LIMIT {$limit}",
            ['u' => $userId, 's' => $sinceId]
        );
    }

    public static function markRead(int $userId, int $id): ?array
    {
        $n = static::db()->fetch('SELECT * FROM notifications WHERE id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
        if ($n && !$n['read_at']) {
            static::db()->update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        }
        return $n;
    }

    /** Bootstrap icon for a notification type. */
    public static function icon(string $type): string
    {
        return [
            'order' => 'bi-receipt', 'stock' => 'bi-box-seam', 'vendor' => 'bi-shop', 'payout' => 'bi-cash-coin',
            'support' => 'bi-headset', 'review' => 'bi-star', 'refund' => 'bi-arrow-counterclockwise', 'system' => 'bi-gear',
        ][$type] ?? 'bi-bell';
    }
}
