<?php
declare(strict_types=1);

/**
 * Courier adapter base (adapter pattern). Each courier implements:
 *   configured()                        are the API credentials filled in?
 *   realCreate(array $payload): array   ['tracking_no', 'consignment_id', 'fee', 'raw']
 *   realCancel(string $consignment): bool
 *   realTrack(string $consignment, string $tracking): ?array  ['status' => our status, 'note', 'raw']
 *   webhookEvents(array $payload): array of ['ref' => consignment or tracking, 'status' => our status, 'note']
 *
 * MOCK MODE (setting courier_mock_mode, or the courier's "mock" option, or missing credentials):
 * no network calls; booking returns a fake tracking number, tracking moves one step per sync.
 * Endpoints / field names follow the couriers' public merchant API docs - check them against your
 * merchant account docs before switching mock mode off.
 */
abstract class CourierBase
{
    /** Our shipment statuses, in delivery order (used by mock tracking). */
    protected const MOCK_FLOW = ['booked', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];

    public function __construct(protected array $courier, protected array $config)
    {
    }

    abstract public function configured(): bool;

    abstract protected function realCreate(array $p): array;

    abstract protected function realCancel(string $consignment, string $tracking): bool;

    abstract protected function realTrack(string $consignment, string $tracking): ?array;

    /** Courier status text -> our status. Override for courier-specific words. */
    protected array $statusMap = [];

    public function code(): string
    {
        return (string) $this->courier['code'];
    }

    public function isMock(): bool
    {
        return (bool) setting('courier_mock_mode', true) || !empty($this->config['mock']) || !$this->configured();
    }

    /* ---------------- public API used by Shipment ---------------- */

    public function createParcel(array $p): array
    {
        if ($this->isMock()) {
            $prefix = strtoupper(substr($this->code(), 0, 3));
            $tracking = $prefix . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
            return [
                'tracking_no'    => $tracking,
                'consignment_id' => 'MOCK-' . $tracking,
                'fee'            => $p['shipping_fee'],
                'raw'            => ['mock' => true, 'courier' => $this->code(), 'invoice' => $p['invoice'], 'cod' => $p['cod_amount']],
                'mock'           => true,
            ];
        }
        $r = $this->realCreate($p);
        $r['mock'] = false;
        return $r;
    }

    public function cancelParcel(string $consignment, string $tracking): bool
    {
        if ($this->isMock() || str_starts_with($consignment, 'MOCK-')) {
            return true;
        }
        return $this->realCancel($consignment, $tracking);
    }

    /** @return array|null ['status', 'note', 'raw'] */
    public function track(string $consignment, string $tracking, string $current): ?array
    {
        if ($this->isMock() || str_starts_with($consignment, 'MOCK-')) {
            $i = array_search($current, self::MOCK_FLOW, true);
            $next = $i === false ? null : (self::MOCK_FLOW[$i + 1] ?? null);
            return $next ? ['status' => $next, 'note' => 'mock tracking', 'raw' => ['mock' => true, 'status' => $next]] : null;
        }
        return $this->realTrack($consignment, $tracking);
    }

    /**
     * Webhook body -> events. Our generic format is always accepted too (useful for tests and own riders):
     *   {"tracking": "...", "status": "delivered", "note": "..."}
     */
    public function webhookEvents(array $payload): array
    {
        if (isset($payload['tracking'], $payload['status'])) {
            return [['ref' => (string) $payload['tracking'], 'status' => $this->mapStatus((string) $payload['status']), 'note' => $payload['note'] ?? null]];
        }
        return $this->parseWebhook($payload);
    }

    protected function parseWebhook(array $payload): array
    {
        return [];
    }

    /* ---------------- helpers ---------------- */

    /** Map a courier's status text to ours (exact map first, then keywords). */
    public function mapStatus(string $text): ?string
    {
        $t = strtolower(trim($text));
        if (isset($this->statusMap[$t])) {
            return $this->statusMap[$t];
        }
        if (in_array($t, Shipment::STATUSES, true)) {
            return $t;
        }
        return match (true) {
            str_contains($t, 'partial')                                  => 'partial_delivered',
            str_contains($t, 'return') && (str_contains($t, 'ing') || str_contains($t, 'transit') || str_contains($t, 'pending')) => 'returning',
            str_contains($t, 'return')                                   => 'returned',
            str_contains($t, 'out_for') || str_contains($t, 'out for') || str_contains($t, 'assigned') => 'out_for_delivery',
            str_contains($t, 'deliver') && !str_contains($t, 'fail')     => 'delivered',
            str_contains($t, 'fail') || str_contains($t, 'unreach') || str_contains($t, 'resched') => 'failed',
            str_contains($t, 'hold')                                     => 'on_hold',
            str_contains($t, 'cancel')                                   => 'cancelled',
            str_contains($t, 'lost')                                     => 'lost',
            str_contains($t, 'pick')                                     => 'picked_up',
            str_contains($t, 'transit') || str_contains($t, 'hub') || str_contains($t, 'sort') => 'in_transit',
            str_contains($t, 'pending') || str_contains($t, 'creat') || str_contains($t, 'accept') || str_contains($t, 'review') => 'booked',
            default                                                      => null,
        };
    }

    /** JSON HTTP call. @throws OrderException with the courier's message */
    protected function http(string $method, string $url, array $headers = [], ?array $body = null, bool $form = false): array
    {
        $hdr = array_merge(['Accept: application/json'], $headers);
        $payload = null;
        if ($body !== null) {
            $payload = $form ? http_build_query($body) : json_encode($body, JSON_UNESCAPED_UNICODE);
            $hdr[] = 'Content-Type: ' . ($form ? 'application/x-www-form-urlencoded' : 'application/json');
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $hdr,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $hdr), 'content' => $payload ?? '', 'timeout' => 20, 'ignore_errors' => true]]);
            $resp = @file_get_contents($url, false, $ctx);
            $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
            $err = $resp === false ? 'connection failed' : '';
        }
        if ($resp === false || $code === 0) {
            throw new OrderException(__('courier_api_error', ['courier' => $this->courier['name'], 'msg' => $err ?: 'no response']));
        }
        $json = json_decode((string) $resp, true);
        if ($code >= 400) {
            $msg = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? json_encode($json)) : mb_substr((string) $resp, 0, 200);
            throw new OrderException(__('courier_api_error', ['courier' => $this->courier['name'], 'msg' => mb_substr($msg, 0, 200)]));
        }
        return is_array($json) ? $json : ['body' => mb_substr((string) $resp, 0, 1000)];
    }

    protected function base(): string
    {
        return rtrim((string) $this->courier['api_base_url'], '/');
    }

    protected function cfg(string $k, string $default = ''): string
    {
        return trim((string) ($this->config[$k] ?? $default));
    }
}
