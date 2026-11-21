<?php /** Static page. Vars: $page, $heading, $content (clean HTML), $siblings */ ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <span><?= e($heading) ?></span></nav>
<div class="sb-page-wrap">
    <article class="sb-block">
        <h1 class="h4 mb-3"><?= e($heading) ?></h1>
        <?php if (trim(strip_tags($content)) !== ''): ?>
            <div class="sb-prose"><?= $content ?></div>
        <?php else: ?>
            <p class="text-muted"><?= e(__('page_coming_soon')) ?></p>
        <?php endif; ?>
        <?php if ($page['slug'] === 'contact-us'): ?>
            <div class="row g-3 mt-2">
                <div class="col-sm-6"><div class="sb-promo-hint mb-0"><i class="bi bi-telephone"></i> <a href="tel:<?= e(setting('contact_phone', '')) ?>"><?= e(num((string) setting('contact_phone', ''))) ?></a></div></div>
                <div class="col-sm-6"><div class="sb-promo-hint mb-0"><i class="bi bi-envelope"></i> <a href="mailto:<?= e(setting('contact_email', '')) ?>"><?= e(setting('contact_email', '')) ?></a></div></div>
                <div class="col-12"><a class="btn btn-primary" href="<?= e(url('account/support/new')) ?>"><i class="bi bi-headset"></i> <?= e(__('open_ticket')) ?></a></div>
            </div>
        <?php endif; ?>
        <p class="small text-muted mt-4 mb-0"><?= e(__('last_updated', ['date' => dt($page['updated_at'] ?? $page['created_at'], 'd M Y')])) ?></p>
    </article>
    <aside class="sb-block sb-side-links align-self-start">
        <h2 class="h6 fw-bold mb-2"><?= e(__('information')) ?></h2>
        <?php foreach ($siblings as $s): ?>
            <a href="<?= e(url('page/' . $s['slug'])) ?>" class="<?= $s['slug'] === $page['slug'] ? 'on' : '' ?>"><?= e(tr($s['title_bn'], $s['title_en'])) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('faq')) ?>"><?= e(__('faq_title')) ?></a>
        <a href="<?= e(url('blog')) ?>"><?= e(__('blog_title')) ?></a>
    </aside>
</div>
