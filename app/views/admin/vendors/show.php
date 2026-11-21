<?php
/** Vendor detail. Vars: $v, $docs, $area, $stats, $wallet, $ledger, $payouts, $products, $recent, $blockers */
$id = (int) $v['id'];
$isDeleted = $v['deleted_at'] !== null;
$blockers = $blockers ?? [];
$actions = [
    'pending'   => ['approve' => 'success', 'reject' => 'danger'],
    'approved'  => ['suspend' => 'warning'],
    'suspended' => ['reactivate' => 'success'],
    'rejected'  => ['approve' => 'success'],
][$v['status']] ?? [];
if ($isDeleted) {
    $actions = [];
}
$drugExpired = $v['drug_license_expiry'] && $v['drug_license_expiry'] < date('Y-m-d');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= e(url('admin/vendors')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('vendors_label')) ?></a>
        <h1 class="h4 mb-0"><?= e($v['shop_name']) ?> <span class="badge text-bg-<?= VendorAccount::BADGES[$v['status']] ?>"><?= e(__('vs_' . $v['status'])) ?></span></h1>
        <span class="small text-muted"><?= e(VendorType::vendorLabel($v)) ?> · <?= e(__('joined')) ?> <?= e(dt($v['created_at'], 'd M Y')) ?><?= $v['approved_at'] ? ' · ' . e(__('approved_on')) . ' ' . e(dt($v['approved_at'], 'd M Y')) : '' ?></span>
    </div>
    <?php if ($v['status'] === 'approved'): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('seller/' . rawurlencode($v['slug']))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('view_shop_page')) ?></a><?php endif; ?>
</div>

