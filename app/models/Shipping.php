<?php
declare(strict_types=1);

/**
 * Delivery charges (Bangladesh).
 *
 * Zones (shipping_zones.zone_type): city = Inside Dhaka City, suburb = Dhaka Sub-area, outside = Outside Dhaka.
 * Every district is mapped to a zone (locations.shipping_zone_id); admins edit both in Shipping settings.
 *
 * One shipment per seller. The zone of a shipment depends on the ROUTE:
 *   pickup district == customer district        -> the "city" zone (same-city delivery)
 *   pickup inside the Dhaka area (city/suburb)  -> the customer district's zone
 *   otherwise                                   -> the "outside" zone
 * In-house stock ships from the warehouse district (setting warehouse_district_id); vendors from their pickup address.
 *
 * Rate of a shipment: cheapest active rate of that zone + service (standard / express) that fits weight and amount:
 *   fee = rate + extra_per_kg x every started kg above 1 kg; 0 when the seller's subtotal reaches free_shipping_threshold.
 *   cod_charge_percent applies to the shipment's payable amount when the customer pays cash on delivery.
 * Delivery options: standard, express (only where every shipment has an express rate), pickup (in-house orders only).
 */
final class Shipping
{
    public const OPTIONS = ['standard', 'express', 'pickup'];
    public const ZONE_TYPES = ['city', 'suburb', 'outside'];

    private static ?array $zones = null;
    private static array $districtZone = [];
    private static array $vendorOrigin = [];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** Active zones keyed by id. */
    public static function zones(): array
    {
        if (self::$zones === null) {
            self::$zones = array_column(self::db()->fetchAll('SELECT * FROM shipping_zones WHERE is_active = 1 ORDER BY sort_order, id'), null, 'id');
        }
        return self::$zones;
    }

    private static function zoneOfType(string $type): ?array
    {
        foreach (self::zones() as $z) {
            if ($z['zone_type'] === $type) {
                return $z;
            }
        }
        return null;
    }

    /** Zone id a district belongs to (district mapping, else its division's, else null). */
    public static function districtZoneId(int $district): ?int
    {
        if (!array_key_exists($district, self::$districtZone)) {
            $id = self::db()->fetchColumn(
                "SELECT COALESCE(d.shipping_zone_id, dv.shipping_zone_id) FROM locations d LEFT JOIN locations dv ON dv.id = d.parent_id
                  WHERE d.id = :id AND d.type = 'district'",
                ['id' => $district]
            );
            self::$districtZone[$district] = $id ? (int) $id : null;
        }
        return self::$districtZone[$district];
    }

    public static function warehouseDistrict(): int
    {
        return (int) setting('warehouse_district_id', 147);
    }

    /** District a seller ships from (vendor pickup location, in-house = warehouse). */
    public static function originDistrict(?int $vendorId): int
    {
        if (!$vendorId) {
            return self::warehouseDistrict();
        }
        if (!isset(self::$vendorOrigin[$vendorId])) {
            $loc = self::db()->fetchColumn('SELECT location_id FROM vendors WHERE id = :id', ['id' => $vendorId]);
            $chain = VendorAccount::locationChain($loc ? (int) $loc : null);
            self::$vendorOrigin[$vendorId] = $chain['district'] ? (int) $chain['district']['id'] : self::warehouseDistrict();
        }
        return self::$vendorOrigin[$vendorId];
    }

    /** Zone for a route (see class comment). */
    public static function routeZone(int $originDistrict, int $destDistrict): ?array
    {
        $zones = self::zones();
        $destZone = ($id = self::districtZoneId($destDistrict)) && isset($zones[$id]) ? $zones[$id] : null;
        $originZone = ($id = self::districtZoneId($originDistrict)) && isset($zones[$id]) ? $zones[$id] : null;
        if ($originDistrict === $destDistrict) {
            return ($destZone && $destZone['zone_type'] === 'city') ? $destZone : (self::zoneOfType('city') ?? $destZone);
        }
        if ($originZone && in_array($originZone['zone_type'], ['city', 'suburb'], true)) {
            return $destZone ?? self::zoneOfType('outside');
        }
        return self::zoneOfType('outside') ?? $destZone;
    }

    /** Cheapest active rate of a zone + service that fits. */
    public static function rate(int $zoneId, string $service, int $weightGrams, float $subtotal): ?array
    {
        return self::db()->fetch(
            'SELECT * FROM shipping_rates
              WHERE zone_id = :z AND service = :s AND is_active = 1
                AND min_weight_grams <= :w1 AND (max_weight_grams IS NULL OR max_weight_grams >= :w2)
                AND min_order_amount <= :a
           ORDER BY rate ASC, id ASC LIMIT 1',
            ['z' => $zoneId, 's' => $service, 'w1' => $weightGrams, 'w2' => $weightGrams, 'a' => (string) $subtotal]
        );
    }

    /** @return array{fee: float, free: bool, threshold: ?float} */
    public static function fee(array $rate, int $weightGrams, float $subtotal): array
    {
        $extraKg = max(0, (int) ceil(($weightGrams - 1000) / 1000));
        $fee = round((float) $rate['rate'] + $extraKg * (float) $rate['extra_per_kg'], 2);
        $threshold = $rate['free_shipping_threshold'] !== null ? (float) $rate['free_shipping_threshold'] : null;
        $free = $threshold !== null && $subtotal >= $threshold;
        return ['fee' => $free ? 0.0 : $fee, 'free' => $free, 'threshold' => $threshold];
    }

