<?php
declare(strict_types=1);

/**
 * Checkout: address (saved or new, Division > District > Upazila), payment, prescription, place order.
 *
 * Who can check out:
 *   - a signed-in customer
 *   - a guest who gave a mobile number or email on the /login "Guest checkout" tab (Guest::contact()). The given
 *     contact is locked; the mobile (or the email, when only an email was given) is verified by OTP before ordering.
 *     No account is created; after the order the guest can create one with one password (OrderController::placed).
 * Anyone else is sent to /login (and comes back here afterwards).
 */
class CheckoutController extends Controller
{
    /** @return array{guest: bool, user: ?array, contact: ?array} */
    private function who(bool $json = false): array
    {
        if (Auth::check()) {
            Auth::requireGuard('customer'); // staff / vendors see the "wrong panel" page
            return ['guest' => false, 'user' => User::find((int) Auth::id()), 'contact' => null];
        }
        $contact = Guest::contact();
        if ($contact && Guest::enabled()) {
            return ['guest' => true, 'user' => null, 'contact' => $contact];
        }
        if ($json) {
            $this->json(['ok' => false, 'error' => __('login_required')], 401);
        }
        $_SESSION['intended_url'] = url('checkout');
        redirect(Guest::enabled() ? 'login?tab=guest' : 'login?tab=login');
        exit;
    }

    /** Guest identity for coupons / first-order offer: the locked contact + the mobile typed in. */
    private static function guestIdentity(?array $contact, string $phone = ''): ?array
    {
        if (!$contact) {
            return null;
        }
        return [
            'phone' => $contact['type'] === 'phone' ? $contact['value'] : (Auth::isValidBdPhone($phone) ? Auth::normalizePhone($phone) : ''),
            'email' => $contact['type'] === 'email' ? $contact['value'] : '',
        ];
    }

