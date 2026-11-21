<?php
declare(strict_types=1);

/**
 * Guest checkout state (kept in the session, no account is created):
 *   guest_contact  = ['type' => 'phone'|'email', 'value' => '01...'|'a@b.c']   chosen on the /login "Guest checkout" tab
 *   guest_verified = ['phone' => '01...', 'email' => 'a@b.c']                    contacts verified by OTP in this session
 *   guest_orders   = [order_no, ...]                                            orders this browser placed as a guest
 * Guest orders are saved with orders.is_guest = 1 and contact_verified = 1 (mobile OTP) / 2 (email OTP) / 0. They are linked
 * to an account (orders.user_id) when the same VERIFIED mobile / email registers or logs in - see linkOrders().
 */
final class Guest
{
    public const PURPOSE = 'guest_checkout';

    public static function enabled(): bool
    {
        return (bool) setting('guest_checkout_enabled', true);
    }

    public static function otpRequired(): bool
    {
        return (bool) setting('guest_otp_required', true);
    }

    public static function defaultContact(): string
    {
        return setting('guest_default_contact', 'phone') === 'email' ? 'email' : 'phone';
    }

    /** @return array{type: string, value: string}|null */
    public static function contact(): ?array
    {
        $c = $_SESSION['guest_contact'] ?? null;
        return is_array($c) && in_array($c['type'] ?? '', ['phone', 'email'], true) && is_string($c['value'] ?? null) ? $c : null;
    }

    public static function setContact(string $type, string $value): void
    {
        $_SESSION['guest_contact'] = ['type' => $type, 'value' => $value];
    }

    public static function clear(): void
    {
        unset($_SESSION['guest_contact']);
    }

    public static function isVerified(string $type, string $value): bool
    {
        return ($value !== '') && (($_SESSION['guest_verified'][$type] ?? null) === $value);
    }

    public static function markVerified(string $type, string $value): void
    {
        $_SESSION['guest_verified'][$type] = $value;
    }

    public static function rememberOrder(string $orderNo): void
    {
        $list = $_SESSION['guest_orders'] ?? [];
        $list[] = $orderNo;
        $_SESSION['guest_orders'] = array_slice(array_values(array_unique($list)), -10);
    }

    public static function ownsOrder(string $orderNo): bool
    {
        return in_array($orderNo, $_SESSION['guest_orders'] ?? [], true);
    }

    /** A valid guest contact from user input, or null. */
    public static function parse(string $type, string $value): ?array
    {
        $value = trim(bn_to_en_digits($value));
        if ($type === 'phone') {
            $p = Auth::normalizePhone($value);
            return preg_match('/^01[3-9]\d{8}$/', $p) ? ['type' => 'phone', 'value' => $p] : null;
        }
        if ($type === 'email') {
            $e = strtolower($value);
            return filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 191 ? ['type' => 'email', 'value' => $e] : null;
        }
        return null;
    }

    /**
     * Link guest orders to an account. Only orders whose contact was verified by OTP are linked, and only to an account
     * whose mobile / email is verified - either already, or by an OTP confirmed in this same session (then the account's
     * phone_verified_at / email_verified_at is set too).
     * @return int number of orders linked
     */
    public static function linkOrders(array $user): int
    {
        $db = Database::getInstance();
        $userId = (int) $user['id'];
        $phoneOk = !empty($user['phone']) && ($user['phone_verified_at'] !== null || self::isVerified('phone', (string) $user['phone']));
        $emailOk = !empty($user['email']) && ($user['email_verified_at'] !== null || self::isVerified('email', strtolower((string) $user['email'])));
        $set = [];
        if ($phoneOk && $user['phone_verified_at'] === null) {
            $set['phone_verified_at'] = date('Y-m-d H:i:s');
        }
        if ($emailOk && $user['email_verified_at'] === null) {
            $set['email_verified_at'] = date('Y-m-d H:i:s');
        }
        if ($set) {
            $db->update('users', $set, 'id = :id', ['id' => $userId]);
        }
        $n = 0;
        if ($phoneOk) {
            $n += $db->query(
                'UPDATE orders SET user_id = :u WHERE user_id IS NULL AND is_guest = 1 AND contact_verified = 1 AND customer_phone = :p', // mobile was verified
                ['u' => $userId, 'p' => (string) $user['phone']]
            )->rowCount();
            $db->query('UPDATE prescriptions SET user_id = :u WHERE user_id IS NULL AND guest_phone = :p', ['u' => $userId, 'p' => (string) $user['phone']]);
        }
        if ($emailOk) {
            $n += $db->query(
                'UPDATE orders SET user_id = :u WHERE user_id IS NULL AND is_guest = 1 AND contact_verified = 2 AND customer_email = :e', // email was verified
                ['u' => $userId, 'e' => strtolower((string) $user['email'])]
            )->rowCount();
        }
        if ($n > 0) {
            Audit::log('orders.guest_linked', 'users', $userId, null, ['orders' => $n], $userId);
        }
        return $n;
    }
}
