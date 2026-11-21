<?php
/** FAQ list + form. Vars: $faqs, $edit, $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$canSave = can($edit ? 'pages.edit' : 'pages.create');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-question-circle"></i> <?= e(__('cms')) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('faq')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('view_page')) ?></a>
</div>
<?= View::partial('admin/cms/_nav', ['active' => 'faqs']) ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <ul class="list-group list-group-flush small">
                <?php if (!$faqs): ?><li class="list-group-item text-muted text-center py-4"><?= e(__('no_records')) ?></li><?php endif; ?>
                <?php $cat = null; foreach ($faqs as $f): ?>
                    <?php if ($cat !== $f['category']): $cat = $f['category']; ?><li class="list-group-item bg-light fw-semibold text-uppercase small"><?= e(__('faqcat_' . $cat)) ?></li><?php endif; ?>
                    <li class="list-group-item d-flex gap-2 align-items-start<?= $edit && (int) $edit['id'] === (int) $f['id'] ? ' bg-warning-subtle' : '' ?>">
                        <span class="text-muted"><?= e(num((int) $f['sort_order'])) ?>.</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold"><?= e(tr($f['question_bn'], $f['question_en'])) ?></div>
                            <div class="text-muted text-truncate"><?= e(Html::excerpt(tr($f['answer_bn'], $f['answer_en']), 120)) ?></div>
                        </div>
                        <?php if (!(int) $f['is_active']): ?><span class="badge text-bg-secondary"><?= e(__('inactive')) ?></span><?php endif; ?>
                        <?php if (can('pages.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/faqs?edit=' . (int) $f['id'])) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php if (can('pages.delete')): ?><?= View::partial('partials/delete_button', ['action' => url('admin/faqs/' . (int) $f['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $f['question_en']])]) ?><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <form method="post" action="<?= e(url('admin/faqs')) ?>" class="card border-0 shadow-sm" novalidate>
            <?= csrf_field() ?>
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
            <div class="card-header bg-white fw-semibold"><?= e(__($edit ? 'edit_faq' : 'add_faq')) ?></div>
            <div class="card-body row g-2">
                <div class="col-8">
                    <label class="form-label small" for="fc"><?= e(__('category')) ?></label>
                    <select class="form-select form-select-sm" id="fc" name="category">
                        <?php foreach (AdminFaqController::CATEGORIES as $c): ?><option value="<?= $c ?>" <?= $val('category') === $c ? 'selected' : '' ?>><?= e(__('faqcat_' . $c)) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4">
                    <label class="form-label small" for="fs"><?= e(__('sort_order')) ?></label>
                    <input class="form-control form-control-sm" id="fs" name="sort_order" value="<?= e($val('sort_order')) ?>" inputmode="numeric">
                </div>
                <div class="col-12">
                    <label class="form-label small" for="qe"><?= e(__('question_en')) ?> *</label>
                    <input class="<?= $cls('question_en', 'form-control form-control-sm') ?>" id="qe" name="question_en" value="<?= e($val('question_en')) ?>" maxlength="255">
                    <?= $err('question_en') ?>
                </div>
                <div class="col-12">
                    <label class="form-label small" for="ae"><?= e(__('answer_en')) ?> *</label>
                    <textarea class="<?= $cls('answer_en', 'form-control form-control-sm') ?>" id="ae" name="answer_en" rows="3" maxlength="5000"><?= e($val('answer_en')) ?></textarea>
                    <?= $err('answer_en') ?>
                </div>
                <div class="col-12">
                    <label class="form-label small" for="qb"><?= e(__('question_bn')) ?></label>
                    <input class="form-control form-control-sm" id="qb" name="question_bn" value="<?= e($val('question_bn')) ?>" maxlength="255" lang="bn">
                </div>
                <div class="col-12">
                    <label class="form-label small" for="ab"><?= e(__('answer_bn')) ?></label>
                    <textarea class="form-control form-control-sm" id="ab" name="answer_bn" rows="3" maxlength="5000" lang="bn"><?= e($val('answer_bn')) ?></textarea>
                </div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="fa" <?= $val('is_active') ? 'checked' : '' ?>><label class="form-check-label small" for="fa"><?= e(__('active')) ?></label></div></div>
            </div>
            <?php if ($canSave): ?>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-brand btn-sm"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
                    <?php if ($edit): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/faqs')) ?>"><?= e(__('cancel')) ?></a><?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
