<?php /** Address book. Vars: $tab, $addresses */ ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account')) ?>"><?= e(__('my_account')) ?></a> › <span><?= e(__('my_addresses')) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => $tab]) ?>
    <section class="sb-block min-w-0">
        <div class="sb-sec-head">
            <h1 class="h5 mb-0"><?= e(__('my_addresses')) ?> <span class="text-muted fw-normal small">(<?= e(num(count($addresses))) ?>/<?= e(num(Address::MAX_PER_USER)) ?>)</span></h1>
            <?php if (count($addresses) < Address::MAX_PER_USER): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('account/addresses/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('addr_add')) ?></a>
            <?php endif; ?>
        </div>

        <?php if (!$addresses): ?>
            <div class="sb-empty">
                <i class="bi bi-geo-alt"></i>
                <p><?= e(__('addr_none')) ?></p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('account/addresses/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('addr_add')) ?></a>
            </div>
        <?php else: ?>
            <div class="sb-addr-grid">
                <?php foreach ($addresses as $a): ?>
                    <div class="sb-addr<?= (int) $a['is_default'] ? ' is-default' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="badge text-bg-light border"><i class="bi <?= $a['label'] === 'office' ? 'bi-building' : ($a['label'] === 'home' ? 'bi-house' : 'bi-geo') ?>"></i> <?= e(Address::labelText($a['label'])) ?></span>
                            <?php if ((int) $a['is_default']): ?><span class="badge bg-primary"><?= e(__('addr_default')) ?></span><?php endif; ?>
                        </div>
                        <div class="fw-semibold mt-2"><?= e($a['recipient_name']) ?></div>
                        <div class="small"><i class="bi bi-telephone"></i> <?= e(num($a['phone'])) ?><?= $a['alt_phone'] ? ', ' . e(num($a['alt_phone'])) : '' ?></div>
                        <div class="small text-muted mt-1"><?= e($a['address_line']) ?><br><?= e(Address::areaLine($a)) ?><?= $a['postcode'] ? ' - ' . e(num($a['postcode'])) : '' ?></div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('account/addresses/' . (int) $a['id'] . '/edit')) ?>"><i class="bi bi-pencil"></i> <?= e(__('edit')) ?></a>
                            <?php if (!(int) $a['is_default']): ?>
                                <form method="post" action="<?= e(url('account/addresses/' . (int) $a['id'] . '/default')) ?>"><?= csrf_field() ?>
                                    <button class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-circle"></i> <?= e(__('addr_make_default')) ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('account/addresses/' . (int) $a['id'] . '/delete')) ?>"
                                  data-confirm="<?= e(__('addr_confirm_delete')) ?>" data-confirm-danger="1"><?= csrf_field() ?>
                                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> <?= e(__('delete')) ?></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
