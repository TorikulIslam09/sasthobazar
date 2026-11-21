<?php
declare(strict_types=1);

/**
 * Promotions engine (Step 9).
 *
 * Offer kinds (table offers):
 *   price          percent / fixed off the unit price of the covered products (product, category, brand, vendor or all)
 *   bogo           buy X get Y free of the same product (e.g. buy 1 get 1, buy 2 get 1)
 *   first_order    percent / fixed off the customer's first order (min order amount, max discount)
 *   free_shipping  free delivery when the covered items reach min_order_amount (platform-funded)
 *
 * Stacking:
 *   unit price = lowest of regular price, running flash sale price, best price offer
 *   buy-X-get-Y only applies when the line is at its regular price (no flash / price offer)
 *   a coupon stacks on top; an applied coupon replaces the first-order offer
 *
 * Who bears the cost (funded_by on offers, flash_sales and coupons):
 *   platform  the marketplace pays: the vendor is paid as if the item sold at the regular price
 *   vendor    the vendor's earning is reduced by the whole discount
 *   shared    vendor_share_percent of the discount is the vendor's, the rest the platform's
 * In-house products (no vendor) are always platform-funded. Commission is charged on the regular price minus the
 * vendor-borne part (see Commission::itemBase), so a platform-funded discount never lowers the vendor's earning.
 *
 * Vendor requests: vendors create offers on their own products with status = pending; only approved,
 * active offers inside their date range are applied.
 */
final class Promo
{
    public const KINDS = ['price', 'bogo', 'first_order', 'free_shipping'];
    public const TYPES = ['percent', 'fixed'];
    public const APPLIES = ['all', 'category', 'product', 'brand', 'vendor'];
    public const FUNDERS = ['platform', 'vendor', 'shared'];
    public const STATUSES = ['pending', 'approved', 'rejected'];

