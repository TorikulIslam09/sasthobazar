<?php /** Blog list. Vars: $posts, $pager, $status, $q */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-richtext"></i> <?= e(__('cms')) ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('blog')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('view_page')) ?></a>
        <?php if (can('pages.create')): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/blog/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_post')) ?></a><?php endif; ?>
    </div>
</div>
<?= View::partial('admin/cms/_nav', ['active' => 'blog']) ?>
<form class="mb-3 d-flex flex-wrap gap-2" method="get">
    <input class="form-control form-control-sm" style="max-width: 260px" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('search')) ?>" aria-label="<?= e(__('search')) ?>">
    <select class="form-select form-select-sm" style="max-width: 160px" name="status" aria-label="<?= e(__('status')) ?>">
        <option value=""><?= e(__('all')) ?></option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>><?= e(__('post_published')) ?></option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>><?= e(__('post_draft')) ?></option>
    </select>
    <button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th style="width: 70px"></th><th><?= e(__('title')) ?></th><th><?= e(__('category')) ?></th><th><?= e(__('status')) ?></th><th class="text-center"><?= e(__('views')) ?></th><th class="text-end"><?= e(__('actions')) ?></th></tr></thead>
            <tbody>
            <?php if (!$posts): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($posts as $p): $live = $p['status'] === 'published' && $p['published_at'] && $p['published_at'] <= date('Y-m-d H:i:s'); ?>
                <tr>
                    <td><?php if ($p['image']): ?><img src="<?= e(url($p['image'])) ?>" alt="" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px"><?php else: ?><span class="text-muted"><i class="bi bi-image fs-4"></i></span><?php endif; ?></td>
                    <td><div class="fw-semibold"><?= e(tr($p['title_bn'], $p['title_en'])) ?></div><a class="text-muted" href="<?= e(url('blog/' . $p['slug'])) ?>" target="_blank" rel="noopener">/blog/<?= e($p['slug']) ?></a></td>
                    <td><?= e(__('blogcat_' . $p['category'])) ?></td>
                    <td><span class="badge text-bg-<?= $live ? 'success' : ($p['status'] === 'published' ? 'info' : 'secondary') ?>"><?= e($live ? __('post_published') : ($p['status'] === 'published' ? __('offer_scheduled') : __('post_draft'))) ?></span>
                        <?php if ($p['published_at']): ?><div class="text-muted"><?= e(dt($p['published_at'], 'd M Y')) ?></div><?php endif; ?></td>
                    <td class="text-center"><?= e(num((int) $p['views'])) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('pages.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/blog/' . (int) $p['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php if (can('pages.delete')): ?><?= View::partial('partials/delete_button', ['action' => url('admin/blog/' . (int) $p['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $p['title_en']])]) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
