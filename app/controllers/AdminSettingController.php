<?php
declare(strict_types=1);

/**
 * Settings: General (site info, logo, contacts, social) and Orders & payments (payment methods on/off,
 * order rules, default commission, seller payouts). SMS / email / notifications: AdminMessagingController;
 * backup: AdminBackupController; password: My profile.
 */
class AdminSettingController extends Controller
{
    /** General tab: key => [type, group] */
    private const GENERAL = [
        'site_name'       => ['string', 'general'],
        'site_name_bn'    => ['string', 'general'],
        'site_tagline_en' => ['string', 'general'],
        'site_tagline'    => ['string', 'general'],
        'contact_phone'   => ['string', 'general'],
        'contact_email'   => ['string', 'general'],
        'contact_address' => ['string', 'general'],
        'facebook_url'    => ['string', 'social'],
        'youtube_url'     => ['string', 'social'],
        'instagram_url'   => ['string', 'social'],
        'whatsapp_number' => ['string', 'social'],
        'meta_title'      => ['string', 'seo'],
        'meta_description'=> ['string', 'seo'],
    ];

    public function home(): void
    {
        redirect('admin/settings/general');
    }

    public function general(): void
    {
        $values = [];
        foreach (array_keys(self::GENERAL) as $k) {
            $values[$k] = (string) setting($k, '');
        }
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/settings/general', [
            'title'  => __('settings'),
            'input'  => is_array($old) ? array_merge($values, $old) : $values,
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function saveGeneral(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'site_name'        => 'required|max:60',
            'site_name_bn'     => 'max:60',
            'site_tagline_en'  => 'max:160',
            'site_tagline'     => 'max:160',
            'contact_phone'    => 'max:30',
            'contact_email'    => 'email|max:120',
            'contact_address'  => 'max:255',
            'facebook_url'     => 'url|max:255',
            'youtube_url'      => 'url|max:255',
            'instagram_url'    => 'url|max:255',
            'whatsapp_number'  => 'max:20',
            'meta_title'       => 'max:160',
            'meta_description' => 'max:300',
        ]);
        $files = [];
        foreach (['site_logo' => 'logo', 'site_favicon' => 'favicon'] as $key => $field) {
            $f = Upload::files($field);
            if ($f && ($e = Upload::validateImage($f[0], 2 * 1024 * 1024))) {
                $errors[$field] = $e;
            } elseif ($f) {
                $files[$key] = $f[0];
            }
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('admin/settings/general');
        }
        $values = [];
        foreach (self::GENERAL as $k => [$type, $group]) {
            $values[$k] = [trim((string) ($in[$k] ?? '')), $group, $type];
        }
        foreach (['site_logo', 'site_favicon'] as $key) {
            $cur = (string) setting($key, '');
            if (isset($files[$key])) {
                $values[$key] = [Upload::storeImage($files[$key], 'site'), 'general', 'string'];
                Upload::delete($cur);
            } elseif (!empty($in['remove_' . $key])) {
                $values[$key] = ['', 'general', 'string'];
                Upload::delete($cur);
            }
        }
        Setting::saveMany($values);
        flash('success', __('settings_saved'));
        redirect('admin/settings/general');
    }

    /* ---------------- Checkout tab: guest checkout ---------------- */

    public function checkout(): void
    {
        $this->view('admin/settings/checkout', [
            'title'   => __('settings'),
            'input'   => [
                'guest_checkout_enabled' => Guest::enabled(),
                'guest_default_contact'  => Guest::defaultContact(),
                'guest_otp_required'     => Guest::otpRequired(),
            ],
            'smsLive'  => Messenger::smsDriver() !== 'log',
            'mailLive' => Messenger::mailDriver() !== 'log',
            'guestOrders' => (int) Database::getInstance()->fetchColumn("SELECT COUNT(*) FROM orders WHERE is_guest = 1 AND placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        ], 'admin');
    }

    public function saveCheckout(): void
    {
        $in = $this->postData();
        Setting::saveMany([
            'guest_checkout_enabled' => [empty($in['guest_checkout_enabled']) ? '0' : '1', 'checkout', 'bool'],
            'guest_default_contact'  => [($in['guest_default_contact'] ?? '') === 'email' ? 'email' : 'phone', 'checkout', 'string'],
            'guest_otp_required'     => [empty($in['guest_otp_required']) ? '0' : '1', 'checkout', 'bool'],
        ]);
        flash('success', __('settings_saved'));
        redirect('admin/settings/checkout');
    }

    /** key => [type, group] */
    private const FIELDS = [
        'cod_enabled'          => ['bool', 'payment'],
        'max_cod_amount'       => ['decimal', 'order'],
        'bkash_enabled'        => ['bool', 'payment'],
        'bkash_number'         => ['string', 'payment'],
        'bkash_account_type'   => ['string', 'payment'],
        'nagad_enabled'        => ['bool', 'payment'],
        'nagad_number'         => ['string', 'payment'],
        'nagad_account_type'   => ['string', 'payment'],
        'rocket_enabled'       => ['bool', 'payment'],
        'rocket_number'        => ['string', 'payment'],
        'rocket_account_type'  => ['string', 'payment'],
        'min_order_amount'     => ['decimal', 'order'],
        'return_window_days'   => ['int', 'order'],
        'customer_cancel_until' => ['string', 'order'],
        'order_prefix'         => ['string', 'order'],
        'default_commission_rate' => ['decimal', 'vendor'],
        'payout_min_amount'    => ['decimal', 'vendor'],
        'payout_hold_days'     => ['int', 'vendor'],
        'vendor_registration_enabled' => ['bool', 'vendor'],
        'vendor_auto_approve'  => ['bool', 'vendor'],
    ];
    public const ACCOUNT_TYPES = ['personal', 'merchant', 'agent'];

    public function index(): void
    {
        $values = [];
        foreach (self::FIELDS as $k => [$type]) {
            $values[$k] = setting($k, $type === 'bool' ? false : '');
        }
        $values['customer_cancel_until'] = isset(Order::CANCEL_UNTIL[(string) $values['customer_cancel_until']]) ? $values['customer_cancel_until'] : 'confirmed';
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/settings/orders', [
            'title'  => __('settings'),
            'input'  => is_array($old) ? array_merge($values, $old) : $values,
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function update(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'max_cod_amount'     => 'required|decimal',
            'min_order_amount'   => 'required|decimal',
            'return_window_days' => 'required|integer|gte:0|lte:60',
            'order_prefix'       => 'required|max:6',
            'default_commission_rate' => 'required|decimal|lte:100',
            'payout_min_amount'  => 'required|decimal',
            'payout_hold_days'   => 'required|integer|gte:0|lte:90',
            'bkash_number'       => 'phone',
            'nagad_number'       => 'phone',
            'rocket_number'      => 'max:20',
        ]);
        if (!isset(Order::CANCEL_UNTIL[$in['customer_cancel_until'] ?? ''])) {
            $in['customer_cancel_until'] = 'confirmed';
        }
        if (!isset($errors['order_prefix']) && !preg_match('/^[A-Z0-9]{1,6}$/', strtoupper($in['order_prefix'] ?? ''))) {
            $errors['order_prefix'] = __('v_in');
        }
        if (($in['rocket_number'] ?? '') !== '' && !preg_match('/^01[3-9]\d{8}\d?$/', preg_replace('/\D/', '', $in['rocket_number']))) {
            $errors['rocket_number'] = __('v_phone');
        }
        foreach (Order::MANUAL_METHODS as $m) {
            if (!empty($in[$m . '_enabled']) && trim((string) ($in[$m . '_number'] ?? '')) === '' && !isset($errors[$m . '_number'])) {
                $errors[$m . '_number'] = __('pay_number_required');
            }
            if (!in_array($in[$m . '_account_type'] ?? '', self::ACCOUNT_TYPES, true)) {
                $in[$m . '_account_type'] = 'personal';
            }
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('admin/settings/orders');
        }

        $old = [];
        $new = [];
        foreach (self::FIELDS as $k => [$type, $group]) {
            $v = match ($type) {
                'bool'    => !empty($in[$k]) ? '1' : '0',
                'decimal' => number_format((float) $in[$k], 2, '.', ''),
                'int'     => (string) (int) $in[$k],
                default   => trim((string) ($in[$k] ?? '')),
            };
            if (in_array($k, ['bkash_number', 'nagad_number'], true) && $v !== '') {
                $v = Auth::normalizePhone($v);
            }
            if ($k === 'order_prefix') {
                $v = strtoupper($v);
            }
            $cur = $this->db->fetchColumn('SELECT `value` FROM settings WHERE `key` = :k', ['k' => $k]);
            if ($cur === false || $cur === null) {
                $this->db->insert('settings', ['group' => $group, 'key' => $k, 'value' => $v, 'type' => $type, 'label' => $k, 'is_public' => 0]);
            } elseif ((string) $cur !== $v) {
                $this->db->update('settings', ['value' => $v], '`key` = :k', ['k' => $k]);
            } else {
                continue;
            }
            $old[$k] = $cur;
            $new[$k] = $v;
        }
        Helpers::clearSettingsCache();
        if ($new) {
            Audit::log('settings.update', 'settings', null, $old, $new);
        }
        flash('success', __('settings_saved'));
        redirect('admin/settings/orders');
    }
}