    private static ?array $active = null;
    private static array $firstOrderUser = [];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** Running offers grouped by kind (price / bogo / first_order / free_shipping), best priority first. */
    public static function active(): array
    {
        if (self::$active === null) {
            self::$active = array_fill_keys(self::KINDS, []);
            $rows = self::db()->fetchAll(
                "SELECT * FROM offers
                  WHERE is_active = 1 AND status = 'approved'
                    AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at > NOW())
               ORDER BY priority DESC, id DESC"
            );
            foreach ($rows as $o) {
                $o['ids'] = self::ids($o);
                self::$active[$o['kind']][] = $o;
            }
        }
        return self::$active;
    }

    /** Forget cached offers (after admin changes inside one request, tests). */
    public static function flush(): void
    {
        self::$active = null;
        self::$firstOrderUser = [];
    }

    public static function ids(array $o): array
    {
        $ids = json_decode((string) ($o['applies_ids'] ?? ''), true);
        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    /** Does the offer cover a product (row with id / product_id, category_id, brand_id, vendor_id)? */
    public static function covers(array $o, array $p): bool
    {
        $vendorId = isset($p['vendor_id']) && $p['vendor_id'] !== null ? (int) $p['vendor_id'] : null;
        if ($o['vendor_id'] !== null && $vendorId !== (int) $o['vendor_id']) {
            return false; // a vendor's offer only ever covers that vendor's products
        }
        $ids = $o['ids'] ?? self::ids($o);
        $pid = (int) ($p['product_id'] ?? $p['id'] ?? 0);
        return match ($o['applies_to']) {
            'product'  => in_array($pid, $ids, true),
            'category' => isset($p['category_id']) && $p['category_id'] !== null && in_array((int) $p['category_id'], self::categoryScope($ids), true),
            'brand'    => isset($p['brand_id']) && $p['brand_id'] !== null && in_array((int) $p['brand_id'], $ids, true),
            'vendor'   => $vendorId !== null && (in_array($vendorId, $ids, true) || ($ids === [] && $o['vendor_id'] !== null)),
            default    => true,
        };
    }

    private static function categoryScope(array $ids): array
    {
        static $cache = [];
        $key = implode(',', $ids);
        if (!isset($cache[$key])) {
            $all = [];
            foreach ($ids as $id) {
                $all = array_merge($all, Category::descendantIds($id));
            }
            $cache[$key] = array_values(array_unique(array_map('intval', $all)));
        }
        return $cache[$key];
    }

    /** Unit saving of a price offer on a base price (percent capped by max_discount, never below 0). */
    public static function unitSaving(array $o, float $base): float
    {
        $off = $o['type'] === 'percent' ? $base * (float) $o['value'] / 100 : (float) $o['value'];
        if ($o['type'] === 'percent' && $o['max_discount'] !== null && (float) $o['max_discount'] > 0) {
            $off = min($off, (float) $o['max_discount']);
        }
        return round(max(0.0, min($off, $base)), 2);
    }

    /**
     * Best price offer for a product at a base unit price.
     * @return array{offer: array, price: float, saving: float}|null
     */
    public static function bestPrice(array $product, float $base): ?array
    {
        $best = null;
        foreach (self::active()['price'] as $o) {
            if (!self::covers($o, $product)) {
                continue;
            }
            $saving = self::unitSaving($o, $base);
            if ($saving > 0 && ($best === null || $saving > $best['saving'])) {
                $best = ['offer' => $o, 'price' => round($base - $saving, 2), 'saving' => $saving];
            }
        }
        return $best;
    }

    /** Buy-X-get-Y offer for a product (most generous first), or null. */
    public static function bogo(array $product): ?array
    {
        $best = null;
        foreach (self::active()['bogo'] as $o) {
            if ((int) $o['buy_qty'] < 1 || (int) $o['get_qty'] < 1 || !self::covers($o, $product)) {
                continue;
            }
            $ratio = (int) $o['get_qty'] / ((int) $o['buy_qty'] + (int) $o['get_qty']);
            if ($best === null || $ratio > $best[1]) {
                $best = [$o, $ratio];
            }
        }
        return $best[0] ?? null;
    }

    /** Free units for a quantity: every (buy + get) units, get units are free. */
    public static function bogoFree(array $o, int $qty): int
    {
        $set = max(1, (int) $o['buy_qty']) + max(1, (int) $o['get_qty']);
        return intdiv(max(0, $qty), $set) * max(1, (int) $o['get_qty']);
    }

    /**
     * True when the customer has never placed a (non-cancelled) order. Guests (no user id) are recognised by
     * $guest = ['phone' => .., 'email' => ..] so a guest cannot take the first-order offer again and again.
     */
    public static function isFirstOrder(?int $userId, ?array $guest = null): bool
    {
        if (!$userId) {
            $phone = (string) ($guest['phone'] ?? '');
            $email = (string) ($guest['email'] ?? '');
            if ($phone === '' && $email === '') {
                return false;
            }
            return !self::db()->fetchColumn(
                "SELECT 1 FROM orders WHERE status NOT IN ('cancelled','failed') AND (customer_phone = :p OR (customer_email = :e AND :e2 <> '')) LIMIT 1",
                ['p' => $phone !== '' ? $phone : '-', 'e' => $email, 'e2' => $email]
            );
        }
        if (!array_key_exists($userId, self::$firstOrderUser)) {
            self::$firstOrderUser[$userId] = !self::db()->fetchColumn(
                "SELECT 1 FROM orders WHERE user_id = :u AND status NOT IN ('cancelled','failed') LIMIT 1",
                ['u' => $userId]
            );
        }
        return self::$firstOrderUser[$userId];
    }

    /**
     * First-order offer for cart lines (each: id, product_id, category_id, brand_id, vendor_id, line_total = net value).
     * @return array{offer: array, discount: float, line_ids: int[], eligible: float}|null
     */
    public static function firstOrder(array $lines, ?int $userId, ?array $guest = null): ?array
    {
        if (!self::active()['first_order'] || !self::isFirstOrder($userId, $guest)) {
            return null;
        }
        $best = null;
        foreach (self::active()['first_order'] as $o) {
            $eligible = 0.0;
            $ids = [];
            foreach ($lines as $l) {
                if (self::covers($o, $l)) {
                    $eligible += (float) $l['line_total'];
                    $ids[] = (int) $l['id'];
                }
            }
            if (!$ids || $eligible <= 0 || $eligible < (float) $o['min_order_amount']) {
                continue;
            }
            $d = $o['type'] === 'percent' ? $eligible * (float) $o['value'] / 100 : (float) $o['value'];
            if ($o['type'] === 'percent' && $o['max_discount'] !== null && (float) $o['max_discount'] > 0) {
                $d = min($d, (float) $o['max_discount']);
            }
            $d = round(min($d, $eligible), 2);
            if ($d > 0 && ($best === null || $d > $best['discount'])) {
                $best = ['offer' => $o, 'discount' => $d, 'line_ids' => $ids, 'eligible' => $eligible];
            }
        }
        return $best;
    }

    /** Running first-order offer to advertise (for the cart hint). */
    public static function firstOrderTeaser(): ?array
    {
        return self::active()['first_order'][0] ?? null;
    }

    /**
     * Free-delivery offer reached by cart lines (net values).
     * @return array{offer: array, line_ids: int[]}|null
     */
    public static function freeShipping(array $lines): ?array
    {
        foreach (self::active()['free_shipping'] as $o) {
            $eligible = 0.0;
            $ids = [];
            foreach ($lines as $l) {
                if (self::covers($o, $l)) {
                    $eligible += (float) $l['line_total'];
                    $ids[] = (int) $l['id'];
                }
            }
            if ($ids && $eligible >= (float) $o['min_order_amount']) {
                return ['offer' => $o, 'line_ids' => $ids];
            }
        }
        return null;
    }

    /** Lowest free-delivery threshold of the offers that apply to everything (for "free delivery over ৳X" texts). */
    public static function freeShippingThreshold(): ?float
    {
        $min = null;
        foreach (self::active()['free_shipping'] as $o) {
            if ($o['applies_to'] === 'all' && $o['vendor_id'] === null) {
                $min = $min === null ? (float) $o['min_order_amount'] : min($min, (float) $o['min_order_amount']);
            }
        }
        return $min;
    }

    /**
     * Who bears an amount.
     * @return array{0: float, 1: float} [vendor part, platform part]
     */
    public static function split(float $amount, ?string $fundedBy, mixed $sharePercent, bool $vendorItem): array
    {
        $amount = round(max(0.0, $amount), 2);
        if (!$vendorItem || $amount <= 0) {
            return [0.0, $amount];
        }
        $vendor = match ($fundedBy) {
            'vendor' => $amount,
            'shared' => round($amount * max(0.0, min(100.0, (float) $sharePercent)) / 100, 2),
            default  => 0.0,
        };
        return [$vendor, round($amount - $vendor, 2)];
    }

    /** Customer label: "15% OFF" / "৳20 OFF" / "Buy 1 Get 1" / "Free delivery over ৳999". */
    public static function label(array $o): string
    {
        return match ($o['kind']) {
            'bogo'          => __('offer_bogo_label', ['buy' => num((int) $o['buy_qty']), 'get' => num((int) $o['get_qty'])]),
            'free_shipping' => (float) $o['min_order_amount'] > 0
                ? __('offer_free_ship_over', ['amount' => money($o['min_order_amount'])]) : __('coupon_free_shipping'),
            default         => $o['type'] === 'percent'
                ? __('offer_pct_off', ['n' => num((float) $o['value'] + 0)]) : __('offer_amount_off', ['amount' => money($o['value'])]),
        };
    }

    /** Discount parts saved on an order item (order_items.promo_info). */
    public static function itemParts(array $it): array
    {
        $parts = json_decode((string) ($it['promo_info'] ?? ''), true);
        return is_array($parts) ? $parts : [];
    }

    /** "Flash sale ৳20 (platform)" for one saved part; $forVendor shows the vendor's own share. */
    public static function partText(array $p, bool $forVendor = false): string
    {
        $name = match ($p['type']) {
            'flash'       => __('flash_sale'),
            'offer'       => isset($p['ptype'])
                ? __($p['ptype'] === 'percent' ? 'offer_pct_off' : 'offer_amount_off', ['n' => num((float) $p['value'] + 0), 'amount' => money($p['value'])])
                : ($p['label'] ?? __('offer')),
            'bogo'        => __('offer_kind_bogo') . (!empty($p['free_qty']) ? ' (' . __('n_free', ['n' => num((int) $p['free_qty'])]) . ')' : ''),
            'first_order' => __('offer_kind_first_order'),
            'coupon'      => __('coupon') . ' ' . ($p['code'] ?? ''),
            default       => (string) $p['type'],
        };
        $who = (float) $p['vendor'] > 0 && (float) $p['platform'] > 0
            ? __('funded_split', ['vendor' => money($p['vendor']), 'platform' => money($p['platform'])])
            : __('funded_' . ((float) $p['vendor'] > 0 ? ($forVendor ? 'you' : 'vendor') : 'platform'));
        return $name . ' -' . money($p['amount']) . ' · ' . $who;
    }

    public static function title(array $o): string
    {
        return tr($o['title_bn'] ?? null, (string) $o['title_en']);
    }

    /** Unique slug for an offer title. */
    public static function slugFor(string $title, ?int $exceptId = null): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'offer';
        $base = mb_substr($base, 0, 150);
        $slug = $base;
        $n = 1;
        while (self::db()->fetchColumn('SELECT 1 FROM offers WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $exceptId ?? 0])) {
            $slug = $base . '-' . ++$n;
        }
        return $slug;
    }
}
