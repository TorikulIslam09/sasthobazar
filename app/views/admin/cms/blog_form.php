<?php
/** Blog post form. Vars: $post (null = new), $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-richtext"></i> <?= e($title) ?></h1>
    <div class="d-flex gap-2">
        <?php if ($post): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('blog/' . $post['slug'])) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('view_page')) ?></a><?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/blog')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
    </div>
</div>
<form method="post" action="<?= e($post ? url('admin/blog/' . (int) $post['id']) : url('admin/blog')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="ten"><?= e(__('title_en')) ?> *</label>
                        <input class="<?= $cls('title_en') ?>" id="ten" name="title_en" value="<?= e($val('title_en')) ?>" maxlength="200" required>
                        <?= $err('title_en') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tbn"><?= e(__('title_bn')) ?></label>
                        <input class="<?= $cls('title_bn') ?>" id="tbn" name="title_bn" value="<?= e($val('title_bn')) ?>" maxlength="200" lang="bn">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="xe"><?= e(__('excerpt_en')) ?></label>
                        <textarea class="<?= $cls('excerpt_en') ?>" id="xe" name="excerpt_en" rows="2" maxlength="500"><?= e($val('excerpt_en')) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="xb"><?= e(__('excerpt_bn')) ?></label>
                        <textarea class="<?= $cls('excerpt_bn') ?>" id="xb" name="excerpt_bn" rows="2" maxlength="500" lang="bn"><?= e($val('excerpt_bn')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <ul class="nav nav-tabs small" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#tabEn" role="tab">English *</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tabBn" role="tab">বাংলা</button></li>
                        </ul>
                        <div class="tab-content border border-top-0 rounded-bottom p-3">
                            <div class="tab-pane fade show active" id="tabEn" role="tabpanel">
                                <?= View::partial('admin/cms/_editor', ['name' => 'content_en', 'id' => 'cen', 'value' => $val('content_en'), 'label' => __('content_en'), 'error' => $errors['content_en'] ?? null, 'rows' => 18]) ?>
                            </div>
                            <div class="tab-pane fade" id="tabBn" role="tabpanel">
                                <?= View::partial('admin/cms/_editor', ['name' => 'content_bn', 'id' => 'cbn', 'value' => $val('content_bn'), 'label' => __('content_bn'), 'lang' => 'bn', 'rows' => 18]) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="st"><?= e(__('status')) ?></label>
                        <select class="form-select" id="st" name="status">
                            <option value="draft" <?= $val('status') !== 'published' ? 'selected' : '' ?>><?= e(__('post_draft')) ?></option>
                            <option value="published" <?= $val('status') === 'published' ? 'selected' : '' ?>><?= e(__('post_published')) ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pa"><?= e(__('publish_at')) ?></label>
                        <input class="<?= $cls('published_at') ?>" type="datetime-local" id="pa" name="published_at" value="<?= e($val('published_at')) ?>">
                        <div class="form-text"><?= e(__('publish_at_help')) ?></div>
                        <?= $err('published_at') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ca"><?= e(__('category')) ?></label>
                        <select class="form-select" id="ca" name="category">
                            <?php foreach (AdminBlogController::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $val('category') === $c ? 'selected' : '' ?>><?= e(__('blogcat_' . $c)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tg"><?= e(__('tags')) ?></label>
                        <input class="<?= $cls('tags') ?>" id="tg" name="tags" value="<?= e($val('tags')) ?>" maxlength="255" placeholder="diabetes, diet">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="sl"><?= e(__('url_slug')) ?></label>
                        <div class="input-group input-group-sm"><span class="input-group-text">/blog/</span><input class="<?= $cls('slug') ?>" id="sl" name="slug" value="<?= e($val('slug')) ?>" maxlength="180"></div>
                        <div class="form-text"><?= e(__('slug_auto_help')) ?></div>
                        <?= $err('slug') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="im"><?= e(__('cover_image')) ?></label>
                        <input class="<?= $cls('image') ?>" type="file" id="im" name="image" accept="image/jpeg,image/png,image/webp">
                        <?= $err('image') ?>
                        <?php if (!empty($post['image'])): ?>
                            <img src="<?= e(url($post['image'])) ?>" alt="" class="img-fluid rounded mt-2">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="ri"><label class="form-check-label small" for="ri"><?= e(__('remove')) ?></label></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold small">SEO</div>
                <div class="card-body row g-2">
                    <div class="col-12"><label class="form-label small" for="mt"><?= e(__('meta_title')) ?></label><input class="form-control form-control-sm" id="mt" name="meta_title" value="<?= e($val('meta_title')) ?>" maxlength="255"></div>
                    <div class="col-12"><label class="form-label small" for="md"><?= e(__('meta_description')) ?></label><textarea class="form-control form-control-sm" id="md" name="meta_description" rows="2" maxlength="500"><?= e($val('meta_description')) ?></textarea></div>
                </div>
            </div>
            <button class="btn btn-brand w-100"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        </div>
    </div>
</form>
