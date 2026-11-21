<?php /** Wishlist. Vars: $tab, $products, $unavailable, $wished */ ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('account')) ?>"><?= e(__('my_account')) ?></a> › <span><?= e(__('wishlist')) ?></span></nav>
<div class="sb-acc">
    <?= View::partial('account/_nav', ['tab' => $tab]) ?>
    <section class="sb-block min-w-0" data-wishlist-page>
        <div class="sb-sec-head">
            <h1 class="h5 mb-0"><?= e(__('wishlist')) ?> <span class="text-muted fw-normal small">(<?= e(num(count($products))) ?>)</span></h1>
        </div>
        <?php if ($unavailable > 0): ?>
            <p class="small text-muted"><i class="bi bi-info-circle"></i> <?= e(__('wishlist_unavailable', ['n' => num($unavailable)])) ?></p>
        <?php endif; ?>
        <?php if ($products): ?>
            <div class="sb-grid">
                <?php foreach ($products as $p): ?>
                    <?= View::partial('shop/_card', ['p' => $p, 'wished' => true]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="sb-empty" data-wishlist-empty <?= $products ? 'hidden' : '' ?>>
            <i class="bi bi-heart"></i>
            <p><?= e(__('wishlist_empty')) ?></p>
            <a class="btn btn-primary btn-sm" href="<?= e(url('products')) ?>"><?= e(__('shop_now')) ?></a>
        </div>
    </section>
</div>
