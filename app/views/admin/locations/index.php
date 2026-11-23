<?php
/**
 * Locations: Division > District > Upazila > Area, one level at a time.
 * Vars: $parent (?array), $chain, $childType, $rows, $q, $status, $counts, $errors, $old
 */
$name = static fn(array $l) => tr((string) ($l['name_bn'] ?: $l['name_en']), (string) $l['name_en']);
$canAdd = can('locations.create');
$canEdit = can('locations.edit');
$canDel = can('locations.delete');
$back = 'admin/locations' . ($parent ? '?parent=' . (int) $parent['id'] : '');
$errFor = $errors['_for'] ?? null;
$err = static fn(string $f, $for) => isset($errors[$f]) && (string) $errFor === (string) $for ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$hasChildren = $childType !== 'area';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="bi bi-geo-alt"></i> <?= e(__('locations')) ?></h1>
    <div class="small text-muted">
        <?php foreach (['division', 'district', 'upazila', 'area'] as $t): ?><span class="me-2"><?= e(__($t === 'area' ? 'loc_area' : $t)) ?>: <b><?= e(num($counts[$t] ?? 0)) ?></b></span><?php endforeach; ?>
    </div>
</div>

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('admin/locations')) ?>"><?= e(__('loc_bangladesh')) ?></a></li>
        <?php foreach ($chain as $i => $c): ?>
            <li class="breadcrumb-item <?= $i === count($chain) - 1 ? 'active' : '' ?>"><?php if ($i < count($chain) - 1): ?><a href="<?= e(url('admin/locations?parent=' . (int) $c['id'])) ?>"><?= e($name($c)) ?></a><?php else: ?><?= e($name($c)) ?><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>

<form class="d-flex flex-wrap gap-2 mb-3" method="get" action="<?= e(url('admin/locations')) ?>">
    <?php if ($parent && $q === ''): ?><input type="hidden" name="parent" value="<?= (int) $parent['id'] ?>"><?php endif; ?>
    <input class="form-control form-control-sm w-auto" type="search" name="q" value="<?= e($q) ?>" maxlength="60" placeholder="<?= e(__('loc_search_ph')) ?>">
    <select class="form-select form-select-sm w-auto" name="status" aria-label="<?= e(__('status')) ?>">
        <option value=""><?= e(__('all')) ?></option>
        <option value="on" <?= $status === 'on' ? 'selected' : '' ?>><?= e(__('active')) ?></option>
        <option value="off" <?= $status === 'off' ? 'selected' : '' ?>><?= e(__('inactive')) ?></option>
    </select>
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    <?php if ($q !== '' || $status !== ''): ?><a class="btn btn-sm btn-link" href="<?= e(url($back)) ?>"><?= e(__('reset')) ?></a><?php endif; ?>
</form>

