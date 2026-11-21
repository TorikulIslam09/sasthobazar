<?php
declare(strict_types=1);

/**
 * Product reviews.
 *  - one review per customer per product
 *  - "verified purchase" when the customer has a delivered order containing the product
 *  - status: approved immediately when setting review_auto_approve is on (default), else pending for moderation
 *  - products.rating_avg / rating_count are recalculated from approved reviews
 */
class Review extends Model
{
    protected static string $table = 'reviews';

    public const PER_PAGE = 5;
    public const MAX_PER_HOUR = 10;

    public static function countApproved(int $productId): int
    {
        return (int) static::db()->fetchColumn(
            "SELECT COUNT(*) FROM reviews WHERE product_id = :p AND status = 'approved'",
            ['p' => $productId]
        );
    }

    public static function approved(int $productId, int $limit, int $offset): array
    {
        return static::db()->fetchAll(
            "SELECT r.id, r.rating, r.title, r.comment, r.is_verified_purchase, r.vendor_reply, r.replied_at, r.created_at,
                    u.name AS user_name
               FROM reviews r JOIN users u ON u.id = r.user_id
              WHERE r.product_id = :p AND r.status = 'approved'
           ORDER BY r.created_at DESC, r.id DESC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            ['p' => $productId]
        );
    }

    /** [5 => n, 4 => n, ... 1 => n] of approved reviews. */
    public static function breakdown(int $productId): array
    {
        $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $rows = static::db()->fetchAll(
            "SELECT rating, COUNT(*) AS n FROM reviews WHERE product_id = :p AND status = 'approved' GROUP BY rating",
            ['p' => $productId]
        );
        foreach ($rows as $r) {
            $out[(int) $r['rating']] = (int) $r['n'];
        }
        return $out;
    }

    public static function byUser(int $userId, int $productId): ?array
    {
        return static::db()->fetch(
            'SELECT * FROM reviews WHERE user_id = :u AND product_id = :p ORDER BY id LIMIT 1',
            ['u' => $userId, 'p' => $productId]
        );
    }

    /**
     * Save a review. Locks the product row so two requests cannot both pass the "already reviewed" check.
     * @return string 'approved' | 'pending' | 'duplicate'
     */
    public static function submit(int $userId, array $product, int $rating, ?string $title, ?string $comment): string
    {
        $db = static::db();
        $productId = (int) $product['id'];
        $result = $db->transaction(static function (Database $db) use ($userId, $product, $productId, $rating, $title, $comment): string {
            $db->fetch('SELECT id FROM products WHERE id = :id FOR UPDATE', ['id' => $productId]);
            if ($db->fetchColumn('SELECT 1 FROM reviews WHERE user_id = :u AND product_id = :p LIMIT 1', ['u' => $userId, 'p' => $productId])) {
                return 'duplicate';
            }
            $orderId = $db->fetchColumn(
                "SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id
                  WHERE o.user_id = :u AND oi.product_id = :p AND o.status = 'delivered'
               ORDER BY o.id DESC LIMIT 1",
                ['u' => $userId, 'p' => $productId]
            );
            $status = setting('review_auto_approve', true) ? 'approved' : 'pending';
            $id = $db->insert('reviews', [
                'product_id'           => $productId,
                'user_id'              => $userId,
                'order_id'             => $orderId ? (int) $orderId : null,
                'vendor_id'            => $product['vendor_id'] !== null ? (int) $product['vendor_id'] : null,
                'rating'               => $rating,
                'title'                => $title,
                'comment'              => $comment,
                'is_verified_purchase' => $orderId ? 1 : 0,
                'status'               => $status,
            ]);
            if ($status === 'approved') {
                self::recalculate($productId);
            }
            Audit::log('reviews.create', 'reviews', $id, null, ['product_id' => $productId, 'rating' => $rating, 'status' => $status]);
            return $status;
        });
        if ($result === 'pending') {
            Notification::toStaff(['reviews.moderate'], 'review', __('notif_review_pending', ['name' => $product['name_en'] ?? '', 'n' => $rating]), $comment, url('admin/reviews?status=pending'));
        }
        return $result;
    }

    /** Refresh the cached rating on the product (keeps products.updated_at unchanged). */
    public static function recalculate(int $productId): void
    {
        static::db()->query(
            "UPDATE products SET
                rating_avg   = (SELECT COALESCE(ROUND(AVG(rating), 2), 0) FROM reviews WHERE product_id = :a AND status = 'approved'),
                rating_count = (SELECT COUNT(*) FROM reviews WHERE product_id = :b AND status = 'approved'),
                updated_at   = updated_at
              WHERE id = :c",
            ['a' => $productId, 'b' => $productId, 'c' => $productId]
        );
    }
}
