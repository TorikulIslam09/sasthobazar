<?php
/** Vendor offers. Vars: $vendor, $offers, $flash (own products in running / upcoming flash sales), $platform (running platform offers) */
$canAct = $vendor['status'] === 'approved';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-gift"></i> <?= e(__('my_offers')) ?></h1>
    <?php if ($canAct): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-brand btn-sm" href="<?= e(url('vendor-panel/offers/create')) ?>"><i class="bi bi-percent"></i> <?= e(__('request_discount')) ?></a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('vendor-panel/offers/create?kind=bogo')) ?>"><i class="bi bi-gift"></i> <?= e(__('request_bogo')) ?></a>
        </div>
    <?php endif; ?>
</div>
<div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('vendor_offer_help')) ?></div>

<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-light"><tr>
                <th><?= e(__('offer')) ?></th><th><?= e(__('discount')) ?></th><th><?= e(__('coupon_applies')) ?></th><th><?= e(__('cost_bearer')) ?></th>
                <th><?= e(__('coupon_validity')) ?></th><th><?= e(__('status')) ?></th><th class="text-end"><?= e(__('actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$offers): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_offers_yet')) ?></td></tr><?php endif; ?>
            <?php foreach ($offers as $o): [$stLabel, $stColor] = OfferForm::state($o); $id = (int) $o['id']; ?>
                <tr>
                    <td><div class="fw-semibold"><?= e(Promo::title($o)) ?></div><span class="badge text-bg-light border"><?= e(__('offer_kind_' . $o['kind'])) ?></span>
                        <?php if ($o['status'] === 'rejected' && $o['review_note']): ?><div class="text-danger mt-1"><i class="bi bi-chat-left-text"></i> <?= e($o['review_note']) ?></div><?php endif; ?>
                        <?php if ($o['status'] === 'approved' && $o['review_note']): ?><div class="text-muted mt-1"><i class="bi bi-chat-left-text"></i> <?= e($o['review_note']) ?></div><?php endif; ?>
                    </td>
                    <td><?= e(Promo::label($o)) ?><?php if ($o['max_discount'] !== null && $o['type'] === 'percent'): ?><div class="text-muted"><?= e(__('coupon_max', ['amount' => money($o['max_discount'])])) ?></div><?php endif; ?></td>
                    <td><?= e(OfferForm::scopeText($o)) ?></td>
                    <td><?= e(OfferForm::fundedText($o['funded_by'], $o['vendor_share_percent'])) ?></td>
                    <td class="text-nowrap"><?= $o['starts_at'] ? e(dt($o['starts_at'], 'd M y')) : '—' ?> → <?= $o['ends_at'] ? e(dt($o['ends_at'], 'd M y')) : '∞' ?></td>
                    <td><span class="badge text-bg-<?= $stColor ?>"><?= e($stLabel) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ($canAct && $o['status'] === 'approved'): ?>
                            <form method="post" action="<?= e(url('vendor-panel/offers/' . $id . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" title="<?= e(__((int) $o['is_active'] ? 'pause' : 'resume')) ?>"><i class="bi <?= (int) $o['is_active'] ? 'bi-pause' : 'bi-play' ?>"></i></button>
                            </form>
                        <?php elseif ($canAct): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('vendor-panel/offers/' . $id . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('vendor-panel/offers/' . $id . '/delete')) ?>" class="d-inline" data-confirm="<?= e(__('confirm_delete_named', ['name' => $o['title_en']])) ?>" data-confirm-danger="1"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" title="<?= e(__('delete')) ?>"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($flash): ?>
    <h2 class="h6 fw-bold"><i class="bi bi-lightning-charge"></i> <?= e(__('my_flash_items')) ?></h2>
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead class="table-light"><tr><th><?= e(__('product')) ?></th><th><?= e(__('flash_sale')) ?></th><th class="text-end"><?= e(__('sale_price')) ?></th><th class="text-center"><?= e(__('sold')) ?></th><th><?= e(__('cost_bearer')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($flash as $f): ?>
                    <tr>
                        <td><?= e(tr($f['name_bn'], $f['name_en'])) ?><?= $f['variant_en'] ? ' — ' . e($f['variant_en']) : '' ?></td>
                        <td><?= e(tr($f['title_bn'], $f['title_en'])) ?><div class="text-muted"><?= e(dt($f['starts_at'], 'd M, h:i A')) ?> → <?= e(dt($f['ends_at'], 'd M, h:i A')) ?></div></td>
                        <td class="text-end"><?= e(money($f['sale_price'])) ?> <s class="text-muted"><?= e(money($f['variant_en'] ? $f['v_price'] : $f['price'])) ?></s></td>
                        <td class="text-center"><?= e(num($f['sold_qty'])) ?><?= $f['quantity_limit'] !== null ? ' / ' . e(num($f['quantity_limit'])) : '' ?></td>
                        <td><?= e(OfferForm::fundedText($f['funded_by'], $f['vendor_share_percent'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($platform): ?>
    <h2 class="h6 fw-bold"><i class="bi bi-megaphone"></i> <?= e(__('platform_offers_running')) ?></h2>
    <div class="card border-0 shadow-sm">
        <ul class="list-group list-group-flush small">
            <?php foreach ($platform as $o): ?>
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                    <span><b><?= e(Promo::title($o)) ?></b> · <?= e(Promo::label($o)) ?> · <span class="text-muted"><?= e(OfferForm::scopeText($o)) ?></span></span>
                    <span class="text-muted"><?= e(__('cost_bearer')) ?>: <?= e(OfferForm::fundedText($o['funded_by'], $o['vendor_share_percent'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
