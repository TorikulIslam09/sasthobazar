<?php
declare(strict_types=1);

/**
 * Login / register / logout for all three panels.
 *  - Storefront   : /login, /register, /logout          (customer)
 *  - Admin        : /admin/login, /admin/logout          (super_admin, employee)
 *  - Vendor panel : /vendor-panel/login, /vendor-panel/logout (vendor)
 */
class AuthController extends Controller
{
    private const PANELS = [
        'customer' => ['types' => ['customer'], 'login' => 'login', 'home' => '/', 'title' => 'customer_login'],
        'admin'    => ['types' => ['super_admin', 'employee'], 'login' => 'admin/login', 'home' => 'admin', 'title' => 'admin_login'],
        'vendor'   => ['types' => ['vendor'], 'login' => 'vendor-panel/login', 'home' => 'vendor-panel', 'title' => 'vendor_login'],
    ];

    /* ---------- storefront ---------- */
    public function loginForm(): void        { $this->showLogin('customer'); }

    /**
     * "Guest checkout" tab: remember the mobile / email in the session (no account) and go to checkout.
     * POST /login/guest  contact_type = phone|email, phone | email
     */
    public function guestStart(): void
    {
        if (!Guest::enabled()) {
            flash('warning', __('guest_disabled'));
            redirect('login?tab=login');
        }
        if (!Cart::count()) {
            flash('warning', __('cart_empty'));
            redirect('cart');
        }
        $type = ($_POST['contact_type'] ?? '') === 'email' ? 'email' : 'phone';
        $value = (string) ($_POST[$type] ?? '');
        $contact = Guest::parse($type, $value);
        if (!$contact) {
            $_SESSION['_errors'] = [$type => __($type === 'phone' ? 'v_guest_phone' : 'v_email')];
            Helpers::setOld(['contact_type' => $type, $type => $value]);
            redirect('login?tab=guest');
        }
        Guest::setContact($contact['type'], $contact['value']);
        redirect('checkout');
    }
    public function login(): void            { $this->doLogin('customer'); }
    public function logout(): void           { $this->doLogout('customer'); }

    /* ---------- admin ---------- */
    public function adminLoginForm(): void   { $this->showLogin('admin'); }
    public function adminLogin(): void       { $this->doLogin('admin'); }
    public function adminLogout(): void      { $this->doLogout('admin'); }

    /* ---------- vendor ---------- */
    public function vendorLoginForm(): void  { $this->showLogin('vendor'); }
    public function vendorLogin(): void      { $this->doLogin('vendor'); }
    public function vendorLogout(): void     { $this->doLogout('vendor'); }

    private function showLogin(string $panel): void
    {
        $p = self::PANELS[$panel];
        $data = [
            'title'  => __($p['title']),
            'panel'  => $panel,
            'action' => url($p['login']),
        ];
        if ($panel === 'customer') {
            // Customers sign in with mobile number + password, on the storefront layout.
            // Tabs: Guest checkout (default when allowed and the cart has items) | Login | Register
            $guestOn = Guest::enabled();
            $tab = Helpers::str('tab');
            if (!in_array($tab, ['guest', 'login'], true)) {
                $tab = $guestOn && Cart::count() > 0 ? 'guest' : 'login';
            }
            if ($tab === 'guest' && !$guestOn) {
                $tab = 'login';
            }
            $old = $_SESSION['_old'] ?? [];
            $data += [
                'tab'          => $tab,
                'guestOn'      => $guestOn,
                'guestType'    => in_array($old['contact_type'] ?? '', ['phone', 'email'], true) ? $old['contact_type'] : (Guest::contact()['type'] ?? Guest::defaultContact()),
                'guestValue'   => Guest::contact(),
                'errors'       => $_SESSION['_errors'] ?? [],
                'cartCount'    => Cart::count(),
            ];
            unset($_SESSION['_errors']);
            $this->view('shop/auth/login', $data);
        } else {
            $this->view('auth/login', $data, 'auth');
        }
        Helpers::clearOld();
    }

