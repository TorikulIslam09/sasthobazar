<?php /** Blog post. Vars: $post, $heading, $content (clean HTML), $related */ ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <a href="<?= e(url('blog')) ?>"><?= e(__('blog_title')) ?></a> › <span><?= e($heading) ?></span></nav>
<div class="sb-page-wrap">
    <article class="sb-block">
        <div class="sb-post-meta mb-1"><a href="<?= e(url('blog?category=' . $post['category'])) ?>"><?= e(__('blogcat_' . $post['category'])) ?></a> · <?= e(dt($post['published_at'], 'd M Y')) ?> · <?= e(__('n_views', ['n' => num((int) $post['views'])])) ?></div>
        <h1 class="h3 mb-3"><?= e($heading) ?></h1>
        <?php if ($post['image']): ?><img class="sb-post-cover" src="<?= e(url($post['image'])) ?>" alt="<?= e($heading) ?>"><?php endif; ?>
        <div class="sb-prose"><?= $content ?></div>
        <?php if ($post['tags']): ?>
            <div class="mt-3 d-flex flex-wrap gap-1"><?php foreach (array_filter(array_map('trim', explode(',', $post['tags']))) as $tag): ?><span class="badge text-bg-light border"># <?= e($tag) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="alert alert-light border small mt-4 mb-0"><i class="bi bi-info-circle"></i> <?= e(__('blog_disclaimer')) ?></div>
    </article>
    <aside class="sb-block align-self-start">
        <h2 class="h6 fw-bold mb-3"><?= e(__('related_posts')) ?></h2>
        <?php if (!$related): ?><p class="small text-muted mb-0"><?= e(__('no_records')) ?></p><?php endif; ?>
        <?php foreach ($related as $r): ?>
            <a href="<?= e(url('blog/' . $r['slug'])) ?>" class="d-flex gap-2 mb-3 text-decoration-none text-dark">
                <span style="width: 70px; flex: none"><?php if ($r['image']): ?><img src="<?= e(url($r['image'])) ?>" alt="" style="width: 70px; height: 50px; object-fit: cover; border-radius: 6px" loading="lazy"><?php else: ?><span class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 70px; height: 50px"><i class="bi bi-heart-pulse text-success"></i></span><?php endif; ?></span>
                <span class="small"><span class="fw-semibold d-block"><?= e(tr($r['title_bn'], $r['title_en'])) ?></span><span class="text-muted"><?= e(dt($r['published_at'], 'd M Y')) ?></span></span>
            </a>
        <?php endforeach; ?>
        <a class="btn btn-outline-primary btn-sm w-100" href="<?= e(url('blog')) ?>"><?= e(__('all_posts')) ?></a>
    </aside>
</div>
