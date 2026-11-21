<?php
declare(strict_types=1);

/**
 * Shipments: one per sub-order (seller). Courier status drives the sub-order:
 *   picked_up / in_transit / out_for_delivery -> sub-order "shipped"
 *   delivered                                  -> sub-order "delivered" (vendor wallet, COD collected)
 *   returned                                   -> sub-order "returned" (stock back to its batches automatically)
 *                                                 or "cancelled" if it never left
 *   failed                                     -> attempt counted; the courier usually retries or returns
 * Staff status changes on the order (e.g. "delivered") are mirrored back onto the shipment.
 *
 * COD: cod_status none -> pending (to collect) -> collected (courier has the money) -> settled (courier paid us).
 */
final class Shipment
{
    public const STATUSES = ['pending', 'booked', 'picked_up', 'in_transit', 'out_for_delivery', 'on_hold', 'failed', 'delivered', 'partial_delivered', 'returning', 'returned', 'cancelled', 'lost'];
    public const FINAL = ['delivered', 'partial_delivered', 'returned', 'lost'];
    /** Statuses staff may set by hand (own delivery man, courier phone updates). */
    public const MANUAL = ['picked_up', 'in_transit', 'out_for_delivery', 'on_hold', 'failed', 'delivered', 'returning', 'returned', 'lost'];
    private const RANK = ['pending' => 0, 'cancelled' => 0, 'booked' => 1, 'picked_up' => 2, 'in_transit' => 3, 'on_hold' => 3, 'failed' => 4,
                          'out_for_delivery' => 4, 'returning' => 5, 'delivered' => 9, 'partial_delivered' => 9, 'returned' => 9, 'lost' => 9];
    public const BADGES = [
        'pending' => 'secondary', 'booked' => 'info', 'picked_up' => 'primary', 'in_transit' => 'primary', 'out_for_delivery' => 'primary',
        'on_hold' => 'warning', 'failed' => 'danger', 'delivered' => 'success', 'partial_delivered' => 'success', 'returning' => 'warning',
        'returned' => 'dark', 'cancelled' => 'secondary', 'lost' => 'danger',
    ];
    public const COD_BADGES = ['none' => 'light', 'pending' => 'warning', 'collected' => 'info', 'settled' => 'success'];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function badge(string $status): string
    {
        return '<span class="badge text-bg-' . (self::BADGES[$status] ?? 'secondary') . '">' . e(__('sh_' . $status)) . '</span>';
    }

    /** Called by Order::place for every sub-order. */
    public static function createForSplit(int $orderId, int $splitId, array $g, float $codAmount, string $option, ?int $by): int
    {
        $id = self::db()->insert('shipments', [
            'order_id'           => $orderId,
            'split_id'           => $splitId,
            'delivery_option'    => $option,
            'zone_id'            => $g['zone_id'] ?? null,
            'rate_id'            => $g['rate_id'] ?? null,
            'origin_district_id' => $g['origin_district_id'] ?? null,
            'dest_district_id'   => $g['dest_district_id'] ?? null,
            'status'             => 'pending',
            'cod_amount'         => round($codAmount, 2),
            'shipping_fee'       => round((float) ($g['fee'] ?? 0), 2),
            'cod_charge'         => round((float) ($g['cod'] ?? 0), 2),
            'cod_status'         => $codAmount > 0 ? 'pending' : 'none',
            'weight_grams'       => max(0, (int) ($g['weight'] ?? 0)),
            'created_by'         => $by,
        ]);
        self::event($id, 'pending', null, 'system', null, $by);
        return $id;
    }

    public static function event(int $shipmentId, string $status, ?string $note, string $source, ?array $raw, ?int $by): void
    {
        self::db()->insert('shipment_events', [
            'shipment_id' => $shipmentId,
            'status'      => $status,
            'note'        => $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null,
            'source'      => $source,
            'raw'         => $raw !== null ? json_encode($raw, JSON_UNESCAPED_UNICODE) : null,
            'created_by'  => $by,
        ]);
    }

