<?php
declare(strict_types=1);

/**
 * Admin: customers - registered accounts and guest buyers.
 * List / search / filter / sort, customer page (orders, addresses, prescriptions, tickets), add a customer,
 * block / unblock (logs them out at once), set a new password, delete (soft; orders are kept), CSV export.
 * Permissions: customers.view / create / edit / delete / export.
 */
class AdminCustomerController extends Controller
{
    private function filters(): array
    {
        $date = static fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', Helpers::str($k)) ? Helpers::str($k) : '';
        return [
            'q'      => mb_substr(Helpers::str('q'), 0, 100),
            'status' => in_array(Helpers::str('status'), Customer::STATUSES, true) ? Helpers::str('status') : '',
            'buyers' => in_array(Helpers::str('buyers'), ['0', '1'], true) ? Helpers::str('buyers') : '',
            'sort'   => in_array(Helpers::str('sort'), Customer::SORTS, true) ? Helpers::str('sort') : 'newest',
            'from'   => $date('from'),
            'to'     => $date('to'),
        ];
    }

    public function index(): void
    {
        $tab = Helpers::str('tab') === 'guests' ? 'guests' : 'registered';
        $f = $this->filters();
        $pager = new Paginator($tab === 'guests' ? Customer::countGuests($f['q']) : Customer::count($f), 25);
        $this->view('admin/customers/index', [
            'title'   => __('customers'),
            'tab'     => $tab,
            'f'       => $f,
            'rows'    => $tab === 'guests' ? Customer::guests($f['q'], $pager->perPage, $pager->offset()) : Customer::list($f, $pager->perPage, $pager->offset()),
            'pager'   => $pager,
            'summary' => Customer::summary(),
        ], 'admin');
    }

    public function export(): void
    {
        $f = $this->filters();
        $rows = Customer::list($f, 50000, 0);
        Audit::log('customers.export', 'users', null, null, ['filters' => $f, 'rows' => count($rows)]);
        Helpers::csvDownload('customers-' . date('Ymd-His') . '.csv',
            ['ID', 'Name', 'Mobile', 'Email', 'Status', 'Orders', 'Spent (BDT)', 'Last order', 'Joined', 'Last login'],
            array_map(static fn($r) => [$r['id'], $r['name'], $r['phone'], $r['email'], $r['status'], $r['orders'],
                number_format((float) $r['spent'], 2, '.', ''), $r['last_order'], $r['created_at'], $r['last_login_at']], $rows)
        );
    }

    public function show(string $id): void
    {
        $u = Customer::find((int) $id);
        if (!$u) {
            $this->notFound();
        }
        $this->view('admin/customers/show', [
            'title'  => $u['name'],
            'u'      => $u,
            'd'      => Customer::details((int) $u['id']),
            'open'   => Customer::openOrders((int) $u['id']),
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
    }

    public function create(): void
    {
        $this->view('admin/customers/form', [
            'title'  => __('cust_add'),
            'old'    => $_SESSION['_old'] ?? [],
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        $in = $this->postData();
        $in['phone'] = Auth::normalizePhone(bn_to_en_digits((string) ($in['phone'] ?? '')));
        $errors = $this->validate($in, [
            'name'     => 'required|max:150',
            'phone'    => 'required|phone',
            'email'    => 'email|max:191',
            'password' => 'required|min:8|max:72',
        ]);
        if (!isset($errors['phone']) && User::phoneExists($in['phone'])) {
            $errors['phone'] = __('phone_taken');
        }
        if (!isset($errors['email']) && ($in['email'] ?? '') !== '' && User::emailExists((string) $in['email'])) {
            $errors['email'] = __('email_taken');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            unset($in['password']);
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('admin/customers/create');
        }
        $id = User::createCustomer($in['name'], $in['phone'], ($in['email'] ?? '') ?: null, $in['password']);
        Audit::log('customers.create', 'users', $id, null, ['name' => $in['name'], 'phone' => $in['phone']]);
        $linked = Guest::linkOrders(User::find($id)); // their verified guest orders, if any
        flash('success', __('cust_created') . ($linked ? ' ' . __('guest_orders_linked', ['n' => num($linked)]) : ''));
        redirect('admin/customers/' . $id);
    }

    /** Block / unblock: active | inactive | banned (+ reason). A blocked customer is logged out on the next click. */
    public function status(string $id): void
    {
        $u = Customer::find((int) $id);
        if (!$u) {
            $this->notFound();
        }
        $status = Helpers::str('status');
        $reason = mb_substr(trim(Helpers::str('reason')), 0, 255);
        if (!in_array($status, Customer::STATUSES, true)) {
            flash('error', __('v_in'));
            redirect('admin/customers/' . (int) $u['id']);
        }
        if ($status !== 'active' && $reason === '') {
            flash('error', __('vendor_reason_required'));
            redirect('admin/customers/' . (int) $u['id']);
        }
        User::updateById((int) $u['id'], ['status' => $status, 'remember_token' => null]);
        Audit::log('customers.status', 'users', (int) $u['id'], ['status' => $u['status']], ['status' => $status, 'reason' => $reason ?: null]);
        flash('success', __('cust_status_saved', ['status' => __('cst_' . $status)]));
        redirect('admin/customers/' . (int) $u['id']);
    }

    /** Set a new password for the customer (e.g. they lost access to their mobile). */
    public function password(string $id): void
    {
        $u = Customer::find((int) $id);
        if (!$u) {
            $this->notFound();
        }
        $pw = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (mb_strlen($pw) < 8 || strlen($pw) > 72) {
            $_SESSION['_errors'] = ['password' => __('v_password_len')];
            redirect('admin/customers/' . (int) $u['id'] . '#password');
        }
        User::updateById((int) $u['id'], ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'remember_token' => null, 'failed_logins' => 0, 'locked_until' => null]);
        Audit::log('customers.password', 'users', (int) $u['id']); // the password itself is never logged
        flash('success', __('cust_password_saved'));
        redirect('admin/customers/' . (int) $u['id']);
    }

    /** Soft delete: the account is closed, its orders stay (with the customer's name / phone on them). */
    public function destroy(string $id): void
    {
        $u = Customer::find((int) $id);
        if (!$u) {
            $this->notFound();
        }
        if ($n = Customer::openOrders((int) $u['id'])) {
            flash('error', __('cust_delete_open', ['n' => num($n)]));
            redirect('admin/customers/' . (int) $u['id']);
        }
        if (Helpers::str('confirm_phone') !== (string) $u['phone']) {
            flash('error', __('cust_delete_confirm_wrong'));
            redirect('admin/customers/' . (int) $u['id'] . '#delete');
        }
        $db = Database::getInstance();
        $db->transaction(static function (Database $db) use ($u): void {
            // free the mobile / email so the person can sign up again later; keep the old values in the audit log
            $db->update('users', [
                'deleted_at' => date('Y-m-d H:i:s'), 'status' => 'inactive', 'remember_token' => null,
                'phone' => null, 'email' => null,
            ], 'id = :id', ['id' => (int) $u['id']]);
            $db->query('DELETE FROM carts WHERE user_id = :u', ['u' => (int) $u['id']]);
            Audit::log('customers.delete', 'users', (int) $u['id'], ['name' => $u['name'], 'phone' => $u['phone'], 'email' => $u['email']], ['deleted' => true]);
        });
        flash('success', __('cust_deleted', ['name' => $u['name']]));
        redirect('admin/customers');
    }
}
