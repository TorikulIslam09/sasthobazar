<?php
declare(strict_types=1);

/**
 * Shopping cart (carts table).
 *  - logged-in customer: rows by user_id
 *  - anyone else: rows by a random cart token kept in the session (survives login, merged into the
 *    customer's cart by Cart::mergeGuest after login / registration)
 * Prices are never stored in the cart; they are read fresh (incl. flash sale) every time.
 */
class Cart
{
    public const MAX_LINES = 50;
    public const MAX_QTY = 100;

    private static ?array $summaryCache = null;

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** [column, value] identifying the current cart. */
    private static function owner(): array
    {
        if (Auth::type() === 'customer') {
            return ['user_id', (int) Auth::id()];
        }
        if (empty($_SESSION['cart_token']) || !is_string($_SESSION['cart_token'])) {
            $_SESSION['cart_token'] = bin2hex(random_bytes(20));
        }
        return ['session_id', $_SESSION['cart_token']];
    }

    private static function ownerWhere(string $alias = ''): array
    {
        [$col, $val] = self::owner();
        $a = $alias !== '' ? $alias . '.' : '';
        return [$col === 'user_id' ? "{$a}user_id = :own AND {$a}session_id IS NULL" : "{$a}session_id = :own AND {$a}user_id IS NULL", ['own' => $val]];
    }

    /** Move guest-cart rows into the customer's cart (same product + variant: quantities are added). */
    /** SQL condition + params for the current browser's cart (logged-in user or guest session). */
    public static function ownerCondition(): array
    {
        return self::ownerWhere('');
    }

    public static function mergeGuest(int $userId): void
    {
        $token = $_SESSION['cart_token'] ?? null;
        if (!is_string($token) || $token === '') {
            return;
        }
        $db = self::db();
        $db->transaction(static function (Database $db) use ($token, $userId) {
            $guest = $db->fetchAll('SELECT * FROM carts WHERE session_id = :t AND user_id IS NULL FOR UPDATE', ['t' => $token]);
            foreach ($guest as $g) {
                $mine = $db->fetch(
                    'SELECT id, quantity FROM carts WHERE user_id = :u AND session_id IS NULL AND product_id = :p AND variant_id <=> :v FOR UPDATE',
                    ['u' => $userId, 'p' => (int) $g['product_id'], 'v' => $g['variant_id'] !== null ? (int) $g['variant_id'] : null]
                );
                if ($mine) {
                    $db->update('carts', ['quantity' => min(self::MAX_QTY, (int) $mine['quantity'] + (int) $g['quantity'])], 'id = :id', ['id' => (int) $mine['id']]);
                    $db->delete('carts', 'id = :id', ['id' => (int) $g['id']]);
                } else {
                    $db->update('carts', ['user_id' => $userId, 'session_id' => null], 'id = :id', ['id' => (int) $g['id']]);
                }
            }
        });
        unset($_SESSION['cart_token']);
        self::$summaryCache = null;
    }

