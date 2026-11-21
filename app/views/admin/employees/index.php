<?php /** Employees list. Vars: $employees, $paginator, $filters, $roles */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-person-badge"></i> <?= e(__('employees')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (can('employees.export')): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/employees/export') . '?' . http_build_query(array_filter($filters))) ?>"><i class="bi bi-download"></i> <?= e(__('export_csv')) ?></a>
        <?php endif; ?>
        <?php if (can('employees.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/employees/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_employee')) ?></a>
        <?php endif; ?>
    </div>
</div>

<form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <input class="form-control form-control-sm" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('search_staff')) ?>">
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" name="role_id">
                <option value=""><?= e(__('role')) ?>: <?= e(__('all')) ?></option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= $filters['role_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e(Role::label($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value=""><?= e(__('status')) ?>: <?= e(__('all')) ?></option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>><?= e(__('active')) ?></option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>><?= e(__('inactive')) ?></option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-funnel"></i> <?= e(__('filter')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/employees')) ?>" title="<?= e(__('reset')) ?>"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('name')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('phone')) ?></th>
                    <th><?= e(__('role')) ?></th>
                    <th><?= e(__('status')) ?></th>
                    <th class="d-none d-lg-table-cell"><?= e(__('last_login')) ?></th>
                    <th class="text-end"><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$employees): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($employees as $u):
                $isSelf = (int) $u['id'] === Auth::id();
                $isSuper = $u['user_type'] === 'super_admin';
                $manageable = !$isSelf && (!$isSuper || Auth::isSuperAdmin());
                $locked = $u['locked_until'] && strtotime($u['locked_until']) > time(); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($u['name']) ?> <?php if ($isSelf): ?><span class="badge bg-info"><?= e(__('you')) ?></span><?php endif; ?></div>
                        <div class="small text-muted text-break"><?= e($u['email']) ?></div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= e($u['phone'] ?? '—') ?></td>
                    <td>
                        <?php if ($isSuper): ?>
                            <span class="badge bg-success"><?= e(__('super_admin')) ?></span>
                        <?php else: ?>
                            <?= e(tr($u['role_name_bn'], $u['role_name'] ?? '—')) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $u['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= e(__($u['status'])) ?></span>
                        <?php if ($locked): ?><span class="badge bg-warning text-dark"><i class="bi bi-lock"></i> <?= e(__('locked')) ?></span><?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell small"><?= $u['last_login_at'] ? e(dt($u['last_login_at'])) : '<span class="text-muted">' . e(__('never')) . '</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($isSelf): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/profile')) ?>"><i class="bi bi-person-gear"></i></a>
                        <?php elseif ($manageable && can('employees.edit')): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/employees/' . $u['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="<?= e(url('admin/employees/' . $u['id'] . '/toggle')) ?>" class="d-inline" data-confirm="<?= e(__('are_you_sure')) ?>">
                                <?= csrf_field() ?>
                                <?php if ($u['status'] === 'active'): ?>
                                    <button class="btn btn-sm btn-outline-danger" title="<?= e(__('disable')) ?>"><i class="bi bi-person-slash"></i></button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success" title="<?= e(__('enable')) ?>"><i class="bi bi-person-check"></i></button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted"><?= e(__('showing', ['from' => num($paginator->from()), 'to' => num($paginator->to()), 'total' => num($paginator->total)])) ?></span>
        <?= $paginator->links() ?>
    </div>
</div>
