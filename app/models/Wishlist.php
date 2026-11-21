<?php
declare(strict_types=1);

/** Customer wishlist (wishlists table: one row per user + product). */
class Wishlist extends Model
{
    protected static string $table = 'wishlists';
    protected static bool $audited = false;

    public const MAX_ITEMS = 200;

    public static function countFor(int $userId): int
    {
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM wishlists WHERE user_id = :u', ['u' => $userId]);
    }

    /** Which of $productIds are in the user's wishlist (as a set: id => true). */
    public static function idsAmong(int $userId, array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $params = ['u' => $userId];
        $in = [];
        foreach ($productIds as $i => $id) {
            $in[] = ':p' . $i;
            $params['p' . $i] = $id;
        }
        $rows = static::db()->fetchAll('SELECT product_id FROM wishlists WHERE user_id = :u AND product_id IN (' . implode(',', $in) . ')', $params);
        return array_fill_keys(array_map('intval', array_column($rows, 'product_id')), true);
    }

    /** Product ids in the wishlist, newest first. */
    public static function productIds(int $userId): array
    {
        return array_map('intval', array_column(static::db()->fetchAll(
            'SELECT product_id FROM wishlists WHERE user_id = :u ORDER BY id DESC LIMIT ' . self::MAX_ITEMS,
            ['u' => $userId]
        ), 'product_id'));
    }

    /**
     * Add or remove. Returns true when the product is now in the wishlist, false when removed,
     * null when the wishlist is full.
     */
    public static function toggle(int $userId, int $productId): ?bool
    {
        $db = static::db();
        if ($db->delete('wishlists', 'user_id = :u AND product_id = :p', ['u' => $userId, 'p' => $productId]) > 0) {
            return false;
        }
        if (self::countFor($userId) >= self::MAX_ITEMS) {
            return null;
        }
        $db->query('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (:u, :p)', ['u' => $userId, 'p' => $productId]);
        return true;
    }

    public static function remove(int $userId, int $productId): void
    {
        static::db()->delete('wishlists', 'user_id = :u AND product_id = :p', ['u' => $userId, 'p' => $productId]);
    }
}
