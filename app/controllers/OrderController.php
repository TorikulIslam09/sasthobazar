<?php
declare(strict_types=1);

/** Customer: order history, order detail + tracking timeline, invoice, cancel, return request; public order tracking. */
class OrderController extends Controller
{
    private function mine(string $no): array
    {
        $o = Order::findByNo($no);
        if (!$o || (int) $o['user_id'] !== (int) Auth::id()) {
            $this->notFound();
        }
        return $o;
    }

    public function index(): void
    {
        $uid = (int) Auth::id();
        $status = Helpers::str('status');
        $pager = new Paginator(OrderView::countForUser($uid, $status), 10);
        $this->view('account/orders', [
            'title'  => __('my_orders'),
            'tab'    => 'orders',
            'status' => $status,
            'orders' => OrderView::forUser($uid, $status, $pager->perPage, $pager->offset()),
            'pager'  => $pager,
        ]);
    }

    public function show(string $no): void
    {
        $o = $this->mine($no);
        $history = OrderView::history((int) $o['id'], true);
        $this->view('account/order', [
            'title'        => __('order') . ' ' . $o['order_no'],
            'tab'          => 'orders',
            'o'            => $o,
            'items'        => OrderView::items((int) $o['id']),
            'history'      => $history,
            'timeline'     => OrderView::timeline($o, $history),
            'payments'     => OrderView::payments((int) $o['id']),
            'refunds'      => OrderView::refunds((int) $o['id']),
            'splits'       => OrderView::splits((int) $o['id']),
            'prescription' => OrderView::prescription($o['prescription_id'] ? (int) $o['prescription_id'] : null),
            'area'         => OrderView::area($o),
            'errors'       => $_SESSION['_errors'] ?? [],
            'placed'       => Helpers::str('placed') === '1',
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function invoice(string $no): void
    {
        $o = $this->mine($no);
        $this->view('orders/invoice', [
            'title'    => __('invoice') . ' ' . $o['order_no'],
            'orders'   => [['o' => $o, 'items' => OrderView::items((int) $o['id']), 'area' => OrderView::area($o), 'payments' => OrderView::payments((int) $o['id'])]],
        ], 'print');
    }

    public function cancel(string $no): void
    {
        $o = $this->mine($no);
        if (!in_array($o['status'], Order::CUSTOMER_CANCELLABLE, true)) {
            flash('error', __('cancel_not_allowed'));
            redirect('account/orders/' . $o['order_no']);
        }
        $reason = mb_substr(trim(Helpers::str('reason')), 0, 255);
        try {
            Order::changeStatus((int) $o['id'], 'cancelled', __('hist_cancel_by_customer') . ($reason !== '' ? ': ' . $reason : ''), true, (int) Auth::id());
            flash('success', __('order_cancelled'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('account/orders/' . $o['order_no']);
    }

    public function requestReturn(string $no): void
    {
        $o = $this->mine($no);
        $in = $this->postData();
        $qtys = [];
        foreach ((array) ($_POST['qty'] ?? []) as $itemId => $q) {
            if (is_scalar($q) && preg_match('/^\d{1,4}$/', (string) $q)) {
                $qtys[(int) $itemId] = (int) $q;
            }
        }
        $errors = $this->validate($in, [
            'reason'        => 'required|min:5|max:500',
            'refund_method' => 'required|in:' . implode(',', Order::REFUND_METHODS),
            'account'       => 'max:255',
        ]);
        if (!isset($errors['refund_method']) && in_array($in['refund_method'], ['bkash', 'nagad'], true) && !Auth::isValidBdPhone((string) ($in['account'] ?? ''))) {
            $errors['account'] = __('v_phone');
        }
        if (!isset($errors['refund_method']) && $in['refund_method'] === 'bank' && mb_strlen((string) ($in['account'] ?? '')) < 10) {
            $errors['account'] = __('v_bank_details');
        }
        if (!$errors) {
            try {
                Order::requestReturn((int) $o['id'], (int) Auth::id(), $qtys, $in['reason'], $in['refund_method'], ($in['account'] ?? '') ?: null);
                flash('success', __('return_requested'));
                redirect('account/orders/' . $o['order_no']);
            } catch (OrderException $e) {
                // business rule (window over, already open, too many) - show it even if the form is hidden
                Helpers::setOld($in);
                flash('error', $e->getMessage());
                redirect('account/orders/' . $o['order_no'] . '#return');
            }
        }
        $_SESSION['_errors'] = $errors;
        Helpers::setOld($in);
        flash('error', __('form_has_errors'));
        redirect('account/orders/' . $o['order_no'] . '#return');
    }

    /** The customer's own prescription file. */
    public function prescriptionFile(string $id): void
    {
        $row = Prescription::find((int) $id);
        if (!$row || (int) $row['user_id'] !== (int) Auth::id()) {
            $this->notFound();
        }
        Prescription::stream($row);
    }

    /** Public tracking: order number + the mobile number OR email used on the order. */
    public function track(): void
    {
        $no = strtoupper(mb_substr(Helpers::str('order_no'), 0, 30));
        $contact = trim(bn_to_en_digits(Helpers::str('contact') !== '' ? Helpers::str('contact') : Helpers::str('phone')));
        $order = null;
        $error = null;
        if ($no !== '' || $contact !== '') {
            $key = 'track:' . Helpers::clientIp();
            if (RateLimiter::tooMany($key, 20)) {
                $error = __('too_many_requests');
            } else {
                RateLimiter::hit($key, 600);
                $o = $no !== '' ? Order::findByNo($no) : null;
                $match = false;
                if ($o && str_contains($contact, '@')) {
                    $match = $o['customer_email'] !== null && strtolower($contact) === strtolower((string) $o['customer_email']);
                } elseif ($o && Auth::isValidBdPhone($contact)) {
                    $match = in_array(Auth::normalizePhone($contact), [$o['customer_phone'], $o['shipping_phone']], true);
                }
                if ($match) {
                    $order = $o;
                } else {
                    $error = __('track_not_found');
                }
            }
        }
        $history = $order ? OrderView::history((int) $order['id'], true) : [];
        $this->view('shop/track', [
            'title'    => __('track_order'),
            'no'       => $no,
            'contact'  => $contact,
            'order'    => $order,
            'timeline' => $order ? OrderView::timeline($order, $history) : [],
            'error'    => $error,
        ]);
    }

    /** Guest: order placed (only the browser that placed it can open this page). */
    public function placed(string $no): void
    {
        $o = Order::findByNo($no);
        if ($o && Auth::check() && (int) $o['user_id'] === (int) Auth::id()) {
            redirect('account/orders/' . $o['order_no'] . '?placed=1');
        }
        if (!$o || !Guest::ownsOrder($o['order_no'])) {
            $this->notFound();
        }
        $phone = (string) $o['customer_phone'];
        $this->view('shop/placed', [
            'title'      => __('order_placed_title'),
            'o'          => $o,
            'items'      => OrderView::items((int) $o['id']),
            'area'       => OrderView::area($o),
            'canAccount' => $o['user_id'] === null && !Auth::check(),
            'phoneTaken' => $phone !== '' && User::phoneExists($phone),
            'errors'     => $_SESSION['_errors'] ?? [],
        ]);
        unset($_SESSION['_errors']);
    }

    /** Guest: create an account right after ordering (one password field). Name / mobile / email come from the order. */
    public function createAccount(string $no): void
    {
        $o = Order::findByNo($no);
        if (!$o || !Guest::ownsOrder($o['order_no']) || $o['user_id'] !== null || Auth::check()) {
            $this->notFound();
        }
        $back = 'order/placed/' . $o['order_no'];
        $rateKey = 'register|' . Helpers::clientIp();
        if (RateLimiter::tooMany($rateKey, 5)) {
            flash('error', __('register_too_many', ['minutes' => num((int) ceil(RateLimiter::availableIn($rateKey) / 60))]));
            redirect($back);
        }
        $password = is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
        $phone = (string) $o['customer_phone'];
        $email = $o['customer_email'] ? strtolower((string) $o['customer_email']) : null;
        $errors = [];
        if (mb_strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = __('v_password_len');
        }
        if (!Auth::isValidBdPhone($phone) || User::phoneExists($phone)) {
            $errors['password'] = __('placed_phone_taken');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            redirect($back);
        }
        RateLimiter::hit($rateKey, 3600);
        if ($email !== null && User::emailExists($email)) {
            $email = null; // the email already belongs to another account: create the account with the mobile only
        }
        $db = Database::getInstance();
        $id = $db->transaction(function () use ($db, $o, $phone, $email, $password): int {
            $id = User::createCustomer((string) $o['customer_name'], $phone, $email, $password);
            // the orders this browser placed as a guest belong to this person - link them even if no OTP was used
            foreach ($_SESSION['guest_orders'] ?? [] as $i => $orderNo) {
                $db->query('UPDATE orders SET user_id = :u WHERE order_no = :n AND user_id IS NULL AND is_guest = 1', ['u' => $id, 'n' => (string) $orderNo]);
            }
            return $id;
        });
        Auth::audit('user.register', 'user', $id, null, ['from_order' => $o['order_no']], $id);
        $user = User::find($id);
        Guest::linkOrders($user); // + older verified guest orders; marks the mobile / email verified if OTP was done
        Auth::login(User::find($id));
        Cart::mergeGuest($id);
        Guest::clear();
        unset($_SESSION['guest_orders']);
        flash('success', __('placed_account_created'));
        redirect('account/orders/' . $o['order_no']);
    }
}