<?php if ($isDeleted): ?>
    <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><i class="bi bi-trash"></i> <b><?= e(__('vdel_is_deleted', ['date' => dt($v['deleted_at'], 'd M Y, h:i A')])) ?></b>
            <?php if ($v['suspension_reason']): ?><div class="small"><?= e($v['suspension_reason']) ?></div><?php endif; ?>
            <div class="small"><?= e(__('vdel_history_kept')) ?></div></div>
        <?php if (can('vendors.delete')): ?>
            <form method="post" action="<?= e(url('admin/vendors/' . $id . '/restore')) ?>"><?= csrf_field() ?>
                <button class="btn btn-light btn-sm" data-confirm-click="<?= e(__('vdel_restore_confirm')) ?>"><i class="bi bi-arrow-counterclockwise"></i> <?= e(__('vdel_restore')) ?></button></form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($actions && can('vendors.approve')): ?>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <form method="post" action="<?= e(url('admin/vendors/' . $id . '/status')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
            <?= csrf_field() ?>
            <div class="flex-grow-1">
                <label class="form-label small mb-1" for="reason"><?= e(__('reason')) ?> <span class="text-muted">(<?= e(__('vendor_reason_hint')) ?>)</span></label>
                <input class="form-control form-control-sm" id="reason" name="reason" maxlength="255">
            </div>
            <?php foreach ($actions as $a => $c): ?>
                <button class="btn btn-<?= $c ?> btn-sm" name="action" value="<?= $a ?>" data-confirm-click="<?= e(__('vendor_confirm_' . $a)) ?>"><?= e(__('vendor_act_' . $a)) ?></button>
            <?php endforeach; ?>
        </form>
        <?php if ($v['status'] === 'rejected' && $v['rejection_reason']): ?><div class="small text-danger mt-2"><?= e(__('reason')) ?>: <?= e($v['rejection_reason']) ?></div><?php endif; ?>
        <?php if ($v['status'] === 'suspended' && $v['suspension_reason']): ?><div class="small text-danger mt-2"><?= e(__('reason')) ?>: <?= e($v['suspension_reason']) ?></div><?php endif; ?>
        <?php if ($v['status'] === 'suspended'): ?><div class="small text-muted mt-1"><?= e(__('vendor_suspend_effect')) ?></div><?php endif; ?>
    </div></div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ([['orders', 'orders', false], ['sales', 'v_sales', true], ['commission', 'commission_earned', true], ['earning', 'vendor_earning', true]] as [$k, $label, $money]): ?>
        <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm h-100"><div class="card-body">
            <div class="small text-muted"><?= e(__($label)) ?></div>
            <div class="fs-5 fw-semibold"><?= e($money ? money($stats[$k] ?? 0) : num((int) ($stats[$k] ?? 0))) ?></div>
            <?php if ($k === 'orders'): ?><div class="small text-muted"><?= e(__('delivered_cancelled', ['d' => num((int) ($stats['delivered'] ?? 0)), 'c' => num((int) ($stats['cancelled'] ?? 0))])) ?></div><?php endif; ?>
        </div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('vendor_details')) ?></div>
            <div class="card-body small row g-2">
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('owner_name')) ?></span><?= e($v['contact_person']) ?> · <?= e($v['phone']) ?><?= $v['email'] ? '<br>' . e($v['email']) : '' ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('vr_pickup')) ?></span><?= e($v['address']) ?><br><?= e($area) ?><?= $v['pickup_phone'] ? '<br>' . e($v['pickup_phone']) : '' ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('trade_license_no')) ?></span><?= e($v['trade_license_no'] ?: '—') ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('drug_license_no')) ?></span><?= e($v['drug_license_no'] ?: '—') ?><?php if ($v['drug_license_expiry']): ?> <span class="<?= $drugExpired ? 'text-danger fw-semibold' : 'text-muted' ?>">(<?= e(__('expires')) ?> <?= e(dt($v['drug_license_expiry'], 'd M Y')) ?>)</span><?php endif; ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('nid_no')) ?></span><?= e($v['nid_no'] ?: '—') ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('tin_no')) ?></span><?= e($v['tin_no'] ?: '—') ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('bank_account_no')) ?></span><?= e(Payout::accountFor($v, 'bank') ?? '—') ?></div>
                <div class="col-sm-6"><span class="text-muted d-block"><?= e(__('mobile_banking')) ?></span><?= $v['mobile_banking_provider'] ? e(__('mfs_' . $v['mobile_banking_provider'])) . ' · ' . e($v['mobile_banking_no']) : '—' ?></div>
                <div class="col-12"><span class="text-muted d-block"><?= e(__('documents')) ?></span>
                    <?php $vt = VendorType::forVendor($v); $needDocs = $vt ? VendorType::requiredDocs($vt) : ['trade_license', 'nid'];
                    foreach (VendorAccount::DOCS as $d): if (!isset($docs[$d]) && !in_array($d, $needDocs, true)) { continue; } ?>
                        <?php if (isset($docs[$d])): ?><a class="btn btn-outline-secondary btn-sm me-1 mb-1" href="<?= e(url('admin/vendors/' . $id . '/documents/' . $d)) ?>" target="_blank" rel="noopener"><i class="bi <?= $docs[$d]['mime'] === 'application/pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' ?>"></i> <?= e(__('doc_' . $d)) ?></a>
                        <?php else: ?><span class="badge text-bg-light border me-1"><?= e(__('doc_' . $d)) ?>: —</span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($v['description']): ?><div class="col-12"><span class="text-muted d-block"><?= e(__('shop_description')) ?></span><?= nl2br(e($v['description'])) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('dash_recent_orders')) ?> <span class="text-muted small">(<?= e(num($products)) ?> <?= e(__('products')) ?>)</span></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <tbody>
                    <?php if (!$recent): ?><tr><td class="text-center text-muted py-3"><?= e(__('dash_no_orders')) ?></td></tr><?php endif; ?>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><a href="<?= e(url('admin/orders/' . (int) $r['order_id'] . '#splits')) ?>"><?= e($r['sub_order_no']) ?></a><div class="text-muted"><?= e(dt($r['placed_at'], 'd M Y')) ?></div></td>
                            <td class="text-end"><?= e(money((float) $r['subtotal'] - (float) $r['discount_total'])) ?></td>
                            <td class="text-end"><?= e(money($r['commission_amount'])) ?><div class="text-muted"><?= e(__('settle_' . $r['settlement_status'])) ?></div></td>
                            <td><?= OrderView::badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('commission')) ?></div>
            <div class="card-body small">
                <form method="post" action="<?= e(url('admin/vendors/' . $id . '/commission')) ?>" class="d-flex gap-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="flex-grow-1">
                        <label class="form-label small mb-1" for="cr"><?= e(__('vendor_rate')) ?> (%)</label>
                        <input class="form-control form-control-sm" id="cr" name="commission_rate" value="<?= e($v['commission_rate'] ?? '') ?>" placeholder="<?= e(__('cscope_global')) ?> <?= e(num(number_format(Commission::globalRate(), 2))) ?>%" inputmode="decimal" <?= can('commissions.edit') ? '' : 'disabled' ?>>
                    </div>
                    <?php if (can('commissions.edit')): ?><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button><?php endif; ?>
                </form>
                <p class="text-muted mt-2 mb-0"><?= e(__('commission_priority')) ?></p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('vendor_type')) ?></div>
            <div class="card-body small">
                <form method="post" action="<?= e(url('admin/vendors/' . $id . '/type')) ?>" class="d-flex gap-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="flex-grow-1">
                        <label class="visually-hidden" for="vtSel"><?= e(__('vendor_type')) ?></label>
                        <select class="form-select form-select-sm" id="vtSel" name="vendor_type_id" <?= can('vendors.edit') ? '' : 'disabled' ?>>
                            <?php foreach (VendorType::all() as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($v['vendor_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e(VendorType::label($t)) ?><?= (int) $t['is_active'] ? '' : ' (' . e(__('inactive')) . ')' ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (can('vendors.edit')): ?><button class="btn btn-brand btn-sm"><?= e(__('save')) ?></button><?php endif; ?>
                </form>
                <?php if ($vt): ?><p class="text-muted mt-2 mb-0">
                    <?= (int) $vt['can_sell_rx'] ? '<i class="bi bi-check-circle text-success"></i> ' . e(__('vt_can_sell_rx')) : '<i class="bi bi-x-circle text-danger"></i> ' . e(__('vt_no_rx')) ?>
                    · <?= e(__('vt_categories')) ?>: <?= e(VendorType::allowedCategories($vt) === null ? __('all') : implode(', ', array_map(static fn($cid) => Category::label(Category::allById()[$cid] ?? ['name_en' => '#' . $cid, 'name_bn' => null]), VendorType::allowedCategories($vt)))) ?>
                </p><?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2"></i> <?= e(__('wallet')) ?></div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_available')) ?></span><b class="text-success"><?= e(money($wallet['balance'])) ?></b></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_pending')) ?></span><b><?= e(money($wallet['pending_balance'])) ?></b></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_total_earned')) ?></span><b><?= e(money($wallet['total_earned'])) ?></b></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(__('wallet_withdrawn')) ?></span><b><?= e(money($wallet['total_withdrawn'])) ?></b></li>
            </ul>
            <?php if (can('vendor_payouts.approve')): ?>
                <div class="card-body border-top">
                    <form method="post" action="<?= e(url('admin/vendors/' . $id . '/adjust')) ?>" class="row g-2" data-confirm="<?= e(__('adjust_confirm')) ?>">
                        <?= csrf_field() ?>
                        <div class="col-4"><input class="form-control form-control-sm" name="amount" placeholder="±৳" inputmode="decimal" aria-label="<?= e(__('amount')) ?>"></div>
                        <div class="col-8"><input class="form-control form-control-sm" name="note" maxlength="255" placeholder="<?= e(__('adjust_note_ph')) ?>" aria-label="<?= e(__('note')) ?>"></div>
                        <div class="col-12"><button class="btn btn-outline-secondary btn-sm w-100"><?= e(__('wallet_adjust')) ?></button></div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= e(__('wallet_ledger')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php if (!$ledger): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
                <?php foreach ($ledger as $l): ?>
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span class="text-truncate"><span class="badge text-bg-light border"><?= e(__('wt_' . $l['type'])) ?></span> <?= e($l['note']) ?><div class="text-muted"><?= e(dt($l['created_at'], 'd M, h:i A')) ?></div></span>
                        <span class="text-end text-nowrap <?= (float) $l['amount'] < 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($l['amount'])) ?><div class="text-muted"><?= e(money($l['balance_after'])) ?></div></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php if (!$isDeleted && can('vendors.delete')): ?>
    <div class="card border-danger mt-3" id="delete">
        <div class="card-header bg-white text-danger fw-semibold"><i class="bi bi-trash"></i> <?= e(__('vdel_title')) ?></div>
        <div class="card-body small">
            <p class="mb-2"><?= e(__('vdel_explain')) ?></p>
            <ul class="mb-3">
                <li><?= e(__('vdel_effect_1')) ?></li>
                <li><?= e(__('vdel_effect_2')) ?></li>
                <li><?= e(__('vdel_effect_3')) ?></li>
            </ul>
            <?php if ($v['status'] === 'approved'): ?><p class="text-muted"><i class="bi bi-lightbulb"></i> <?= e(__('vdel_suspend_tip')) ?></p><?php endif; ?>
            <?php if ($blockers): ?>
                <div class="alert alert-warning mb-0"><b><?= e(__('vdel_blocked')) ?></b>
                    <ul class="mb-0 mt-1"><?php foreach ($blockers as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul></div>
            <?php else: ?>
                <form method="post" action="<?= e(url('admin/vendors/' . $id . '/delete')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-6">
                        <label class="form-label mb-1" for="delReason"><?= e(__('reason')) ?> *</label>
                        <input class="form-control form-control-sm" id="delReason" name="reason" maxlength="200" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1" for="delName"><?= e(__('vdel_type_name', ['shop' => $v['shop_name']])) ?></label>
                        <input class="form-control form-control-sm" id="delName" name="confirm_name" autocomplete="off" required>
                    </div>
                    <div class="col-12"><button class="btn btn-danger btn-sm" data-confirm-click="<?= e(__('vdel_confirm')) ?>"><i class="bi bi-trash"></i> <?= e(__('vdel_button')) ?></button></div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