    private function doLogin(string $panel): void
    {
        $p = self::PANELS[$panel];
        $login = (string) Helpers::input('login', '');
        $password = (string) ($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            Helpers::setOld($_POST);
            flash('error', __('auth_failed'));
            redirect($p['login']);
        }
        $loginPage = $panel === 'customer' ? 'login?tab=login' : $p['login'];
        if ($panel === 'customer' && !Auth::isValidBdPhone($login)) {
            Helpers::setOld($_POST);
            flash('error', __('v_phone'));
            redirect($loginPage);
        }

        $result = Auth::attempt($login, $password, $p['types']);
        if ($result !== true) {
            Helpers::setOld($_POST);
            flash('error', $result);
            redirect($loginPage);
        }

        Helpers::clearOld();
        if ($panel === 'customer') {
            Cart::mergeGuest((int) Auth::id());
            $linked = Guest::linkOrders(User::find((int) Auth::id()));
            Guest::clear();
            if ($linked > 0) {
                flash('info', __('guest_orders_linked', ['n' => num($linked)]));
            }
        }
        flash('success', __('welcome_user', ['name' => Auth::user()['name']]));
        redirect(Auth::intendedUrl($p['home']));
    }

    private function doLogout(string $panel): void
    {
        Auth::logout();
        flash('success', __('logged_out'));
        // "switch account" from the wrong-panel page: go to that panel's login (only our own login pages)
        $next = Helpers::str('next');
        $allowed = array_column(self::PANELS, 'login');
        redirect(in_array($next, $allowed, true) ? $next : self::PANELS[$panel]['login']);
    }

    /* ---------- customer registration ---------- */

    public function registerForm(): void
    {
        $this->view('shop/auth/register', [
            'title'  => __('register'),
            'errors' => $_SESSION['_errors'] ?? [],
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function register(): void
    {
        // Accept scalars only (arrays in POST would break string functions)
        $in = array_map(static fn($v) => is_scalar($v) ? trim((string) $v) : '', $_POST);
        // at most 5 new accounts per IP per hour (bulk sign-ups, probing which numbers are registered)
        $rateKey = 'register|' . Helpers::clientIp();
        if (RateLimiter::tooMany($rateKey, 5)) {
            Helpers::setOld($in);
            flash('error', __('register_too_many', ['minutes' => num((int) ceil(RateLimiter::availableIn($rateKey) / 60))]));
            redirect('register');
        }
        RateLimiter::hit($rateKey, 3600);
        $errors = $this->validate($in, [
            'name'     => 'required|max:150',
            'phone'    => 'required|phone',
            'email'    => 'email|max:191',
            'password' => 'required|min:8|max:72|confirmed',
        ]);
        if (empty($in['agree'])) {
            $errors['agree'] = __('v_agree');
        }
        $in['phone'] = Auth::normalizePhone((string) ($in['phone'] ?? ''));
        if (!isset($errors['phone']) && User::phoneExists((string) $in['phone'])) {
            $errors['phone'] = __('phone_taken');
        }
        if (!isset($errors['email']) && !empty($in['email']) && User::emailExists((string) $in['email'])) {
            $errors['email'] = __('email_taken');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            redirect('register');
        }

        $id = User::createCustomer($in['name'], $in['phone'], ($in['email'] ?? '') ?: null, $in['password']);
        Auth::audit('user.register', 'user', $id, null, null, $id);
        Auth::login(User::find($id));
        Cart::mergeGuest($id);
        $linked = Guest::linkOrders(User::find($id)); // earlier guest orders of the same verified mobile / email
        Guest::clear();
        if ($linked > 0) {
            flash('info', __('guest_orders_linked', ['n' => num($linked)]));
        }
        flash('success', __('registered'));
        redirect(Auth::intendedUrl('/'));
    }
}
