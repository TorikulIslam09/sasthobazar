<?php
/** Page form. Vars: $page (null = new), $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$core = $page && in_array($page['slug'], AdminPageController::CORE, true);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-file-earmark-text"></i> <?= e($title) ?></h1>
    <div class="d-flex gap-2">
        <?php if ($page): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('page/' . $page['slug'])) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('view_page')) ?></a><?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/pages')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
    </div>
</div>
<form method="post" action="<?= e($page ? url('admin/pages/' . (int) $page['id']) : url('admin/pages')) ?>" class="card border-0 shadow-sm" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-5">
            <label class="form-label" for="ten"><?= e(__('title_en')) ?> *</label>
            <input class="<?= $cls('title_en') ?>" id="ten" name="title_en" value="<?= e($val('title_en')) ?>" maxlength="200" required>
            <?= $err('title_en') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tbn"><?= e(__('title_bn')) ?></label>
            <input class="<?= $cls('title_bn') ?>" id="tbn" name="title_bn" value="<?= e($val('title_bn')) ?>" maxlength="200" lang="bn">
            <?= $err('title_bn') ?>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="slug"><?= e(__('url_slug')) ?></label>
            <div class="input-group"><span class="input-group-text small">/page/</span>
                <input class="<?= $cls('slug') ?>" id="slug" name="slug" value="<?= e($val('slug')) ?>" maxlength="180" <?= $core ? 'readonly' : '' ?> placeholder="about-us"></div>
            <?= $err('slug') ?>
        </div>
        <div class="col-12">
            <ul class="nav nav-tabs small" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#tabEn" role="tab">English *</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabBn" role="tab">বাংলা</button></li>
            </ul>
            <div class="tab-content border border-top-0 rounded-bottom p-3">
                <div class="tab-pane fade show active" id="tabEn" role="tabpanel">
                    <?= View::partial('admin/cms/_editor', ['name' => 'content_en', 'id' => 'cen', 'value' => $val('content_en'), 'label' => __('content_en'), 'error' => $errors['content_en'] ?? null]) ?>
                </div>
                <div class="tab-pane fade" id="tabBn" role="tabpanel">
                    <?= View::partial('admin/cms/_editor', ['name' => 'content_bn', 'id' => 'cbn', 'value' => $val('content_bn'), 'label' => __('content_bn'), 'lang' => 'bn']) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="mt"><?= e(__('meta_title')) ?></label>
            <input class="<?= $cls('meta_title') ?>" id="mt" name="meta_title" value="<?= e($val('meta_title')) ?>" maxlength="255">
            <?= $err('meta_title') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="md"><?= e(__('meta_description')) ?></label>
            <input class="<?= $cls('meta_description') ?>" id="md" name="meta_description" value="<?= e($val('meta_description')) ?>" maxlength="500">
            <?= $err('meta_description') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="so"><?= e(__('sort_order')) ?></label>
            <input class="<?= $cls('sort_order') ?>" id="so" name="sort_order" value="<?= e($val('sort_order')) ?>" inputmode="numeric">
        </div>
        <div class="col-md-6 d-flex align-items-end gap-4">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="show_in_footer" value="1" id="sf" <?= $val('show_in_footer') ? 'checked' : '' ?>><label class="form-check-label" for="sf"><?= e(__('show_in_footer')) ?></label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ia" <?= $val('is_active') ? 'checked' : '' ?>><label class="form-check-label" for="ia"><?= e(__('active')) ?></label></div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/pages')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
