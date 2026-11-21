<?php
/** Banner / popup form. Vars: $banner (null = new), $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$action = $banner ? url('admin/banners/' . (int) $banner['id']) : url('admin/banners');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-images"></i> <?= e($title) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/banners')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
</div>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card border-0 shadow-sm" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label" for="pos"><?= e(__('position')) ?> *</label>
            <select class="<?= $cls('position', 'form-select') ?>" id="pos" name="position">
                <?php foreach (Banner::POSITIONS as $p): ?><option value="<?= $p ?>" <?= $val('position') === $p ? 'selected' : '' ?>><?= e(__('bpos_' . $p)) ?></option><?php endforeach; ?>
            </select>
            <?= $err('position') ?>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="lang"><?= e(__('language')) ?></label>
            <select class="form-select" id="lang" name="lang">
                <?php foreach (Banner::LANGS as $l): ?><option value="<?= $l ?>" <?= $val('lang') === $l ? 'selected' : '' ?>><?= e(__('blang_' . $l)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="so"><?= e(__('sort_order')) ?></label>
            <input class="<?= $cls('sort_order') ?>" id="so" name="sort_order" value="<?= e($val('sort_order')) ?>" inputmode="numeric">
            <?= $err('sort_order') ?>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="t"><?= e(__('title_en')) ?></label>
            <input class="<?= $cls('title') ?>" id="t" name="title" value="<?= e($val('title')) ?>" maxlength="200">
            <?= $err('title') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="tb"><?= e(__('title_bn')) ?></label>
            <input class="<?= $cls('title_bn') ?>" id="tb" name="title_bn" value="<?= e($val('title_bn')) ?>" maxlength="200" lang="bn">
            <?= $err('title_bn') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="st"><?= e(__('subtitle_en')) ?></label>
            <input class="<?= $cls('subtitle') ?>" id="st" name="subtitle" value="<?= e($val('subtitle')) ?>" maxlength="255">
            <?= $err('subtitle') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="sb"><?= e(__('subtitle_bn')) ?></label>
            <input class="<?= $cls('subtitle_bn') ?>" id="sb" name="subtitle_bn" value="<?= e($val('subtitle_bn')) ?>" maxlength="255" lang="bn">
            <?= $err('subtitle_bn') ?>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="lk"><?= e(__('link_url')) ?></label>
            <input class="<?= $cls('link_url') ?>" id="lk" name="link_url" value="<?= e($val('link_url')) ?>" maxlength="255" placeholder="products?sort=discount">
            <div class="form-text"><?= e(__('banner_link_help')) ?></div>
            <?= $err('link_url') ?>
        </div>
        <div class="col-md-4" data-show-when="position:popup home_middle home_bottom">
            <label class="form-label" for="bt"><?= e(__('button_text')) ?></label>
            <input class="<?= $cls('button_text') ?>" id="bt" name="button_text" value="<?= e($val('button_text')) ?>" maxlength="60" placeholder="<?= e(__('shop_now')) ?>">
            <?= $err('button_text') ?>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="img"><?= e(__('image')) ?> <span data-show-when="position:home_slider home_middle home_bottom category_top sidebar">*</span></label>
            <input class="<?= $cls('image') ?>" type="file" id="img" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="form-text"><?= e(__('banner_image_help')) ?></div>
            <?= $err('image') ?>
            <?php if (!empty($banner['image'])): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <img src="<?= e(url($banner['image'])) ?>" alt="" style="height: 60px; border-radius: 6px">
                    <div class="form-check" data-show-when="position:popup"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="ri"><label class="form-check-label small" for="ri"><?= e(__('remove')) ?></label></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-6" data-show-when="position:home_slider home_middle home_bottom category_top">
            <label class="form-label" for="imgm"><?= e(__('image_mobile')) ?></label>
            <input class="<?= $cls('image_mobile') ?>" type="file" id="imgm" name="image_mobile" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="form-text"><?= e(__('banner_mobile_help')) ?></div>
            <?= $err('image_mobile') ?>
            <?php if (!empty($banner['image_mobile'])): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                    <img src="<?= e(url($banner['image_mobile'])) ?>" alt="" style="height: 60px; border-radius: 6px">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_mobile" value="1" id="rm"><label class="form-check-label small" for="rm"><?= e(__('remove')) ?></label></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-6 col-md-3" data-show-when="position:popup">
            <label class="form-label" for="pd"><?= e(__('popup_delay')) ?></label>
            <input class="<?= $cls('popup_delay') ?>" id="pd" name="popup_delay" value="<?= e($val('popup_delay')) ?>" inputmode="numeric">
            <div class="form-text"><?= e(__('popup_delay_help')) ?></div>
            <?= $err('popup_delay') ?>
        </div>

        <div class="col-6 col-md-3">
            <label class="form-label" for="sa"><?= e(__('coupon_starts')) ?></label>
            <input class="<?= $cls('starts_at') ?>" type="datetime-local" id="sa" name="starts_at" value="<?= e($val('starts_at')) ?>">
            <?= $err('starts_at') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="ea"><?= e(__('coupon_ends')) ?></label>
            <input class="<?= $cls('ends_at') ?>" type="datetime-local" id="ea" name="ends_at" value="<?= e($val('ends_at')) ?>">
            <?= $err('ends_at') ?>
        </div>
        <div class="col-6 col-md-3 d-flex align-items-end">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" <?= $val('is_active') ? 'checked' : '' ?>><label class="form-check-label" for="ia"><?= e(__('active')) ?></label></div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/banners')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
