<?php
declare(strict_types=1);

/**
 * Offer form input -> offers row. Shared by the admin offer pages and the vendor offer requests.
 *
 * Vendor context ($vendorId set): only price / buy-X-get-Y offers, only on the vendor's own products
 * (all of them, or a product list), no priority; the offer is saved as a pending request.
 */
final class OfferForm
{
    public const VENDOR_KINDS = ['price', 'bogo'];

    /** Form values for editing an existing row. */
    public static function input(array $o): array
    {
        $in = $o;
        $ids = Promo::ids($o);
        $in['applies_list'] = $ids;
        $in['applies_products'] = $o['applies_to'] === 'product' ? self::productRefs($ids) : '';
        foreach (['starts_at', 'ends_at'] as $k) {
            $in[$k] = $o[$k] ? date('Y-m-d\TH:i', strtotime($o[$k])) : '';
        }
        $in['value'] = $o['value'] !== null ? (string) ((float) $o['value'] + 0) : '';
        return $in;
    }

    /** "SKU-1, 15" for product ids (SKU when the product has one). */
    public static function productRefs(array $ids): string
    {
        if (!$ids) {
            return '';
        }
        $in = implode(',', array_map('intval', $ids));
        $rows = Database::getInstance()->fetchAll("SELECT id, sku FROM products WHERE id IN ({$in})");
        $map = array_column($rows, 'sku', 'id');
        return implode(', ', array_map(static fn($id) => ($map[$id] ?? '') !== '' ? $map[$id] : (string) $id, $ids));
    }

