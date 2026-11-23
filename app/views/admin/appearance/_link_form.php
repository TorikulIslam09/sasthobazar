<?php
/** Menu link add / edit fields. Vars: $action, $m (values), $location, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f) => 'form-control form-control-sm' . (isset($errors[$f]) ? ' is-invalid' : '');
$v = static fn(string $f) => (string) ($m[$f] ?? '');
?>
<form method="post" action="<?= e($action) ?>" class="row g-2 align-items-start" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="location" value="<?= e($location) ?>">
    <div class="col-md-3"><input class="<?= $cls('label_bn') ?>" name="label_bn" value="<?= e($v('label_bn')) ?>" maxlength="100" placeholder="<?= e(__('appr_label')) ?> (বাংলা)" aria-label="<?= e(__('appr_label')) ?> (বাংলা)"></div>
    <div class="col-md-3"><input class="<?= $cls('label_en') ?>" name="label_en" value="<?= e($v('label_en')) ?>" maxlength="100" placeholder="<?= e(__('appr_label')) ?> (English)" aria-label="<?= e(__('appr_label')) ?> (English)"><?= $err('label_en') ?></div>
    <div class="col-md-3"><input class="<?= $cls('url') ?> font-monospace" name="url" value="<?= e($v('url')) ?>" maxlength="255" placeholder="products / page/about-us / https://…" aria-label="<?= e(__('appr_link')) ?>" required><?= $err('url') ?></div>
    <div class="col-md-1"><input class="<?= $cls('icon') ?> font-monospace" name="icon" value="<?= e($v('icon')) ?>" maxlength="50" placeholder="bi-…" aria-label="<?= e(__('icon')) ?>" title="<?= e(__('vt_icon_help')) ?>"><?= $err('icon') ?></div>
    <div class="col-md-1 pt-1"><div class="form-check"><input class="form-check-input" type="checkbox" name="new_tab" value="1" id="nt<?= e(md5($action)) ?>" <?= (int) ($m['new_tab'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label small" for="nt<?= e(md5($action)) ?>"><?= e(__('appr_new_tab')) ?></label></div></div>
    <div class="col-md-1"><button class="btn btn-brand btn-sm w-100"><?= e(__('save')) ?></button></div>
</form>
