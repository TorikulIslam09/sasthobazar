<?php
declare(strict_types=1);

/**
 * One-time codes (6 digits) sent by SMS or email through Messenger (mock "log" gateway until a real one is set).
 *   Otp::send('sms', '01712345678', 'guest_checkout')   -> ['ok' => true, 'dev_code' => '123456' only on localhost while sending is off]
 *   Otp::verify('sms', '01712345678', 'guest_checkout', '123456') -> null (ok) | error message
 * Rules: valid 5 minutes, 5 wrong tries per code, a new code after 60 s, at most 5 codes per number / hour and 15 per IP / hour.
 * Codes are stored hashed and are masked in the message log after sending.
 */
final class Otp
{
    public const TTL = 300;
    public const RESEND_AFTER = 60;
    public const MAX_ATTEMPTS = 5;

    private static function db(): Database
    {
        return Database::getInstance();
    }

    private static function normalise(string $channel, string $recipient): string
    {
        return $channel === 'sms' ? Auth::normalizePhone($recipient) : strtolower(trim($recipient));
    }

    /** @return array{ok: bool, error: ?string, wait: int, dev_code: ?string} */
    public static function send(string $channel, string $recipient, string $purpose): array
    {
        $to = self::normalise($channel, $recipient);
        $fail = static fn(string $e, int $wait = 0) => ['ok' => false, 'error' => $e, 'wait' => $wait, 'dev_code' => null];
        if ($channel === 'sms' ? !Auth::isValidBdPhone($to) : !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $fail(__($channel === 'sms' ? 'v_phone' : 'v_email'));
        }
        $last = self::db()->fetch(
            'SELECT created_at FROM otp_codes WHERE recipient = :r AND purpose = :p ORDER BY id DESC LIMIT 1',
            ['r' => $to, 'p' => $purpose]
        );
        if ($last && ($left = self::RESEND_AFTER - (time() - strtotime($last['created_at']))) > 0) {
            return $fail(__('otp_wait', ['s' => num($left)]), $left);
        }
        $kTo = 'otp:' . $purpose . ':' . $to;
        $kIp = 'otp-ip:' . Helpers::clientIp();
        if (RateLimiter::tooMany($kTo, 5) || RateLimiter::tooMany($kIp, 15)) {
            return $fail(__('otp_too_many'));
        }
        RateLimiter::hit($kTo, 3600);
        RateLimiter::hit($kIp, 3600);

        $code = (string) random_int(100000, 999999);
        self::db()->insert('otp_codes', [
            'channel'    => $channel,
            'recipient'  => $to,
            'purpose'    => $purpose,
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL),
            'ip_address' => Helpers::clientIp(),
        ]);
        $site = __('app_name');
        $msgId = $channel === 'sms'
            ? Messenger::sms($to, __('otp_sms_text', ['site' => $site, 'code' => $code]), 'otp', null, true)
            : Messenger::email($to, __('otp_email_subject', ['site' => $site]),
                '<p>' . e(__('otp_email_text')) . '</p><p style="font-size:28px;letter-spacing:6px;font-weight:bold">' . $code . '</p><p style="color:#6b7a7a">' . e(__('otp_valid_minutes', ['n' => num(5)])) . '</p>',
                'otp', null, true);
        $status = (string) self::db()->fetchColumn('SELECT status FROM message_logs WHERE id = :id', ['id' => $msgId]);
        // never keep usable codes in the message log
        self::db()->query("UPDATE message_logs SET body = REPLACE(body, :c, '******') WHERE id = :id", ['c' => $code, 'id' => $msgId]);
        if ($status === 'failed') {
            return $fail(__('otp_send_failed'));
        }
        $logOnly = ($channel === 'sms' ? Messenger::smsDriver() : Messenger::mailDriver()) === 'log';
        return ['ok' => true, 'error' => null, 'wait' => self::RESEND_AFTER, 'dev_code' => ($logOnly && IS_LOCAL) ? $code : null];
    }

    /** @return string|null error message, null = verified */
    public static function verify(string $channel, string $recipient, string $purpose, string $code): ?string
    {
        $to = self::normalise($channel, $recipient);
        $code = preg_replace('/\D/', '', bn_to_en_digits($code));
        $row = self::db()->fetch(
            'SELECT * FROM otp_codes WHERE channel = :c AND recipient = :r AND purpose = :p AND verified_at IS NULL ORDER BY id DESC LIMIT 1',
            ['c' => $channel, 'r' => $to, 'p' => $purpose]
        );
        if (!$row || strtotime($row['expires_at']) < time()) {
            return __('otp_expired');
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return __('otp_too_many_tries');
        }
        self::db()->query('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id', ['id' => (int) $row['id']]);
        if (strlen($code) !== 6 || !password_verify($code, $row['code_hash'])) {
            return __('otp_wrong');
        }
        self::db()->update('otp_codes', ['verified_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $row['id']]);
        return null;
    }
}