    /** Shipment with order, sub-order, courier and seller columns. */
    public static function detail(int $id): ?array
    {
        return self::db()->fetch(
            'SELECT sh.*, o.order_no, o.status AS order_status, o.payment_method, o.shipping_name, o.shipping_phone, o.shipping_address,
                    o.postcode, o.district_id, o.upazila_id, o.division_id, o.customer_note, o.placed_at,
                    s.sub_order_no, s.status AS split_status, s.vendor_id, s.grand_total AS split_total,
                    v.shop_name, v.address AS vendor_address, v.pickup_phone, v.phone AS vendor_phone,
                    c.name AS courier_name, c.code AS courier_code, c.tracking_url_template,
                    z.name AS zone_name, z.name_bn AS zone_name_bn,
                    (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE split_id = sh.split_id AND status <> \'cancelled\') AS items
               FROM shipments sh
               JOIN orders o ON o.id = sh.order_id
          LEFT JOIN order_vendor_splits s ON s.id = sh.split_id
          LEFT JOIN vendors v ON v.id = s.vendor_id
          LEFT JOIN couriers c ON c.id = sh.courier_id
          LEFT JOIN shipping_zones z ON z.id = sh.zone_id
              WHERE sh.id = :id',
            ['id' => $id]
        );
    }

    public static function trackingUrl(array $sh): ?string
    {
        if (!$sh['tracking_no'] || empty($sh['tracking_url_template'])) {
            return null;
        }
        return str_replace('{tracking_no}', rawurlencode((string) $sh['tracking_no']), (string) $sh['tracking_url_template']);
    }

    /**
     * Hand a shipment to a courier (or to our own delivery man).
     * @param array $rider ['name' => , 'phone' => ] for own delivery
     * @throws OrderException
     */
    public static function book(int $id, string $courierCode, array $rider, ?int $by): array
    {
        $sh = self::detail($id);
        if (!$sh) {
            throw new OrderException(__('shipment_not_found'));
        }
        if (!in_array($sh['status'], ['pending', 'cancelled'], true)) {
            throw new OrderException(__('shipment_already_booked'));
        }
        if ($sh['delivery_option'] === 'pickup') {
            throw new OrderException(__('shipment_is_pickup'));
        }
        if (!in_array($sh['split_status'], ['confirmed', 'processing', 'packed'], true)) {
            throw new OrderException(__('shipment_split_not_ready'));
        }
        $courier = Courier::findByCode($courierCode);
        if (!$courier || !(int) $courier['is_active']) {
            throw new OrderException(__('courier_inactive'));
        }
        $adapter = Courier::adapter($courier);
        if ($courierCode === 'self') {
            if (trim((string) ($rider['name'] ?? '')) === '' || !Auth::isValidBdPhone((string) ($rider['phone'] ?? ''))) {
                throw new OrderException(__('rider_required'));
            }
        }
        $result = $adapter->createParcel(Courier::payload($sh, $rider)); // may throw OrderException with the courier's message
        self::db()->transaction(static function (Database $db) use ($id, $courier, $result, $rider, $by, $courierCode): void {
            $db->fetch('SELECT id FROM shipments WHERE id = :id FOR UPDATE', ['id' => $id]);
            $db->update('shipments', [
                'courier_id'      => (int) $courier['id'],
                'tracking_no'     => $result['tracking_no'],
                'consignment_id'  => $result['consignment_id'],
                'delivery_charge' => $result['fee'] ?? 0,
                'status'          => 'booked',
                'api_response'    => json_encode($result['raw'] ?? null, JSON_UNESCAPED_UNICODE),
                'is_mock'         => !empty($result['mock']) ? 1 : 0,
                'rider_name'      => $courierCode === 'self' ? mb_substr(trim((string) $rider['name']), 0, 150) : null,
                'rider_phone'     => $courierCode === 'self' ? Auth::normalizePhone((string) $rider['phone']) : null,
                'rider_user_id'   => $courierCode === 'self' && !empty($rider['user_id']) ? (int) $rider['user_id'] : null,
                'booked_at'       => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $id]);
            self::event($id, 'booked', __('sh_booked_with', ['courier' => $courier['name'], 'tracking' => $result['tracking_no']]) . (!empty($result['mock']) ? ' (mock)' : ''), 'staff', $result['raw'] ?? null, $by);
        });
        Audit::log('shipments.book', 'shipments', $id, null, ['courier' => $courierCode, 'tracking' => $result['tracking_no'], 'mock' => !empty($result['mock'])]);
        return self::detail($id);
    }

    /** Cancel the courier booking (before pickup); the shipment can be booked again. */
    public static function cancelBooking(int $id, ?int $by): void
    {
        $sh = self::detail($id);
        if (!$sh || $sh['status'] !== 'booked') {
            throw new OrderException(__('shipment_cannot_cancel'));
        }
        $courier = Courier::find((int) $sh['courier_id']);
        if ($courier) {
            Courier::adapter($courier)->cancelParcel((string) $sh['consignment_id'], (string) $sh['tracking_no']);
        }
        self::db()->update('shipments', ['status' => 'cancelled', 'booked_at' => null], 'id = :id', ['id' => $id]);
        self::event($id, 'cancelled', __('sh_booking_cancelled'), 'staff', null, $by);
    }

    /**
     * New status from the courier API, a webhook or staff. Older / duplicate updates are ignored.
     * Moves the sub-order along (stock + wallet handled by Order). Returns true when something changed.
     */
    public static function updateStatus(int $id, string $status, ?string $note, string $source, ?array $raw, ?int $by): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new OrderException(__('v_in'));
        }
        $orderId = (int) self::db()->fetchColumn('SELECT order_id FROM shipments WHERE id = :id', ['id' => $id]);
        if (!$orderId) {
            throw new OrderException(__('shipment_not_found'));
        }
        return self::db()->transaction(static function (Database $db) use ($id, $orderId, $status, $note, $source, $raw, $by): bool {
            $db->fetch('SELECT id FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]); // same lock order as Order
            $sh = $db->fetch('SELECT * FROM shipments WHERE id = :id FOR UPDATE', ['id' => $id]);
            $from = $sh['status'];
            if ($from === $status && $status !== 'failed') {
                return false;
            }
            if (in_array($from, self::FINAL, true)) {
                self::event($id, $status, __('sh_ignored_final', ['status' => __('sh_' . $from)]) . ($note ? ' ' . $note : ''), $source, $raw, $by);
                return false;
            }
            if ((self::RANK[$status] ?? 0) < (self::RANK[$from] ?? 0) && !in_array($status, ['failed', 'on_hold', 'returning', 'returned', 'lost'], true)) {
                self::event($id, $status, __('sh_ignored_old') . ($note ? ' ' . $note : ''), $source, $raw, $by);
                return false;
            }
            $now = date('Y-m-d H:i:s');
            $set = ['status' => $status, 'last_synced_at' => $source === 'staff' ? $sh['last_synced_at'] : $now];
            if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery'], true) && !$sh['shipped_at']) {
                $set['shipped_at'] = $now;
            }
            if ($status === 'failed') {
                $set['failed_attempts'] = (int) $sh['failed_attempts'] + 1;
            }
            if (in_array($status, ['delivered', 'partial_delivered'], true)) {
                $set['delivered_at'] = $now;
                if ($sh['cod_status'] === 'pending') {
                    $set['cod_status'] = 'collected';
                    $set['collected_amount'] = $sh['cod_amount'];
                }
            }
            if ($status === 'returned') {
                $set['returned_at'] = $now;
                if ($sh['cod_status'] === 'pending') {
                    $set['cod_status'] = 'none';
                }
            }
            $db->update('shipments', $set, 'id = :id', ['id' => $id]);
            self::event($id, $status, $note, $source, $raw, $by);

            // drive the sub-order
            if ($sh['split_id']) {
                self::driveSplit((int) $sh['split_id'], $status, $note, $by);
            }
            return true;
        });
    }

    private static function driveSplit(int $splitId, string $status, ?string $note, ?int $by): void
    {
        $split = self::db()->fetchColumn('SELECT status FROM order_vendor_splits WHERE id = :id', ['id' => $splitId]);
        $steps = [];
        if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery'], true) && in_array($split, ['confirmed', 'processing', 'packed'], true)) {
            $steps = $split === 'confirmed' ? ['processing', 'shipped'] : ['shipped'];
        } elseif (in_array($status, ['delivered', 'partial_delivered'], true)) {
            $steps = match ($split) {
                'confirmed'  => ['processing', 'shipped', 'delivered'],
                'processing', 'packed' => ['shipped', 'delivered'],
                'shipped', 'out_for_delivery' => ['delivered'],
                default => [],
            };
        } elseif ($status === 'returned') {
            $steps = match ($split) {
                'shipped', 'out_for_delivery', 'delivered' => ['returned'],
                'confirmed', 'processing', 'packed' => ['cancelled'],
                default => [],
            };
        }
        foreach ($steps as $to) {
            Order::changeSplitStatus($splitId, $to, $note !== null && $note !== '' ? $note : __('sh_' . $status), $to === end($steps), $by, null, true);
        }
    }

    /** Staff changed the sub-order status by hand: keep the shipment in step (no further propagation). */
    public static function mirrorSplit(int $splitId, string $splitStatus, ?int $by): void
    {
        $db = self::db();
        $sh = $db->fetch('SELECT * FROM shipments WHERE split_id = :s ORDER BY id DESC LIMIT 1 FOR UPDATE', ['s' => $splitId]);
        if (!$sh || in_array($sh['status'], self::FINAL, true)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $set = null;
        if ($splitStatus === 'shipped' && in_array($sh['status'], ['pending', 'booked', 'cancelled'], true)) {
            $set = ['status' => 'picked_up', 'shipped_at' => $now];
        } elseif ($splitStatus === 'delivered') {
            $set = ['status' => $sh['delivery_option'] === 'pickup' ? 'delivered' : 'delivered', 'delivered_at' => $now];
            if ($sh['cod_status'] === 'pending') {
                $set += ['cod_status' => $sh['courier_id'] ? 'collected' : 'settled', 'collected_amount' => $sh['cod_amount']];
            }
        } elseif ($splitStatus === 'returned') {
            $set = ['status' => 'returned', 'returned_at' => $now, 'cod_status' => $sh['cod_status'] === 'pending' ? 'none' : $sh['cod_status']];
        } elseif ($splitStatus === 'cancelled') {
            if ($sh['status'] === 'booked' && $sh['courier_id'] && ($c = Courier::find((int) $sh['courier_id']))) {
                try {
                    Courier::adapter($c)->cancelParcel((string) $sh['consignment_id'], (string) $sh['tracking_no']);
                } catch (Throwable) {
                    // courier refused; staff will sort it out with the courier
                }
            }
            $set = ['status' => 'cancelled', 'cod_status' => $sh['cod_status'] === 'pending' ? 'none' : $sh['cod_status']];
        }
        if ($set) {
            $db->update('shipments', $set, 'id = :id', ['id' => (int) $sh['id']]);
            self::event((int) $sh['id'], $set['status'], __('sh_from_order'), 'staff', null, $by);
        }
    }

    /** Ask the courier for the latest status (mock: next step). */
    public static function sync(int $id, ?int $by): ?string
    {
        $sh = self::detail($id);
        if (!$sh || !$sh['courier_id'] || in_array($sh['status'], array_merge(self::FINAL, ['pending', 'cancelled']), true)) {
            return null;
        }
        $courier = Courier::find((int) $sh['courier_id']);
        $res = Courier::adapter($courier)->track((string) $sh['consignment_id'], (string) $sh['tracking_no'], $sh['status']);
        self::db()->update('shipments', ['last_synced_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        if ($res && $res['status'] && $res['status'] !== $sh['status']) {
            self::updateStatus($id, $res['status'], $res['note'] ?? null, 'api', $res['raw'] ?? null, $by);
            return $res['status'];
        }
        return null;
    }

    /**
     * Courier paid us the COD of these shipments (all must be "collected", same courier).
     * @throws OrderException
     */
    public static function settleCod(array $ids, int $courierId, string $reference, float $received, ?string $note, string $date, int $by): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            throw new OrderException(__('cod_pick_shipments'));
        }
        return self::db()->transaction(static function (Database $db) use ($ids, $courierId, $reference, $received, $note, $date, $by): int {
            sort($ids);
            $in = implode(',', $ids);
            $rows = $db->fetchAll("SELECT id, courier_id, cod_status, collected_amount, delivery_charge FROM shipments WHERE id IN ({$in}) FOR UPDATE");
            if (count($rows) !== count($ids)) {
                throw new OrderException(__('shipment_not_found'));
            }
            $expected = 0.0;
            foreach ($rows as $r) {
                if ((int) $r['courier_id'] !== $courierId || $r['cod_status'] !== 'collected') {
                    throw new OrderException(__('cod_not_collected'));
                }
                $expected += (float) $r['collected_amount'];
            }
            $expected = round($expected, 2);
            if ($received < 0 || $received > $expected + 0.009) {
                throw new OrderException(__('cod_received_range', ['amount' => money($expected)]));
            }
            $sid = $db->insert('cod_settlements', [
                'courier_id' => $courierId, 'reference_no' => mb_substr($reference, 0, 100), 'shipments_count' => count($ids),
                'amount_expected' => $expected, 'amount_received' => round($received, 2), 'courier_fees' => round($expected - $received, 2),
                'note' => $note, 'received_at' => $date, 'created_by' => $by,
            ]);
            $db->query("UPDATE shipments SET cod_status = 'settled', cod_settlement_id = :s WHERE id IN ({$in})", ['s' => $sid]);
            foreach ($ids as $id) {
                self::event($id, 'cod_settled', __('cod_settled_ref', ['ref' => $reference]), 'staff', null, $by);
            }
            Audit::log('shipments.cod_settle', 'cod_settlements', $sid, null, ['shipments' => count($ids), 'expected' => $expected, 'received' => $received]);
            return $sid;
        });
    }
}
