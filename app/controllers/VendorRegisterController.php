<?php
declare(strict_types=1);

/**
 * Public vendor sign-up (vendor-panel/register) - short form in 3 steps:
 *   1. business type (Pharmacy, Medical equipment, Beauty & personal care, Grocery ... - admin-editable vendor types)
 *   2. shop + login (shop name, owner, mobile, password, pickup address)
 *   3. documents - only the ones the chosen type needs (a drug licence only for medicine sellers)
 * Payout details (bank / bKash ...) are added later in the vendor panel's profile, before the first payout.
 */
class VendorRegisterController extends Controller
{
    /** Number field that goes with a document (documents without one - BSTI - are file only). */
    private const DOC_NUMBER = ['trade_license' => 'trade_license_no', 'nid' => 'nid_no', 'drug_license' => 'drug_license_no', 'tin' => 'tin_no'];

    public function form(): void
    {
        if (!setting('vendor_registration_enabled', true)) {
            flash('warning', __('vendor_reg_closed'));
            redirect('vendor-panel/login');
        }
        $old = $_SESSION['_old'] ?? [];
        $division = (int) ($old['division_id'] ?? 0);
        $district = (int) ($old['district_id'] ?? 0);
        $types = VendorType::active();
        $typeId = (int) ($old['vendor_type_id'] ?? Helpers::str('type'));
        if (!isset($types[$typeId])) {
            $typeId = 0;
        }
        $this->view('vendor/register', [
            'title'     => __('vendor_register'),
            'old'       => $old,
            'errors'    => $_SESSION['_errors'] ?? [],
            'types'     => $types,
            'typeId'    => $typeId,
            'divisions' => Location::divisions(),
            'districts' => $division ? Location::children($division) : [],
            'upazilas'  => $district ? Location::children($district) : [],
        ], 'auth_wide');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        if (!setting('vendor_registration_enabled', true)) {
            redirect('vendor-panel/login');
        }
        $key = 'vendor-reg:' . Helpers::clientIp();
        if (RateLimiter::tooMany($key, 5)) {
            flash('error', __('too_many_requests'));
            redirect('vendor-panel/register');
        }
        $in = $this->postData();
        foreach (['phone', 'nid_no'] as $f) {
            if (isset($in[$f])) {
                $in[$f] = preg_replace('/[\s-]/', '', bn_to_en_digits((string) $in[$f]));
            }
        }
        $types = VendorType::active();
        $type = $types[(int) ($in['vendor_type_id'] ?? 0)] ?? null;

        $errors = $this->validate($in, [
            'shop_name'           => 'required|max:150',
            'contact_person'      => 'required|max:150',
            'phone'               => 'required|phone',
            'email'               => 'email|max:191',
            'password'            => 'required|min:8|max:72',
            'trade_license_no'    => 'max:100',
            'drug_license_no'     => 'max:100',
            'drug_license_expiry' => 'date',
            'tin_no'              => 'max:50',
            'pickup_address'      => 'required|min:5|max:255',
            'division_id'         => 'required|integer',
            'district_id'         => 'required|integer',
            'upazila_id'          => 'integer',
        ]);
        if (!$type) {
            $errors['vendor_type_id'] = __('vr_pick_type');
        }
        if (!isset($errors['phone']) && User::phoneExists((string) $in['phone'])) {
            $errors['phone'] = __('phone_taken');
        }
        if (!isset($errors['email']) && ($in['email'] ?? '') !== '' && User::emailExists((string) $in['email'])) {
            $errors['email'] = __('email_taken');
        }
        if (!array_intersect_key($errors, array_flip(['division_id', 'district_id', 'upazila_id']))) {
            $errors += Address::validateLocations((int) $in['division_id'], (int) $in['district_id'], ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : null);
        }
        if (empty($in['agree'])) {
            $errors['agree'] = __('v_agree');
        }

        // documents: required ones for the chosen type, optional ones when given
        $uploads = [];
        if ($type) {
            $required = VendorType::requiredDocs($type);
            foreach (array_merge($required, VendorType::optionalDocs($type)) as $doc) {
                $isReq = in_array($doc, $required, true);
                $numField = self::DOC_NUMBER[$doc] ?? null;
                if ($numField !== null && $isReq && trim((string) ($in[$numField] ?? '')) === '' && !isset($errors[$numField])) {
                    $errors[$numField] = __('v_required');
                }
                $f = Upload::files($doc . '_file')[0] ?? null;
                if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $f = null;
                }
                if (!$f) {
                    if ($isReq) {
                        $errors[$doc . '_file'] = __('v_required');
                    }
                    continue;
                }
                if ($err = PrivateFile::validate($f)) {
                    $errors[$doc . '_file'] = $err;
                } else {
                    $uploads[$doc] = $f;
                }
            }
            if (in_array('nid', $required, true) && !isset($errors['nid_no']) && !preg_match('/^(\d{10}|\d{13}|\d{17})$/', (string) ($in['nid_no'] ?? ''))) {
                $errors['nid_no'] = __('v_nid');
            }
            if ((int) $type['needs_drug_license'] && !isset($errors['drug_license_expiry'])
                && (($in['drug_license_expiry'] ?? '') === '' || $in['drug_license_expiry'] <= date('Y-m-d'))) {
                $errors['drug_license_expiry'] = __('v_drug_license_expiry');
            }
            // numbers of documents this type does not use are not kept
            foreach (self::DOC_NUMBER as $doc => $numField) {
                if (!in_array($doc, array_merge($required, VendorType::optionalDocs($type)), true)) {
                    $in[$numField] = '';
                }
            }
            if (!(int) $type['needs_drug_license'] && !in_array('drug_license', VendorType::optionalDocs($type), true)) {
                $in['drug_license_expiry'] = '';
            }
        }

        if ($errors) {
            RateLimiter::hit($key, 3600);
            $_SESSION['_errors'] = $errors;
            unset($in['password']);
            Helpers::setOld($in);
            flash('error', __('form_has_errors') . ' ' . __('vendor_reselect_files'));
            redirect('vendor-panel/register');
        }

        $stored = [];
        foreach ($uploads as $doc => $f) {
            $stored[$doc] = PrivateFile::store($f, 'vendor-docs');
        }
        $in['vendor_type_id'] = (int) $type['id'];
        $in['vendor_type'] = (string) $type['slug'];
        $location = ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : (int) $in['district_id'];
        try {
            $vendorId = VendorAccount::register($in, $stored, $location);
        } catch (Throwable $e) {
            foreach ($stored as $s) {
                PrivateFile::delete($s['path']);
            }
            throw $e;
        }
        RateLimiter::hit($key, 3600);
        Auth::login(User::find((int) Database::getInstance()->fetchColumn('SELECT user_id FROM vendors WHERE id = :id', ['id' => $vendorId])));
        $this->notifyStaff($vendorId, $in['shop_name'], VendorType::label($type));
        flash('success', __(setting('vendor_auto_approve', false) ? 'vendor_registered_live' : 'vendor_registered'));
        flash('info', __('vr_add_payout_later'));
        redirect('vendor-panel');
    }

    private function notifyStaff(int $vendorId, string $shop, string $typeLabel): void
    {
        Notification::toStaff(['vendors.approve'], 'vendor', __('notif_new_vendor', ['shop' => $shop]), $typeLabel, url('admin/vendors/' . $vendorId));
    }
}
