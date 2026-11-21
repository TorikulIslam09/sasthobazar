<?php
/** Shipping settings. Vars: $zones, $rates (zone id => rows), $districts, $edit (rate being edited or null) */
$canEdit = can('shipping.edit');
$zoneName = static fn(array $z) => tr($z['name_bn'], $z['name']);
$r = $edit ?: ['id' => 0, 'zone_id' => $zones[0]['id'] ?? 0, 'service' => 'standard', 'name' => '', 'name_bn' => '', 'min_weight_grams' => 0, 'max_weight_grams' => null,
    'min_order_amount' => '0.00', 'rate' => '', 'extra_per_kg' => '0.00', 'free_shipping_threshold' => null, 'cod_charge_percent' => '0.00', 'est_days_min' => null, 'est_days_max' => null, 'is_active' => 1];
$byDivision = [];
foreach ($districts as $d) {
    $byDivision[tr($d['division_bn'], $d['division_en'])][] = $d;
}
?>
<h1 class="h4 mb-3"><i class="bi bi-truck"></i> <?= e(__('shipping_settings')) ?></h1>
<?= View::partial('admin/shipments/_nav', ['active' => 'shipping']) ?>
<div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('shipping_how')) ?></div>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="post" action="<?= e(url('admin/shipping/zones')) ?>" class="card border-0 shadow-sm mb-3" id="zones">
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><?= e(__('shipping_zones')) ?></div>
            <div class="card-body small">
                <?php foreach ($zones as $z): ?>
                    <div class="row g-1 mb-2 align-items-center">
                        <div class="col-6"><input class="form-control form-control-sm" name="zone[<?= (int) $z['id'] ?>][name]" value="<?= e($z['name']) ?>" maxlength="100" aria-label="<?= e(__('name_en')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="zone[<?= (int) $z['id'] ?>][name_bn]" value="<?= e($z['name_bn']) ?>" maxlength="100" aria-label="<?= e(__('name_bn')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" name="zone[<?= (int) $z['id'] ?>][zone_type]" aria-label="<?= e(__('zone_type')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                                <?php foreach (Shipping::ZONE_TYPES as $t): ?><option value="<?= $t ?>" <?= $z['zone_type'] === $t ? 'selected' : '' ?>><?= e(__('ztype_' . $t)) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-3"><input class="form-control form-control-sm" name="zone[<?= (int) $z['id'] ?>][sort_order]" value="<?= (int) $z['sort_order'] ?>" aria-label="<?= e(__('sort_order')) ?>" <?= $canEdit ? '' : 'disabled' ?>></div>
                        <div class="col-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="zone[<?= (int) $z['id'] ?>][is_active]" value="1" id="za<?= (int) $z['id'] ?>" <?= (int) $z['is_active'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="za<?= (int) $z['id'] ?>"><?= e(__('active')) ?></label></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($canEdit): ?>
                    <div class="row g-1 border-top pt-2">
                        <div class="col-7"><input class="form-control form-control-sm" name="new_zone" maxlength="100" placeholder="<?= e(__('zone_add')) ?>" aria-label="<?= e(__('zone_add')) ?>"></div>
                        <div class="col-5"><select class="form-select form-select-sm" name="new_zone_type" aria-label="<?= e(__('zone_type')) ?>"><?php foreach (Shipping::ZONE_TYPES as $t): ?><option value="<?= $t ?>"><?= e(__('ztype_' . $t)) ?></option><?php endforeach; ?></select></div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($canEdit): ?><div class="card-footer bg-white"><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button></div><?php endif; ?>
        </form>

        <form method="post" action="<?= e(url('admin/shipping/general')) ?>" class="card border-0 shadow-sm mb-3" id="general">
            <?= csrf_field() ?>
            <div class="card-header bg-white fw-semibold"><?= e(__('warehouse_pickup')) ?></div>
            <div class="card-body small">
                <label class="form-label" for="wh"><?= e(__('warehouse_district')) ?></label>
                <select class="form-select form-select-sm mb-2" id="wh" name="warehouse_district_id" <?= $canEdit ? '' : 'disabled' ?>>
                    <?php foreach ($districts as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) setting('warehouse_district_id', 147) === (int) $d['id'] ? 'selected' : '' ?>><?= e(tr($d['name_bn'], $d['name_en'])) ?></option><?php endforeach; ?>
                </select>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="store_pickup_enabled" value="1" id="spe" <?= setting('store_pickup_enabled', true) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><label class="form-check-label" for="spe"><?= e(__('dopt_pickup')) ?></label></div>
                <label class="form-label" for="spa"><?= e(__('store_pickup_address')) ?></label>
                <input class="form-control form-control-sm" id="spa" name="store_pickup_address" maxlength="255" value="<?= e(setting('store_pickup_address', '')) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <?php if ($canEdit): ?><div class="card-footer bg-white"><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button></div><?php endif; ?>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3" id="rates">
            <div class="card-header bg-white fw-semibold"><?= e(__('shipping_rates')) ?></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('zone')) ?> / <?= e(__('rate_name')) ?></th><th class="text-end"><?= e(__('rate_base')) ?></th><th class="text-end"><?= e(__('rate_per_kg')) ?></th><th class="text-end"><?= e(__('rate_free_over')) ?></th><th class="text-end">COD %</th><th><?= e(__('rate_eta')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($zones as $z): foreach ($rates[(int) $z['id']] ?? [] as $rt): ?>
                        <tr class="<?= (int) $rt['is_active'] ? '' : 'text-muted' ?>">
                            <td><b><?= e($zoneName($z)) ?></b> · <?= e($rt['name']) ?> <span class="badge text-bg-<?= $rt['service'] === 'express' ? 'warning' : 'light border' ?>"><?= e(__('dopt_' . $rt['service'])) ?></span>
                                <?php if ((int) $rt['min_weight_grams'] || $rt['max_weight_grams'] !== null): ?><div class="text-muted"><?= e(num((int) $rt['min_weight_grams'])) ?>–<?= $rt['max_weight_grams'] !== null ? e(num((int) $rt['max_weight_grams'])) : '∞' ?> g</div><?php endif; ?></td>
                            <td class="text-end"><?= e(money($rt['rate'])) ?></td>
                            <td class="text-end"><?= e(money($rt['extra_per_kg'])) ?></td>
                            <td class="text-end"><?= $rt['free_shipping_threshold'] !== null ? e(money($rt['free_shipping_threshold'])) : '—' ?></td>
                            <td class="text-end"><?= e(num((float) $rt['cod_charge_percent'])) ?></td>
                            <td><?= $rt['est_days_max'] !== null ? e(num((int) $rt['est_days_min']) . '–' . num((int) $rt['est_days_max'])) : '—' ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($canEdit): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/shipping?rate=' . (int) $rt['id'] . '#rates')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                                <?php if (can('shipping.delete')): ?><?= View::partial('partials/delete_button', ['action' => url('admin/shipping/rates/' . (int) $rt['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $rt['name']])]) ?><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canEdit): ?>
                <form method="post" action="<?= e(url('admin/shipping/rates')) ?>" class="card-body border-top row g-2 small">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <div class="col-12 fw-semibold"><?= e($r['id'] ? __('rate_edit') : __('rate_add')) ?></div>
                    <div class="col-md-4"><label class="form-label mb-0" for="rz"><?= e(__('zone')) ?></label><select class="form-select form-select-sm" id="rz" name="zone_id"><?php foreach ($zones as $z): ?><option value="<?= (int) $z['id'] ?>" <?= (int) $r['zone_id'] === (int) $z['id'] ? 'selected' : '' ?>><?= e($zoneName($z)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label mb-0" for="rs"><?= e(__('service')) ?></label><select class="form-select form-select-sm" id="rs" name="service"><?php foreach (['standard', 'express'] as $sv): ?><option value="<?= $sv ?>" <?= $r['service'] === $sv ? 'selected' : '' ?>><?= e(__('dopt_' . $sv)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-5"><label class="form-label mb-0" for="rn"><?= e(__('rate_name')) ?></label><input class="form-control form-control-sm" id="rn" name="name" value="<?= e($r['name']) ?>" maxlength="100" required></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="rr"><?= e(__('rate_base')) ?> (৳)</label><input class="form-control form-control-sm" id="rr" name="rate" value="<?= e($r['rate']) ?>" inputmode="decimal" required></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="rk"><?= e(__('rate_per_kg')) ?> (৳)</label><input class="form-control form-control-sm" id="rk" name="extra_per_kg" value="<?= e($r['extra_per_kg']) ?>" inputmode="decimal"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="rf"><?= e(__('rate_free_over')) ?> (৳)</label><input class="form-control form-control-sm" id="rf" name="free_shipping_threshold" value="<?= e($r['free_shipping_threshold'] ?? '') ?>" inputmode="decimal" placeholder="—"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="rc">COD %</label><input class="form-control form-control-sm" id="rc" name="cod_charge_percent" value="<?= e($r['cod_charge_percent']) ?>" inputmode="decimal"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="rmin"><?= e(__('rate_min_weight')) ?> (g)</label><input class="form-control form-control-sm" id="rmin" name="min_weight_grams" value="<?= (int) $r['min_weight_grams'] ?>" inputmode="numeric"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="rmax"><?= e(__('rate_max_weight')) ?> (g)</label><input class="form-control form-control-sm" id="rmax" name="max_weight_grams" value="<?= e($r['max_weight_grams'] ?? '') ?>" inputmode="numeric" placeholder="∞"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="rmo"><?= e(__('rate_min_order')) ?> (৳)</label><input class="form-control form-control-sm" id="rmo" name="min_order_amount" value="<?= e($r['min_order_amount']) ?>" inputmode="decimal"></div>
                    <div class="col-6 col-md-3"><label class="form-label mb-0" for="re1"><?= e(__('rate_eta')) ?></label><div class="d-flex gap-1"><input class="form-control form-control-sm" id="re1" name="est_days_min" value="<?= e($r['est_days_min'] ?? '') ?>" inputmode="numeric" aria-label="min"><input class="form-control form-control-sm" name="est_days_max" value="<?= e($r['est_days_max'] ?? '') ?>" inputmode="numeric" aria-label="max"></div></div>
                    <div class="col-12 d-flex align-items-center gap-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ra" <?= (int) $r['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="ra"><?= e(__('active')) ?></label></div>
                        <button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button>
                        <?php if ($r['id']): ?><a class="btn btn-link btn-sm" href="<?= e(url('admin/shipping#rates')) ?>"><?= e(__('cancel')) ?></a><?php endif; ?>
                        <span class="text-muted ms-auto"><?= e(__('rate_help')) ?></span>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12">
        <form method="post" action="<?= e(url('admin/shipping/districts')) ?>" class="card border-0 shadow-sm" id="districts">
            <?= csrf_field() ?>
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= e(__('district_zones')) ?></span>
                <?php if ($canEdit): ?><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button><?php endif; ?>
            </div>
            <div class="card-body row g-3 small">
                <?php foreach ($byDivision as $division => $list): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="fw-semibold border-bottom mb-1"><?= e($division) ?></div>
                        <?php foreach ($list as $d): ?>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                <label for="dz<?= (int) $d['id'] ?>" class="text-truncate"><?= e(tr($d['name_bn'], $d['name_en'])) ?></label>
                                <select class="form-select form-select-sm w-auto" id="dz<?= (int) $d['id'] ?>" name="district[<?= (int) $d['id'] ?>]" <?= $canEdit ? '' : 'disabled' ?>>
                                    <option value="0">—</option>
                                    <?php foreach ($zones as $z): ?><option value="<?= (int) $z['id'] ?>" <?= (int) $d['shipping_zone_id'] === (int) $z['id'] ? 'selected' : '' ?>><?= e($zoneName($z)) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>
