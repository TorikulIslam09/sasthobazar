<?php /** FAQ. Vars: $groups (category => rows), $q */ $n = 0; ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <span><?= e(__('faq_title')) ?></span></nav>
<div class="sb-block">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0"><i class="bi bi-question-circle"></i> <?= e(__('faq_title')) ?></h1>
        <form method="get" class="d-flex gap-2" role="search" style="max-width: 360px; width: 100%">
            <input class="form-control form-control-sm" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('faq_search')) ?>" aria-label="<?= e(__('faq_search')) ?>">
            <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <?php if (!$groups): ?>
        <p class="text-muted"><?= e($q !== '' ? __('faq_no_match') : __('no_records')) ?></p>
    <?php endif; ?>
    <?php foreach ($groups as $cat => $rows): ?>
        <h2 class="h6 fw-bold text-uppercase text-muted mt-4 mb-2"><?= e(__('faqcat_' . $cat)) ?></h2>
        <div class="accordion sb-faq" id="faq-<?= e($cat) ?>">
            <?php foreach ($rows as $f): $n++; ?>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $n ?>" aria-expanded="false" aria-controls="faq<?= $n ?>"><?= e(tr($f['question_bn'], $f['question_en'])) ?></button>
                    </h3>
                    <div id="faq<?= $n ?>" class="accordion-collapse collapse" data-bs-parent="#faq-<?= e($cat) ?>">
                        <div class="accordion-body sb-prose"><?= Html::clean(nl2br(tr($f['answer_bn'], $f['answer_en']), false)) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <div class="sb-promo-hint mt-4 mb-0 justify-content-between flex-wrap">
        <span><i class="bi bi-headset"></i> <?= e(__('faq_still_need_help')) ?></span>
        <a class="btn btn-primary btn-sm" href="<?= e(url('account/support/new')) ?>"><?= e(__('open_ticket')) ?></a>
    </div>
</div>