    /**
     * Add to cart. Returns the new quantity of that line.
     * @throws InventoryException (translated message) when the product cannot be added
     */
    public static function add(int $productId, ?int $variantId, int $qty): int
    {
        $db = self::db();
        $p = $db->fetch(
            "SELECT p.*, v.status AS vendor_status, v.deleted_at AS vendor_deleted, c.is_active AS cat_active
               FROM products p LEFT JOIN vendors v ON v.id = p.vendor_id LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.id = :id",
            ['id' => $productId]
        );
        if (!$p || !self::visible($p)) {
            throw new InventoryException(__('product_unavailable'));
        }
        $variant = null;
        if ((int) $p['has_variants']) {
            $variant = $variantId ? $db->fetch('SELECT * FROM product_variants WHERE id = :v AND product_id = :p AND is_active = 1', ['v' => $variantId, 'p' => $productId]) : null;
            if (!$variant) {
                throw new InventoryException(__('cart_choose_variant'));
            }
        } else {
            $variantId = null;
        }
        $qty = max(1, min(self::MAX_QTY, $qty));

        [$where, $params] = self::ownerWhere();
        [$col, $val] = self::owner();
        return $db->transaction(static function (Database $db) use ($p, $variant, $variantId, $qty, $where, $params, $col, $val, $productId): int {
            $line = $db->fetch(
                "SELECT id, quantity FROM carts WHERE {$where} AND product_id = :p AND variant_id <=> :v FOR UPDATE",
                $params + ['p' => $productId, 'v' => $variantId]
            );
            $new = ($line ? (int) $line['quantity'] : 0) + $qty;
            $max = self::maxQty($p, $variant);
            if ($max < 1) {
                throw new InventoryException(__('out_of_stock'));
            }
            if ($new > $max) {
                throw new InventoryException(__('cart_max_qty', ['n' => num($max)]));
            }
            $new = max($new, max(1, (int) $p['min_order_qty']));
            if ($line) {
                $db->update('carts', ['quantity' => $new], 'id = :id', ['id' => (int) $line['id']]);
            } else {
                if ((int) $db->fetchColumn("SELECT COUNT(*) FROM carts WHERE {$where}", $params) >= self::MAX_LINES) {
                    throw new InventoryException(__('cart_full', ['n' => self::MAX_LINES]));
                }
                $db->insert('carts', [$col => $val, 'product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $new]);
            }
            self::$summaryCache = null;
            return $new;
        });
    }

    /** Set a line's quantity (0 removes it). */
    public static function setQty(int $lineId, int $qty): void
    {
        [$where, $params] = self::ownerWhere();
        $db = self::db();
        if ($qty <= 0) {
            $db->delete('carts', "id = :id AND {$where}", $params + ['id' => $lineId]);
        } else {
            [$cWhere, $cParams] = self::ownerWhere('c');
            $line = $db->fetch(
                "SELECT p.*, c.id AS line_id, c.variant_id AS line_variant FROM carts c JOIN products p ON p.id = c.product_id WHERE c.id = :id AND {$cWhere}",
                $cParams + ['id' => $lineId]
            );
            if (!$line) {
                return;
            }
            $variant = $line['line_variant'] ? $db->fetch('SELECT * FROM product_variants WHERE id = :v', ['v' => (int) $line['line_variant']]) : null;
            $max = self::maxQty($line, $variant);
            if ($qty > $max) {
                throw new InventoryException(__('cart_max_qty', ['n' => num(max(0, $max))]));
            }
            $qty = max($qty, max(1, (int) $line['min_order_qty']));
            $db->update('carts', ['quantity' => $qty], "id = :id AND {$where}", $params + ['id' => $lineId]);
        }
        self::$summaryCache = null;
    }

    public static function remove(int $lineId): void
    {
        self::setQty($lineId, 0);
    }

    public static function clear(): void
    {
        [$where, $params] = self::ownerWhere();
        self::db()->delete('carts', $where, $params);
        self::$summaryCache = null;
    }

    /** Number of items (sum of quantities) for the header badge. */
    public static function count(): int
    {
        if (Auth::type() !== 'customer' && empty($_SESSION['cart_token'])) {
            return 0;
        }
        [$where, $params] = self::ownerWhere();
        return (int) self::db()->fetchColumn("SELECT COALESCE(SUM(quantity), 0) FROM carts WHERE {$where}", $params);
    }

    /** Most a customer may hold of this product / variant. */
    private static function maxQty(array $p, ?array $variant): int
    {
        $max = (int) ($p['max_order_qty'] ?: self::MAX_QTY);
        if ((int) $p['track_stock']) {
            $max = min($max, (int) ($variant ? $variant['stock_qty'] : $p['stock_qty']));
        }
        return min($max, self::MAX_QTY);
    }

    private static function visible(array $p): bool
    {
        return $p['status'] === 'active' && $p['deleted_at'] === null
            && ($p['vendor_id'] === null || ($p['vendor_status'] === 'approved' && $p['vendor_deleted'] === null))
            && ($p['category_id'] === null || (int) $p['cat_active'] === 1);
    }

    /**
     * Cart lines with live prices and problems + totals.
     * $userId given => that customer's cart (used by Order::place inside its transaction).
     * @return array{lines: array, subtotal: float, mrp_total: float, items: int, has_rx: bool, has_problems: bool, weight: int}
     */
    public static function summary(?int $userId = null, bool $fresh = false): array
    {
        if ($fresh) {
            self::$summaryCache = null;
        }
        if ($userId === null && self::$summaryCache !== null) {
            return self::$summaryCache;
        }
        if ($userId !== null) {
            $where = 'c.user_id = :own AND c.session_id IS NULL';
            $params = ['own' => $userId];
        } else {
            if (Auth::type() !== 'customer' && empty($_SESSION['cart_token'])) {
                return self::$summaryCache = ['lines' => [], 'subtotal' => 0.0, 'mrp_total' => 0.0, 'bogo_total' => 0.0, 'items' => 0, 'has_rx' => false, 'has_problems' => false, 'weight' => 0];
            }
            [$where, $params] = self::ownerWhere('c');
        }
        $rows = self::db()->fetchAll(
            "SELECT c.id, c.product_id, c.variant_id, c.quantity,
                    p.slug, p.name_en, p.name_bn, p.sku, p.unit, p.pack_size, p.generic_name, p.strength, p.mrp, p.price, p.cost_price,
                    p.stock_qty, p.track_stock, p.requires_prescription, p.min_order_qty, p.max_order_qty, p.status, p.deleted_at,
                    p.has_variants, p.category_id, p.brand_id, p.vendor_id, p.weight_grams, p.commission_rate, p.vat_percent,
                    v.name_en AS v_en, v.name_bn AS v_bn, v.sku AS v_sku, v.price AS v_price, v.mrp AS v_mrp, v.stock_qty AS v_stock,
                    v.is_active AS v_active, v.weight_grams AS v_weight,
                    vd.status AS vendor_status, vd.deleted_at AS vendor_deleted, vd.shop_name, vd.shop_name_bn, vd.commission_rate AS vendor_commission,
                    cat.is_active AS cat_active, cat.commission_rate AS category_commission,
                    (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image
               FROM carts c
               JOIN products p ON p.id = c.product_id
          LEFT JOIN product_variants v ON v.id = c.variant_id
          LEFT JOIN vendors vd ON vd.id = p.vendor_id
          LEFT JOIN categories cat ON cat.id = p.category_id
              WHERE {$where}
           ORDER BY c.id",
            $params
        );

        $lines = [];
        $subtotal = $mrpTotal = $promoTotal = 0.0;
        $items = $weight = 0;
        $hasRx = $problems = false;
        $flashCache = [];
        foreach ($rows as $r) {
            $pid = (int) $r['product_id'];
            $flashCache[$pid] ??= Storefront::productFlash($pid);
            $variantId = $r['variant_id'] !== null ? (int) $r['variant_id'] : null;
            $base = $variantId ? ['price' => $r['v_price'], 'mrp' => $r['v_mrp']] : ['price' => $r['price'], 'mrp' => $r['mrp']];
            $pricing = Storefront::priceFor($base, $flashCache[$pid], $variantId, $r);
            $qty = (int) $r['quantity'];
            $flashCap = $pricing['flash']['per_order'] ?? null;
            $stock = (int) $r['track_stock'] ? (int) ($variantId ? $r['v_stock'] : $r['stock_qty']) : PHP_INT_MAX;

            $problem = null;
            if (!self::visible($r) || ($variantId && !(int) $r['v_active']) || (!$variantId && (int) $r['has_variants'])) {
                $problem = __('product_unavailable');
            } elseif ($stock <= 0) {
                $problem = __('out_of_stock');
            } elseif ($qty > $stock) {
                $problem = __('only_n_left', ['n' => num($stock)]);
            } elseif ($r['max_order_qty'] && $qty > (int) $r['max_order_qty']) {
                $problem = __('cart_max_qty', ['n' => num((int) $r['max_order_qty'])]);
            } elseif ($flashCap !== null && $qty > $flashCap) {
                $problem = __('flash_max_per_order', ['n' => num($flashCap)]);
            }

            $lineTotal = round($pricing['price'] * $qty, 2);
            $line = $r + self::pricingFields($pricing, $qty, $base) + [
                'name'        => tr($r['name_bn'], $r['name_en']),
                'variant'     => $variantId ? tr($r['v_bn'], $r['v_en']) : '',
                'stock'       => $stock,
                'max_qty'     => min(self::MAX_QTY, $stock, (int) ($r['max_order_qty'] ?: self::MAX_QTY), $flashCap ?? self::MAX_QTY),
                'problem'     => $problem,
                'line_weight' => (int) ($variantId && (int) $r['v_weight'] ? $r['v_weight'] : $r['weight_grams']) * $qty,
            ];
            $lines[] = $line;
            if ($problem === null) {
                $subtotal += $lineTotal;
                $promoTotal += $line['bogo_discount'];
                $mrpTotal += $pricing['mrp'] * $qty;
                $items += $qty;
                $weight += $line['line_weight'];
                $hasRx = $hasRx || (int) $r['requires_prescription'] === 1;
            } else {
                $problems = true;
            }
        }
        $out = [
            'lines'        => $lines,
            'subtotal'     => round($subtotal, 2),
            'mrp_total'    => round($mrpTotal, 2),
            'bogo_total'   => round($promoTotal, 2),
            'items'        => $items,
            'has_rx'       => $hasRx,
            'has_problems' => $problems,
            'weight'       => $weight,
        ];
        if ($userId === null) {
            self::$summaryCache = $out;
        }
        return $out;
    }

    /**
     * Price columns of a cart line from Storefront::priceFor().
     *   unit_price / line_total  price paid per unit (flash / price offer inside), before buy-X-get-Y, coupon, first-order
     *   price_row                regular price + MRP (to re-price when a flash quantity runs out at checkout)
     *   promo                    discount inside the unit price {type, id, funded_by, share, label}
     *   bogo / bogo_free / bogo_discount  buy-X-get-Y offer, free units, their value
     */
    public static function pricingFields(array $pricing, int $qty, array $priceRow): array
    {
        $bogoFree = $pricing['bogo'] ? Promo::bogoFree($pricing['bogo'], $qty) : 0;
        return [
            'unit_price'    => $pricing['price'],
            'unit_mrp'      => $pricing['mrp'],
            'base_price'    => $pricing['base'],
            'price_row'     => $priceRow,
            'flash'         => $pricing['flash'],
            'promo'         => $pricing['promo'],
            'bogo'          => $pricing['bogo'],
            'bogo_free'     => $bogoFree,
            'bogo_discount' => round($bogoFree * $pricing['price'], 2),
            'line_total'    => round($pricing['price'] * $qty, 2),
        ];
    }

    /* ---------------- Coupon kept in the session ---------------- */

    public static function couponCode(): ?string
    {
        $c = $_SESSION['cart_coupon'] ?? null;
        return is_string($c) && $c !== '' ? $c : null;
    }

    public static function setCoupon(?string $code): void
    {
        if ($code === null) {
            unset($_SESSION['cart_coupon']);
        } else {
            $_SESSION['cart_coupon'] = strtoupper(trim($code));
        }
    }
}
