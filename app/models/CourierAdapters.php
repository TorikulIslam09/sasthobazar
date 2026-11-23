<?php
declare(strict_types=1);

/*
 * Courier adapters. Loaded by app/models/Courier*.php stubs (the autoloader maps one class per file),
 * so each class below also has a tiny file that requires this one.
 */

/** Our own delivery man: no API; staff update the status by hand (or the rider app posts to the webhook). */
final class CourierOwn extends CourierBase
{
    public function configured(): bool
    {
        return true;
    }

    public function isMock(): bool
    {
        return false;
    }

    protected function realCreate(array $p): array
    {
        $tracking = 'OWN' . date('ymd') . str_pad((string) $p['shipment_id'], 6, '0', STR_PAD_LEFT);
        return ['tracking_no' => $tracking, 'consignment_id' => $tracking, 'fee' => 0, 'raw' => ['rider' => $p['rider']]];
    }

    protected function realCancel(string $consignment, string $tracking): bool
    {
        return true;
    }

    protected function realTrack(string $consignment, string $tracking): ?array
    {
        return null; // statuses come from staff / rider
    }

    public function track(string $consignment, string $tracking, string $current): ?array
    {
        return null;
    }
}

/**
 * Pathao Courier (Aladdin merchant API).
 *  - Login: client_id + client_secret (api_secret) + merchant email (username) + password → access token (cached until it expires)
 *  - Parcels: city / zone / area are NOT sent - Pathao reads them from the full address (house, area, upazila, district).
 *    (default_city_id / default_zone_id are only used if filled in, e.g. for a store that needs them.)
 *  - Webhook: Pathao sends the secret in X-PATHAO-Signature; we answer 202 with X-Pathao-Merchant-Webhook-Integration-Secret.
 * Sandbox: https://courier-api-sandbox.pathao.com   Live: https://api-hermes.pathao.com
 */
final class CourierPathao extends CourierBase
{
    protected array $statusMap = [
        'created' => 'booked', 'pending' => 'booked', 'pickup_requested' => 'booked', 'assigned_for_pickup' => 'booked',
        'pickup_failed' => 'on_hold', 'pickup_cancelled' => 'cancelled', 'pickup_cancel' => 'cancelled',
        'picked' => 'picked_up', 'at_the_sorting_hub' => 'in_transit', 'in_transit' => 'in_transit', 'received_at_last_mile_hub' => 'in_transit',
        'assigned_for_delivery' => 'out_for_delivery', 'delivered' => 'delivered', 'partial_delivery' => 'partial_delivered',
        'delivery_failed' => 'failed', 'on_hold' => 'on_hold', 'return' => 'returning', 'returned' => 'returned',
        'paid_return' => 'returned', 'exchanged' => 'delivered',
    ];

    public function configured(): bool
    {
        return $this->cfg('client_id') !== '' && (string) $this->courier['api_secret'] !== '' && $this->cfg('username') !== ''
            && $this->cfg('password') !== '' && $this->cfg('store_id') !== '';
    }

    /** Pathao status text / event ("order.pickup-requested", "Pickup Requested") → our status. */
    public function mapStatus(string $text): ?string
    {
        $t = strtolower(trim($text));
        $t = (string) preg_replace('/^order\./', '', $t);
        $t = (string) preg_replace('/[\s\-]+/', '_', $t);
        if (in_array($t, ['paid', 'webhook_integration', 'store_created', 'store_updated'], true)) {
            return null; // money / setup events, not parcel movement
        }
        return parent::mapStatus($t);
    }

    private function tokenFile(): string
    {
        return STORAGE_PATH . '/private/cache/pathao-token-' . (int) $this->courier['id'] . '.json';
    }