    public function index(): void
    {
        $who = $this->who();
        $uid = $who['guest'] ? null : (int) $who['user']['id'];
        $cart = Cart::summary();
        if (!$cart['lines']) {
            flash('warning', __('cart_empty'));
            redirect('cart');
        }
        if ($cart['has_problems']) {
            flash('error', __('cart_has_problems'));
            redirect('cart');
        }
        $addresses = $uid ? Address::forUser($uid) : [];
        $old = $_SESSION['_old'] ?? [];
        $selected = $old['address_id'] ?? ($addresses ? (string) $addresses[0]['id'] : 'new');
        $district = 0;
        foreach ($addresses as $a) {
            if ((string) $a['id'] === (string) $selected) {
                $district = (int) $a['district_id'];
            }
        }
        if ($selected === 'new') {
            $district = (int) ($old['district_id'] ?? 0);
        }
        $methods = Order::enabledMethods();
        $method = in_array($old['payment_method'] ?? '', $methods, true) ? $old['payment_method'] : ($methods[0] ?? 'cod');
        $code = Cart::couponCode();
        $coupon = $code ? Coupon::findByCode($code) : null;
        $division = (int) ($old['division_id'] ?? 0);
        $option = in_array($old['delivery_option'] ?? '', Shipping::OPTIONS, true) ? $old['delivery_option'] : 'standard';
        $contact = $who['contact'];

        $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
        $this->view('shop/checkout', [
            'title'         => __('checkout'),
            'cart'          => $cart,
            'totals'        => Order::totals($cart, $option === 'pickup' ? null : ($district ?: null), $method, $coupon, $uid, $option, self::guestIdentity($contact, (string) ($old['phone'] ?? ''))),
            'option'        => $option,
            'addresses'     => $addresses,
            'selected'      => (string) $selected,
            'methods'       => $methods,
            'method'        => $method,
            'old'           => $old,
            'errors'        => $_SESSION['_errors'] ?? [],
            'divisions'     => Location::divisions(),
            'districts'     => $division ? Location::children($division) : [],
            'upazilas'      => !empty($old['district_id']) ? Location::children((int) $old['district_id']) : [],
            'prescriptions' => ($cart['has_rx'] && $uid) ? Prescription::reusable($uid) : [],
            'token'         => $_SESSION['checkout_token'],
            'guest'         => $who['guest'] ? [
                'contact'     => $contact,
                'otp'         => Guest::otpRequired(),
                'verified'    => Guest::isVerified($contact['type'], $contact['value']),
                'channel'     => $contact['type'] === 'phone' ? 'sms' : 'email',
            ] : null,
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    /** GET checkout/quote?district_id=|address_id=&payment_method=&delivery_option= -> summary box (JSON). */
    public function quote(): void
    {
        $who = $this->who(true);
        $cart = Cart::summary();
        $district = (int) Helpers::str('district_id');
        $address = (int) Helpers::str('address_id');
        $method = Helpers::str('payment_method') ?: 'cod';
        $option = in_array(Helpers::str('delivery_option'), Shipping::OPTIONS, true) ? Helpers::str('delivery_option') : 'standard';
        $code = Cart::couponCode();
        if (!$who['guest'] && $address && ($a = Address::findForUser($address, (int) $who['user']['id']))) {
            $district = (int) $a['district_id'];
        }
        $uid = $who['guest'] ? null : (int) $who['user']['id'];
        $t = Order::totals($cart, $option === 'pickup' ? null : ($district ?: null), $method, $code ? Coupon::findByCode($code) : null, $uid, $option,
            self::guestIdentity($who['contact'], Helpers::str('phone')));
        $this->json(self::summaryJson($t));
    }

    /** Summary box data shared by the page and the JSON endpoint. */
    public static function summaryJson(array $t): array
    {
        $q = $t['quote'];
        $groups = [];
        $offerAt = Promo::freeShippingThreshold();
        foreach ($t['groups'] as $g) {
            $hint = null;
            if ($q['ok'] && $g['service'] !== 'pickup') {
                if (!empty($g['free_offer'])) {
                    $hint = __('free_delivery_offer_applied');
                } elseif (!$g['free'] && $g['fee'] > 0 && $g['threshold'] !== null) {
                    $hint = __('free_delivery_over', ['amount' => money($offerAt !== null ? min($offerAt, (float) $g['threshold']) : $g['threshold'])]);
                } elseif ($g['fee'] > 0 && $offerAt !== null) {
                    $hint = __('free_delivery_over', ['amount' => money($offerAt)]);
                }
            }
            $groups[] = [
                'seller' => $g['seller'],
                'zone'   => $g['zone_name'],
                'fee'    => $q['ok'] ? ($g['fee'] > 0 ? money($g['fee']) : __('free')) : '—',
                'eta'    => Shipping::etaText($g),
                'hint'   => $hint,
            ];
        }
        return [
            'subtotal'    => money($t['subtotal']),
            'discount'    => $t['coupon_discount'] > 0 ? '-' . money($t['coupon_discount']) : null,
            'shipping'    => $q['ok'] ? ($t['shipping'] > 0 ? money($t['shipping']) : __('free')) : ($q['error'] ?? __('select_area_first')),
            'cod_charge'  => $t['cod_charge'] > 0 ? money($t['cod_charge']) : null,
            'grand_total' => money($t['grand_total']),
            'eta'         => $q['ok'] ? Shipping::etaSummary($q) : '',
            'ok'          => $q['ok'],
            'error'       => $q['error'],
            'available'   => $q['available'],
            'groups'      => $groups,
            'shipments'   => count($groups),
        ];
    }

    /* ---------------- guest OTP (JSON) ---------------- */

    /** POST checkout/otp/send -> code to the guest's mobile (SMS) or email. */
    public function otpSend(): void
    {
        $who = $this->who(true);
        if (!$who['guest']) {
            $this->json(['ok' => false, 'error' => __('login_required')], 400);
        }
        $c = $who['contact'];
        if (Guest::isVerified($c['type'], $c['value'])) {
            $this->json(['ok' => true, 'verified' => true, 'message' => __('otp_already_verified')]);
        }
        $r = Otp::send($c['type'] === 'phone' ? 'sms' : 'email', $c['value'], Guest::PURPOSE);
        $this->json([
            'ok'       => $r['ok'],
            'error'    => $r['error'],
            'wait'     => $r['wait'],
            'message'  => $r['ok'] ? __($c['type'] === 'phone' ? 'otp_sent_sms' : 'otp_sent_email', ['to' => $c['value']]) : null,
            // only on a local test machine while SMS / email sending is off (mock gateway): show the code on screen
            'dev_code' => $r['dev_code'],
        ], $r['ok'] ? 200 : 429);
    }

    /** POST checkout/otp/verify  code=123456 */
    public function otpVerify(): void
    {
        $who = $this->who(true);
        if (!$who['guest']) {
            $this->json(['ok' => false, 'error' => __('login_required')], 400);
        }
        $c = $who['contact'];
        $err = Otp::verify($c['type'] === 'phone' ? 'sms' : 'email', $c['value'], Guest::PURPOSE, (string) ($_POST['code'] ?? ''));
        if ($err !== null) {
            $this->json(['ok' => false, 'error' => $err], 422);
        }
        Guest::markVerified($c['type'], $c['value']);
        $this->json(['ok' => true, 'verified' => true, 'message' => __('otp_verified')]);
    }

    /* ---------------- place order ---------------- */

    public function place(): void
    {
        $who = $this->who();
        $guest = $who['guest'];
        $user = $who['user'];
        $contact = $who['contact'];
        $in = $this->postData();
        if ($guest && $contact['type'] === 'phone') {
            $in['phone'] = $contact['value']; // locked field: the verified number, whatever was posted
        }
        $back = static function (array $errors, ?string $msg = null) use ($in): void {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', $msg ?? __('form_has_errors'));
            redirect('checkout');
        };

        // one submit per checkout page (double click / refresh protection)
        $token = $_SESSION['checkout_token'] ?? '';
        unset($_SESSION['checkout_token']);
        if (!is_string($token) || $token === '' || !hash_equals($token, (string) ($in['checkout_token'] ?? ''))) {
            flash('warning', __('checkout_resubmit'));
            redirect('checkout');
        }

        $cart = Cart::summary();
        if (!$cart['lines']) {
            redirect('cart');
        }

        // ---- delivery option ----
        $option = in_array($in['delivery_option'] ?? '', Shipping::OPTIONS, true) ? $in['delivery_option'] : 'standard';

        $errors = [];
        // ---- guest: contact + verification ----
        $guestEmail = '';
        if ($guest) {
            if ($contact['type'] === 'email') {
                $guestEmail = $contact['value'];
            } elseif (($in['email'] ?? '') !== '') {
                if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = __('v_email');
                } else {
                    $guestEmail = strtolower(trim((string) $in['email']));
                }
            }
            if (Guest::otpRequired() && !Guest::isVerified($contact['type'], $contact['value'])) {
                $errors['otp'] = __($contact['type'] === 'phone' ? 'otp_verify_phone_first' : 'otp_verify_email_first');
            }
        }

        // ---- address (not needed for store pickup) ----
        $addrId = $guest ? 'new' : (string) ($in['address_id'] ?? 'new');
        $ship = null;
        if ($option === 'pickup') {
            if ($guest) {
                $errors += $this->validate($in, ['recipient_name' => 'required|max:150', 'phone' => 'required|phone']);
            }
            $wh = Shipping::warehouseDistrict();
            $ship = [
                'shipping_name' => $guest ? (string) ($in['recipient_name'] ?? '') : $user['name'],
                'shipping_phone' => $guest ? Auth::normalizePhone((string) ($in['phone'] ?? '')) : (string) $user['phone'],
                'shipping_address' => mb_substr(__('store_pickup') . ': ' . (setting('store_pickup_address', '') ?: setting('contact_address', '')), 0, 255),
                'division_id' => (int) $this->db->fetchColumn('SELECT parent_id FROM locations WHERE id = :id', ['id' => $wh]),
                'district_id' => $wh, 'upazila_id' => null, 'postcode' => null,
            ];
            $addrId = 'pickup';
        } elseif ($addrId !== 'new') {
            $a = Address::findForUser((int) $addrId, (int) $user['id']);
            if (!$a) {
                $errors['address_id'] = __('v_required');
            } else {
                $ship = [
                    'shipping_name' => $a['recipient_name'], 'shipping_phone' => $a['phone'], 'shipping_address' => $a['address_line'],
                    'division_id' => (int) $a['division_id'], 'district_id' => (int) $a['district_id'],
                    'upazila_id' => $a['upazila_id'] ? (int) $a['upazila_id'] : null, 'postcode' => $a['postcode'],
                ];
            }
        } else {
            $errors += $this->validate($in, [
                'recipient_name' => 'required|max:150',
                'phone'          => 'required|phone',
                'division_id'    => 'required|integer',
                'district_id'    => 'required|integer',
                'upazila_id'     => 'integer',
                'address_line'   => 'required|min:5|max:255',
                'postcode'       => 'max:10',
            ]);
            if (!isset($errors['postcode']) && ($in['postcode'] ?? '') !== '' && !preg_match('/^\d{4}$/', $in['postcode'])) {
                $errors['postcode'] = __('v_postcode');
            }
            if (!array_intersect_key($errors, array_flip(['division_id', 'district_id', 'upazila_id']))) {
                $errors += Address::validateLocations((int) $in['division_id'], (int) $in['district_id'], ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : null);
            }
            if (!array_intersect_key($errors, array_flip(['recipient_name', 'phone', 'division_id', 'district_id', 'upazila_id', 'address_line', 'postcode']))) {
                $ship = [
                    'shipping_name' => $in['recipient_name'], 'shipping_phone' => Auth::normalizePhone($in['phone']),
                    'shipping_address' => $in['address_line'], 'division_id' => (int) $in['division_id'], 'district_id' => (int) $in['district_id'],
                    'upazila_id' => ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : null, 'postcode' => ($in['postcode'] ?? '') ?: null,
                ];
            }
        }

        // ---- payment ----
        $method = (string) ($in['payment_method'] ?? '');
        if (!in_array($method, Order::enabledMethods(), true)) {
            $errors['payment_method'] = __('pay_method_unavailable');
        }
        $payment = ['method' => $method];
        if (in_array($method, Order::MANUAL_METHODS, true)) {
            $trx = strtoupper(preg_replace('/\s+/', '', (string) ($in['trx_id'] ?? '')));
            if (!preg_match('/^[A-Z0-9]{8,20}$/', $trx)) {
                $errors['trx_id'] = __('v_trx');
            }
            if (!Auth::isValidBdPhone((string) ($in['payer_account'] ?? ''))) {
                $errors['payer_account'] = __('v_phone');
            }
            $payment['trx_id'] = $trx;
            $payment['payer_account'] = Auth::normalizePhone((string) ($in['payer_account'] ?? ''));
        }

        $note = mb_substr(trim((string) ($in['customer_note'] ?? '')), 0, 500) ?: null;
        if (empty($in['agree'])) {
            $errors['agree'] = __('v_agree');
        }

        // ---- prescription (only when the cart has Rx items) ----
        $prescriptionId = null;
        $uploadedNow = false;
        $file = Upload::files('prescription')[0] ?? null;
        if ($cart['has_rx'] && !$errors) {
            $existing = (int) ($in['prescription_id'] ?? 0);
            if ($file) {
                if ($err = Prescription::validate($file)) {
                    $errors['prescription'] = $err;
                } else {
                    $prescriptionId = Prescription::store($file, $guest ? null : (int) $user['id'], [
                        'patient_name' => mb_substr(trim((string) ($in['patient_name'] ?? '')), 0, 150) ?: null,
                        'notes'        => $note,
                    ] + ($guest ? ['guest_name' => mb_substr((string) ($in['recipient_name'] ?? ''), 0, 150), 'guest_phone' => $ship['shipping_phone'] ?? null] : []));
                    $uploadedNow = true;
                }
            } elseif ($existing && !$guest) {
                $ok = array_filter(Prescription::reusable((int) $user['id']), static fn($p) => (int) $p['id'] === $existing);
                if ($ok) {
                    $prescriptionId = $existing;
                } else {
                    $errors['prescription'] = __('rx_upload_required');
                }
            } else {
                $errors['prescription'] = __('rx_upload_required');
            }
        } elseif ($cart['has_rx'] && $file) {
            $errors['prescription'] = __('rx_reselect');
        }
        if ($errors) {
            $back($errors);
        }

        if ($guest) {
            $buyer = [
                'id'       => null,
                'name'     => (string) $ship['shipping_name'],
                'phone'    => $contact['type'] === 'phone' ? $contact['value'] : (string) $ship['shipping_phone'],
                'email'    => $guestEmail,
                'verified' => Guest::isVerified($contact['type'], $contact['value']) ? $contact['type'] : null,
            ];
        } else {
            $buyer = $user;
        }

        try {
            $order = Order::place($buyer, $ship, $payment, Cart::couponCode(), $prescriptionId, $note, $option);
        } catch (OrderException | InventoryException $e) {
            if ($uploadedNow) {
                Prescription::discard($prescriptionId);
            }
            $field = $e instanceof OrderException && $e->field !== '' ? $e->field : '_';
            if ($field === 'coupon') {
                Cart::setCoupon(null);
            }
            $back([$field => $e->getMessage()], $e->getMessage());
        } catch (Throwable $e) {
            if ($uploadedNow) {
                Prescription::discard($prescriptionId);
            }
            throw $e;
        }

        Cart::setCoupon(null);
        flash('success', __('order_placed', ['no' => $order['order_no']]));
        if ($guest) {
            Guest::rememberOrder($order['order_no']);
            redirect('order/placed/' . $order['order_no']);
        }
        // save a new address to the address book
        if ($addrId === 'new' && !empty($in['save_address']) && Address::countForUser((int) $user['id']) < Address::MAX_PER_USER) {
            Address::save((int) $user['id'], null, [
                'label' => 'home', 'recipient_name' => $ship['shipping_name'], 'phone' => $ship['shipping_phone'], 'alt_phone' => null,
                'division_id' => $ship['division_id'], 'district_id' => $ship['district_id'], 'upazila_id' => $ship['upazila_id'],
                'address_line' => $ship['shipping_address'], 'postcode' => $ship['postcode'],
            ], false);
        }
        redirect('account/orders/' . $order['order_no'] . '?placed=1');
    }
}
