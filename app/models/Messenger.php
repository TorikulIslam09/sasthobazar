<?php
declare(strict_types=1);

/**
 * SMS / email outbox. Every message is written to message_logs first, then delivered at the end of the request
 * (after the page is sent) by the configured driver, so a slow gateway never slows down checkout.
 *
 * SMS drivers (Settings → SMS):
 *   log   default - nothing is sent, the message is only logged (status "logged")
 *   http  any HTTP SMS gateway (SSL Wireless, BulkSMSBD, Alpha, Twilio-compatible ...):
 *         sms_api_url with placeholders {to} {message} {api_key} {sender}, GET or POST (form fields from the URL query)
 *         success = HTTP 2xx and, when set, the response contains sms_success_keyword
 * Email drivers (Settings → Email):
 *   log   default - only logged;  mail - PHP mail();  smtp - SmtpClient (host, port, tls/ssl, username, password)
 *
 * Adding a gateway later = one more case in deliverSms() / deliverEmail().
 */
final class Messenger
{
    public const SMS_DRIVERS = ['log', 'http'];
    public const MAIL_DRIVERS = ['log', 'mail', 'smtp'];

    private static array $queue = [];
    private static bool $hooked = false;

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** Bangladeshi mobile number -> 8801XXXXXXXXX (other numbers unchanged, digits only). */
    public static function msisdn(string $phone): string
    {
        $d = (string) preg_replace('/\D/', '', $phone);
        if (preg_match('/^01[3-9]\d{8}$/', $d)) {
            return '88' . $d;
        }
        return $d;
    }

    public static function sms(string $to, string $text, ?string $event = null, ?int $userId = null, bool $now = false): int
    {
        return self::queue('sms', self::msisdn($to), null, mb_substr($text, 0, 1000), $event, $userId, $now);
    }

    public static function email(string $to, string $subject, string $html, ?string $event = null, ?int $userId = null, bool $now = false): int
    {
        return self::queue('email', trim($to), mb_substr($subject, 0, 255), $html, $event, $userId, $now);
    }

    private static function queue(string $channel, string $to, ?string $subject, string $body, ?string $event, ?int $userId, bool $now): int
    {
        if ($to === '' || ($channel === 'email' && !filter_var($to, FILTER_VALIDATE_EMAIL))) {
            return 0;
        }
        $id = self::db()->insert('message_logs', [
            'channel' => $channel, 'recipient' => mb_substr($to, 0, 190), 'subject' => $subject, 'body' => $body,
            'event' => $event, 'user_id' => $userId, 'status' => 'queued',
        ]);
        if ($now) {
            self::deliver($id);
            return $id;
        }
        self::$queue[] = $id;
        if (!self::$hooked) {
            self::$hooked = true;
            register_shutdown_function([self::class, 'flush']);
        }
        return $id;
    }

