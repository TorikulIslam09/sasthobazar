<?php
declare(strict_types=1);

/** Customer wishlist page + add/remove (AJAX or plain form). */
class WishlistController extends Controller
{
    public function index(): void
    {
        $uid = (int) Auth::id();
        $ids = Wishlist::productIds($uid);
        $rows = $ids ? Storefront::cards(['ids' => $ids], 'newest', 100) : [];
        // keep wishlist order (newest added first)
        $byId = array_column($rows, null, 'id');
        $products = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $products[] = $byId[$id];
            }
        }
        $this->view('account/wishlist', [
            'title'       => __('wishlist'),
            'tab'         => 'wishlist',
            'products'    => $products,
            'unavailable' => count($ids) - count($products),
            'wished'      => array_fill_keys($ids, true),
        ]);
    }

    public function toggle(): void
    {
        $uid = (int) Auth::id();
        $pid = (int) Helpers::str('product_id');
        $product = $pid > 0 ? $this->db->fetch('SELECT id, status, deleted_at FROM products WHERE id = :id', ['id' => $pid]) : null;
        $wasIn = $product && Wishlist::idsAmong($uid, [$pid]);

        if (!$product || (!$wasIn && ($product['status'] !== 'active' || $product['deleted_at'] !== null))) {
            $this->respond(false, __('product_unavailable'), 404, $uid);
        }
        $now = Wishlist::toggle($uid, $pid);
        if ($now === null) {
            $this->respond(false, __('wishlist_full', ['n' => Wishlist::MAX_ITEMS]), 422, $uid);
        }
        $this->respond($now, __($now ? 'wishlist_added' : 'wishlist_removed'), 200, $uid);
    }

    private function respond(bool $in, string $message, int $code, int $uid): void
    {
        if (Helpers::isAjax()) {
            $this->json(['in_wishlist' => $in, 'count' => Wishlist::countFor($uid), 'message' => $message], $code);
        }
        flash($code === 200 ? 'success' : 'error', $message);
        back();
    }
}