<?php if ($canAdd && $q === ''): ?>
    <form method="post" action="<?= e(url('admin/locations')) ?>" class="card border-0 shadow-sm mb-3">
        <?= csrf_field() ?>
        <?php if ($parent): ?><input type="hidden" name="parent_id" value="<?= (int) $parent['id'] ?>"><?php endif; ?>
        <div class="card-body row g-2 align-items-start">
            <div class="col-12 small fw-semibold"><i class="bi bi-plus-lg"></i> <?= e(__('loc_add_type', ['type' => __($childType === 'area' ? 'loc_area' : $childType)])) ?><?= $parent ? ' — ' . e($name($parent)) : '' ?></div>
            <div class="col-md-4"><input class="form-control form-control-sm<?= $err('name_en', 'new') ? ' is-invalid' : '' ?>" name="name_en" value="<?= e($errFor === 'new' ? (string) ($old['name_en'] ?? '') : '') ?>" maxlength="100" placeholder="<?= e(__('name')) ?> (English) *" required><?= $err('name_en', 'new') ?></div>
            <div class="col-md-4"><input class="form-control form-control-sm" name="name_bn" value="<?= e($errFor === 'new' ? (string) ($old['name_bn'] ?? '') : '') ?>" maxlength="100" placeholder="<?= e(__('name')) ?> (বাংলা)"></div>
            <div class="col-md-2"><input class="form-control form-control-sm<?= $err('postcode', 'new') ? ' is-invalid' : '' ?>" name="postcode" value="<?= e($errFor === 'new' ? (string) ($old['postcode'] ?? '') : '') ?>" maxlength="4" inputmode="numeric" placeholder="<?= e(__('postcode')) ?>"><?= $err('postcode', 'new') ?></div>
            <div class="col-md-2"><button class="btn btn-brand btn-sm w-100"><?= e(__('add')) ?></button></div>
        </div>
    </form>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('name')) ?></th><?php if ($q !== ''): ?><th><?= e(__('type')) ?></th><?php endif; ?><th><?= e(__('postcode')) ?></th><th><?= e(__('loc_zone')) ?></th><?php if ($hasChildren || $q !== ''): ?><th class="text-end"><?= e(__('loc_inside')) ?></th><?php endif; ?><th><?= e(__('status')) ?></th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $l): $lid = (int) $l['id']; $editing = (string) $errFor === (string) $lid; ?>
                <tr class="<?= (int) $l['is_active'] ? '' : 'text-muted' ?>">
                    <td>
                        <?php if (isset(['division' => 1, 'district' => 1, 'upazila' => 1][$l['type']])): ?>
                            <a class="fw-semibold" href="<?= e(url('admin/locations?parent=' . $lid)) ?>"><?= e($l['name_en']) ?></a>
                        <?php else: ?><b><?= e($l['name_en']) ?></b><?php endif; ?>
                        <?php if ($l['name_bn']): ?><span class="text-muted"> · <?= e($l['name_bn']) ?></span><?php endif; ?>
                        <?php if ($q !== '' && $l['parent_en']): ?><div class="text-muted"><?= e(tr((string) ($l['parent_bn'] ?: $l['parent_en']), (string) $l['parent_en'])) ?></div><?php endif; ?>
                    </td>
                    <?php if ($q !== ''): ?><td><?= e(__($l['type'] === 'area' ? 'loc_area' : $l['type'])) ?></td><?php endif; ?>
                    <td><?= e((string) ($l['postcode'] ?? '—')) ?></td>
                    <td><?= $l['zone_name'] ? e($l['zone_name']) : '<span class="text-muted">—</span>' ?></td>
                    <?php if ($hasChildren || $q !== ''): ?><td class="text-end"><?= $l['type'] !== 'area' ? e(num($l['children_on'])) . ' / ' . e(num($l['children'])) : '' ?></td><?php endif; ?>
                    <td><span class="badge text-bg-<?= (int) $l['is_active'] ? 'success' : 'secondary' ?>"><?= e(__((int) $l['is_active'] ? 'active' : 'inactive')) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ($canEdit): ?>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#le<?= $lid ?>" aria-expanded="<?= $editing ? 'true' : 'false' ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= e(url('admin/locations/' . $lid . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($q !== '' ? 'admin/locations?q=' . rawurlencode($q) : $back) ?>">
                                <button class="btn btn-sm btn-outline-secondary" title="<?= e(__((int) $l['is_active'] ? 'deactivate' : 'activate')) ?>" <?= (int) $l['is_active'] ? 'data-confirm-click="' . e(__('loc_off_confirm')) . '"' : '' ?>><i class="bi <?= (int) $l['is_active'] ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i></button></form>
                        <?php endif; ?>
                        <?php if ($canDel): ?>
                            <form method="post" action="<?= e(url('admin/locations/' . $lid . '/delete')) ?>" class="d-inline"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" title="<?= e(__('delete')) ?>" data-confirm-click="<?= e(__('loc_delete_confirm')) ?>"><i class="bi bi-trash"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($canEdit): ?>
                    <tr class="collapse<?= $editing ? ' show' : '' ?>" id="le<?= $lid ?>"><td colspan="7" class="bg-light">
                        <form method="post" action="<?= e(url('admin/locations/' . $lid)) ?>" class="row g-2 align-items-start">
                            <?= csrf_field() ?>
                            <div class="col-md-4"><input class="form-control form-control-sm<?= $err('name_en', $lid) ? ' is-invalid' : '' ?>" name="name_en" value="<?= e($editing ? (string) ($old['name_en'] ?? '') : $l['name_en']) ?>" maxlength="100" aria-label="<?= e(__('name')) ?> (English)" required><?= $err('name_en', $lid) ?></div>
                            <div class="col-md-4"><input class="form-control form-control-sm" name="name_bn" value="<?= e($editing ? (string) ($old['name_bn'] ?? '') : (string) $l['name_bn']) ?>" maxlength="100" aria-label="<?= e(__('name')) ?> (বাংলা)" placeholder="বাংলা নাম"></div>
                            <div class="col-md-2"><input class="form-control form-control-sm<?= $err('postcode', $lid) ? ' is-invalid' : '' ?>" name="postcode" value="<?= e($editing ? (string) ($old['postcode'] ?? '') : (string) $l['postcode']) ?>" maxlength="4" inputmode="numeric" aria-label="<?= e(__('postcode')) ?>"><?= $err('postcode', $lid) ?></div>
                            <div class="col-md-2"><button class="btn btn-brand btn-sm w-100"><?= e(__('save')) ?></button></div>
                        </form>
                    </td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> <?= e(__('loc_help')) ?><?php if (can('shipping.view')): ?> <a href="<?= e(url('admin/shipping')) ?>"><?= e(__('loc_zone_link')) ?></a><?php endif; ?></p>