    /**
     * @return array{0: array, 1: array} [row data, errors]
     */
    public static function parse(array $in, ?int $vendorId = null): array
    {
        $db = Database::getInstance();
        $errors = [];
        $kinds = $vendorId ? self::VENDOR_KINDS : Promo::KINDS;
        $kind = in_array($in['kind'] ?? '', $kinds, true) ? $in['kind'] : null;
        if ($kind === null) {
            $errors['kind'] = __('v_in');
            $kind = 'price';
        }
        $str = static fn(string $k) => trim((string) ($in[$k] ?? ''));
        $nul = static fn(string $k) => $str($k) !== '' ? $str($k) : null;

        if ($str('title_en') === '') {
            $errors['title_en'] = __('v_required');
        } elseif (mb_strlen($str('title_en')) > 200) {
            $errors['title_en'] = __('v_max', ['n' => 200]);
        }
        if (mb_strlen($str('title_bn')) > 200) {
            $errors['title_bn'] = __('v_max', ['n' => 200]);
        }
        if (mb_strlen($str('description')) > 1000) {
            $errors['description'] = __('v_max', ['n' => 1000]);
        }
        $dec = static function (string $k, bool $required = false, float $min = 0.0, ?float $max = null) use ($str, &$errors): ?float {
            $v = $str($k);
            if ($v === '') {
                if ($required) {
                    $errors[$k] = __('v_required');
                }
                return null;
            }
            if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v)) {
                $errors[$k] = __('v_decimal');
                return null;
            }
            if ((float) $v < $min) {
                $errors[$k] = __('v_gte', ['n' => $min]);
            } elseif ($max !== null && (float) $v > $max) {
                $errors[$k] = __('v_lte', ['n' => $max]);
            }
            return (float) $v;
        };
        $int = static function (string $k, int $min, int $max, int $default) use ($str, &$errors): int {
            $v = $str($k);
            if ($v === '') {
                return $default;
            }
            if (!preg_match('/^\d{1,5}$/', $v) || (int) $v < $min || (int) $v > $max) {
                $errors[$k] = __('v_between', ['min' => $min, 'max' => $max]);
                return $default;
            }
            return (int) $v;
        };

        // discount
        $type = 'fixed';
        $value = 0.0;
        $maxDiscount = null;
        $buy = $get = 1;
        $minOrder = 0.0;
        if (in_array($kind, ['price', 'first_order'], true)) {
            $type = in_array($in['type'] ?? '', Promo::TYPES, true) ? $in['type'] : 'percent';
            $value = $dec('value', true, 0.01, $type === 'percent' ? 100.0 : null) ?? 0.0;
            $maxDiscount = $type === 'percent' ? $dec('max_discount', false, 0.01) : null;
        }
        if ($kind === 'bogo') {
            $type = 'bogo';
            $buy = $int('buy_qty', 1, 20, 1);
            $get = $int('get_qty', 1, 20, 1);
        }
        if (in_array($kind, ['first_order', 'free_shipping'], true)) {
            $minOrder = $dec('min_order_amount', $kind === 'free_shipping', 0.0) ?? 0.0;
        }

        // what it covers
        $applies = $in['applies_to'] ?? 'all';
        $allowed = $vendorId ? ['vendor', 'product'] : Promo::APPLIES;
        if (!in_array($applies, $allowed, true)) {
            $errors['applies_to'] = __('v_in');
            $applies = $vendorId ? 'vendor' : 'all';
        }
        $ids = [];
        if ($applies === 'product') {
            foreach (preg_split('/[\s,]+/', $str('applies_products'), -1, PREG_SPLIT_NO_EMPTY) as $ref) {
                $pid = $db->fetchColumn(
                    'SELECT id FROM products WHERE deleted_at IS NULL AND (id = :id OR sku = :sku)' . ($vendorId ? ' AND vendor_id = :v' : '') . ' LIMIT 1',
                    ['id' => ctype_digit($ref) ? (int) $ref : 0, 'sku' => $ref] + ($vendorId ? ['v' => $vendorId] : [])
                );
                if (!$pid) {
                    $errors['applies_products'] = __('coupon_unknown_product', ['ref' => mb_substr($ref, 0, 40)]);
                    break;
                }
                $ids[] = (int) $pid;
            }
            if (!$ids && !isset($errors['applies_products'])) {
                $errors['applies_products'] = __('v_required');
            }
        } elseif (!$vendorId && in_array($applies, ['category', 'brand', 'vendor'], true)) {
            $list = (array) ($in['applies_' . $applies . 's'] ?? []);
            $ids = array_map('intval', array_filter($list, static fn($x) => ctype_digit((string) $x)));
            if (!$ids) {
                $errors['applies_' . $applies . 's'] = __('v_required');
            }
        }

        // owner + who bears the cost
        $owner = $vendorId;
        if (!$vendorId && $str('vendor_id') !== '') {
            if (!ctype_digit($str('vendor_id')) || !Vendor::existsActive((int) $str('vendor_id'))) {
                $errors['vendor_id'] = __('v_in');
            } else {
                $owner = (int) $str('vendor_id');
            }
        }
        $funded = in_array($in['funded_by'] ?? '', Promo::FUNDERS, true) ? $in['funded_by'] : ($vendorId ? 'vendor' : 'platform');
        if ($kind === 'free_shipping') {
            $funded = 'platform'; // delivery fees are collected by the platform
        }
        $share = $funded === 'shared' ? ($dec('vendor_share_percent', true, 1.0, 99.0) ?? 50.0) : ($funded === 'vendor' ? 100.0 : 0.0);

        // schedule
        $dates = [];
        foreach (['starts_at', 'ends_at'] as $k) {
            $v = $str($k);
            if ($v === '') {
                $dates[$k] = null;
                continue;
            }
            $d = DateTime::createFromFormat('!Y-m-d\TH:i', $v);
            if (!$d) {
                $errors[$k] = __('v_date');
                $dates[$k] = null;
            } else {
                $dates[$k] = $d->format('Y-m-d H:i:00');
            }
        }
        if ($dates['starts_at'] && $dates['ends_at'] && $dates['ends_at'] <= $dates['starts_at']) {
            $errors['ends_at'] = __('v_end_after_start');
        }

        $data = [
            'title_en'             => mb_substr($str('title_en'), 0, 200),
            'title_bn'             => $nul('title_bn'),
            'description'          => $nul('description'),
            'kind'                 => $kind,
            'type'                 => $type,
            'value'                => $value,
            'max_discount'         => $maxDiscount,
            'buy_qty'              => $buy,
            'get_qty'              => $get,
            'min_order_amount'     => $minOrder,
            'applies_to'           => $applies,
            'applies_ids'          => $ids ? json_encode(array_values(array_unique($ids))) : null,
            'vendor_id'            => $owner,
            'funded_by'            => $funded,
            'vendor_share_percent' => $share,
            'starts_at'            => $dates['starts_at'],
            'ends_at'              => $dates['ends_at'],
            'is_active'            => !empty($in['is_active']) ? 1 : 0,
        ];
        if (!$vendorId) {
            $data['priority'] = $int('priority', 0, 1000, 0);
        }
        return [$data, $errors];
    }

    /** "Running" / "Scheduled" / "Expired" / "Paused" / "Pending" / "Rejected" + bootstrap colour. */
    public static function state(array $o): array
    {
        if (($o['status'] ?? 'approved') !== 'approved') {
            return [__('offer_status_' . $o['status']), $o['status'] === 'pending' ? 'warning' : 'danger'];
        }
        $now = date('Y-m-d H:i:s');
        if (!(int) $o['is_active']) {
            return [__('offer_paused'), 'secondary'];
        }
        if ($o['ends_at'] !== null && $o['ends_at'] <= $now) {
            return [__('offer_expired'), 'dark'];
        }
        if ($o['starts_at'] !== null && $o['starts_at'] > $now) {
            return [__('offer_scheduled'), 'info'];
        }
        return [__('offer_running'), 'success'];
    }

    /** "All products" / "3 products" / "Category: Pain relief" ... */
    public static function scopeText(array $o): string
    {
        $ids = Promo::ids($o);
        return match ($o['applies_to']) {
            'product'  => __('n_products', ['n' => num(count($ids))]),
            'category' => __('applies_category') . ' (' . num(count($ids)) . ')',
            'brand'    => __('applies_brand') . ' (' . num(count($ids)) . ')',
            'vendor'   => $ids ? __('applies_vendor') . ' (' . num(count($ids)) . ')' : __('all_shop_products'),
            default    => __('applies_all'),
        };
    }

    /** "Platform" / "Vendor" / "Shared 50% vendor". */
    public static function fundedText(string $fundedBy, mixed $share): string
    {
        return $fundedBy === 'shared'
            ? __('funded_by_shared_pct', ['n' => num((float) $share + 0)])
            : __('funded_by_' . $fundedBy);
    }
}
