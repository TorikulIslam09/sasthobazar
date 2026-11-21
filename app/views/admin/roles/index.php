<?php /** Roles list. Vars: $roles */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-shield-lock"></i> <?= e(__('roles')) ?></h1>
    <div class="d-flex gap-2">
        <?php if (Auth::isSuperAdmin()): ?>
            <form method="post" action="<?= e(url('admin/roles/sync')) ?>"><?= csrf_field() ?>
                <button class="btn btn-outline-secondary btn-sm" title="config/modules.php → permissions"><i class="bi bi-arrow-repeat"></i> <?= e(__('sync_permissions')) ?></button>
            </form>
        <?php endif; ?>
        <?php if (can('roles.create')): ?>
            <a class="btn btn-brand btn-sm" href="<?= e(url('admin/roles/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('create_role')) ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= e(__('role_name')) ?></th>
                    <th class="d-none d-md-table-cell"><?= e(__('description')) ?></th>
                    <th class="text-center"><?= e(__('users')) ?></th>
                    <th class="text-center"><?= e(__('permissions')) ?></th>
                    <th class="text-end"><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($roles as $r):
                $isSuper = $r['slug'] === Role::SUPER_ADMIN;
                $isOwn = !Auth::isSuperAdmin() && (int) $r['id'] === (int) (Auth::user()['role_id'] ?? 0); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e(Role::label($r)) ?></div>
                        <div class="small text-muted"><?= e($r['slug']) ?>
                            <?php if ((int) $r['is_system'] === 1): ?><span class="badge bg-secondary ms-1"><?= e(__('system_role')) ?></span><?php endif; ?>
                            <?php if ($isOwn): ?><span class="badge bg-info ms-1"><?= e(__('you')) ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small text-muted"><?= e($r['description']) ?></td>
                    <td class="text-center"><?= e(num($r['users_count'])) ?></td>
                    <td class="text-center"><?= $isSuper ? '<span class="badge bg-success">' . e(__('all_permissions')) . '</span>' : e(num($r['permissions_count'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/roles/' . $r['id'] . '/edit')) ?>">
                            <i class="bi <?= (can('roles.edit') && !$isSuper && !$isOwn) ? 'bi-pencil' : 'bi-eye' ?>"></i>
                        </a>
                        <?php if (can('roles.delete') && (int) $r['is_system'] === 0 && !$isOwn): ?>
                            <form method="post" action="<?= e(url('admin/roles/' . $r['id'] . '/delete')) ?>" class="d-inline" data-confirm="<?= e(__('are_you_sure')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" <?= (int) $r['users_count'] > 0 ? 'disabled title="' . e(__('role_has_users')) . '"' : '' ?>><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
