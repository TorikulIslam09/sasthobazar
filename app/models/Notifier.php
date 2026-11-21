<?php
declare(strict_types=1);

/**
 * SMS / email for account events. Notification::send(..., $event) calls Notifier::user() so every in-app
 * notification of a known event can also go out by SMS and / or email - each switched on or off per event
 * (Settings → Notifications). Messages are delivered by Messenger (log only until a gateway is configured).
 */
final class Notifier
{
    public const EVENTS = ['order_placed', 'order_confirmed', 'order_shipped', 'order_delivered', 'order_cancelled',
                           'refund_update', 'prescription', 'ticket_reply', 'payout_update', 'vendor_status'];

    /** event => ['sms' => bool, 'email' => bool] */
    public static function matrix(): array
    {
        $raw = setting('notify_events', []);
        $set = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $out = [];
        foreach (self::EVENTS as $e) {
            $out[$e] = ['sms' => !empty($set[$e]['sms']), 'email' => !empty($set[$e]['email'])];
        }
        return $out;
    }

    /** Map an order status to its event (null = no SMS / email for this status). */
    public static function orderEvent(string $status): ?string
    {
        $e = 'order_' . ($status === 'out_for_delivery' ? 'shipped' : $status);
        return in_array($e, self::EVENTS, true) ? $e : null;
    }

    /** SMS / email to a contact that has no account (guest orders). */
    public static function contact(string $phone, string $email, string $event, string $title, ?string $message = null, ?string $link = null): void
    {
        if (!in_array($event, self::EVENTS, true)) {
            return;
        }
        $m = self::matrix()[$event];
        if ($m['sms'] && Auth::isValidBdPhone($phone)) {
            Messenger::sms($phone, __('app_name') . ': ' . $title . ($message ? ' - ' . Html::excerpt($message, 120) : ''), $event);
        }
        if ($m['email'] && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $body = '<p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>'
                . ($message ? '<p style="color:#6b7a7a">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>' : '')
                . ($link ? '<p><a href="' . htmlspecialchars(Helpers::absoluteUrl($link), ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0e7673;color:#fff;padding:9px 16px;border-radius:6px;text-decoration:none">' . htmlspecialchars(__('track_order'), ENT_QUOTES, 'UTF-8') . '</a></p>' : '');
            Messenger::email($email, $title, $body, $event);
        }
    }

    public static function user(int $userId, string $event, string $title, ?string $message = null, ?string $link = null): void
    {
        if (!in_array($event, self::EVENTS, true)) {
            return;
        }
        $m = self::matrix()[$event];
        if (!$m['sms'] && !$m['email']) {
            return;
        }
        $u = Database::getInstance()->fetch('SELECT id, name, phone, email, status FROM users WHERE id = :id AND deleted_at IS NULL', ['id' => $userId]);
        if (!$u || $u['status'] !== 'active') {
            return;
        }
        $site = __('app_name');
        if ($m['sms'] && $u['phone']) {
            $text = $site . ': ' . $title . ($message ? ' - ' . Html::excerpt($message, 120) : '');
            Messenger::sms((string) $u['phone'], $text, $event, (int) $u['id']);
        }
        if ($m['email'] && $u['email']) {
            $body = '<p>' . htmlspecialchars(__('email_hello', ['name' => $u['name']]), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>'
                . ($message ? '<p style="color:#6b7a7a">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>' : '')
                . ($link ? '<p><a href="' . htmlspecialchars(Helpers::absoluteUrl($link), ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0e7673;color:#fff;padding:9px 16px;border-radius:6px;text-decoration:none">' . htmlspecialchars(__('view_details'), ENT_QUOTES, 'UTF-8') . '</a></p>' : '');
            Messenger::email((string) $u['email'], $title, $body, $event, (int) $u['id']);
        }
    }
}
