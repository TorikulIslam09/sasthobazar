<?php
declare(strict_types=1);

/** Vendor: wallet balance, ledger, payout requests; shop profile & payout details; own documents. */
class VendorWalletController extends VendorBaseController
{
    public function index(): void
    {
        $v = $this->activeVendor();
        $vid = (int) $v['id'];
        Wallet::releaseDue($vid);
        $pager = new Paginator(Wallet::ledgerCount($vid), 25);
        $this->view('vendor/payouts', [
            'title'    => __('wallet_payouts'),
            'vendor'   => $v,
            'wallet'   => Wallet::forVendor($vid),
            'ledger'   => Wallet::ledger($vid, $pager->perPage, $pager->offset()),
            'pager'    => $pager,
            'payouts'  => $this->db->fetchAll('SELECT * FROM vendor_payouts WHERE vendor_id = :v ORDER BY id DESC LIMIT 50', ['v' => $vid]),
            'nextRelease' => $this->db->fetch(
                "SELECT MIN(release_at) AS at, COALESCE(SUM(vendor_earning), 0) AS amount FROM order_vendor_splits WHERE vendor_id = :v AND settlement_status = 'pending'",
                ['v' => $vid]
            ),
            'errors'   => $_SESSION['_errors'] ?? [],
        ], 'vendor');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function request(): void
    {
        $v = $this->activeVendor();
        $this->requireApproved($v);
        $in = $this->postData();
        $errors = $this->validate($in, ['amount' => 'required|decimal|gte:1', 'method' => 'required|in:' . implode(',', Payout::METHODS), 'note' => 'max:255']);
        if (!$errors) {
            try {
                Payout::request($v, (float) $in['amount'], $in['method'], ($in['note'] ?? '') ?: null);
                flash('success', __('payout_requested'));
                redirect('vendor-panel/payouts');
            } catch (OrderException $e) {
                $errors[$e->field !== '' ? $e->field : 'amount'] = $e->getMessage();
            }
        }
        $_SESSION['_errors'] = $errors;
        Helpers::setOld($in);
        flash('error', __('form_has_errors'));
        redirect('vendor-panel/payouts');
    }

    public function profile(): void
    {
        $v = $this->vendor();
        $old = $_SESSION['_old'] ?? null;
        $chain = VendorAccount::locationChain($v['location_id'] !== null ? (int) $v['location_id'] : null);
        $input = is_array($old) ? $old : $v + [
            'division_id' => $chain['division']['id'] ?? '', 'district_id' => $chain['district']['id'] ?? '', 'upazila_id' => $chain['upazila']['id'] ?? '',
        ];
        $this->view('vendor/profile', [
            'title'     => __('shop_profile'),
            'vendor'    => $v,
            'input'     => $input,
            'errors'    => $_SESSION['_errors'] ?? [],
            'divisions' => Location::divisions(),
            'districts' => !empty($input['division_id']) ? Location::children((int) $input['division_id']) : [],
            'upazilas'  => !empty($input['district_id']) ? Location::children((int) $input['district_id']) : [],
            'docs'      => VendorAccount::docs($v),
            'area'      => VendorAccount::areaText($v['location_id'] !== null ? (int) $v['location_id'] : null),
        ], 'vendor');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    /** Vendors may update pickup address and payout details; licences / NID are changed by staff only. */
    public function update(): void
    {
        $v = $this->vendor();
        $in = $this->postData();
        $errors = $this->validate($in, [
            'description'             => 'max:1000',
            'pickup_address'          => 'required|min:5|max:255',
            'pickup_phone'            => 'phone',
            'division_id'             => 'required|integer',
            'district_id'             => 'required|integer',
            'upazila_id'              => 'integer',
            'bank_name'               => 'max:100',
            'bank_branch'             => 'max:100',
            'bank_account_name'       => 'max:150',
            'bank_account_no'         => 'max:50',
            'bank_routing_no'         => 'max:20',
            'mobile_banking_provider' => 'in:' . implode(',', VendorAccount::MFS),
            'mobile_banking_no'       => 'phone',
        ]);
        if (($in['bank_account_no'] ?? '') === '' && (($in['mobile_banking_provider'] ?? '') === '' || ($in['mobile_banking_no'] ?? '') === '')) {
            $errors['bank_account_no'] = __('v_payout_details');
        }
        if (!array_intersect_key($errors, array_flip(['division_id', 'district_id', 'upazila_id']))) {
            $errors += Address::validateLocations((int) $in['division_id'], (int) $in['district_id'], ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : null);
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('vendor-panel/profile');
        }
        $nul = static fn(string $k) => ($in[$k] ?? '') !== '' ? $in[$k] : null;
        Vendor::updateById((int) $v['id'], [
            'description'             => $nul('description'),
            'address'                 => $in['pickup_address'],
            'pickup_phone'            => $nul('pickup_phone') ? Auth::normalizePhone($in['pickup_phone']) : null,
            'location_id'             => ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : (int) $in['district_id'],
            'bank_name'               => $nul('bank_name'),
            'bank_branch'             => $nul('bank_branch'),
            'bank_account_name'       => $nul('bank_account_name'),
            'bank_account_no'         => $nul('bank_account_no'),
            'bank_routing_no'         => $nul('bank_routing_no'),
            'mobile_banking_provider' => $nul('mobile_banking_provider'),
            'mobile_banking_no'       => $nul('mobile_banking_no') ? Auth::normalizePhone($in['mobile_banking_no']) : null,
        ]);
        flash('success', __('profile_saved'));
        redirect('vendor-panel/profile');
    }

    public function document(string $doc): void
    {
        $v = $this->vendor();
        $file = VendorAccount::docs($v)[$doc] ?? null;
        if (!$file || !in_array($doc, VendorAccount::DOCS, true)) {
            $this->notFound();
        }
        PrivateFile::stream($file['path'], $doc);
    }
}
