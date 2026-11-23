<?php
declare(strict_types=1);

/**
 * Orders - placing, status changes, prescription review, payments, returns & refunds.
 *
 * Status flow
 *   pending ─┬─> confirmed > processing > shipped > delivered
 *            │      (Rx orders start at prescription_review; approving the prescription confirms them)
 *   any open status ─> cancelled          (stock goes back to the same batches)
 *   shipped / delivered ─> returned       (remaining stock goes back)
 *
 * Every change runs in a DB transaction with the order row locked (SELECT ... FOR UPDATE) and writes
 * order_status_history. Placing an order locks the cart rows, the coupon row and then the products in
 * ascending id order (same order as Inventory), so parallel checkouts cannot oversell or overuse a coupon.
 */
final class Order
{
    public const STATUSES = ['pending', 'prescription_review', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'returned', 'failed'];
    /** Main path shown on the tracking timeline */
    public const FLOW = ['pending', 'prescription_review', 'confirmed', 'processing', 'shipped', 'delivered'];
    public const OPEN = ['pending', 'prescription_review', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery'];
    /** Allowed manual transitions (prescription_review -> confirmed only through reviewPrescription) */
    public const NEXT = [
        'pending'             => ['confirmed', 'cancelled'],
        'prescription_review' => ['cancelled'],
        'confirmed'           => ['processing', 'cancelled'],
        'processing'          => ['shipped', 'cancelled'],
        'packed'              => ['shipped', 'cancelled'],
        'shipped'             => ['delivered', 'returned'],
        'out_for_delivery'    => ['delivered', 'returned'],
        'delivered'           => ['returned'],
        'cancelled'           => [],
        'returned'            => [],
        'failed'              => [],
    ];
    public const CUSTOMER_CANCELLABLE = ['pending', 'prescription_review'];
    /** Settings → Orders "customers can cancel until": pending (not confirmed yet) | confirmed | processing (not packed yet) */
    public const CANCEL_UNTIL = ['pending' => ['pending', 'prescription_review'], 'confirmed' => ['pending', 'prescription_review', 'confirmed'],
                                 'processing' => ['pending', 'prescription_review', 'confirmed', 'processing']];

    /** May the customer cancel this order themselves (from My orders)? */
    public static function customerCanCancel(array $o): bool
    {
        $until = (string) setting('customer_cancel_until', 'confirmed');
        return in_array($o['status'], self::CANCEL_UNTIL[$until] ?? self::CUSTOMER_CANCELLABLE, true);
    }
    public const PAYMENT_METHODS = ['cod', 'bkash', 'nagad', 'rocket'];
    public const MANUAL_METHODS = ['bkash', 'nagad', 'rocket'];
    public const REFUND_METHODS = ['bkash', 'nagad', 'bank', 'cash'];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** Payment methods the shop currently accepts. */
    public static function enabledMethods(): array
    {
        $out = [];
        if (setting('cod_enabled', true)) {
            $out[] = 'cod';
        }
        foreach (self::MANUAL_METHODS as $m) {
            if (setting($m . '_enabled', false) && trim((string) setting($m . '_number', '')) !== '') {
                $out[] = $m;
            }
        }
        return $out;
    }

    /* ======================================================================
     * Totals (used by checkout preview and by place())
     * ==================================================================== */

    /**
     * @param array $cart Cart::summary()
     * @return array subtotal, bogo_discount, coupon, coupon_discount, coupon_error, first_order, first_order_discount,
     *   promo_discount (buy-X-get-Y + first-order), discount_total, free_shipping, free_offer, shipping, quote, groups,
     *   cod_charge, grand_total, line_discounts (all discounts per cart line), line_parts (line id => bogo / coupon / first)
     *   quote  = Shipping::quoteCart() (ok / error / available options)
     *   groups = one entry per seller (= future shipment): subtotal, discount, fee, cod, zone, rate ...
     * Order of discounts: unit price (flash / price offer) -> buy-X-get-Y -> coupon, or the first-order offer when no coupon.
     */
    public static function totals(array $cart, ?int $districtId, string $method, ?array $coupon, ?int $userId, string $option = 'standard', ?array $guest = null): array
    {
        $subtotal = (float) $cart['subtotal'];
        $okLines = array_values(array_filter($cart['lines'], static fn($l) => $l['problem'] === null));
        $parts = [];

        // 1. buy-X-get-Y (free units of the same line)
        $bogoTotal = 0.0;
        foreach ($okLines as $l) {
            $d = round((float) ($l['bogo_discount'] ?? 0), 2);
            if ($d > 0) {
                $parts[(int) $l['id']]['bogo'] = $d;
                $bogoTotal += $d;
            }
        }
        // line values after buy-X-get-Y: the base for the coupon and the first-order offer
        $net = static function (array $lines) use (&$parts): array {
            return array_map(static function ($l) use ($parts) {
                $p = $parts[(int) $l['id']] ?? [];
                $l['line_total'] = round((float) $l['line_total'] - array_sum($p), 2);
                return $l;
            }, $lines);
        };
        $netLines = $net($okLines);

        // 2. coupon
        $couponResult = null;
        $couponDiscount = 0.0;
        $freeShipping = false;
        if ($coupon !== null) {
            $couponResult = Coupon::evaluate($coupon, $netLines, $userId, $guest);
            if ($couponResult['ok']) {
                $couponDiscount = $couponResult['discount'];
                $freeShipping = $couponResult['free_shipping'];
                foreach (self::allocate($couponDiscount, $netLines, $couponResult['line_ids']) as $id => $d) {
                    $parts[$id]['coupon'] = $d;
                }
            }
        }

        // 3. first-order offer (an applied coupon replaces it)
        $firstOrder = null;
        if (!($couponResult['ok'] ?? false)) {
            $firstOrder = Promo::firstOrder($netLines, $userId, $guest);
            if ($firstOrder) {
                foreach (self::allocate($firstOrder['discount'], $netLines, $firstOrder['line_ids']) as $id => $d) {
                    $parts[$id]['first'] = $d;
                }
            }
        }
        $firstDiscount = $firstOrder ? (float) $firstOrder['discount'] : 0.0;

        $lineDiscounts = [];
        foreach ($parts as $id => $p) {
            $lineDiscounts[$id] = round(array_sum($p), 2);
        }

        // VAT per line on what the customer pays for it (inclusive: shown only; exclusive: added on top)
        $inclusive = Tax::inclusive();
        $vatLines = [];
        $vatTotal = 0.0;
        foreach ($okLines as $l) {
            $rate = Tax::rateFor($l);
            if ($rate > 0) {
                $v = Tax::amount((float) $l['line_total'] - (float) ($lineDiscounts[(int) $l['id']] ?? 0), $rate, $inclusive);
                $vatLines[(int) $l['id']] = ['rate' => $rate, 'amount' => $v];
                $vatTotal += $v;
            }
        }
        $vatTotal = round($vatTotal, 2);
        $vatAdded = $inclusive ? 0.0 : $vatTotal;

        // 4. free-delivery offer on the value after all discounts
        $freeOffer = Promo::freeShipping($net($okLines));
        $freeIds = $freeOffer['line_ids'] ?? [];

        // one shipment per seller: own fee, free threshold and COD charge
        $quote = Shipping::quoteCart($okLines, $districtId, $option);
        $groups = $quote['groups'];
        $shipping = 0.0;
        $cod = 0.0;
        foreach ($groups as &$g) {
            $g['discount'] = round(array_sum(array_map(static fn($id) => (float) ($lineDiscounts[$id] ?? 0), $g['line_ids'])), 2);
            $g['vat'] = round(array_sum(array_map(static fn($id) => (float) ($vatLines[$id]['amount'] ?? 0), $g['line_ids'])), 2);
            $g['free_offer'] = false;
            if ($freeShipping) {
                $g['fee'] = 0.0;
            } elseif ($freeIds && array_intersect($g['line_ids'], $freeIds) && $g['fee'] > 0) {
                $g['fee'] = 0.0;
                $g['free_offer'] = true;
            }
            $payableG = max(0, $g['subtotal'] - $g['discount'] + $g['fee'] + ($inclusive ? 0 : $g['vat']));
            $g['cod'] = ($method === 'cod' && $g['cod_percent'] > 0) ? round($payableG * $g['cod_percent'] / 100, 2) : 0.0;
            $shipping += $g['fee'];
            $cod += $g['cod'];
        }
        unset($g);
        $shipping = round($shipping, 2);
        $cod = round($cod, 2);
        $promo = round($bogoTotal + $firstDiscount, 2);
        $discountTotal = round($promo + $couponDiscount, 2);
        $payable = max(0, $subtotal - $discountTotal + $shipping + $vatAdded);

        return [
            'subtotal'             => $subtotal,
            'bogo_discount'        => round($bogoTotal, 2),
            'coupon'               => $couponResult,
            'coupon_discount'      => $couponDiscount,
            'coupon_error'         => $couponResult && !$couponResult['ok'] ? $couponResult['error'] : null,
            'first_order'          => $firstOrder,
            'first_order_discount' => $firstDiscount,
            'promo_discount'       => $promo,
            'discount_total'       => $discountTotal,
            'free_shipping'        => $freeShipping,
            'free_offer'           => $freeOffer && array_filter($groups, static fn($g) => $g['free_offer']) ? $freeOffer['offer'] : null,
            'shipping'             => $shipping,
            'quote'                => $quote,
            'groups'               => $groups,
            'cod_charge'           => $cod,
            'grand_total'          => round($payable + $cod, 2),
            'line_discounts'       => $lineDiscounts,
            'line_parts'           => $parts,
            'vat_total'            => $vatTotal,
            'vat_added'            => $vatAdded,
            'vat_inclusive'        => $inclusive,
            'vat_lines'            => $vatLines,
        ];
    }

    /** Split a discount over the eligible lines by value (last line takes the rounding remainder). */
    private static function allocate(float $discount, array $lines, array $lineIds): array
    {
        $eligible = array_values(array_filter($lines, static fn($l) => in_array((int) $l['id'], $lineIds, true)));
        $base = array_sum(array_column($eligible, 'line_total'));
        $out = [];
        $left = $discount;
        foreach ($eligible as $i => $l) {
            $share = $i === count($eligible) - 1 ? $left : round($discount * (float) $l['line_total'] / max(0.01, $base), 2);
            $share = min($share, (float) $l['line_total'], $left);
            $out[(int) $l['id']] = $share;
            $left = round($left - $share, 2);
        }
        return $out;
    }

    /**
     * Discount parts of one cart line and who bears them.
     * @return array{offer: float, vendor: float, platform: float, parts: array}
     *   offer = flash / price-offer saving inside the unit price; parts = [{type, id, amount, funded_by, vendor, platform}]
     */
    private static function fundLine(array $l, array $t, ?array $coupon): array
    {
        $qty = (int) $l['quantity'];
        $vendorItem = $l['vendor_id'] !== null;
        $lp = $t['line_parts'][(int) $l['id']] ?? [];
        $offer = $l['promo'] ? round(max(0, (float) $l['base_price'] - (float) $l['unit_price']) * $qty, 2) : 0.0;
        $raw = [];
        if ($offer > 0) {
            $raw[] = [$l['promo']['type'], $l['promo']['type'] === 'flash' ? ($l['promo']['sale_id'] ?? $l['promo']['id']) : $l['promo']['id'],
                      $offer, $l['promo']['funded_by'], $l['promo']['share'],
                      $l['promo']['type'] === 'offer' ? ['ptype' => $l['promo']['ptype'] ?? 'percent', 'value' => $l['promo']['value'] ?? 0] : []];
        }
        if (($lp['bogo'] ?? 0) > 0 && $l['bogo']) {
            $raw[] = ['bogo', (int) $l['bogo']['id'], $lp['bogo'], $l['bogo']['funded_by'], $l['bogo']['vendor_share_percent'], ['free_qty' => (int) $l['bogo_free']]];
        }
        if (($lp['first'] ?? 0) > 0 && $t['first_order']) {
            $o = $t['first_order']['offer'];
            $raw[] = ['first_order', (int) $o['id'], $lp['first'], $o['funded_by'], $o['vendor_share_percent'], []];
        }
        if (($lp['coupon'] ?? 0) > 0 && $coupon) {
            $raw[] = ['coupon', (int) $coupon['id'], $lp['coupon'], $coupon['funded_by'], $coupon['vendor_share_percent'] ?? 50, ['code' => $coupon['code']]];
        }
        $parts = [];
        $vendor = $platform = 0.0;
        foreach ($raw as [$type, $id, $amount, $fundedBy, $share, $extra]) {
            [$v, $p] = Promo::split((float) $amount, $fundedBy, $share, $vendorItem);
            $vendor += $v;
            $platform += $p;
            $parts[] = ['type' => $type, 'id' => (int) $id, 'amount' => round((float) $amount, 2),
                        'funded_by' => $vendorItem ? $fundedBy : 'platform', 'vendor' => $v, 'platform' => $p] + $extra;
        }
        return ['offer' => $offer, 'vendor' => round($vendor, 2), 'platform' => round($platform, 2), 'parts' => $parts];
    }

    /* ======================================================================
     * Place order
     * ==================================================================== */

    /**
     * Create the order from the customer's cart.
     * @param array $ship    shipping_name, shipping_phone, shipping_address, division_id, district_id, upazila_id, postcode
     * @param array $payment method, trx_id?, payer_account?
     * @return array the new order row
     * @throws OrderException|InventoryException with a translated message (nothing is saved)
     */
    public static function place(array $user, array $ship, array $payment, ?string $couponCode, ?int $prescriptionId, ?string $note, string $option = 'standard'): array
    {
        $db = self::db();
        // guest checkout: $user = ['id' => null, 'name', 'phone', 'email', 'verified' => bool]; the cart is the browser's session cart
        $userId = isset($user['id']) && $user['id'] !== null ? (int) $user['id'] : null;
        $guest = $userId === null ? ['phone' => (string) ($user['phone'] ?? ''), 'email' => (string) ($user['email'] ?? '')] : null;
        $method = $payment['method'];
        if (!in_array($method, self::enabledMethods(), true)) {
            throw new OrderException(__('pay_method_unavailable'), 'payment_method');
        }

        $stockBefore = []; // product id => stock before this order (low-stock alerts)
        [$cartWhere, $cartParams] = $userId !== null ? ['user_id = :own AND session_id IS NULL', ['own' => $userId]] : Cart::ownerCondition();
        $orderId = $db->transaction(static function (Database $db) use ($user, $userId, $guest, $cartWhere, $cartParams, $ship, $payment, $method, $couponCode, $prescriptionId, $note, $option, &$stockBefore): int {
            // 1. lock the cart, then the coupon, then the products (ascending id)
            $cartRows = $db->fetchAll("SELECT id, product_id FROM carts WHERE {$cartWhere} ORDER BY product_id, id FOR UPDATE", $cartParams);
            if (!$cartRows) {
                throw new OrderException(__('cart_empty'));
            }
            $coupon = null;
            if ($couponCode !== null && $couponCode !== '') {
                $coupon = Coupon::findByCode($couponCode, true);
                if (!$coupon) {
                    throw new OrderException(__('coupon_invalid'), 'coupon');
                }
            }
            $productIds = array_values(array_unique(array_map('intval', array_column($cartRows, 'product_id'))));
            sort($productIds);
            foreach ($productIds as $pid) {
                $db->fetch('SELECT id FROM products WHERE id = :id FOR UPDATE', ['id' => $pid]);
            }

            // 2. fresh cart with prices (rows are locked now)
            $cart = $userId !== null ? Cart::summary($userId) : Cart::summary(null, true);
            if ($cart['has_problems']) {
                throw new OrderException(__('cart_has_problems'));
            }
            if ($cart['has_rx'] && !$prescriptionId) {
                throw new OrderException(__('rx_upload_required'), 'prescription');
            }

            foreach ($cart['lines'] as $sl) {
                if ((int) $sl['track_stock']) {
                    $stockBefore[(int) $sl['product_id']] = (int) $sl['stock_qty'];
                }
            }

            // 3. flash sale quantities: lock the flash rows; if not enough left, charge the regular price
            $flashUsed = [];
            foreach ($cart['lines'] as &$l) {
                if ($l['flash']) {
                    $fi = $db->fetch('SELECT * FROM flash_sale_items WHERE id = :id FOR UPDATE', ['id' => $l['flash']['id']]);
                    $left = $fi && $fi['quantity_limit'] !== null ? (int) $fi['quantity_limit'] - (int) $fi['sold_qty'] : PHP_INT_MAX;
                    if (!$fi || $left < (int) $l['quantity']) {
                        // re-price without the flash sale (a price offer or buy-X-get-Y may still apply)
                        $pricing = Storefront::priceFor($l['price_row'], [], $l['variant_id'] !== null ? (int) $l['variant_id'] : null, $l);
                        $l = array_merge($l, Cart::pricingFields($pricing, (int) $l['quantity'], $l['price_row']));
                    } else {
                        $flashUsed[(int) $fi['id']] = ($flashUsed[(int) $fi['id']] ?? 0) + (int) $l['quantity'];
                    }
                }
            }
            unset($l);
            $cart['subtotal'] = round(array_sum(array_column($cart['lines'], 'line_total')), 2);

            // 4. totals
            $t = self::totals($cart, $option === 'pickup' ? null : (int) $ship['district_id'], $method, $coupon, $userId, $option, $guest);
            if ($t['coupon_error'] !== null) {
                throw new OrderException($t['coupon_error'], 'coupon');
            }
            if (!$t['quote']['ok']) {
                throw new OrderException((string) $t['quote']['error'], 'delivery_option');
            }
            $firstGroup = reset($t['groups']) ?: [];
            $min = (float) setting('min_order_amount', 0);
            if ($t['subtotal'] < $min) {
                throw new OrderException(__('min_order', ['amount' => money($min)]));
            }
            $maxCod = (float) setting('max_cod_amount', 0);
            if ($method === 'cod' && $maxCod > 0 && $t['grand_total'] > $maxCod) {
                throw new OrderException(__('cod_max', ['amount' => money($maxCod)]), 'payment_method');
            }
            if (in_array($method, self::MANUAL_METHODS, true)) {
                $dup = $db->fetchColumn('SELECT 1 FROM payments WHERE gateway = :g AND transaction_id = :t', ['g' => $method . '_manual', 't' => $payment['trx_id']]);
                if ($dup) {
                    throw new OrderException(__('trx_used'), 'trx_id');
                }
            }

            // 5. order row
            $status = $cart['has_rx'] ? 'prescription_review' : 'pending';
            $orderNo = self::newOrderNo();
            $orderId = $db->insert('orders', [
                'order_no'              => $orderNo,
                'user_id'               => $userId,
                'is_guest'              => $userId === null ? 1 : 0,
                // guest orders: which contact was confirmed by OTP (1 = mobile, 2 = email, 0 = none) - decides account linking later
                'contact_verified'      => $userId === null ? (['phone' => 1, 'email' => 2][$user['verified'] ?? ''] ?? 0) : 0,
                'customer_name'         => $user['name'],
                'customer_phone'        => (string) $user['phone'],
                'customer_email'        => ($user['email'] ?? '') !== '' ? strtolower((string) $user['email']) : null,
                'shipping_name'         => $ship['shipping_name'],
                'shipping_phone'        => $ship['shipping_phone'],
                'shipping_address'      => $ship['shipping_address'],
                'division_id'           => (int) $ship['division_id'],
                'district_id'           => (int) $ship['district_id'],
                'upazila_id'            => $ship['upazila_id'] ?: null,
                'postcode'              => $ship['postcode'] ?: null,
                'shipping_zone_id'      => $firstGroup['zone_id'] ?? null,
                'shipping_rate_id'      => $firstGroup['rate_id'] ?? null,
                'delivery_option'       => $option,
                'subtotal'              => $t['subtotal'],
                'discount_total'        => $t['discount_total'],
                'coupon_id'             => $coupon && $t['coupon']['ok'] ? (int) $coupon['id'] : null,
                'coupon_code'           => $coupon && $t['coupon']['ok'] ? $coupon['code'] : null,
                'coupon_discount'       => $t['coupon_discount'],
                'promo_discount'        => $t['promo_discount'],
                'shipping_total'        => $t['shipping'],
                'cod_charge'            => $t['cod_charge'],
                'vat_total'             => $t['vat_total'],
                'grand_total'           => $t['grand_total'],
                'payment_method'        => $method,
                'payment_status'        => 'unpaid',
                'status'                => $status,
                'requires_prescription' => $cart['has_rx'] ? 1 : 0,
                'prescription_id'       => $cart['has_rx'] ? $prescriptionId : null,
                'customer_note'         => $note,
                'source'                => 'web',
                'ip_address'            => Helpers::clientIp(),
                'user_agent'            => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            // 6. vendor splits (one per seller; NULL = sold by SasthoBazar)
            $byVendor = [];
            foreach ($cart['lines'] as $l) {
                $byVendor[$l['vendor_id'] === null ? 0 : (int) $l['vendor_id']][] = $l;
            }
            $splitIds = [];
            $n = 0;
            foreach (array_keys($byVendor) as $vid) {
                $splitIds[$vid] = $db->insert('order_vendor_splits', [
                    'order_id'     => $orderId,
                    'vendor_id'    => $vid ?: null,
                    'sub_order_no' => $orderNo . '-' . ++$n,
                    'status'       => $status,
                ]);
            }

            // 7. items + stock (FEFO) in ascending product order
            $commissionTotal = 0.0;
            $splitTotals = [];
            foreach ($cart['lines'] as $l) {
                $qty = (int) $l['quantity'];
                $variantId = $l['variant_id'] !== null ? (int) $l['variant_id'] : null;
                $allocations = [];
                $unitCost = $variantId ? null : ($l['cost_price'] !== null ? (float) $l['cost_price'] : null);
                if ((int) $l['track_stock']) {
                    $taken = Inventory::remove([
                        'product_id'     => (int) $l['product_id'],
                        'variant_id'     => $variantId,
                        'quantity'       => $qty,
                        'type'           => 'sale',
                        'reference_type' => 'order',
                        'reference_id'   => $orderId,
                        'note'           => $orderNo,
                    ]);
                    $costSum = 0.0;
                    $costQty = 0;
                    foreach ($taken as $tk) {
                        $b = $db->fetch('SELECT batch_no, expiry_date, cost_price FROM inventory_batches WHERE id = :id', ['id' => $tk['batch_id']]);
                        $allocations[] = [
                            'batch_id' => $tk['batch_id'],
                            'batch_no' => $b['batch_no'],
                            'expiry'   => $b['expiry_date'],
                            'cost'     => $b['cost_price'] !== null ? (float) $b['cost_price'] : null,
                            'qty'      => (int) $tk['quantity'],
                            'returned' => 0,
                        ];
                        if ($b['cost_price'] !== null) {
                            $costSum += (float) $b['cost_price'] * (int) $tk['quantity'];
                            $costQty += (int) $tk['quantity'];
                        }
                    }
                    if ($costQty > 0) {
                        $unitCost = round($costSum / $costQty, 2);
                    }
                }
                $discount = (float) ($t['line_discounts'][(int) $l['id']] ?? 0);
                $net = round((float) $l['line_total'] - $discount, 2);
                $vid = $l['vendor_id'] === null ? 0 : (int) $l['vendor_id'];
                // who bears each discount on this line (platform / vendor / shared)
                $promo = self::fundLine($l, $t, $coupon);
                $vat = $t['vat_lines'][(int) $l['id']] ?? null;
                $vatAdd = $t['vat_inclusive'] ? 0.0 : (float) ($vat['amount'] ?? 0);
                // marketplace commission: product > category > vendor > global (estimate; booked on delivery)
                // charged on the regular price minus the vendor-borne discounts (platform-funded discounts do not cut the vendor's earning)
                [$rate, $scope] = Commission::rateFor($l);
                $base = round($net + $promo['platform'], 2);
                $commission = round($base * $rate / 100, 2);
                $commissionTotal += $commission;
                $db->insert('order_items', [
                    'order_id'              => $orderId,
                    'split_id'              => $splitIds[$vid],
                    'product_id'            => (int) $l['product_id'],
                    'variant_id'            => $variantId,
                    'vendor_id'             => $vid ?: null,
                    'product_name'          => mb_substr(trim($l['name_en'] . ($l['strength'] && !str_contains($l['name_en'], (string) $l['strength']) ? ' ' . $l['strength'] : '')), 0, 255),
                    'variant_name'          => $variantId ? $l['v_en'] : null,
                    'sku'                   => $variantId ? ($l['v_sku'] ?: $l['sku']) : $l['sku'],
                    'quantity'              => $qty,
                    'mrp'                   => $l['unit_mrp'],
                    'unit_price'            => $l['unit_price'],
                    'discount_amount'       => $discount,
                    'offer_discount'        => $promo['offer'],
                    'vendor_funded'         => $promo['vendor'],
                    'platform_funded'       => $promo['platform'],
                    'promo_info'            => $promo['parts'] ? json_encode($promo['parts'], JSON_UNESCAPED_UNICODE) : null,
                    'vat_rate'              => $vat['rate'] ?? 0,
                    'vat_amount'            => $vat['amount'] ?? 0,
                    'vat_in_price'          => $t['vat_inclusive'] ? 1 : 0,
                    'line_total'            => $l['line_total'],
                    'unit_cost'             => $unitCost,
                    'commission_rate'       => $rate,
                    'commission_type'       => 'percent',
                    'commission_scope'      => $scope,
                    'commission_amount'     => $commission,
                    'requires_prescription' => (int) $l['requires_prescription'],
                    'batch_allocations'     => $allocations ? json_encode($allocations) : null,
                ]);
                $splitTotals[$vid]['subtotal'] = ($splitTotals[$vid]['subtotal'] ?? 0) + (float) $l['line_total'];
                $splitTotals[$vid]['discount'] = ($splitTotals[$vid]['discount'] ?? 0) + $discount;
                $splitTotals[$vid]['commission'] = ($splitTotals[$vid]['commission'] ?? 0) + $commission;
                $splitTotals[$vid]['base'] = ($splitTotals[$vid]['base'] ?? 0) + $base;
                $splitTotals[$vid]['vat'] = ($splitTotals[$vid]['vat'] ?? 0) + (float) ($vat['amount'] ?? 0);
                $splitTotals[$vid]['vat_add'] = ($splitTotals[$vid]['vat_add'] ?? 0) + $vatAdd;
                $db->query('UPDATE products SET sold_count = sold_count + :q, updated_at = updated_at WHERE id = :id', ['q' => $qty, 'id' => (int) $l['product_id']]);
            }

            // split totals: each seller's own delivery fee + COD charge; one shipment per split
            foreach ($splitTotals as $vid => $st) {
                $g = $t['groups'][$vid] ?? ['fee' => 0.0, 'cod' => 0.0];
                $extra = round((float) $g['fee'] + (float) $g['cod'], 2);
                $grand = round($st['subtotal'] - $st['discount'] + $extra + $st['vat_add'], 2);
                $db->update('order_vendor_splits', [
                    'subtotal'          => round($st['subtotal'], 2),
                    'discount_total'    => round($st['discount'], 2),
                    'vat_total'         => round($st['vat'], 2),
                    'shipping_total'    => $extra,
                    'grand_total'       => $grand,
                    'commission_amount' => round($st['commission'], 2),
                    'vendor_earning'    => $vid ? round($st['base'] - $st['commission'] + $st['vat_add'], 2) : 0,
                ], 'id = :id', ['id' => $splitIds[$vid]]);
                Shipment::createForSplit($orderId, $splitIds[$vid], $g + ['weight' => 0], $method === 'cod' ? $grand : 0.0, $option, $userId);
            }
            $db->update('orders', ['commission_total' => round($commissionTotal, 2)], 'id = :id', ['id' => $orderId]);

            // 8. flash sale counters, coupon usage
            foreach ($flashUsed as $fid => $q) {
                $db->query('UPDATE flash_sale_items SET sold_qty = sold_qty + :q WHERE id = :id', ['q' => $q, 'id' => $fid]);
            }
            if ($coupon && $t['coupon']['ok']) {
                $db->insert('coupon_usages', ['coupon_id' => (int) $coupon['id'], 'user_id' => $userId, 'order_id' => $orderId, 'discount' => $t['coupon_discount']]);
                $db->query('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id', ['id' => (int) $coupon['id']]);
            }

            // 9. payment record
            $manual = in_array($method, self::MANUAL_METHODS, true);
            $db->insert('payments', [
                'order_id'       => $orderId,
                'user_id'        => $userId,
                'method'         => $method,
                'gateway'        => $manual ? $method . '_manual' : null,
                'transaction_id' => $manual ? $payment['trx_id'] : null,
                'amount'         => $t['grand_total'],
                'status'         => 'pending',
                'payer_account'  => $manual ? $payment['payer_account'] : null,
            ]);

            // 10. history, cart
            self::history($orderId, null, $status, __($status === 'prescription_review' ? 'hist_placed_rx' : 'hist_placed'), true, $userId);
            $db->query("DELETE FROM carts WHERE {$cartWhere}", $cartParams);
            Audit::log('orders.create', 'orders', $orderId, null, ['order_no' => $orderNo, 'grand_total' => $t['grand_total'], 'method' => $method]);
            return $orderId;
        });

        $order = self::find($orderId);
        self::notifyCustomer($order, __('notif_order_placed', ['no' => $order['order_no']]), null, 'order_placed');
        self::notifyStaff($order);
        self::lowStockAlerts($stockBefore);
        return $order;
    }

    private static function newOrderNo(): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) setting('order_prefix', 'SB'))) ?: 'SB';
        for ($i = 0; $i < 10; $i++) {
            $no = $prefix . date('ymd') . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            if (!self::db()->fetchColumn('SELECT 1 FROM orders WHERE order_no = :n', ['n' => $no])) {
                return $no;
            }
        }
        throw new RuntimeException('Could not generate an order number');
    }

    /* ======================================================================
     * Status changes
     * ==================================================================== */

    public static function find(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM orders WHERE id = :id', ['id' => $id]);
    }

    public static function findByNo(string $no): ?array
    {
        return self::db()->fetch('SELECT * FROM orders WHERE order_no = :n', ['n' => $no]);
    }

    private static function lock(int $id): array
    {
        $o = self::db()->fetch('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $id]);
        if (!$o) {
            throw new OrderException(__('order_not_found'));
        }
        return $o;
    }

    public static function history(int $orderId, ?string $from, string $to, ?string $note, bool $notify, ?int $by): void
    {
        self::db()->insert('order_status_history', [
            'order_id'        => $orderId,
            'from_status'     => $from,
            'to_status'       => $to,
            'note'            => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            'notify_customer' => $notify ? 1 : 0,
            'changed_by'      => $by,
        ]);
    }

    /** Progress order of the open statuses (the parent order shows its least advanced sub-order). */
    private const RANK = ['pending' => 0, 'prescription_review' => 1, 'confirmed' => 2, 'processing' => 3, 'packed' => 4, 'shipped' => 5, 'out_for_delivery' => 6];
    /** What a vendor may do with its own sub-order. */
    public const VENDOR_NEXT = [
        'confirmed'  => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'packed'     => ['shipped', 'cancelled'],
    ];

    /**
     * Whole-order status change (staff). Every sub-order that can make this step is moved; the order status
     * then follows its sub-orders. Before confirmation the order moves as one unit.
     * @throws OrderException
     */
    public static function changeStatus(int $orderId, string $to, ?string $note, bool $notify, ?int $by, bool $force = false): array
    {
        $order = self::db()->transaction(static function (Database $db) use ($orderId, $to, $note, $notify, $by, $force): array {
            $o = self::lock($orderId);
            $from = $o['status'];
            if ($from === $to) {
                throw new OrderException(__('status_same'));
            }
            if (!$force && !in_array($to, self::NEXT[$from] ?? [], true)) {
                throw new OrderException(__('status_not_allowed', ['from' => __('os_' . $from), 'to' => __('os_' . $to)]));
            }
            $splits = $db->fetchAll('SELECT * FROM order_vendor_splits WHERE order_id = :o ORDER BY id FOR UPDATE', ['o' => $orderId]);
            $moved = 0;
            foreach ($splits as $s) {
                if ($s['status'] === $to || !($force || in_array($to, self::NEXT[$s['status']] ?? [], true))) {
                    continue;
                }
                self::applySplit($o, $s, $to, $note, $by, count($splits));
                $moved++;
            }
            if ($moved === 0) {
                throw new OrderException(__('status_not_allowed', ['from' => __('os_' . $from), 'to' => __('os_' . $to)]));
            }
            self::syncParent($o, $note, $notify, $by);
            Audit::log('orders.status', 'orders', $orderId, ['status' => $from], ['status' => $to]);
            return self::find($orderId);
        });
        if ($notify) {
            self::notifyCustomer($order, __('notif_status', ['no' => $order['order_no'], 'status' => __('os_' . $order['status'])]), $note, Notifier::orderEvent($order['status']));
        }
        return $order;
    }

    /**
     * Status change of ONE sub-order (staff, or the vendor when $vendorId is given).
     * Sub-orders move on their own only after the order is confirmed.
     * @throws OrderException
     */
    public static function changeSplitStatus(int $splitId, string $to, ?string $note, bool $notify, ?int $by, ?int $vendorId = null, bool $fromShipment = false): array
    {
        $db = self::db();
        $result = $db->transaction(static function (Database $db) use ($splitId, $to, $note, $by, $vendorId, $notify, $fromShipment): array {
            $orderId = (int) $db->fetchColumn('SELECT order_id FROM order_vendor_splits WHERE id = :id', ['id' => $splitId]);
            if (!$orderId) {
                throw new OrderException(__('order_not_found'));
            }
            $o = self::lock($orderId);
            $s = $db->fetch('SELECT * FROM order_vendor_splits WHERE id = :id FOR UPDATE', ['id' => $splitId]);
            if ($vendorId !== null && (int) $s['vendor_id'] !== $vendorId) {
                throw new OrderException(__('order_not_found'));
            }
            if ($s['status'] === $to) {
                throw new OrderException(__('status_same'));
            }
            if (in_array($s['status'], ['pending', 'prescription_review'], true)) {
                throw new OrderException(__('split_wait_confirm'));
            }
            $map = $vendorId !== null ? self::VENDOR_NEXT : self::NEXT;
            $allowed = $map[$s['status']] ?? [];
            if ($vendorId !== null && !setting('vendor_can_cancel', true)) {
                $allowed = array_values(array_diff($allowed, ['cancelled']));
            }
            if (!in_array($to, $allowed, true)) {
                throw new OrderException(__('status_not_allowed', ['from' => __('os_' . $s['status']), 'to' => __('os_' . $to)]));
            }
            if ($to === 'cancelled' && $vendorId !== null && ($note === null || trim($note) === '')) {
                throw new OrderException(__('cancel_reason_required'));
            }
            $count = (int) $db->fetchColumn('SELECT COUNT(*) FROM order_vendor_splits WHERE order_id = :o', ['o' => $orderId]);
            self::applySplit($o, $s, $to, $note, $by, max(2, $count), $fromShipment); // always keep a sub-order history line here
            self::syncParent($o, $note, false, $by);
            Audit::log('orders.split_status', 'order_vendor_splits', $splitId, ['status' => $s['status']], ['status' => $to, 'by_vendor' => $vendorId !== null]);
            return [self::find($orderId), $s['sub_order_no']];
        });
        [$order, $subNo] = $result;
        if ($notify) {
            self::notifyCustomer($order, __('notif_split_status', ['no' => $subNo, 'status' => __('os_' . $to)]), $note, Notifier::orderEvent($to));
        }
        return $order;
    }

    /** Apply a status to one sub-order (stock, wallet, refunds). Order + split rows are locked by the caller. */
    private static function applySplit(array $o, array $s, string $to, ?string $note, ?int $by, int $splitCount, bool $fromShipment = false): void
    {
        $db = self::db();
        $now = date('Y-m-d H:i:s');
        $set = ['status' => $to];
        $sid = (int) $s['id'];
        if ($to === 'cancelled') {
            self::restockItems($o, $db->fetchAll(
                "SELECT * FROM order_items WHERE split_id = :s AND status = 'active' AND product_id IS NOT NULL ORDER BY product_id, id FOR UPDATE", ['s' => $sid]
            ), 'cancel');
            foreach ($db->fetchAll("SELECT product_id, quantity FROM order_items WHERE split_id = :s AND status = 'active' AND product_id IS NOT NULL", ['s' => $sid]) as $it) {
                $db->query('UPDATE products SET sold_count = GREATEST(CAST(sold_count AS SIGNED) - :q, 0), updated_at = updated_at WHERE id = :id', ['q' => (int) $it['quantity'], 'id' => (int) $it['product_id']]);
            }
            $db->query("UPDATE order_items SET status = 'cancelled' WHERE split_id = :s AND status = 'active'", ['s' => $sid]);
            $set['cancelled_at'] = $now;
            $set['commission_amount'] = 0;
            $set['vendor_earning'] = 0;
            $db->query("UPDATE order_items SET commission_amount = 0 WHERE split_id = :s", ['s' => $sid]);
            self::queueRefund($o, (float) $s['grand_total'], $note, $by, $sid);
        } elseif ($to === 'returned') {
            $before = $db->fetchAll("SELECT * FROM order_items WHERE split_id = :s AND status = 'active' ORDER BY product_id, id FOR UPDATE", ['s' => $sid]);
            self::restockItems($o, array_values(array_filter($before, static fn($it) => $it['product_id'] !== null)), 'return');
            $qtys = [];
            foreach ($before as $it) {
                $qtys[(int) $it['id']] = (int) $it['quantity'] - (int) $it['returned_qty'];
            }
            Wallet::reverseReturn($s, $before, $qtys, $by);
            $db->query("UPDATE order_items SET status = 'returned', returned_qty = quantity WHERE split_id = :s AND status = 'active'", ['s' => $sid]);
        } elseif ($to === 'delivered') {
            $set['delivered_at'] = $now;
            Wallet::settleSplit($s, $o['order_no'], $by);
        } elseif ($to === 'shipped' && $note !== null && trim($note) !== '') {
            $set['tracking_note'] = mb_substr(trim($note), 0, 255);
        }
        $db->update('order_vendor_splits', $set, 'id = :id', ['id' => $sid]);
        if ($to === 'cancelled') {
            Wallet::recalcOrderCommission((int) $o['id']);
        }
        if (!$fromShipment) {
            Shipment::mirrorSplit($sid, $to, $by); // keep the shipment in step with a hand-made change
        }
        if ($splitCount > 1) {
            $db->insert('order_status_history', [
                'order_id' => (int) $o['id'], 'split_id' => $sid, 'from_status' => $s['status'], 'to_status' => $to,
                'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null, 'notify_customer' => 0, 'changed_by' => $by,
            ]);
        }
    }

    /**
     * Derive the order status from its sub-orders and apply order-level effects
     * (coupon release / payment cancel on full cancellation, COD collection on delivery).
     */
    private static function syncParent(array $o, ?string $note, bool $notify, ?int $by): string
    {
        $db = self::db();
        $orderId = (int) $o['id'];
        $splits = $db->fetchAll('SELECT status, grand_total FROM order_vendor_splits WHERE order_id = :o', ['o' => $orderId]);
        $statuses = array_column($splits, 'status');
        $open = array_values(array_filter($statuses, static fn($st) => isset(self::RANK[$st])));
        if ($open) {
            usort($open, static fn($a, $b) => self::RANK[$a] <=> self::RANK[$b]);
            $new = $open[0];
        } elseif (in_array('delivered', $statuses, true)) {
            $new = 'delivered';
        } elseif (in_array('returned', $statuses, true)) {
            $new = 'returned';
        } else {
            $new = 'cancelled';
        }
        $from = $o['status'];
        if ($new === $from) {
            return $new;
        }
        $now = date('Y-m-d H:i:s');
        $set = ['status' => $new];
        if ($new === 'confirmed' && !$o['confirmed_at']) {
            $set['confirmed_at'] = $now;
        }
        if ($new === 'cancelled') {
            $db->query("UPDATE payments SET status = 'cancelled' WHERE order_id = :o AND status IN ('initiated','pending')", ['o' => $orderId]);
            if ($o['coupon_id']) {
                $db->delete('coupon_usages', 'coupon_id = :c AND order_id = :o', ['c' => (int) $o['coupon_id'], 'o' => $orderId]);
                $db->query('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = :id', ['id' => (int) $o['coupon_id']]);
            }
            $set['cancelled_at'] = $now;
            $set['cancel_reason'] = $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null;
        } elseif ($new === 'delivered') {
            $set['delivered_at'] = $now;
            if ($o['payment_method'] === 'cod') {
                // the courier collected the delivered sub-orders' amount
                $collected = 0.0;
                foreach ($splits as $sp) {
                    if ($sp['status'] === 'delivered') {
                        $collected += (float) $sp['grand_total'];
                    }
                }
                $collected = round($collected, 2);
                $db->query("UPDATE payments SET status = 'success', amount = :a, paid_at = :t, received_by = :b WHERE order_id = :o AND method = 'cod' AND status = 'pending'",
                    ['a' => (string) $collected, 't' => $now, 'b' => $by, 'o' => $orderId]);
                $set['paid_amount'] = $collected;
                $set['payment_status'] = 'paid';
            }
        } elseif ($new === 'returned') {
            $db->query("UPDATE payments SET status = 'cancelled' WHERE order_id = :o AND status IN ('initiated','pending')", ['o' => $orderId]);
        }
        $db->update('orders', $set, 'id = :id', ['id' => $orderId]);
        self::history($orderId, $from, $new, $note, $notify, $by);
        return $new;
    }

    /** Money already received for a cancelled part must go back: queue an approved refund (never more than was paid). */
    private static function queueRefund(array $o, float $amount, ?string $note, ?int $by, ?int $splitId): void
    {
        $db = self::db();
        $paid = (float) $db->fetchColumn('SELECT paid_amount FROM orders WHERE id = :id', ['id' => (int) $o['id']]);
        $queued = (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE order_id = :o AND status IN ('requested','approved','processed')", ['o' => (int) $o['id']]);
        $owed = round(min($amount, $paid - $queued), 2);
        if ($owed <= 0) {
            return;
        }
        $db->insert('refunds', [
            'refund_no'    => self::newRefundNo(),
            'order_id'     => (int) $o['id'],
            'split_id'     => $splitId,
            'amount'       => $owed,
            'reason'       => mb_substr(__('refund_cancelled_order') . ($note ? ': ' . $note : ''), 0, 500),
            'method'       => 'original',
            'restock'      => 0,
            'status'       => 'approved',
            'requested_by' => $by,
        ]);
    }

    /**
     * Put stock back into the batches it came from (items in ascending product id order).
     * Recalled batches / deleted products are skipped and noted in the history.
     */
    private static function restockItems(array $o, array $items, string $type): void
    {
        $db = self::db();
        $skipped = [];
        foreach ($items as $it) {
            $allocs = json_decode((string) $it['batch_allocations'], true);
            if (!is_array($allocs) || !$allocs) {
                continue; // stock not tracked for this line
            }
            foreach ($allocs as &$a) {
                $qty = (int) $a['qty'] - (int) ($a['returned'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                try {
                    Inventory::receive([
                        'product_id'     => (int) $it['product_id'],
                        'variant_id'     => $it['variant_id'] !== null ? (int) $it['variant_id'] : null,
                        'batch_no'       => $a['batch_no'],
                        'expiry_date'    => $a['expiry'] ?? null,
                        'unit_cost'      => $a['cost'] ?? null,
                        'quantity'       => $qty,
                        'type'           => $type,
                        'reference_type' => 'order',
                        'reference_id'   => (int) $o['id'],
                        'note'           => $o['order_no'],
                    ]);
                    $a['returned'] = (int) $a['qty'];
                } catch (InventoryException $e) {
                    $skipped[] = $it['product_name'] . ' (' . $a['batch_no'] . '): ' . $e->getMessage();
                }
            }
            unset($a);
            $db->update('order_items', ['batch_allocations' => json_encode($allocs)], 'id = :id', ['id' => (int) $it['id']]);
        }
        if ($skipped) {
            self::history((int) $o['id'], $o['status'], $o['status'], __('restock_skipped') . ' ' . implode('; ', $skipped), false, Auth::id());
        }
    }

    /** Internal staff note (also shown to the customer when $notify). */
    public static function addNote(int $orderId, string $note, bool $notify, ?int $by): void
    {
        $o = self::find($orderId);
        if (!$o) {
            throw new OrderException(__('order_not_found'));
        }
        self::history($orderId, $o['status'], $o['status'], $note, $notify, $by);
        if ($notify) {
            self::notifyCustomer($o, __('notif_note', ['no' => $o['order_no']]), $note);
        }
    }

    /* ======================================================================
     * Prescription review
     * ==================================================================== */

    /** Approve => order confirmed. Reject => order cancelled (stock restored). */
    public static function reviewPrescription(int $orderId, bool $approve, ?string $note, int $by): array
    {
        $db = self::db();
        $o = $db->transaction(static function (Database $db) use ($orderId, $approve, $note, $by): array {
            $o = self::lock($orderId);
            if ($o['status'] !== 'prescription_review' || !$o['prescription_id']) {
                throw new OrderException(__('rx_not_waiting'));
            }
            $db->update('prescriptions', [
                'status'      => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $by,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            ], 'id = :id', ['id' => (int) $o['prescription_id']]);
            Audit::log($approve ? 'prescriptions.approve' : 'prescriptions.reject', 'prescriptions', (int) $o['prescription_id'], null, ['order_id' => $orderId]);
            if ($approve) {
                $db->update('orders', ['status' => 'confirmed', 'confirmed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $orderId]);
                $db->update('order_vendor_splits', ['status' => 'confirmed'], 'order_id = :id', ['id' => $orderId]);
                self::history($orderId, 'prescription_review', 'confirmed', trim(__('hist_rx_approved') . ' ' . ($note ?? '')), true, $by);
                return self::find($orderId);
            }
            return self::changeStatus($orderId, 'cancelled', trim(__('hist_rx_rejected') . ' ' . ($note ?? '')), false, $by, true);
        });
        self::notifyCustomer($o, __($approve ? 'notif_rx_ok' : 'notif_rx_bad', ['no' => $o['order_no']]), $note, 'prescription');
        return $o;
    }

    /* ======================================================================
     * Payments
     * ==================================================================== */

    /** Staff checked a manual bKash/Nagad/Rocket payment (or a COD collection). */
    public static function verifyPayment(int $orderId, int $paymentId, bool $ok, ?string $note, int $by): void
    {
        self::db()->transaction(static function (Database $db) use ($orderId, $paymentId, $ok, $note, $by): void {
            $o = self::lock($orderId);
            $p = $db->fetch('SELECT * FROM payments WHERE id = :id AND order_id = :o FOR UPDATE', ['id' => $paymentId, 'o' => $orderId]);
            if (!$p || $p['status'] !== 'pending') {
                throw new OrderException(__('payment_not_pending'));
            }
            if ($ok) {
                $db->update('payments', ['status' => 'success', 'paid_at' => date('Y-m-d H:i:s'), 'received_by' => $by], 'id = :id', ['id' => $paymentId]);
                $paid = round((float) $o['paid_amount'] + (float) $p['amount'], 2);
                $db->update('orders', [
                    'paid_amount'    => $paid,
                    'payment_status' => $paid + 0.009 >= (float) $o['grand_total'] ? 'paid' : 'partial',
                ], 'id = :id', ['id' => $orderId]);
            } else {
                $db->update('payments', ['status' => 'failed'], 'id = :id', ['id' => $paymentId]);
                $db->update('orders', ['payment_status' => 'failed'], 'id = :id', ['id' => $orderId]);
            }
            self::history($orderId, $o['status'], $o['status'], trim(__($ok ? 'hist_pay_ok' : 'hist_pay_bad', ['trx' => (string) $p['transaction_id']]) . ' ' . ($note ?? '')), !$ok, $by);
            Audit::log($ok ? 'payments.verify' : 'payments.reject', 'payments', $paymentId, ['status' => 'pending'], ['status' => $ok ? 'success' : 'failed']);
        });
    }

    /* ======================================================================
     * Returns & refunds (refunds table, one row per returned item)
     * ==================================================================== */

    /** Can the customer still ask for a return? */
    public static function returnable(array $o): bool
    {
        if ($o['status'] !== 'delivered' || !$o['delivered_at']) {
            return false;
        }
        $days = (int) setting('return_window_days', 7);
        return strtotime($o['delivered_at']) + $days * 86400 >= time();
    }

    /**
     * Customer return request.
     * @param array $qtys order_item_id => quantity to return
     */
    public static function requestReturn(int $orderId, int $userId, array $qtys, string $reason, string $method, ?string $account): int
    {
        return self::db()->transaction(static function (Database $db) use ($orderId, $userId, $qtys, $reason, $method, $account): int {
            $o = self::lock($orderId);
            if ((int) $o['user_id'] !== $userId) {
                throw new OrderException(__('order_not_found'));
            }
            if (!self::returnable($o)) {
                throw new OrderException(__('return_window_over', ['n' => num((int) setting('return_window_days', 7))]));
            }
            if ($db->fetchColumn("SELECT 1 FROM refunds WHERE order_id = :o AND status = 'requested'", ['o' => $orderId])) {
                throw new OrderException(__('return_already_open'));
            }
            $items = array_column($db->fetchAll("SELECT * FROM order_items WHERE order_id = :o AND status = 'active'", ['o' => $orderId]), null, 'id');
            $created = 0;
            foreach ($qtys as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $it = $items[(int) $itemId] ?? null;
                if (!$it) {
                    throw new OrderException(__('return_bad_item'));
                }
                $pending = (int) $db->fetchColumn("SELECT COALESCE(SUM(quantity), 0) FROM refunds WHERE order_item_id = :i AND status IN ('requested','approved')", ['i' => (int) $itemId]);
                $max = (int) $it['quantity'] - (int) $it['returned_qty'] - $pending;
                if ($qty > $max) {
                    throw new OrderException(__('return_qty_max', ['name' => $it['product_name'], 'n' => num(max(0, $max))]));
                }
                $amount = self::refundAmount($it, $qty);
                $db->insert('refunds', [
                    'refund_no'       => self::newRefundNo(),
                    'order_id'        => $orderId,
                    'split_id'        => (int) $it['split_id'],
                    'order_item_id'   => (int) $itemId,
                    'quantity'        => $qty,
                    'amount'          => $amount,
                    'reason'          => mb_substr($reason, 0, 500),
                    'method'          => $method,
                    'account_details' => $account !== null && $account !== '' ? mb_substr($account, 0, 255) : null,
                    'restock'         => 1,
                    'status'          => 'requested',
                    'requested_by'    => $userId,
                ]);
                $created++;
            }
            if (!$created) {
                throw new OrderException(__('return_pick_items'));
            }
            self::history($orderId, $o['status'], $o['status'], __('hist_return_requested') . ' ' . mb_substr($reason, 0, 300), false, $userId);
            Audit::log('refunds.request', 'orders', $orderId, null, ['items' => $created]);
            return $created;
        });
    }

    /** What the customer paid for $qty of this line (discounts deducted, VAT added on top included). */
    private static function refundAmount(array $it, int $qty): float
    {
        $vatOnTop = (int) ($it['vat_in_price'] ?? 1) ? 0.0 : (float) ($it['vat_amount'] ?? 0);
        $perUnit = ((float) $it['line_total'] - (float) $it['discount_amount'] + $vatOnTop) / max(1, (int) $it['quantity']);
        return max(0.01, round($perUnit * $qty, 2));
    }

    private static function newRefundNo(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $no = 'RF' . date('ymd') . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            if (!self::db()->fetchColumn('SELECT 1 FROM refunds WHERE refund_no = :n', ['n' => $no])) {
                return $no;
            }
        }
        throw new RuntimeException('Could not generate a refund number');
    }

    /**
     * Staff decision on the open return request of an order.
     * Approve: items marked returned, stock put back (when $restock), order -> returned when everything came back.
     */
    public static function decideReturn(int $orderId, bool $approve, bool $restock, ?string $note, int $by): void
    {
        $o = self::db()->transaction(static function (Database $db) use ($orderId, $approve, $restock, $note, $by): array {
            $o = self::lock($orderId);
            $refunds = $db->fetchAll("SELECT * FROM refunds WHERE order_id = :o AND status = 'requested' FOR UPDATE", ['o' => $orderId]);
            if (!$refunds) {
                throw new OrderException(__('return_none_open'));
            }
            $now = date('Y-m-d H:i:s');
            if (!$approve) {
                $db->query("UPDATE refunds SET status = 'rejected', admin_note = :n, processed_by = :b, processed_at = :t WHERE order_id = :o AND status = 'requested'",
                    ['n' => $note, 'b' => $by, 't' => $now, 'o' => $orderId]);
                self::history($orderId, $o['status'], $o['status'], trim(__('hist_return_rejected') . ' ' . ($note ?? '')), true, $by);
                return $o;
            }
            usort($refunds, static fn($a, $b) => (int) $a['order_item_id'] <=> (int) $b['order_item_id']);
            $bySplit = [];
            foreach ($refunds as $rf) {
                $it = $db->fetch('SELECT * FROM order_items WHERE id = :id FOR UPDATE', ['id' => (int) $rf['order_item_id']]);
                $qty = min((int) $rf['quantity'], (int) $it['quantity'] - (int) $it['returned_qty']);
                if ($qty <= 0) {
                    continue;
                }
                $bySplit[(int) $it['split_id']]['before'][] = $it;
                $bySplit[(int) $it['split_id']]['qtys'][(int) $it['id']] = $qty;
                $allocs = json_decode((string) $it['batch_allocations'], true) ?: [];
                if ($restock && $it['product_id']) {
                    $left = $qty;
                    foreach (array_reverse(array_keys($allocs)) as $k) { // newest batch first
                        $a = &$allocs[$k];
                        $can = min($left, (int) $a['qty'] - (int) ($a['returned'] ?? 0));
                        if ($can <= 0) {
                            unset($a);
                            continue;
                        }
                        try {
                            Inventory::receive([
                                'product_id' => (int) $it['product_id'], 'variant_id' => $it['variant_id'] !== null ? (int) $it['variant_id'] : null,
                                'batch_no' => $a['batch_no'], 'expiry_date' => $a['expiry'] ?? null, 'unit_cost' => $a['cost'] ?? null,
                                'quantity' => $can, 'type' => 'return', 'reference_type' => 'refund', 'reference_id' => (int) $rf['id'], 'note' => $rf['refund_no'],
                            ]);
                            $a['returned'] = (int) ($a['returned'] ?? 0) + $can;
                            $left -= $can;
                        } catch (InventoryException $e) {
                            self::history($orderId, $o['status'], $o['status'], __('restock_skipped') . ' ' . $it['product_name'] . ': ' . $e->getMessage(), false, $by);
                        }
                        unset($a);
                        if ($left <= 0) {
                            break;
                        }
                    }
                }
                $returned = (int) $it['returned_qty'] + $qty;
                $db->update('order_items', [
                    'returned_qty'      => $returned,
                    'status'            => $returned >= (int) $it['quantity'] ? 'returned' : 'active',
                    'batch_allocations' => $allocs ? json_encode($allocs) : $it['batch_allocations'],
                ], 'id = :id', ['id' => (int) $it['id']]);
                $db->update('refunds', ['status' => 'approved', 'restock' => $restock ? 1 : 0, 'admin_note' => $note, 'processed_by' => $by], 'id = :id', ['id' => (int) $rf['id']]);
            }
            self::history($orderId, $o['status'], $o['status'], trim(__('hist_return_approved') . ' ' . ($note ?? '')), true, $by);

            // vendor wallet: take back the returned share; a sub-order with nothing left becomes "returned"
            foreach ($bySplit as $splitId => $x) {
                $split = $db->fetch('SELECT * FROM order_vendor_splits WHERE id = :id FOR UPDATE', ['id' => $splitId]);
                Wallet::reverseReturn($split, $x['before'], $x['qtys'], $by);
                $left = (int) $db->fetchColumn("SELECT COUNT(*) FROM order_items WHERE split_id = :s AND status = 'active'", ['s' => $splitId]);
                if ($left === 0 && $split['status'] !== 'returned') {
                    $db->update('order_vendor_splits', ['status' => 'returned'], 'id = :id', ['id' => $splitId]);
                }
            }
            self::syncParent($o, null, true, $by);
            return self::find($orderId);
        });
        self::notifyCustomer($o, __($approve ? 'notif_return_ok' : 'notif_return_bad', ['no' => $o['order_no']]), $note, 'refund_update');
    }

    /** Money sent back for approved returns: refunds -> processed, order refunded_amount / payment_status updated. */
    public static function markRefunded(int $orderId, string $reference, int $by): float
    {
        return self::db()->transaction(static function (Database $db) use ($orderId, $reference, $by): float {
            $o = self::lock($orderId);
            $rows = $db->fetchAll("SELECT * FROM refunds WHERE order_id = :o AND status = 'approved' FOR UPDATE", ['o' => $orderId]);
            if (!$rows) {
                throw new OrderException(__('refund_none_approved'));
            }
            $sum = round(array_sum(array_map(static fn($r) => (float) $r['amount'], $rows)), 2);
            $db->query("UPDATE refunds SET status = 'processed', reference_no = :r, processed_by = :b, processed_at = :t WHERE order_id = :o AND status = 'approved'",
                ['r' => mb_substr($reference, 0, 100), 'b' => $by, 't' => date('Y-m-d H:i:s'), 'o' => $orderId]);
            $refunded = round((float) $o['refunded_amount'] + $sum, 2);
            $paid = (float) $o['paid_amount'];
            $db->update('orders', [
                'refunded_amount' => $refunded,
                'payment_status'  => $paid > 0 && $refunded + 0.009 >= $paid ? 'refunded' : 'partially_refunded',
            ], 'id = :id', ['id' => $orderId]);
            self::history($orderId, $o['status'], $o['status'], __('hist_refunded', ['amount' => money($sum), 'ref' => $reference]), true, $by);
            Audit::log('refunds.process', 'orders', $orderId, null, ['amount' => $sum, 'reference' => $reference]);
            return $sum;
        });
    }

    /* ======================================================================
     * Notifications
     * ==================================================================== */

    private static function notifyCustomer(array $o, string $title, ?string $message = null, ?string $event = null): void
    {
        if ($o['user_id']) {
            Notification::send((int) $o['user_id'], 'order', $title, $message ?: null, url('account/orders/' . $o['order_no']), $event);
        } elseif ($event !== null) {
            // guest order: SMS / email to the order's contact, link to public tracking
            Notifier::contact((string) $o['customer_phone'], (string) ($o['customer_email'] ?? ''), $event, $title, $message ?: null,
                url('track?order_no=' . rawurlencode($o['order_no']) . '&contact=' . rawurlencode((string) ($o['customer_phone'] ?: $o['customer_email']))));
        }
    }

    /** New order alert for staff who can see orders (Rx orders also to prescription reviewers). */
    /** Tell stock staff about products that just fell to / below their low-stock level (or ran out). */
    public static function lowStockAlerts(array $stockBefore): void
    {
        if (!$stockBefore) {
            return;
        }
        $in = implode(',', array_map('intval', array_keys($stockBefore)));
        foreach (self::db()->fetchAll("SELECT id, name_en, stock_qty, low_stock_threshold FROM products WHERE id IN ({$in})") as $p) {
            $before = $stockBefore[(int) $p['id']];
            $now = (int) $p['stock_qty'];
            $limit = (int) $p['low_stock_threshold'];
            if ($before > $limit && $now <= $limit) {
                Notification::toStaff(['inventory.view'], 'stock',
                    __($now <= 0 ? 'notif_out_of_stock' : 'notif_low_stock', ['name' => $p['name_en'], 'n' => $now]),
                    null, url('admin/inventory?q=' . rawurlencode($p['name_en'])));
            }
        }
    }

    private static function notifyStaff(array $o): void
    {
        $keys = $o['requires_prescription'] ? ['orders.view', 'prescriptions.verify'] : ['orders.view'];
        $in = implode(',', array_fill(0, count($keys), '?'));
        $staff = self::db()->fetchAll(
            "SELECT DISTINCT u.id FROM users u
              WHERE u.status = 'active' AND u.deleted_at IS NULL AND (u.user_type = 'super_admin' OR (u.user_type = 'employee' AND EXISTS (
                    SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
                     WHERE rp.role_id = u.role_id AND p.`key` IN ($in))))
              LIMIT 50",
            $keys
        );
        foreach ($staff as $s) {
            Notification::send((int) $s['id'], 'order', __('notif_new_order', ['no' => $o['order_no'], 'amount' => money($o['grand_total'])]), null, url('admin/orders/' . (int) $o['id']));
        }
    }
}
