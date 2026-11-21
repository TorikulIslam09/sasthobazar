<?php
declare(strict_types=1);

/** Cart page + add / update / remove (AJAX or plain forms) + coupon. */
class CartController extends Controller
{
    public function index(): void
    {
        $cart = Cart::summary();
        $code = Cart::couponCode();
        $coupon = $code ? Coupon::findByCode($code) : null;
        $totals = Order::totals($cart, null, 'cod', $coupon, Auth::type() === 'customer' ? (int) Auth::id() : null);
        $this->view('shop/cart', [
            'title'  => __('cart'),
            'cart'   => $cart,
            'code'   => $code,
            'coupon' => $coupon,
            'totals' => $totals,
        ]);
    }

    public function add(): void
    {
        $pid = (int) Helpers::str('product_id');
        $vid = (int) Helpers::str('variant_id');
        $qty = (int) (Helpers::str('qty') ?: 1);
        try {
            Cart::add($pid, $vid ?: null, $qty);
        } catch (InventoryException $e) {
            $this->respond(false, $e->getMessage(), 422);
        }
        if (Helpers::str('buy_now') === '1') {
            redirect(Auth::type() === 'customer' ? 'checkout' : 'cart');
        }
        $this->respond(true, __('cart_added'));
    }

    public function update(): void
    {
        try {
            Cart::setQty((int) Helpers::str('line_id'), (int) Helpers::str('qty'));
        } catch (InventoryException $e) {
            $this->respond(false, $e->getMessage(), 422);
        }
        $this->respond(true, __('cart_updated'));
    }

    public function remove(): void
    {
        Cart::remove((int) Helpers::str('line_id'));
        $this->respond(true, __('cart_removed'));
    }

    public function coupon(): void
    {
        if (Helpers::str('remove') === '1') {
            Cart::setCoupon(null);
            flash('success', __('coupon_removed'));
            redirect('cart');
        }
        $code = strtoupper(mb_substr(Helpers::str('code'), 0, 50));
        $key = 'coupon:' . Helpers::clientIp();
        if (RateLimiter::tooMany($key, 20)) {
            flash('error', __('too_many_requests'));
            redirect('cart');
        }
        RateLimiter::hit($key, 600);
        $cart = Cart::summary();
        $okLines = array_values(array_filter($cart['lines'], static fn($l) => $l['problem'] === null));
        $r = Coupon::evaluate(Coupon::findByCode($code), $okLines, Auth::type() === 'customer' ? (int) Auth::id() : null);
        if (!$r['ok']) {
            Cart::setCoupon(null);
            flash('error', $r['error']);
        } else {
            Cart::setCoupon($code);
            flash('success', __('coupon_applied', ['code' => $code]));
        }
        redirect('cart');
    }

    private function respond(bool $ok, string $message, int $code = 200): void
    {
        if (Helpers::isAjax()) {
            $this->json(['ok' => $ok, 'message' => $message, 'count' => Cart::count()], $code);
        }
        flash($ok ? 'success' : 'error', $message);
        back();
    }
}