    /** Access token, reused until 5 minutes before it expires (Pathao tokens last days). */
    private function token(bool $fresh = false): string
    {
        $file = $this->tokenFile();
        $sig = hash('sha256', $this->base() . '|' . $this->cfg('client_id') . '|' . $this->cfg('username'));
        if (!$fresh && is_file($file)) {
            $c = json_decode((string) @file_get_contents($file), true);
            if (is_array($c) && ($c['sig'] ?? '') === $sig && ($c['expires'] ?? 0) > time() + 300 && !empty($c['token'])) {
                return (string) $c['token'];
            }
        }
        $r = $this->http('POST', $this->base() . '/aladdin/api/v1/issue-token', [], [
            'client_id' => $this->cfg('client_id'), 'client_secret' => (string) $this->courier['api_secret'],
            'username' => $this->cfg('username'), 'password' => $this->cfg('password'), 'grant_type' => 'password',
        ]);
        $token = (string) ($r['access_token'] ?? '');
        if ($token === '') {
            throw new OrderException(__('courier_api_error', ['courier' => $this->courier['name'], 'msg' => 'login failed (check client id / secret / email / password)']));
        }
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0770, true);
        }
        @file_put_contents($file, json_encode(['token' => $token, 'expires' => time() + max(600, (int) ($r['expires_in'] ?? 3600)), 'sig' => $sig]), LOCK_EX);
        @chmod($file, 0600);
        return $token;
    }

    /** Authorised API call; logs in again once if the saved token was rejected. */
    private function api(string $method, string $path, ?array $body = null): array
    {
        try {
            return $this->http($method, $this->base() . $path, ['Authorization: Bearer ' . $this->token()], $body);
        } catch (OrderException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'unauth') && !str_contains($e->getMessage(), '401')) {
                throw $e;
            }
            return $this->http($method, $this->base() . $path, ['Authorization: Bearer ' . $this->token(true)], $body);
        }
    }

    /** Admin "Test connection": logs in and returns the merchant's stores [id, name, address]. */
    public function stores(): array
    {
        $r = $this->api('GET', '/aladdin/api/v1/stores');
        $out = [];
        foreach ((array) ($r['data']['data'] ?? $r['data'] ?? []) as $st) {
            if (is_array($st) && isset($st['store_id'])) {
                $out[] = ['id' => (string) $st['store_id'], 'name' => (string) ($st['store_name'] ?? ''), 'address' => (string) ($st['store_address'] ?? ''), 'active' => !empty($st['is_active'])];
            }
        }
        return $out;
    }

    protected function realCreate(array $p): array
    {
        $body = [
            'store_id'            => (int) $this->cfg('store_id'),
            'merchant_order_id'   => $p['invoice'],
            'recipient_name'      => mb_substr($p['recipient_name'], 0, 100),
            'recipient_phone'     => $p['recipient_phone'],
            // full address: Pathao detects city / zone / area from it
            'recipient_address'   => mb_substr($p['address'] . ($p['postcode'] !== '' ? ' - ' . $p['postcode'] : ''), 0, 220),
            'delivery_type'       => $p['express'] ? 12 : 48,
            'item_type'           => 2, // parcel
            'special_instruction' => $p['note'],
            'item_quantity'       => $p['items'],
            'item_weight'         => $p['weight_kg'],
            'amount_to_collect'   => (int) round($p['cod_amount']),
            'item_description'    => $p['description'],
        ];
        if ($this->cfg('default_city_id') !== '' && $this->cfg('default_zone_id') !== '') { // only when the admin fills them in
            $body['recipient_city'] = (int) $this->cfg('default_city_id');
            $body['recipient_zone'] = (int) $this->cfg('default_zone_id');
        }
        $r = $this->api('POST', '/aladdin/api/v1/orders', $body);
        $d = $r['data'] ?? [];
        if (empty($d['consignment_id'])) {
            throw new OrderException(__('courier_api_error', ['courier' => $this->courier['name'], 'msg' => (string) ($r['message'] ?? 'no consignment id')]));
        }
        return ['tracking_no' => (string) $d['consignment_id'], 'consignment_id' => (string) $d['consignment_id'], 'fee' => (float) ($d['delivery_fee'] ?? 0), 'raw' => $r];
    }

    protected function realCancel(string $consignment, string $tracking): bool
    {
        return false; // Pathao: cancel from the merchant panel
    }

    protected function realTrack(string $consignment, string $tracking): ?array
    {
        $r = $this->api('GET', '/aladdin/api/v1/orders/' . rawurlencode($consignment) . '/info');
        $st = (string) ($r['data']['order_status_slug'] ?? $r['data']['order_status'] ?? '');
        return $st !== '' ? ['status' => $this->mapStatus($st), 'note' => $st, 'raw' => $r] : null;
    }

    protected function parseWebhook(array $p): array
    {
        $ref = $p['consignment_id'] ?? null;
        $st = $p['event'] ?? $p['order_status_slug'] ?? $p['order_status'] ?? null;
        return $ref && $st ? [['ref' => (string) $ref, 'status' => $this->mapStatus((string) $st), 'note' => (string) $st]] : [];
    }
}

