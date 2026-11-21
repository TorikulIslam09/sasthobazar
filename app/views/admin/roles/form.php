<?php
/**
 * Role create/edit with permission matrix.
 * Vars: $role (null on create), $input, $selected (permission ids), $errors, $matrix, $standard, $actions,
 *       $editable, $isSuper, $myPermIds (null = super admin), $usersCount
 */
$selectedSet = array_flip($selected);
$disabledAll = !$editable;
$canTouch = static fn(int $id): bool => $myPermIds === null || in_array($id, $myPermIds, true);
$isOwn = $role && !Auth::isSuperAdmin() && (int) $role['id'] === (int) (Auth::user()['role_id'] ?? 0);
$action = $role ? url('admin/roles/' . $role['id']) : url('admin/roles');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">
        <a href="<?= e(url('admin/roles')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
        <?= e($title) ?><?= $role ? ': ' . e(Role::label($role)) : '' ?>
    </h1>
    <?php if ($role): ?><span class="badge bg-light text-dark border"><?= e(__('users')) ?>: <?= e(num($usersCount)) ?></span><?php endif; ?>
</div>

<?php if ($isSuper): ?>
    <div class="alert alert-success"><i class="bi bi-shield-check"></i> <?= e(__('super_admin_all')) ?></div>
<?php elseif ($isOwn): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle"></i> <?= e(__('own_role_notice')) ?></div>
<?php elseif (!$editable): ?>
    <div class="alert alert-secondary"><i class="bi bi-eye"></i> <?= e(__('read_only')) ?></div>
<?php elseif ($myPermIds !== null): ?>
    <div class="alert alert-warning small"><i class="bi bi-info-circle"></i> <?= e(__('perm_limited_notice')) ?></div>
<?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" id="roleForm">
    <?= csrf_field() ?>
    <fieldset <?= $disabledAll ? 'disabled' : '' ?>>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="name"><?= e(__('name_en')) ?> *</label>
                <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" maxlength="100" required value="<?= e($input['name'] ?? '') ?>">
                <?= View::partial('partials/field_error', ['errors' => $errors, 'field' => 'name']) ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="name_bn"><?= e(__('name_bn')) ?></label>
                <input class="form-control <?= isset($errors['name_bn']) ? 'is-invalid' : '' ?>" id="name_bn" name="name_bn" maxlength="100" value="<?= e($input['name_bn'] ?? '') ?>">
                <?= View::partial('partials/field_error', ['errors' => $errors, 'field' => 'name_bn']) ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="description"><?= e(__('description')) ?></label>
                <input class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" id="description" name="description" maxlength="255" value="<?= e($input['description'] ?? '') ?>">
                <?= View::partial('partials/field_error', ['errors' => $errors, 'field' => 'description']) ?>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h6 mb-0"><?= e(__('permissions')) ?> <span class="badge bg-brand" id="permCount"><?= e(num(count($selected))) ?></span></h2>
        <?php if (!$disabledAll): ?>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-matrix="all"><?= e(__('select_all')) ?></button>
                <button type="button" class="btn btn-outline-secondary" data-matrix="none"><?= e(__('select_none')) ?></button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="table-responsive perm-matrix">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="min-width:180px"><?= e(__('module')) ?></th>
                        <?php foreach ($standard as $a): ?>
                            <th class="text-center">
                                <div class="small"><?= e(tr($actions[$a][0], $actions[$a][1])) ?></div>
                                <input type="checkbox" class="form-check-input" data-col="<?= e($a) ?>" title="<?= e(__('select_all')) ?>">
                            </th>
                        <?php endforeach; ?>
                        <th style="min-width:200px"><?= e(__('other_actions')) ?></th>
                        <th class="text-center"><?= e(__('all')) ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($matrix as $groupKey => $group): ?>
                    <tr class="table-group-row"><td colspan="<?= count($standard) + 3 ?>" class="small fw-semibold text-uppercase text-muted bg-light"><?= e($group['label']) ?></td></tr>
                    <?php foreach ($group['modules'] as $module => $m): ?>
                        <tr data-row="<?= e($module) ?>">
                            <td><i class="bi <?= e($m['icon']) ?> text-muted"></i> <?= e($m['label']) ?></td>
                            <?php foreach ($standard as $a): ?>
                                <td class="text-center">
                                    <?php if (isset($m['actions'][$a])): $p = $m['actions'][$a]; ?>
                                        <input type="checkbox" class="form-check-input perm-cb" name="permissions[]" value="<?= (int) $p['id'] ?>"
                                               data-col="<?= e($a) ?>" title="<?= e($p['key']) ?>"
                                               <?= isset($selectedSet[$p['id']]) ? 'checked' : '' ?> <?= $canTouch($p['id']) ? '' : 'disabled' ?>>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <?php foreach ($m['actions'] as $a => $p): if (in_array($a, $standard, true)) continue; ?>
                                    <label class="form-check form-check-inline small mb-0 me-2" title="<?= e($p['key']) ?>">
                                        <input type="checkbox" class="form-check-input perm-cb" name="permissions[]" value="<?= (int) $p['id'] ?>"
                                               data-col="other" <?= isset($selectedSet[$p['id']]) ? 'checked' : '' ?> <?= $canTouch($p['id']) ? '' : 'disabled' ?>>
                                        <span class="form-check-label"><?= e($p['label']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center"><input type="checkbox" class="form-check-input" data-row-toggle="<?= e($module) ?>" title="<?= e(__('select_all')) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </fieldset>

    <div class="d-flex gap-2">
        <?php if ($editable): ?><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button><?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/roles')) ?>"><?= e(__('back')) ?></a>
    </div>
</form>
