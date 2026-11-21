<?php
declare(strict_types=1);

/**
 * Seller types (Pharmacy, Medical equipment, Beauty & personal care, Grocery ...), edited by admins.
 * A type decides the sign-up documents, whether a drug licence is needed, whether prescription medicine may be sold,
 * which top categories its sellers may sell in, and a default commission rate.
 */
class VendorType extends Model
{
    protected static string $table = 'vendor_types';

    /** Every document a type can ask for. */
    public const DOCS = ['trade_license', 'nid', 'drug_license', 'bsti', 'tin'];

    private static ?array $cache = null;

    /** id => row (all types, active first by sort order). */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = array_column(
                static::db()->fetchAll('SELECT * FROM vendor_types ORDER BY is_active DESC, sort_order, id'),
                null,
                'id'
            );
        }
        return self::$cache;
    }

    public static function active(): array
    {
        return array_filter(self::all(), static fn($t) => (int) $t['is_active'] === 1);
    }

    public static function get(?int $id): ?array
    {
        return $id ? (self::all()[$id] ?? null) : null;
    }

    public static function forVendor(array $v): ?array
    {
        return self::get(isset($v['vendor_type_id']) ? (int) $v['vendor_type_id'] : null);
    }

    public static function label(?array $t): string
    {
        return $t ? tr((string) ($t['name_bn'] ?: $t['name_en']), (string) $t['name_en']) : '—';
    }

    /** Type name for a vendor row (falls back to the old fixed type). */
    public static function vendorLabel(array $v): string
    {
        $t = self::forVendor($v);
        return $t ? self::label($t) : (string) ($v['vendor_type'] ?? '—');
    }

    public static function description(array $t): string
    {
        return tr((string) ($t['description_bn'] ?: $t['description_en']), (string) $t['description_en']);
    }

    private static function list(?string $json): array
    {
        $a = json_decode((string) $json, true);
        return is_array($a) ? array_values($a) : [];
    }

    /** @return string[] */
    public static function requiredDocs(array $t): array
    {
        $docs = array_values(array_intersect(self::list($t['required_docs']), self::DOCS));
        if ((int) $t['needs_drug_license'] && !in_array('drug_license', $docs, true)) {
            $docs[] = 'drug_license';
        }
        return $docs;
    }

    /** @return string[] */
    public static function optionalDocs(array $t): array
    {
        return array_values(array_diff(array_intersect(self::list($t['optional_docs']), self::DOCS), self::requiredDocs($t)));
    }

    /** @return int[]|null top category ids; null = every category */
    public static function allowedCategories(array $t): ?array
    {
        $ids = array_map('intval', self::list($t['allowed_categories']));
        return $ids ?: null;
    }

    /** May a seller of this type sell in this category (the category or one of its parents is allowed)? */
    public static function allowsCategory(array $t, ?int $categoryId): bool
    {
        $allowed = self::allowedCategories($t);
        if ($allowed === null || !$categoryId) {
            return true;
        }
        $cats = Category::allById();
        $id = $categoryId;
        $guard = 0;
        while ($id && isset($cats[$id]) && $guard++ < 20) {
            if (in_array($id, $allowed, true)) {
                return true;
            }
            $id = $cats[$id]['parent_id'] !== null ? (int) $cats[$id]['parent_id'] : 0;
        }
        return false;
    }

    /** Type's default commission for a vendor id (null when not set). */
    public static function rateForVendor(int $vendorId): ?float
    {
        $r = static::db()->fetchColumn(
            'SELECT t.commission_rate FROM vendors v JOIN vendor_types t ON t.id = v.vendor_type_id WHERE v.id = :id',
            ['id' => $vendorId]
        );
        return ($r === false || $r === null) ? null : (float) $r;
    }

    public static function vendorCount(int $typeId): int
    {
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM vendors WHERE vendor_type_id = :t AND deleted_at IS NULL', ['t' => $typeId]);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