/** Steadfast (Packzy) API. api_key = Api-Key, api_secret = Secret-Key. */
final class CourierSteadfast extends CourierBase
{
    protected array $statusMap = [
        'in_review' => 'booked', 'pending' => 'booked', 'hold' => 'on_hold', 'delivered_approval_pending' => 'delivered',
        'partial_delivered_approval_pending' => 'partial_delivered', 'cancelled_approval_pending' => 'returning',
        'delivered' => 'delivered', 'partial_delivered' => 'partial_delivered', 'cancelled' => 'returned', 'unknown' => 'on_hold',
    ];

    public function configured(): bool
    {
        return (string) $this->courier['api_key'] !== '' && (string) $this->courier['api_secret'] !== '';
    }

    private function headers(): array
    {
        return ['Api-Key: ' . $this->courier['api_key'], 'Secret-Key: ' . $this->courier['api_secret']];
    }

    protected function realCreate(array $p): array
    {
        $r = $this->http('POST', $this->base() . '/create_order', $this->headers(), [
            'invoice' => $p['invoice'], 'recipient_name' => $p['recipient_name'], 'recipient_phone' => $p['recipient_phone'],
            'recipient_address' => mb_substr($p['address'], 0, 250), 'cod_amount' => $p['cod_amount'], 'note' => $p['note'],
        ]);
        $c = $r['consignment'] ?? [];
        return ['tracking_no' => (string) ($c['tracking_code'] ?? ''), 'consignment_id' => (string) ($c['consignment_id'] ?? ''), 'fee' => 0, 'raw' => $r];
    }

    protected function realCancel(string $consignment, string $tracking): bool
    {
        return false; // Steadfast: cancel from the merchant panel
    }

    protected function realTrack(string $consignment, string $tracking): ?array
    {
        $r = $this->http('GET', $this->base() . '/status_by_cid/' . rawurlencode($consignment), $this->headers());
        $st = (string) ($r['delivery_status'] ?? '');
        return $st !== '' ? ['status' => $this->mapStatus($st), 'note' => $st, 'raw' => $r] : null;
    }

    protected function parseWebhook(array $p): array
    {
        $ref = $p['consignment_id'] ?? $p['tracking_code'] ?? null;
        $st = $p['status'] ?? $p['delivery_status'] ?? null;
        return $ref && $st ? [['ref' => (string) $ref, 'status' => $this->mapStatus((string) $st), 'note' => (string) ($p['tracking_message'] ?? $st)]] : [];
    }
}

/** RedX open API. api_key = API-ACCESS-TOKEN. Config: delivery_area_id (default), pickup_store_id. */
final class CourierRedx extends CourierBase
{
    protected array $statusMap = [
        'ready-for-delivery' => 'booked', 'pickup-pending' => 'booked', 'picked-up' => 'picked_up', 'delivery-in-progress' => 'out_for_delivery',
        'delivered' => 'delivered', 'agent-hold' => 'on_hold', 'agent-returning' => 'returning', 'returned' => 'returned', 'agent-area-change' => 'in_transit',
    ];

    public function configured(): bool
    {
        return (string) $this->courier['api_key'] !== '';
    }

