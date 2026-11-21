<?php
declare(strict_types=1);

/**
 * Coupons: checks + discount calculation. Used by the cart (preview) and by Order::place (final, with the
 * coupon row locked so usage limits cannot be exceeded by parallel orders).
 */
class Coupon extends Model
{
    protected static string $table = 'coupons';

    public const TYPES = ['percent', 'fixed', 'free_shipping'];
    public const APPLIES = ['all', 'category', 'product', 'brand', 'vendor'];

    public static function findByCode(string $code, bool $forUpdate = false): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        return static::db()->fetch('SELECT * FROM coupons WHERE code = :c' . ($forUpdate ? ' FOR UPDATE' : ''), ['c' => $code]);
    }

    public static function appliesIds(array $c): array
    {
        $ids = json_decode((string) ($c['applies_ids'] ?? ''), true);
        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    /** Does the coupon cover this cart line? */
    private static function covers(array $c, array $line): bool
    {
        if ($c['vendor_id'] !== null && (int) $line['vendor_id'] !== (int) $c['vendor_id']) {
            return false;
        }
        $ids = self::appliesIds($c);
        return match ($c['applies_to']) {
            'category' => in_array((int) $line['category_id'], self::categoryScope($ids), true),
            'product'  => in_array((int) $line['product_id'], $ids, true),
            'brand'    => in_array((int) $line['brand_id'], $ids, true),
            'vendor'   => in_array((int) $line['vendor_id'], $ids, true),
            default    => true,
        };
    }

    /** Category ids plus their sub-categories. */
    private static function categoryScope(array $ids): array
    {
        static $cache = [];
        $key = implode(',', $ids);
        if (!isset($cache[$key])) {
            $all = [];
            foreach ($ids as $id) {
                $all = array_merge($all, Category::descendantIds($id));
            }
            $cache[$key] = array_values(array_unique($all));
        }
        return $cache[$key];
    }

    /**
     * Check a coupon against cart lines (each: product_id, category_id, brand_id, vendor_id, line_total).
     * @return array{ok: bool, error: ?string, discount: float, free_shipping: bool, eligible: float, line_ids: int[]}
     */
    public static function evaluate(?array $c, array $lines, ?int $userId, ?array $guest = null): array
    {
        $fail = static fn(string $msg) => ['ok' => false, 'error' => $msg, 'discount' => 0.0, 'free_shipping' => false, 'eligible' => 0.0, 'line_ids' => []];
        if (!$c || !(int) $c['is_active']) {
            return $fail(__('coupon_invalid'));
        }
        $now = date('Y-m-d H:i:s');
        if ($c['starts_at'] !== null && $c['starts_at'] > $now) {
            return $fail(__('coupon_not_started'));
        }
        if ($c['ends_at'] !== null && $c['ends_at'] <= $now) {
            return $fail(__('coupon_expired'));
        }
        if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
            return $fail(__('coupon_used_up'));
        }
        $db = static::db();
        if ($userId) {
            if ($c['usage_limit_per_user'] !== null) {
                $mine = (int) $db->fetchColumn(
                    "SELECT COUNT(*) FROM coupon_usages cu JOIN orders o ON o.id = cu.order_id
                      WHERE cu.coupon_id = :c AND cu.user_id = :u AND o.status <> 'cancelled'",
                    ['c' => (int) $c['id'], 'u' => $userId]
                );
                if ($mine >= (int) $c['usage_limit_per_user']) {
                    return $fail(__('coupon_user_limit'));
                }
            }
            if ((int) $c['first_order_only'] && $db->fetchColumn(
                "SELECT 1 FROM orders WHERE user_id = :u AND status NOT IN ('cancelled','failed') LIMIT 1",
                ['u' => $userId]
            )) {
                return $fail(__('coupon_first_order'));
            }
        }

        // guests: per-customer limit and first-order rule by mobile / email
        $gPhone = (string) ($guest['phone'] ?? '');
        $gEmail = (string) ($guest['email'] ?? '');
        if (!$userId && ($gPhone !== '' || $gEmail !== '')) {
            $who = "(o.customer_phone = :p OR (o.customer_email = :e AND :e2 <> ''))";
            $wp = ['p' => $gPhone !== '' ? $gPhone : '-', 'e' => $gEmail, 'e2' => $gEmail];
            if ($c['usage_limit_per_user'] !== null) {
                $mine = (int) $db->fetchColumn(
                    "SELECT COUNT(*) FROM coupon_usages cu JOIN orders o ON o.id = cu.order_id WHERE cu.coupon_id = :c AND o.status <> 'cancelled' AND {$who}",
                    ['c' => (int) $c['id']] + $wp
                );
                if ($mine >= (int) $c['usage_limit_per_user']) {
                    return $fail(__('coupon_user_limit'));
                }
            }
            if ((int) $c['first_order_only'] && $db->fetchColumn("SELECT 1 FROM orders o WHERE o.status NOT IN ('cancelled','failed') AND {$who} LIMIT 1", $wp)) {
                return $fail(__('coupon_first_order'));
            }
        } elseif (!$userId && (int) $c['first_order_only']) {
            return $fail(__('coupon_need_contact')); // first-order coupons need a known customer
        }

        $eligible = 0.0;
        $lineIds = [];
        foreach ($lines as $l) {
            if (self::covers($c, $l)) {
                $eligible += (float) $l['line_total'];
                $lineIds[] = (int) $l['id'];
            }
        }
        if (!$lineIds) {
            return $fail(__('coupon_not_applicable'));
        }
        if ($eligible < (float) $c['min_order_amount']) {
            return $fail(__('coupon_min_amount', ['amount' => money($c['min_order_amount'])]));
        }

        $discount = match ($c['type']) {
            'percent' => $eligible * (float) $c['value'] / 100,
            'fixed'   => (float) $c['value'],
            default   => 0.0,
        };
        if ($c['type'] === 'percent' && $c['max_discount'] !== null) {
            $discount = min($discount, (float) $c['max_discount']);
        }
        $discount = round(min($discount, $eligible), 2);
        return [
            'ok'            => true,
            'error'         => null,
            'discount'      => $discount,
            'free_shipping' => $c['type'] === 'free_shipping',
            'eligible'      => $eligible,
            'line_ids'      => $lineIds,
        ];
    }

    /** Short description for customers: "10% off (max ৳100)" / "৳50 off" / "Free delivery". */
    public static function summary(array $c): string
    {
        return match ($c['type']) {
            'percent'       => __('coupon_pct', ['n' => num((float) $c['value'] + 0)])
                . ($c['max_discount'] !== null ? ' (' . __('coupon_max', ['amount' => money($c['max_discount'])]) . ')' : ''),
            'fixed'         => __('coupon_fixed', ['amount' => money($c['value'])]),
            default         => __('coupon_free_shipping'),
        };
    }
}
