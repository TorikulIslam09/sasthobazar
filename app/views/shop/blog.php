<?php /** Blog list. Vars: $posts, $pager, $category, $counts */ ?>
<nav class="sb-crumbs"><a href="<?= e(url('/')) ?>"><?= e(__('home')) ?></a> › <?php if ($category !== ''): ?><a href="<?= e(url('blog')) ?>"><?= e(__('blog_title')) ?></a> › <span><?= e(__('blogcat_' . $category)) ?></span><?php else: ?><span><?= e(__('blog_title')) ?></span><?php endif; ?></nav>
<div class="sb-block">
    <h1 class="h4 mb-1"><i class="bi bi-heart-pulse"></i> <?= e($category !== '' ? __('blogcat_' . $category) : __('blog_title')) ?></h1>
    <p class="text-muted small"><?= e(__('blog_intro')) ?></p>
    <div class="sb-chips mb-3">
        <a href="<?= e(url('blog')) ?>" class="<?= $category === '' ? 'on' : '' ?>"><?= e(__('all')) ?></a>
        <?php foreach (AdminBlogController::CATEGORIES as $c): if (empty($counts[$c])) { continue; } ?>
            <a href="<?= e(url('blog?category=' . $c)) ?>" class="<?= $category === $c ? 'on' : '' ?>"><?= e(__('blogcat_' . $c)) ?> <span class="text-muted">(<?= e(num((int) $counts[$c])) ?>)</span></a>
        <?php endforeach; ?>
    </div>
    <?php if (!$posts): ?><p class="text-muted"><?= e(__('blog_empty')) ?></p><?php endif; ?>
    <div class="sb-blog-grid">
        <?php foreach ($posts as $p): $t = tr($p['title_bn'], $p['title_en']); ?>
            <a class="sb-post-card" href="<?= e(url('blog/' . $p['slug'])) ?>">
                <span class="img"><?php if ($p['image']): ?><img src="<?= e(url($p['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-heart-pulse"></i><?php endif; ?></span>
                <span class="body">
                    <span class="sb-post-meta"><?= e(__('blogcat_' . $p['category'])) ?> · <?= e(dt($p['published_at'], 'd M Y')) ?></span>
                    <h3><?= e($t) ?></h3>
                    <span class="small text-muted"><?= e(tr($p['excerpt_bn'], (string) $p['excerpt_en']) ?: Html::excerpt(tr($p['content_bn'], (string) $p['content_en']), 130)) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="mt-3"><?= $pager->links() ?></div>
</div>