    private function headers(): array
    {
        return ['API-ACCESS-TOKEN: Bearer ' . $this->courier['api_key']];
    }

    protected function realCreate(array $p): array
    {
        $r = $this->http('POST', $this->base() . '/parcel', $this->headers(), [
            'customer_name' => $p['recipient_name'], 'customer_phone' => $p['recipient_phone'], 'customer_address' => $p['address'],
            'delivery_area' => $p['upazila'] ?: $p['district'], 'delivery_area_id' => (int) $this->cfg('delivery_area_id', '1'),
            'merchant_invoice_id' => $p['invoice'], 'cash_collection_amount' => (string) $p['cod_amount'],
            'parcel_weight' => (int) round($p['weight_kg'] * 1000), 'value' => (string) $p['value'], 'instruction' => $p['note'],
            'pickup_store_id' => $this->cfg('pickup_store_id') !== '' ? (int) $this->cfg('pickup_store_id') : null,
        ]);
        return ['tracking_no' => (string) ($r['tracking_id'] ?? ''), 'consignment_id' => (string) ($r['tracking_id'] ?? ''), 'fee' => 0, 'raw' => $r];
    }

    protected function realCancel(string $consignment, string $tracking): bool
    {
        $r = $this->http('PATCH', $this->base() . '/parcels', $this->headers(), ['entity_type' => 'parcel-tracking-id', 'entity_id' => $tracking, 'update_details' => ['property_name' => 'status', 'new_value' => 'cancelled']]);
        return ($r['success'] ?? false) == true;
    }

    protected function realTrack(string $consignment, string $tracking): ?array
    {
        $r = $this->http('GET', $this->base() . '/parcel/info/' . rawurlencode($tracking), $this->headers());
        $st = (string) ($r['parcel']['status'] ?? '');
        return $st !== '' ? ['status' => $this->mapStatus($st), 'note' => $st, 'raw' => $r] : null;
    }

    protected function parseWebhook(array $p): array
    {
        $ref = $p['tracking_number'] ?? $p['tracking_id'] ?? null;
        $st = $p['status'] ?? null;
        return $ref && $st ? [['ref' => (string) $ref, 'status' => $this->mapStatus((string) $st), 'note' => (string) ($p['message_en'] ?? $st)]] : [];
    }
}

/** Paperfly merchant API. Config: username, password; api_key = paperflykey. */
final class CourierPaperfly extends CourierBase
{
    public function configured(): bool
    {
        return $this->cfg('username') !== '' && $this->cfg('password') !== '' && (string) $this->courier['api_key'] !== '';
    }

    private function headers(): array
    {
        return ['Authorization: Basic ' . base64_encode($this->cfg('username') . ':' . $this->cfg('password')), 'paperflykey: ' . $this->courier['api_key']];
    }

    protected function realCreate(array $p): array
    {
        $r = $this->http('POST', $this->base() . '/OrderPlacement', $this->headers(), [
            'merOrderRef' => $p['invoice'], 'pickMerchantName' => setting('site_name', 'SasthoBazar'), 'pickMerchantAddress' => $p['pickup_address'],
            'pickMerchantThana' => $this->cfg('pickup_thana'), 'pickMerchantDistrict' => $this->cfg('pickup_district', 'Dhaka'), 'pickupMerchantPhone' => $p['pickup_phone'],
            'productSizeWeight' => 'standard', 'productBrief' => $p['description'], 'packagePrice' => (string) $p['value'], 'max_weight' => (string) $p['weight_kg'],
            'deliveryOption' => 'regular', 'custname' => $p['recipient_name'], 'custaddress' => $p['address'], 'customerThana' => $p['upazila'],
            'customerDistrict' => $p['district'], 'custPhone' => $p['recipient_phone'],
        ]);
        $t = (string) ($r['success']['tracking_number'] ?? '');
        return ['tracking_no' => $t, 'consignment_id' => $t, 'fee' => 0, 'raw' => $r];
    }

