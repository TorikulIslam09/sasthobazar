<?php
declare(strict_types=1);

/**
 * VAT (Accounting → VAT / tax settings).
 *   vat_enabled    off = no VAT is calculated
 *   vat_rate       default rate in %; a product with its own VAT % (product form) uses that instead
 *   vat_inclusive  on  = prices already include VAT (the VAT part is shown, the total does not change)
 *                  off = VAT is added on top of the discounted price
 *   vat_label / vat_reg_no (BIN) printed on invoices
 * VAT is calculated per line on what the customer pays for it (after every discount) and saved on the order item.
 * Each seller is responsible for the VAT on its own sales: VAT added on top of a seller's item is passed to the
 * seller with the sale (Wallet::settleSplit); VAT on in-house items is the platform's VAT payable.
 */
final class Tax
{
    public static function enabled(): bool
    {
        return (bool) setting('vat_enabled', false);
    }

    public static function inclusive(): bool
    {
        return (bool) setting('vat_inclusive', true);
    }

    public static function defaultRate(): float
    {
        return max(0.0, min(100.0, (float) setting('vat_rate', 0)));
    }

    public static function label(): string
    {
        $l = trim((string) setting('vat_label', 'VAT'));
        return $l !== '' ? $l : 'VAT';
    }

    public static function regNo(): string
    {
        return trim((string) setting('vat_reg_no', ''));
    }

    /** Was the order's VAT added on top of the prices (true) or included in them (false)? */
    public static function orderVatOnTop(array $o): bool
    {
        if ((float) ($o['vat_total'] ?? 0) <= 0) {
            return false;
        }
        $sum = (float) $o['subtotal'] - (float) $o['discount_total'] + (float) $o['shipping_total'] + (float) $o['cod_charge'] + (float) $o['vat_total'];
        return abs($sum - (float) $o['grand_total']) < 0.02;
    }

    /** "VAT (5%)" style label for an order total row. */
    public static function rowLabel(array $o): string
    {
        return self::orderVatOnTop($o) ? self::label() : __('vat_included', ['label' => self::label()]);
    }

    /** Rate for a product row (vat_percent > 0 = product rate, else the default rate); 0 when VAT is off. */
    public static function rateFor(array $p): float
    {
        if (!self::enabled()) {
            return 0.0;
        }
        $own = (float) ($p['vat_percent'] ?? 0);
        return $own > 0 ? min(100.0, $own) : self::defaultRate();
    }

    /** VAT on an amount the customer pays (inclusive: the VAT part inside it; exclusive: VAT on top). */
    public static function amount(float $net, float $rate, ?bool $inclusive = null): float
    {
        if ($rate <= 0 || $net <= 0) {
            return 0.0;
        }
        $inclusive ??= self::inclusive();
        return round($inclusive ? $net * $rate / (100 + $rate) : $net * $rate / 100, 2);
    }
}