    /**
     * Per-seller delivery quote for cart lines (each: vendor_id, line_total, line_weight, shop_name / shop_name_bn).
     * @return array{ok: bool, error: ?string, option: string, available: array<string,bool>, groups: array, shipping: float}
     */
    public static function quoteCart(array $lines, ?int $destDistrict, string $option): array
    {
        if (!in_array($option, self::OPTIONS, true)) {
            $option = 'standard';
        }
        $groups = [];
        foreach ($lines as $l) {
            $vid = $l['vendor_id'] === null ? 0 : (int) $l['vendor_id'];
            $groups[$vid] ??= [
                'vendor_id' => $vid ?: null,
                'seller'    => $vid ? tr($l['shop_name_bn'] ?? null, $l['shop_name'] ?? '') : __('app_name'),
                'subtotal'  => 0.0,
                'weight'    => 0,
                'line_ids'  => [],
            ];
            $groups[$vid]['subtotal'] += (float) $l['line_total'];
            $groups[$vid]['weight'] += (int) ($l['line_weight'] ?? 0);
            $groups[$vid]['line_ids'][] = (int) $l['id'];
        }

        $available = [
            'standard' => true,
            'express'  => $destDistrict !== null,
            'pickup'   => (bool) setting('store_pickup_enabled', true) && $groups && !array_filter(array_keys($groups)),
        ];
        $error = null;
        $total = 0.0;
        foreach ($groups as $vid => &$g) {
            $g['origin_district_id'] = self::originDistrict($vid ?: null);
            $g['dest_district_id'] = $destDistrict;
            $g += ['zone_id' => null, 'zone_name' => '', 'zone_type' => null, 'rate_id' => null, 'service' => $option,
                   'fee' => 0.0, 'free' => false, 'threshold' => null, 'cod_percent' => 0.0, 'est_min' => null, 'est_max' => null];
            if ($destDistrict === null) {
                continue;
            }
            $zone = self::routeZone($g['origin_district_id'], $destDistrict);
            if (!$zone) {
                $available['standard'] = $available['express'] = false;
                continue;
            }
            $g['zone_id'] = (int) $zone['id'];
            $g['zone_name'] = tr($zone['name_bn'], $zone['name']);
            if ($g['origin_district_id'] === $destDistrict && (int) (self::districtZoneId($destDistrict) ?? 0) !== (int) $zone['id']) {
                $g['zone_name'] = __('zone_same_city'); // e.g. Chattogram seller -> Chattogram customer uses the city rate
            }
            $g['zone_type'] = $zone['zone_type'];
            $weight = max(0, (int) $g['weight']);
            if (!self::rate((int) $zone['id'], 'express', $weight, $g['subtotal'])) {
                $available['express'] = false;
            }
            if (!self::rate((int) $zone['id'], 'standard', $weight, $g['subtotal'])) {
                $available['standard'] = false;
            }
        }
        unset($g);

        if (!$available[$option]) {
            $error = $option === 'pickup' ? __('pickup_not_available') : ($option === 'express' ? __('express_not_available') : __('no_delivery_area'));
        } elseif ($option !== 'pickup' && $destDistrict === null) {
            $error = __('select_area_first');
        }
        if ($error === null) {
            foreach ($groups as &$g) {
                if ($option === 'pickup') {
                    $g['service'] = 'pickup';
                    continue;
                }
                $rate = self::rate((int) $g['zone_id'], $option, max(0, (int) $g['weight']), $g['subtotal']);
                $f = self::fee($rate, max(0, (int) $g['weight']), $g['subtotal']);
                $g['rate_id'] = (int) $rate['id'];
                $g['fee'] = $f['fee'];
                $g['free'] = $f['free'];
                $g['threshold'] = $f['threshold'];
                $g['cod_percent'] = (float) $rate['cod_charge_percent'];
                $g['est_min'] = $rate['est_days_min'] !== null ? (int) $rate['est_days_min'] : null;
                $g['est_max'] = $rate['est_days_max'] !== null ? (int) $rate['est_days_max'] : null;
                $total += $g['fee'];
            }
            unset($g);
        }
        return ['ok' => $error === null, 'error' => $error, 'option' => $option, 'available' => $available, 'groups' => $groups, 'shipping' => round($total, 2)];
    }

    /** "1-2 days" / "same day" / "store pickup" for one shipment group. */
    public static function etaText(?array $g): string
    {
        if (!$g) {
            return '';
        }
        if (($g['service'] ?? '') === 'pickup') {
            return __('eta_pickup');
        }
        if (!isset($g['est_max']) || $g['est_max'] === null) {
            return '';
        }
        if ($g['est_max'] === 0) {
            return __('eta_same_day');
        }
        $min = $g['est_min'] ?? $g['est_max'];
        return __('eta_days', ['days' => num($min === $g['est_max'] ? (string) $min : $min . '-' . $g['est_max'])]);
    }

    /** Longest ETA among the groups (for the checkout summary). */
    public static function etaSummary(array $quote): string
    {
        $worst = null;
        foreach ($quote['groups'] ?? [] as $g) {
            if ($worst === null || ($g['est_max'] ?? -1) > ($worst['est_max'] ?? -1)) {
                $worst = $g;
            }
        }
        return self::etaText($worst);
    }
}
