<?php
declare(strict_types=1);

/** Customer address book (create, edit, delete, set default). */
class AddressController extends Controller
{
    public function index(): void
    {
        $this->view('account/addresses', [
            'title'     => __('my_addresses'),
            'tab'       => 'addresses',
            'addresses' => Address::forUser((int) Auth::id()),
        ]);
    }

    public function create(): void
    {
        $uid = (int) Auth::id();
        if (Address::countForUser($uid) >= Address::MAX_PER_USER) {
            flash('warning', __('addr_limit', ['n' => Address::MAX_PER_USER]));
            redirect('account/addresses');
        }
        $user = Auth::user();
        $this->form(null, ['label' => 'home', 'recipient_name' => $user['name'], 'phone' => $user['phone']]);
    }

    public function edit(string $id): void
    {
        $a = Address::findForUser((int) $id, (int) Auth::id());
        if (!$a) {
            $this->notFound();
        }
        $this->form($a, $a);
    }

    private function form(?array $address, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $input = is_array($old) ? $old : $defaults;
        $division = (int) ($input['division_id'] ?? 0);
        $district = (int) ($input['district_id'] ?? 0);
        $this->view('account/address_form', [
            'title'     => __($address ? 'addr_edit' : 'addr_add'),
            'tab'       => 'addresses',
            'address'   => $address,
            'input'     => $input,
            'errors'    => $_SESSION['_errors'] ?? [],
            'divisions' => Location::divisions(),
            'districts' => $division ? Location::children($division) : [],
            'upazilas'  => $district ? Location::children($district) : [],
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        $uid = (int) Auth::id();
        if (Address::countForUser($uid) >= Address::MAX_PER_USER) {
            flash('warning', __('addr_limit', ['n' => Address::MAX_PER_USER]));
            redirect('account/addresses');
        }
        [$data, $default] = $this->validated('account/addresses/create');
        Address::save($uid, null, $data, $default);
        flash('success', __('addr_saved'));
        redirect($this->returnTo());
    }

    public function update(string $id): void
    {
        $uid = (int) Auth::id();
        if (!Address::findForUser((int) $id, $uid)) {
            $this->notFound();
        }
        [$data, $default] = $this->validated('account/addresses/' . (int) $id . '/edit');
        Address::save($uid, (int) $id, $data, $default);
        flash('success', __('addr_saved'));
        redirect('account/addresses');
    }

    public function destroy(string $id): void
    {
        if (!Address::remove((int) $id, (int) Auth::id())) {
            $this->notFound();
        }
        flash('success', __('addr_deleted'));
        redirect('account/addresses');
    }

    public function makeDefault(string $id): void
    {
        if (!Address::setDefault((int) $id, (int) Auth::id())) {
            $this->notFound();
        }
        flash('success', __('addr_default_set'));
        redirect('account/addresses');
    }

    /** Validate POST; on error go back to $formPath with messages. @return array{0: array, 1: bool} */
    private function validated(string $formPath): array
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'label'          => 'required|in:' . implode(',', Address::LABELS),
            'recipient_name' => 'required|max:150',
            'phone'          => 'required|phone',
            'alt_phone'      => 'phone',
            'division_id'    => 'required|integer',
            'district_id'    => 'required|integer',
            'upazila_id'     => 'integer',
            'address_line'   => 'required|min:5|max:255',
            'postcode'       => 'max:10',
        ]);
        if (!isset($errors['postcode']) && ($in['postcode'] ?? '') !== '' && !preg_match('/^\d{4}$/', $in['postcode'])) {
            $errors['postcode'] = __('v_postcode');
        }
        if (!isset($errors['division_id']) && !isset($errors['district_id']) && !isset($errors['upazila_id'])) {
            $errors += Address::validateLocations(
                (int) $in['division_id'],
                (int) $in['district_id'],
                ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : null
            );
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        $alt = ($in['alt_phone'] ?? '') !== '' ? Auth::normalizePhone($in['alt_phone']) : null;
        return [[
            'label'          => $in['label'],
            'recipient_name' => $in['recipient_name'],
            'phone'          => Auth::normalizePhone($in['phone']),
            'alt_phone'      => $alt,
            'division_id'    => (int) $in['division_id'],
            'district_id'    => (int) $in['district_id'],
            'upazila_id'     => ($in['upazila_id'] ?? '') !== '' ? (int) $in['upazila_id'] : null,
            'address_line'   => $in['address_line'],
            'postcode'       => ($in['postcode'] ?? '') !== '' ? $in['postcode'] : null,
        ], !empty($in['is_default'])];
    }

    /** After adding an address, return to a same-site page given in ?return= (e.g. checkout later). */
    private function returnTo(): string
    {
        $r = Helpers::str('return');
        return (str_starts_with($r, '/') && !str_starts_with($r, '//') && !str_contains($r, '\\')) ? $r : 'account/addresses';
    }
}
