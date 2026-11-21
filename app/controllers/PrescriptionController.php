<?php
declare(strict_types=1);

/**
 * "Order by prescription" (/prescription): a customer - signed in or guest - uploads a prescription (1-5 photos / PDF)
 * with a contact number and address; the pharmacy team calls back and prepares the order.
 * "My prescriptions" (/account/prescriptions): every prescription the customer uploaded (here or at checkout).
 */
class PrescriptionController extends Controller
{
    /** Signed-in customer, or null for a guest. Staff / vendors get the "wrong panel" page. */
    private function customer(): ?array
    {
        if (!Auth::check()) {
            return null;
        }
        Auth::requireGuard('customer');
        return User::find((int) Auth::id());
    }

    public function form(): void
    {
        $user = $this->customer();
        $old = $_SESSION['_old'] ?? [];
        $division = (int) ($old['division_id'] ?? 0);
        $this->view('shop/prescription', [
            'title'     => __('rx_order_title'),
            'metaDescription' => __('rx_order_subtitle'),
            'user'      => $user,
            'enabled'   => Prescription::requestsEnabled(),
            'guestContact' => $user ? null : Guest::contact(),
            'divisions' => Location::divisions(),
            'districts' => $division ? Location::children($division) : [],
            'upazilas'  => !empty($old['district_id']) ? Location::children((int) $old['district_id']) : [],
            'old'       => $old,
            'errors'    => $_SESSION['_errors'] ?? [],
            'maxMb'     => round(Prescription::maxBytes() / 1048576, 1),
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        $user = $this->customer();
        if (!Prescription::requestsEnabled()) {
            redirect('prescription');
        }
        $in = array_map(static fn($v) => is_scalar($v) ? trim((string) $v) : '', $_POST);
        $back = static function (array $errors) use ($in): void {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('prescription');
        };

        // spam guard: 6 requests per IP per hour
        $rateKey = 'rx_request|' . Helpers::clientIp();
        if (RateLimiter::tooMany($rateKey, 6)) {
            flash('error', __('too_many_requests'));
            redirect('prescription');
        }

        $errors = [];
        $name = $user ? (string) $user['name'] : mb_substr($in['name'] ?? '', 0, 150);
        $phoneIn = (string) ($in['phone'] ?? '');
        if ($phoneIn === '' && $user) {
            $phoneIn = (string) $user['phone']; // signed in: their account mobile unless they typed another one
        }
        $phone = Auth::normalizePhone(bn_to_en_digits($phoneIn));
        if ($name === '') {
            $errors['name'] = __('v_required');
        }
        if (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
            $errors['phone'] = __('v_guest_phone');
        }
        $email = strtolower($in['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('v_email');
        }
        $notes = mb_substr($in['notes'] ?? '', 0, 500);
        $address = mb_substr($in['address'] ?? '', 0, 255);
        $districtId = (int) ($in['district_id'] ?? 0);
        $upazilaId = (int) ($in['upazila_id'] ?? 0);
        if ($address === '') {
            $errors['address'] = __('v_required');
        }
        if (($in['division_id'] ?? '') !== '' || $districtId) {
            $errors += Address::validateLocations((int) ($in['division_id'] ?? 0), $districtId ?: null, $upazilaId ?: null);
        }
        if (empty($in['agree'])) {
            $errors['agree'] = __('v_agree');
        }

        $files = array_values(array_filter(Upload::files('files'), static fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
        if (!$files) {
            $errors['files'] = __('rx_req_need_file');
        } elseif (count($files) > Prescription::MAX_FILES) {
            $errors['files'] = __('rx_req_too_many', ['n' => num(Prescription::MAX_FILES)]);
        } else {
            foreach ($files as $f) {
                if ($e = Prescription::validate($f)) {
                    $errors['files'] = $e;
                    break;
                }
            }
        }
        if ($errors) {
            $back($errors);
        }

        RateLimiter::hit($rateKey, 3600);
        $code = Prescription::createRequest($files, $user ? (int) $user['id'] : null, [
            'name'         => $name,
            'phone'        => $phone,
            'email'        => $email,
            'patient_name' => mb_substr($in['patient_name'] ?? '', 0, 150),
            'notes'        => $notes,
            'address'      => $address,
            'district_id'  => $districtId ?: null,
            'upazila_id'   => $upazilaId ?: null,
        ]);
        $sent = $_SESSION['rx_sent'] ?? [];
        $sent[] = $code;
        $_SESSION['rx_sent'] = array_slice($sent, -10);
        redirect('prescription/sent/' . $code);
    }

    /** Thank-you page (only for the browser that sent the request). */
    public function sent(string $code): void
    {
        if (!in_array($code, $_SESSION['rx_sent'] ?? [], true)) {
            $this->notFound();
        }
        $rows = Prescription::group($code);
        if (!$rows) {
            $this->notFound();
        }
        $this->view('shop/prescription_sent', [
            'title' => __('rx_req_sent_title'),
            'code'  => $code,
            'first' => $rows[0],
            'files' => count($rows),
        ]);
    }

    /** My prescriptions. */
    public function mine(): void
    {
        $uid = (int) Auth::id();
        $pager = new Paginator(Prescription::countForUser($uid), 10);
        $rows = Prescription::forUser($uid, $pager->perPage, $pager->offset());
        $groups = [];
        foreach ($rows as $r) {
            if ($r['group_code']) {
                $groups[$r['group_code']] = Prescription::group((string) $r['group_code']);
            }
        }
        $this->view('account/prescriptions', [
            'title'  => __('my_prescriptions'),
            'tab'    => 'prescriptions',
            'rows'   => $rows,
            'groups' => $groups,
            'pager'  => $pager,
        ]);
    }
}