    /** Deliver this request's queued messages (runs after the response when possible). */
    public static function flush(): void
    {
        if (!self::$queue) {
            return;
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        ignore_user_abort(true);
        @set_time_limit(60);
        $ids = self::$queue;
        self::$queue = [];
        foreach ($ids as $id) {
            try {
                self::deliver($id);
            } catch (Throwable $e) {
                error_log('Messenger: ' . $e->getMessage());
            }
        }
    }

    /** Send one logged message now (also used for retries). Returns the new status. */
    public static function deliver(int $id): string
    {
        $m = self::db()->fetch('SELECT * FROM message_logs WHERE id = :id', ['id' => $id]);
        if (!$m || $m['status'] === 'sent') {
            return $m['status'] ?? 'missing';
        }
        try {
            [$status, $provider, $response] = $m['channel'] === 'sms' ? self::deliverSms($m) : self::deliverEmail($m);
        } catch (Throwable $e) {
            [$status, $provider, $response] = ['failed', $m['channel'] === 'sms' ? self::smsDriver() : self::mailDriver(), $e->getMessage()];
        }
        self::db()->update('message_logs', [
            'status'   => $status,
            'provider' => $provider,
            'response' => mb_substr((string) $response, 0, 1000),
            'attempts' => (int) $m['attempts'] + 1,
            'sent_at'  => $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ], 'id = :id', ['id' => $id]);
        return $status;
    }

    public static function smsDriver(): string
    {
        $d = (string) setting('sms_driver', 'log');
        return (bool) setting('sms_enabled', false) && in_array($d, self::SMS_DRIVERS, true) ? $d : 'log';
    }

    public static function mailDriver(): string
    {
        $d = (string) setting('mail_driver', 'log');
        return (bool) setting('mail_enabled', false) && in_array($d, self::MAIL_DRIVERS, true) ? $d : 'log';
    }

    /** @return array{0: string, 1: string, 2: string} [status, provider, response] */
    private static function deliverSms(array $m): array
    {
        $driver = self::smsDriver();
        if ($driver === 'log') {
            return ['logged', 'log', 'SMS sending is off - message logged only'];
        }
        $url = trim((string) setting('sms_api_url', ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('SMS API URL is not set');
        }
        $vars = [
            '{to}' => $m['recipient'], '{message}' => $m['body'],
            '{api_key}' => (string) setting('sms_api_key', ''), '{sender}' => (string) setting('sms_sender_id', ''),
        ];
        $method = strtoupper((string) setting('sms_api_method', 'GET')) === 'POST' ? 'POST' : 'GET';
        if ($method === 'GET') {
            $final = strtr($url, array_map('rawurlencode', $vars));
            [$code, $body] = self::http('GET', $final, null);
        } else {
            $parts = parse_url($url);
            parse_str($parts['query'] ?? '', $fields);
            foreach ($fields as $k => $v) {
                $fields[$k] = strtr((string) $v, $vars);
            }
            $base = strtok($url, '?');
            [$code, $body] = self::http('POST', (string) $base, http_build_query($fields));
        }
        $keyword = trim((string) setting('sms_success_keyword', ''));
        $ok = $code >= 200 && $code < 300 && ($keyword === '' || stripos($body, $keyword) !== false);
        return [$ok ? 'sent' : 'failed', 'http', 'HTTP ' . $code . ': ' . mb_substr(trim($body), 0, 900)];
    }

    /** @return array{0: int, 1: string} */
    private static function http(string $method, string $url, ?string $postBody): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'SasthoBazar/1.0',
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $postBody);
            }
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($body === false) {
                throw new RuntimeException('HTTP error: ' . $err);
            }
            return [$code, (string) $body];
        }
        $ctx = stream_context_create(['http' => ['method' => $method, 'timeout' => 10, 'ignore_errors' => true,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: SasthoBazar/1.0\r\n", 'content' => (string) $postBody]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $mm) ? (int) $mm[1] : 0;
        if ($body === false) {
            throw new RuntimeException('HTTP error');
        }
        return [$code, $body];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private static function deliverEmail(array $m): array
    {
        $driver = self::mailDriver();
        if ($driver === 'log') {
            return ['logged', 'log', 'Email sending is off - message logged only'];
        }
        $from = trim((string) setting('mail_from_address', '')) ?: (string) setting('contact_email', '');
        $fromName = trim((string) setting('mail_from_name', '')) ?: __('app_name');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('From address is not set');
        }
        $html = self::layout((string) $m['subject'], (string) $m['body']);
        if ($driver === 'mail') {
            $msg = SmtpClient::message($from, $fromName, $m['recipient'], (string) $m['subject'], $html);
            [$head, $body] = explode("\r\n\r\n", $msg, 2);
            $headers = implode("\r\n", array_filter(explode("\r\n", $head), static fn($h) => !preg_match('/^(To|Subject):/i', $h)));
            $ok = @mail($m['recipient'], '=?UTF-8?B?' . base64_encode((string) $m['subject']) . '?=', $body, $headers);
            return [$ok ? 'sent' : 'failed', 'mail', $ok ? 'Accepted by mail()' : 'mail() returned false'];
        }
        $smtp = new SmtpClient((string) setting('mail_host', ''), (int) setting('mail_port', 587), (string) setting('mail_encryption', 'tls'),
            (string) setting('mail_username', ''), (string) setting('mail_password', ''));
        return ['sent', 'smtp', $smtp->send($from, $fromName, $m['recipient'], (string) $m['subject'], $html)];
    }

    /** Branded HTML wrapper for emails. */
    public static function layout(string $subject, string $body): string
    {
        $name = htmlspecialchars(__('app_name'), ENT_QUOTES, 'UTF-8');
        $logo = (string) setting('site_logo', '');
        $logoHtml = $logo !== '' ? '<img src="' . htmlspecialchars(Helpers::absoluteUrl($logo), ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '" style="max-height:40px">' : '<strong style="font-size:20px;color:#0e7673">' . $name . '</strong>';
        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="margin:0;background:#f2f5f5;font-family:Arial,sans-serif;color:#1f2a2a">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px"><div style="padding-bottom:16px">' . $logoHtml . '</div>'
            . '<div style="background:#fff;border-radius:10px;padding:20px"><h2 style="font-size:18px;margin:0 0 12px">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>'
            . $body . '</div><p style="font-size:12px;color:#6b7a7a;text-align:center">' . $name . ' · ' . htmlspecialchars((string) setting('contact_phone', ''), ENT_QUOTES, 'UTF-8') . '</p></div></body></html>';
    }
}
