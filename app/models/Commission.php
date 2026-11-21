<?php
declare(strict_types=1);

/**
 * Marketplace commission.
 * Rate priority (first one that is set wins):
 *   1. product   products.commission_rate
 *   2. category  categories.commission_rate of the product's category, else the nearest parent category with a rate
 *   3. vendor    vendors.commission_rate (the rate agreed with this seller)
 *   4. type      vendor_types.commission_rate of the seller's type (Pharmacy, Grocery ...)
 *   5. global    setting default_commission_rate (8%)
 * A seller's own agreed rate wins over its type's default; category rates stay above both so that low-margin
 * categories (e.g. prescription medicine) keep their rate whoever sells them.
 * The rate is fixed on the order item when the order is placed. The money is booked when the vendor's
 * sub-order is delivered: commission = (regular value - vendor-borne discounts - returned part) x rate.
 * Who bears a discount is set on the offer / flash sale / coupon (platform, vendor or shared); a platform-funded
 * discount is paid to the vendor by the platform, so it lowers neither the commission base nor the vendor's earning.
 */
final class Commission
{
    private static array $vendorCache = [];

    public static function globalRate(): float
    {
        return (float) setting('default_commission_rate', 8);
    }

    /**
     * @param array $p product row with commission_rate, category_id, vendor_id
     * @return array{0: float, 1: string} [rate, scope]
     */
    public static function rateFor(array $p): array
    {
        if ($p['vendor_id'] === null) {
            return [0.0, 'in_house'];
        }
        if ($p['commission_rate'] !== null && $p['commission_rate'] !== '') {
            return [(float) $p['commission_rate'], 'product'];
        }
        $cats = Category::allById();
        $id = $p['category_id'] !== null ? (int) $p['category_id'] : 0;
        $guard = 0;
        while ($id && isset($cats[$id]) && $guard++ < 20) {
            if ($cats[$id]['commission_rate'] !== null) {
                return [(float) $cats[$id]['commission_rate'], 'category'];
            }
            $id = $cats[$id]['parent_id'] !== null ? (int) $cats[$id]['parent_id'] : 0;
        }
        $vid = (int) $p['vendor_id'];
        if (!array_key_exists($vid, self::$vendorCache)) {
            $r = Database::getInstance()->fetch(
                'SELECT v.commission_rate AS own, t.commission_rate AS type_rate FROM vendors v LEFT JOIN vendor_types t ON t.id = v.vendor_type_id WHERE v.id = :id',
                ['id' => $vid]
            );
            self::$vendorCache[$vid] = [
                'own'  => ($r && $r['own'] !== null) ? (float) $r['own'] : null,
                'type' => ($r && $r['type_rate'] !== null) ? (float) $r['type_rate'] : null,
            ];
        }
        if (self::$vendorCache[$vid]['own'] !== null) {
            return [self::$vendorCache[$vid]['own'], 'vendor'];
        }
        if (self::$vendorCache[$vid]['type'] !== null) {
            return [self::$vendorCache[$vid]['type'], 'vendor_type'];
        }
        return [self::globalRate(), 'global'];
    }

    /**
     * Value the vendor is paid on for one unit: what the customer paid + the platform-borne discounts
     * (= regular price - vendor-borne discounts). Older items without funding data: paid amount.
     */
    public static function unitBase(array $it): float
    {
        return ((float) $it['line_total'] - (float) $it['discount_amount'] + (float) ($it['platform_funded'] ?? 0)) / max(1, (int) $it['quantity']);
    }

    /** VAT added on top of this item's price for the units still sold (passed to the seller with the sale). */
    public static function itemVatOnTop(array $it): float
    {
        if ((int) ($it['vat_in_price'] ?? 1) || (float) ($it['vat_amount'] ?? 0) <= 0) {
            return 0.0;
        }
        $qty = max(1, (int) $it['quantity']);
        return round((float) $it['vat_amount'] * max(0, $qty - (int) $it['returned_qty']) / $qty, 2);
    }

    /** Commission base still sold on this item (returned units removed). */
    public static function itemBase(array $it): float
    {
        $qty = max(1, (int) $it['quantity']);
        $kept = max(0, $qty - (int) $it['returned_qty']);
        return round(self::unitBase($it) * $kept, 2);
    }
}
