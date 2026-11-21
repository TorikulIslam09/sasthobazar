<?php
declare(strict_types=1);

/**
 * Roles & permission matrix.
 *
 * Privilege-escalation rules for non-Super-Admin users with roles.* permissions:
 *  - they cannot edit their own role
 *  - they can only grant/revoke permissions they themselves hold (others are kept unchanged)
 * The Super Admin role always has every permission and cannot be edited or deleted.
 */
class AdminRoleController extends Controller
{
    public function index(): void
    {
        $this->view('admin/roles/index', [
            'title' => __('roles'),
            'roles' => Role::staffRoles(),
        ], 'admin');
    }

    public function create(): void
    {
        $this->renderForm(null, ['name' => '', 'name_bn' => '', 'description' => ''], [], []);
    }

    public function store(): void
    {
        $in = $this->postData(['permissions']);
        $errors = $this->validateRole($in, 0);
        $selected = $this->allowedSelection($in['permissions'] ?? [], []);
        if ($errors) {
            $this->renderForm(null, $in, $selected, $errors);
            return;
        }

        $id = $this->db->transaction(function () use ($in, $selected) {
            $id = Role::create([
                'name'        => $in['name'],
                'name_bn'     => ($in['name_bn'] ?? '') ?: null,
                'slug'        => Role::uniqueSlug($in['name']),
                'description' => ($in['description'] ?? '') ?: null,
                'is_system'   => 0,
            ]);
            Role::syncPermissions($id, $selected);
            return $id;
        });

        flash('success', __('role_saved'));
        redirect('admin/roles/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $role = $this->findRole((int) $id);
        $this->renderForm($role, $role, Role::permissionIds((int) $role['id']), []);
    }

    public function update(string $id): void
    {
        $role = $this->findRole((int) $id);
        if (!$this->isEditable($role)) {
            flash('error', __('role_not_editable'));
            redirect('admin/roles/' . $role['id'] . '/edit');
        }

        $in = $this->postData(['permissions']);
        $current = Role::permissionIds((int) $role['id']);
        $selected = $this->allowedSelection($in['permissions'] ?? [], $current);
        $errors = $this->validateRole($in, (int) $role['id']);
        if ($errors) {
            $this->renderForm($role, array_merge($role, $in), $selected, $errors);
            return;
        }

        $this->db->transaction(function () use ($role, $in, $selected) {
            Role::updateById((int) $role['id'], [
                'name'        => $in['name'],
                'name_bn'     => ($in['name_bn'] ?? '') ?: null,
                'description' => ($in['description'] ?? '') ?: null,
            ]);
            Role::syncPermissions((int) $role['id'], $selected);
        });

        flash('success', __('role_saved'));
        redirect('admin/roles/' . $role['id'] . '/edit');
    }

    public function destroy(string $id): void
    {
        $role = $this->findRole((int) $id);
        if ((int) $role['is_system'] === 1) {
            flash('error', __('role_system_delete'));
            redirect('admin/roles');
        }
        if (Role::usersCount((int) $role['id']) > 0) {
            flash('error', __('role_has_users'));
            redirect('admin/roles');
        }
        if ($role['slug'] === Role::SUPER_ADMIN
            || (!Auth::isSuperAdmin() && (int) $role['id'] === (int) (Auth::user()['role_id'] ?? 0))) {
            Auth::forbidden();
        }
        // keep a record of what the role could do before it disappears
        Audit::log('roles.permissions', 'roles', (int) $role['id'], ['removed' => Role::permissionKeys((int) $role['id'])], null);
        Role::deleteById((int) $role['id']);
        flash('success', __('role_deleted'));
        redirect('admin/roles');
    }

    /** Super Admin only: add permissions for modules newly added to config/modules.php */
    public function sync(): void
    {
        $r = Permission::sync($this->db->pdo(), false);
        Audit::log('permissions.sync', 'permissions', null, null, ['added' => $r['added'], 'obsolete' => $r['obsolete']]);
        flash('success', __('perm_synced', ['n' => num($r['added'])]));
        if ($r['obsolete']) {
            flash('warning', __('perm_obsolete') . ' ' . implode(', ', $r['obsolete']));
        }
        redirect('admin/roles');
    }

    /* ---------------- helpers ---------------- */

    private function findRole(int $id): array
    {
        $role = Role::find($id);
        if (!$role || in_array($role['slug'], Role::NON_STAFF, true)) {
            $this->notFound();
        }
        return $role;
    }

    /** Can the current user change this role's name/permissions? */
    private function isEditable(array $role): bool
    {
        if ($role['slug'] === Role::SUPER_ADMIN) {
            return false;
        }
        if (!Auth::isSuperAdmin() && (int) $role['id'] === (int) (Auth::user()['role_id'] ?? 0)) {
            return false; // no editing your own role
        }
        return can('roles.edit');
    }

    /**
     * Permission ids that will be saved.
     * Non-super-admins: (submitted ∩ mine) ∪ (current − mine) - they can't touch permissions they don't hold.
     */
    private function allowedSelection(array $submitted, array $current): array
    {
        $valid = Permission::idKeyMap();
        $submitted = array_values(array_filter(array_map('intval', $submitted), static fn($id) => isset($valid[$id])));
        if (Auth::isSuperAdmin()) {
            return $submitted;
        }
        $mine = array_keys(array_intersect($valid, Auth::permissions()));
        return array_values(array_unique(array_merge(
            array_intersect($submitted, $mine),
            array_diff($current, $mine)
        )));
    }

    private function validateRole(array $in, int $id): array
    {
        $errors = $this->validate($in, [
            'name'        => 'required|max:100',
            'name_bn'     => 'max:100',
            'description' => 'max:255',
        ]);
        if (!isset($errors['name'])) {
            $dupe = $this->db->fetchColumn('SELECT 1 FROM roles WHERE name = :n AND id <> :id', ['n' => $in['name'], 'id' => $id]);
            if ($dupe) {
                $errors['name'] = __('role_name_taken');
            }
        }
        return $errors;
    }

    private function renderForm(?array $role, array $input, array $selected, array $errors): void
    {
        $isSuper = $role && $role['slug'] === Role::SUPER_ADMIN;
        $editable = $role ? $this->isEditable($role) : can('roles.create');
        $this->view('admin/roles/form', [
            'title'     => $role ? __('edit_role') : __('create_role'),
            'role'      => $role,
            'input'     => $input,
            'selected'  => $isSuper ? array_keys(Permission::idKeyMap()) : $selected,
            'errors'    => $errors,
            'matrix'    => Permission::matrix(),
            'standard'  => config('modules.standard_actions'),
            'actions'   => config('modules.actions'),
            'editable'  => $editable,
            'isSuper'   => $isSuper,
            'myPermIds' => Auth::isSuperAdmin() ? null : array_keys(array_intersect(Permission::idKeyMap(), Auth::permissions())),
            'usersCount'=> $role ? Role::usersCount((int) $role['id']) : 0,
        ], 'admin');
    }
}
