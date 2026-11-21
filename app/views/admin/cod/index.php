<?php /** COD management. Vars: $summary, $unbooked, $courier, $courierRow, $collected, $settlements */ ?>
<h1 class="h4 mb-3"><i class="bi bi-cash-stack"></i> <?= e(__('cod_management')) ?></h1>
<?= View::partial('admin/shipments/_nav', ['active' => 'cod']) ?>
<p class="small text-muted"><?= e(__('cod_how')) ?></p>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= e(__('cod_by_courier')) ?></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('courier')) ?></th><th class="text-end"><?= e(__('cod_pending')) ?></th><th class="text-end"><?= e(__('cod_collected')) ?></th><th class="text-end"><?= e(__('courier_charge')) ?></th><th></th></tr></thead>
                    <tbody>
                    <tr class="text-muted"><td><?= e(__('not_booked')) ?></td><td class="text-end"><?= e(money($unbooked['amt'])) ?> <span>(<?= e(num((int) $unbooked['n'])) ?>)</span></td><td class="text-end">—</td><td class="text-end">—</td><td></td></tr>
                    <?php foreach ($summary as $s): ?>
                        <tr class="<?= $courier === (int) $s['id'] ? 'table-active' : '' ?>">
                            <td><?= e($s['name']) ?></td>
                            <td class="text-end"><?= e(money($s['pending_amt'])) ?> <span class="text-muted">(<?= e(num((int) $s['pending_n'])) ?>)</span></td>
                            <td class="text-end fw-semibold"><?= e(money($s['collected_amt'])) ?> <span class="text-muted">(<?= e(num((int) $s['collected_n'])) ?>)</span></td>
                            <td class="text-end"><?= e(money($s['fees'])) ?></td>
                            <td class="text-end"><?php if ((int) $s['collected_n'] > 0): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/cod?courier=' . (int) $s['id'])) ?>"><?= e(__('cod_settle')) ?></a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= e(__('cod_recent_settlements')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php if (!$settlements): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
                <?php foreach ($settlements as $st): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between"><b><?= e($st['courier_name']) ?></b><span class="text-success"><?= e(money($st['amount_received'])) ?></span></div>
                        <div class="text-muted"><?= e($st['reference_no']) ?> · <?= e(dt($st['received_at'], 'd M Y')) ?> · <?= e(__('dash_n_orders', ['n' => num((int) $st['shipments_count'])])) ?><?= (float) $st['courier_fees'] > 0 ? ' · ' . e(__('fees')) . ' ' . e(money($st['courier_fees'])) : '' ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<?php if ($courierRow): ?>
    <form method="post" action="<?= e(url('admin/cod/settle')) ?>" class="card border-0 shadow-sm" data-confirm="<?= e(__('cod_settle_confirm')) ?>" id="settleForm">
        <?= csrf_field() ?><input type="hidden" name="courier" value="<?= (int) $courierRow['id'] ?>">
        <div class="card-header bg-white fw-semibold"><?= e(__('cod_settle_with', ['courier' => $courierRow['name']])) ?></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light"><tr><th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all checked aria-label="<?= e(__('select_all')) ?>"></th><th><?= e(__('tracking')) ?></th><th><?= e(__('sub_order')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('os_delivered')) ?></th><th class="text-end"><?= e(__('cod_collected')) ?></th><th class="text-end"><?= e(__('courier_charge')) ?></th></tr></thead>
                <tbody>
                <?php if (!$collected): ?><tr><td colspan="7" class="text-center text-muted py-3"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                <?php foreach ($collected as $r): ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-row-check data-amount="<?= e($r['collected_amount']) ?>" data-fee="<?= e($r['delivery_charge']) ?>" checked aria-label="#<?= (int) $r['id'] ?>"></td>
                        <td><a href="<?= e(url('admin/shipments/' . (int) $r['id'])) ?>" class="font-monospace"><?= e($r['tracking_no']) ?></a></td>
                        <td><?= e($r['sub_order_no']) ?></td>
                        <td><?= e($r['shipping_name']) ?></td>
                        <td><?= e(dt($r['delivered_at'], 'd M Y')) ?></td>
                        <td class="text-end"><?= e(money($r['collected_amount'])) ?></td>
                        <td class="text-end"><?= e(money($r['delivery_charge'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($collected): ?>
            <div class="card-body border-top row g-2 align-items-end small">
                <div class="col-md-3"><span class="text-muted d-block"><?= e(__('cod_expected')) ?></span><b class="fs-6" data-cod-expected>—</b> <span class="text-muted" data-cod-fees></span></div>
                <div class="col-6 col-md-2"><label class="form-label mb-0" for="ar"><?= e(__('cod_received')) ?> (৳) *</label><input class="form-control form-control-sm" id="ar" name="amount_received" inputmode="decimal" required data-cod-received></div>
                <div class="col-6 col-md-2"><label class="form-label mb-0" for="rd"><?= e(__('date')) ?> *</label><input class="form-control form-control-sm" type="date" id="rd" name="received_at" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-md-2"><label class="form-label mb-0" for="rf"><?= e(__('reference')) ?> *</label><input class="form-control form-control-sm" id="rf" name="reference_no" maxlength="100" required></div>
                <div class="col-md-2"><label class="form-label mb-0" for="rn"><?= e(__('note')) ?></label><input class="form-control form-control-sm" id="rn" name="note" maxlength="255"></div>
                <div class="col-md-1"><button class="btn btn-brand btn-sm w-100"><?= e(__('save')) ?></button></div>
            </div>
        <?php endif; ?>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var f = document.getElementById('settleForm'); if (!f) return;
        var calc = function () {
            var amt = 0, fee = 0;
            f.querySelectorAll('[data-row-check]').forEach(function (c) { if (c.checked) { amt += parseFloat(c.dataset.amount) || 0; fee += parseFloat(c.dataset.fee) || 0; } });
            var ex = f.querySelector('[data-cod-expected]'), fe = f.querySelector('[data-cod-fees]'), rc = f.querySelector('[data-cod-received]');
            if (ex) ex.textContent = '৳' + amt.toFixed(2);
            if (fe) fe.textContent = fee > 0 ? '(− ৳' + fee.toFixed(2) + ')' : '';
            if (rc && !rc.dataset.touched) rc.value = Math.max(0, amt - fee).toFixed(2);
        };
        f.addEventListener('change', calc);
        var rc = f.querySelector('[data-cod-received]'); if (rc) rc.addEventListener('input', function () { rc.dataset.touched = '1'; });
        var all = f.querySelector('[data-check-all]');
        if (all) all.addEventListener('change', function () { f.querySelectorAll('[data-row-check]').forEach(function (c) { c.checked = all.checked; }); calc(); });
        calc();
    });
    </script>
<?php endif; ?>
