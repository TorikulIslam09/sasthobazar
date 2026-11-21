<?php
declare(strict_types=1);

/**
 * Courier companies (couriers table) + adapter factory.
 * api_config JSON holds per-courier settings: mock (bool), webhook_token, store_id, client_id, username, password ...
 */
final class Courier
{
    /** code => adapter class */
    public const ADAPTERS = [
        'self'      => CourierOwn::class,
        'pathao'    => CourierPathao::class,
        'steadfast' => CourierSteadfast::class,
        'redx'      => CourierRedx::class,
        'paperfly'  => CourierPaperfly::class,
        'ecourier'  => CourierEcourier::class,
    ];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function all(bool $activeOnly = false): array
    {
        return self::db()->fetchAll('SELECT * FROM couriers' . ($activeOnly ? ' WHERE is_active = 1' : '') . " ORDER BY code = 'self' DESC, name");
    }

    public static function find(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM couriers WHERE id = :id', ['id' => $id]);
    }

    public static function findByCode(string $code): ?array
    {
        return self::db()->fetch('SELECT * FROM couriers WHERE code = :c', ['c' => $code]);
    }

    public static function config(array $c): array
    {
        $cfg = json_decode((string) ($c['api_config'] ?? ''), true);
        return is_array($cfg) ? $cfg : [];
    }

    public static function adapter(array $c): CourierBase
    {
        $class = self::ADAPTERS[$c['code']] ?? null;
        if ($class === null) {
            throw new OrderException(__('courier_no_adapter'));
        }
        return new $class($c, self::config($c));
    }

    /** Public webhook URL for a courier (token in the query string). */
    public static function webhookUrl(array $c): string
    {
        $base = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '');
        return $base . url('webhooks/courier/' . $c['code']) . '?token=' . rawurlencode((string) (self::config($c)['webhook_token'] ?? ''));
    }

    /** Everything an adapter needs to book a parcel. */
    public static function payload(array $sh, array $rider = []): array
    {
        $loc = static function (?int $id): string {
            if (!$id) {
                return '';
            }
            return (string) self::db()->fetchColumn('SELECT name_en FROM locations WHERE id = :id', ['id' => $id]);
        };
        $items = self::db()->fetchAll("SELECT product_name, variant_name, quantity FROM order_items WHERE split_id = :s AND status <> 'cancelled'", ['s' => (int) $sh['split_id']]);
        $desc = implode(', ', array_map(static fn($i) => $i['product_name'] . ($i['variant_name'] ? ' ' . $i['variant_name'] : '') . ' x' . $i['quantity'], $items));
        return [
            'shipment_id'     => (int) $sh['id'],
            'invoice'         => (string) ($sh['sub_order_no'] ?? $sh['order_no']),
            'order_no'        => (string) $sh['order_no'],
            'recipient_name'  => (string) $sh['shipping_name'],
            'recipient_phone' => (string) $sh['shipping_phone'],
            'address'         => trim($sh['shipping_address'] . ', ' . $loc($sh['upazila_id'] ? (int) $sh['upazila_id'] : null) . ', ' . $loc($sh['district_id'] ? (int) $sh['district_id'] : null), ', '),
            'district'        => $loc($sh['district_id'] ? (int) $sh['district_id'] : null),
            'upazila'         => $loc($sh['upazila_id'] ? (int) $sh['upazila_id'] : null),
            'postcode'        => (string) ($sh['postcode'] ?? ''),
            'cod_amount'      => round((float) $sh['cod_amount'], 2),
            'value'           => round((float) $sh['split_total'], 2),
            'weight_kg'       => max(0.5, round(((int) $sh['weight_grams']) / 1000, 2)),
            'items'           => max(1, (int) $sh['items']),
            'description'     => mb_substr($desc, 0, 250),
            'note'            => mb_substr((string) ($sh['customer_note'] ?? ''), 0, 200),
            'express'         => $sh['delivery_option'] === 'express',
            'pickup_address'  => (string) ($sh['vendor_address'] ?? setting('store_pickup_address', '')),
            'pickup_phone'    => (string) ($sh['pickup_phone'] ?: ($sh['vendor_phone'] ?? setting('contact_phone', ''))),
            'shipping_fee'    => (float) $sh['shipping_fee'],
            'rider'           => $rider,
        ];
    }
}
