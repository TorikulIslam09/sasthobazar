<?php
declare(strict_types=1);

/**
 * Employees (staff accounts): list, add, edit, enable/disable, assign role, CSV export.
 *
 * Safety rules:
 *  - you cannot edit/disable yourself here (use "My profile")
 *  - only a Super Admin can edit/disable a Super Admin; the last active Super Admin can't be disabled
 *  - a non-Super-Admin can only manage employees / assign roles whose permissions they also hold
 *  - disabling a user or changing their password logs them out on their next request
 */
class AdminEmployeeController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters();
        $p = new Paginator(User::countStaff($filters), 20);
        $this->view('admin/employees/index', [
            'title'     => __('employees'),
            'employees' => User::staff($filters, $p->perPage, $p->offset()),
            'paginator' => $p,
            'filters'   => $filters,
            'roles'     => Role::assignable(),
        ], 'admin');
    }

    public function export(): void
    {
        $filters = $this->filters();
        $rows = User::staff($filters, 10000, 0);
        Audit::log('employees.export', 'users', null, null, ['filters' => $filters, 'rows' => count($rows)]);
        Helpers::csvDownload('employees-' . date('Ymd-His') . '.csv',
            ['ID', 'Name', 'Email', 'Phone', 'Type', 'Role', 'Status', 'Last login', 'Created'],
            array_map(static fn($u) => [
                $u['id'], $u['name'], $u['email'], $u['phone'], $u['user_type'],
                $u['role_name'], $u['status'], $u['last_login_at'], $u['created_at'],
            ], $rows)
        );
    }

    public function create(): void
    {
        $this->renderForm(null, ['status' => 'active', 'lang' => 'bn'], []);
    }

    public function store(): void
    {
        $in = $this->postData();
        $errors = $this->validateEmployee($in, null);
        if ($errors) {
            $this->renderForm(null, $in, $errors);
            return;
        }
        try {
            $id = User::create([
                'role_id'       => (int) $in['role_id'],
                'user_type'     => 'employee',
                'name'          => $in['name'],
                'email'         => strtolower($in['email']),
                'phone'         => ($in['phone'] ?? '') !== '' ? Auth::normalizePhone($in['phone']) : null,
                'password_hash' => password_hash($in['password'], PASSWORD_DEFAULT),
                'lang'          => in_array($in['lang'] ?? '', ['bn', 'en'], true) ? $in['lang'] : 'bn',
                'status'        => $in['status'],
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // unique email/phone race
                $this->renderForm(null, $in, ['email' => __('email_taken')]);
                return;
            }
            throw $e;
        }
        flash('success', __('employee_saved'));
        redirect('admin/employees');
    }

    public function edit(string $id): void
    {
        $user = $this->findManageable((int) $id);
        $this->renderForm($user, $user, []);
    }

    public function update(string $id): void
    {
        $user = $this->findManageable((int) $id);
        $in = $this->postData();
        $errors = $this->validateEmployee($in, $user);
        if ($errors) {
            $this->renderForm($user, array_merge($user, $in), $errors);
            return;
        }

        $data = [
            'name'  => $in['name'],
            'email' => strtolower($in['email']),
            'phone' => ($in['phone'] ?? '') !== '' ? Auth::normalizePhone($in['phone']) : null,
            'lang'  => in_array($in['lang'] ?? '', ['bn', 'en'], true) ? $in['lang'] : $user['lang'],
        ];
        if ($user['user_type'] === 'employee') {
            $data['role_id'] = (int) $in['role_id'];
        }
        if (($in['status'] ?? '') !== $user['status']) {
            if ($in['status'] === 'inactive' && !$this->canDisable($user)) {
                $this->renderForm($user, array_merge($user, $in), ['status' => __('last_super_admin')]);
                return;
            }
            $data['status'] = $in['status'];
        }
        if (($in['password'] ?? '') !== '') {
            $data['password_hash'] = password_hash($in['password'], PASSWORD_DEFAULT);
            $data['failed_logins'] = 0;
            $data['locked_until'] = null;
        }

        try {
            User::updateById((int) $user['id'], $data);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->renderForm($user, array_merge($user, $in), ['email' => __('email_taken')]);
                return;
            }
            throw $e;
        }
        flash('success', __('employee_saved'));
        redirect('admin/employees');
    }

    /** Enable / disable. Enabling also clears a login lockout. */
    public function toggle(string $id): void
    {
        $user = $this->findManageable((int) $id);
        if ($user['status'] === 'active') {
            if (!$this->canDisable($user)) {
                flash('error', __('last_super_admin'));
                redirect('admin/employees');
            }
            User::updateById((int) $user['id'], ['status' => 'inactive']);
            flash('success', __('employee_disabled', ['name' => $user['name']]));
        } else {
            User::updateById((int) $user['id'], ['status' => 'active', 'failed_logins' => 0, 'locked_until' => null]);
            flash('success', __('employee_enabled', ['name' => $user['name']]));
        }
        back();
    }

    /* ---------------- helpers ---------------- */

    private function filters(): array
    {
        return [
            'q'       => mb_substr(Helpers::str('q'), 0, 100),
            'role_id' => ctype_digit(Helpers::str('role_id')) ? (int) Helpers::str('role_id') : 0,
            'status'  => Helpers::str('status'),
        ];
    }

    /** Load a staff user the current user is allowed to manage, or 404/403/redirect. */
    private function findManageable(int $id): array
    {
        $user = User::findStaff($id);
        if (!$user) {
            $this->notFound();
        }
        if ((int) $user['id'] === Auth::id()) {
            flash('info', __('use_profile_for_self'));
            redirect('admin/profile');
        }
        if ($user['user_type'] === 'super_admin' && !Auth::isSuperAdmin()) {
            Auth::forbidden();
        }
        if ($user['user_type'] === 'employee' && $user['role_id'] && !$this->roleWithinMine((int) $user['role_id'])) {
            Auth::forbidden();
        }
        return $user;
    }

    /** True when every permission of $roleId is also held by the current user. */
    private function roleWithinMine(int $roleId): bool
    {
        if (Auth::isSuperAdmin()) {
            return true;
        }
        return !array_diff(Role::permissionKeys($roleId), Auth::permissions());
    }

    private function canDisable(array $user): bool
    {
        return !($user['user_type'] === 'super_admin' && $user['status'] === 'active' && User::activeSuperAdminCount() <= 1);
    }

    private function validateEmployee(array $in, ?array $user): array
    {
        $isNew = $user === null;
        $rules = [
            'name'     => 'required|max:150',
            'email'    => 'required|email|max:191',
            'phone'    => 'phone',
            'password' => ($isNew ? 'required|' : '') . 'min:8|max:72',
            'status'   => 'required|in:active,inactive',
        ];
        $errors = $this->validate($in, $rules);

        if (($in['password'] ?? '') !== '' && ($in['password'] !== ($in['password_confirmation'] ?? null))) {
            $errors['password'] ??= __('v_confirmed');
        }
        $exceptId = (int) ($user['id'] ?? 0);
        if (!isset($errors['email']) && User::emailExists($in['email'], $exceptId)) {
            $errors['email'] = __('email_taken');
        }
        if (!isset($errors['phone']) && ($in['phone'] ?? '') !== '' && User::phoneExists($in['phone'], $exceptId)) {
            $errors['phone'] = __('phone_taken');
        }

        if ($isNew || $user['user_type'] === 'employee') {
            $roleId = (int) ($in['role_id'] ?? 0);
            if (!$roleId || !Role::isAssignable($roleId)) {
                $errors['role_id'] = __('v_required');
            } elseif (!$this->roleWithinMine($roleId)) {
                $errors['role_id'] = __('role_exceeds_yours');
            }
        }
        return $errors;
    }

    private function renderForm(?array $user, array $input, array $errors): void
    {
        $this->view('admin/employees/form', [
            'title'  => $user ? __('edit_employee') : __('add_employee'),
            'user'   => $user,
            'input'  => $input,
            'errors' => $errors,
            'roles'  => Role::assignable(),
        ], 'admin');
    }
}