    protected function realCancel(string $consignment, string $tracking): bool
    {
        $r = $this->http('POST', $this->base() . '/api/v1/cancel-order/', $this->headers(), ['order_id' => $tracking]);
        return isset($r['success']);
    }

    protected function realTrack(string $consignment, string $tracking): ?array
    {
        $r = $this->http('POST', $this->base() . '/API-Order-Tracking', $this->headers(), ['ReferenceNumber' => $tracking]);
        $st = (string) ($r['success']['trackingStatus'][0]['Status'] ?? '');
        return $st !== '' ? ['status' => $this->mapStatus($st), 'note' => $st, 'raw' => $r] : null;
    }

    protected function parseWebhook(array $p): array
    {
        $ref = $p['tracking_number'] ?? $p['ReferenceNumber'] ?? null;
        $st = $p['status'] ?? $p['Status'] ?? null;
        return $ref && $st ? [['ref' => (string) $ref, 'status' => $this->mapStatus((string) $st), 'note' => (string) $st]] : [];
    }
}

/** eCourier API. api_key = API-KEY, api_secret = API-SECRET; config user_id = USER-ID. */
final class CourierEcourier extends CourierBase
{
    public function configured(): bool
    {
        return (string) $this->courier['api_key'] !== '' && (string) $this->courier['api_secret'] !== '' && $this->cfg('user_id') !== '';
    }

    private function headers(): array
    {
        return ['API-KEY: ' . $this->courier['api_key'], 'API-SECRET: ' . $this->courier['api_secret'], 'USER-ID: ' . $this->cfg('user_id')];
    }

    protected function realCreate(array $p): array
    {
        $r = $this->http('POST', $this->base() . '/order-place', $this->headers(), [
            'recipient_name' => $p['recipient_name'], 'recipient_mobile' => $p['recipient_phone'], 'recipient_city' => $p['district'],
            'recipient_area' => $p['upazila'], 'recipient_thana' => $p['upazila'], 'recipient_address' => $p['address'],
            'package_code' => $this->cfg('package_code', '#2416'), 'product_price' => $p['cod_amount'],
            'payment_method' => $p['cod_amount'] > 0 ? 'COD' : 'MPAY', 'recipient_landmark' => '', 'parcel_type' => 'BOX',
            'requested_delivery_time' => date('Y-m-d', strtotime('+1 day')), 'delivery_hour' => 'any', 'recipient_zip' => $p['postcode'],
            'product_id' => $p['invoice'], 'pick_address' => $p['pickup_address'], 'comments' => $p['note'], 'number_of_item' => $p['items'],
            'actual_product_price' => $p['value'], 'pgwid' => '', 'pgwtxn_id' => '',
        ]);
        $id = (string) ($r['ID'] ?? $r['tracking'] ?? '');
        return ['tracking_no' => $id, 'consignment_id' => $id, 'fee' => 0, 'raw' => $r];
    }

    protected function realCancel(string $consignment, string $tracking): bool
    {
        $r = $this->http('POST', $this->base() . '/cancel-order', $this->headers(), ['tracking' => $tracking, 'comment' => 'Cancelled by merchant']);
        return ($r['success'] ?? false) == true;
    }

    protected function realTrack(string $consignment, string $tracking): ?array
    {
        $r = $this->http('POST', $this->base() . '/track', $this->headers(), ['product_id' => '', 'ecr' => $tracking]);
        $st = (string) ($r['query_data']['status'][0]['status'] ?? $r['status'] ?? '');
        return $st !== '' ? ['status' => $this->mapStatus($st), 'note' => $st, 'raw' => $r] : null;
    }

    protected function parseWebhook(array $p): array
    {
        $ref = $p['ID'] ?? $p['tracking'] ?? $p['ecr'] ?? null;
        $st = $p['status'] ?? $p['Status'] ?? null;
        return $ref && $st ? [['ref' => (string) $ref, 'status' => $this->mapStatus((string) $st), 'note' => (string) $st]] : [];
    }
}
