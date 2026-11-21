<?php
/** Wallet, ledger, payouts. Vars: $vendor, $wallet, $ledger, $pager, $payouts, $nextRelease, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$open = array_filter($payouts, static fn($p) => in_array($p['status'], ['requested', 'approved'], true));
$methods = array_filter(Payout::METHODS, static fn($m) => Payout::accountFor($vendor, $m) !== null);
?>
<h1 class="h4 mb-3"><i class="bi bi-wallet2"></i> <?= e(__('wallet_payouts')) ?></h1>
<div class="row g-3 mb-3">
    <?php foreach ([['balance', 'wallet_available', 'success'], ['pending_balance', 'wallet_pending', 'warning'], ['total_earned', 'wallet_total_earned', 'primary'], ['total_withdrawn', 'wallet_withdrawn', 'secondary']] as [$k, $label, $c]): ?>
        <div class="col-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted"><?= e(__($label)) ?></div>
                <div class="fs-5 fw-semibold text-<?= $c ?>"><?= e(money($wallet[$k])) ?></div>
                <?php if ($k === 'pending_balance' && $nextRelease['at']): ?><div class="small text-muted"><?= e(__('release_on', ['date' => dt($nextRelease['at'], 'd M Y')])) ?></div><?php endif; ?>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= e(__('request_payout')) ?></div>
            <div class="card-body">
                <?php if ($open): ?>
                    <div class="alert alert-info small mb-0"><?= e(__('payout_open_exists')) ?></div>
                <?php elseif (!$methods): ?>
                    <div class="alert alert-warning small mb-0"><?= e(__('payout_no_account')) ?> <a href="<?= e(url('vendor-panel/profile')) ?>"><?= e(__('shop_profile')) ?></a></div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('vendor-panel/payouts')) ?>" novalidate data-confirm="<?= e(__('payout_confirm')) ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small" for="pa"><?= e(__('amount')) ?> (৳) *</label>
                        <input class="form-control<?= isset($errors['amount']) ? ' is-invalid' : '' ?>" id="pa" name="amount" inputmode="decimal" value="<?= e(old('amount', number_format((float) $wallet['balance'], 2, '.', ''))) ?>">
                        <div class="form-text"><?= e(__('payout_rules', ['min' => money(Payout::minAmount()), 'max' => money($wallet['balance'])])) ?></div>
                        <?= $err('amount') ?>
                        <label class="form-label small mt-2" for="pm"><?= e(__('payout_method')) ?> *</label>
                        <select class="form-select<?= isset($errors['method']) ? ' is-invalid' : '' ?>" id="pm" name="method">
                            <?php foreach ($methods as $m): ?><option value="<?= $m ?>"><?= e(__('mfs_' . $m)) ?> — <?= e(Payout::accountFor($vendor, $m)) ?></option><?php endforeach; ?>
                        </select>
                        <?= $err('method') ?>
                        <label class="form-label small mt-2" for="pn"><?= e(__('note')) ?></label>
                        <input class="form-control" id="pn" name="note" maxlength="255" value="<?= e(old('note')) ?>">
                        <button class="btn btn-brand mt-3 w-100" <?= $vendor['status'] === 'approved' ? '' : 'disabled' ?>><i class="bi bi-send"></i> <?= e(__('request_payout')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= e(__('payout_history')) ?></div>
            <ul class="list-group list-group-flush small">
                <?php if (!$payouts): ?><li class="list-group-item text-muted"><?= e(__('no_records')) ?></li><?php endif; ?>
                <?php foreach ($payouts as $p): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between"><b>#<?= e(num($p['id'])) ?> · <?= e(money($p['amount'])) ?></b><span class="badge text-bg-<?= Payout::BADGES[$p['status']] ?>"><?= e(__('po_st_' . $p['status'])) ?></span></div>
                        <div class="text-muted"><?= e(__('mfs_' . $p['method'])) ?> · <?= e($p['account_details']) ?> · <?= e(dt($p['requested_at'], 'd M Y')) ?></div>
                        <?php if ($p['reference_no']): ?><div><?= e(__('reference')) ?>: <code><?= e($p['reference_no']) ?></code></div><?php endif; ?>
                        <?php if ($p['admin_note']): ?><div class="text-muted"><?= e($p['admin_note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= e(__('wallet_ledger')) ?></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light"><tr><th><?= e(__('time')) ?></th><th><?= e(__('details')) ?></th><th class="text-end"><?= e(__('amount')) ?></th><th class="text-end"><?= e(__('inv_balance')) ?></th></tr></thead>
                    <tbody>
                    <?php if (!$ledger): ?><tr><td colspan="4" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
                    <?php foreach ($ledger as $l): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(dt($l['created_at'], 'd M, h:i A')) ?></td>
                            <td><span class="badge text-bg-light border"><?= e(__('wt_' . $l['type'])) ?></span> <?= e($l['note']) ?></td>
                            <td class="text-end <?= (float) $l['amount'] < 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($l['amount'])) ?></td>
                            <td class="text-end"><?= e(money($l['balance_after'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted"><?= e(__('ledger_help', ['days' => num((int) setting('payout_hold_days', 7))])) ?></div>
        </div>
        <div class="mt-3"><?= $pager->links() ?></div>
    </div>
</div>
