<?php /** Pages list. Vars: $pages */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-file-earmark-text"></i> <?= e(__('cms')) ?></h1>
    <?php if (can('pages.create')): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/pages/create')) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_page')) ?></a><?php endif; ?>
</div>
<?= View::partial('admin/cms/_nav', ['active' => 'pages']) ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th><?= e(__('title')) ?></th><th><?= e(__('url')) ?></th><th class="text-center"><?= e(__('bangla')) ?></th><th class="text-center"><?= e(__('in_footer')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('updated')) ?></th><th class="text-end"><?= e(__('actions')) ?></th></tr></thead>
            <tbody>
            <?php if (!$pages): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($pages as $p): ?>
                <tr>
                    <td class="fw-semibold"><?= e(tr($p['title_bn'], $p['title_en'])) ?><?php if (in_array($p['slug'], AdminPageController::CORE, true)): ?> <span class="badge text-bg-light border"><?= e(__('core_page')) ?></span><?php endif; ?></td>
                    <td><a href="<?= e(url('page/' . $p['slug'])) ?>" target="_blank" rel="noopener">/page/<?= e($p['slug']) ?> <i class="bi bi-box-arrow-up-right"></i></a></td>
                    <td class="text-center"><?= (int) $p['len_bn'] > 0 ? '<i class="bi bi-check2 text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?></td>
                    <td class="text-center"><?= (int) $p['show_in_footer'] ? '<i class="bi bi-check2 text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?></td>
                    <td><span class="badge text-bg-<?= (int) $p['is_active'] ? 'success' : 'secondary' ?>"><?= e(__((int) $p['is_active'] ? 'active' : 'inactive')) ?></span><?php if ((int) $p['len_en'] < 40): ?> <span class="badge text-bg-warning"><?= e(__('page_needs_content')) ?></span><?php endif; ?></td>
                    <td class="text-nowrap"><?= e(dt($p['updated_at'], 'd M Y')) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('pages.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/pages/' . (int) $p['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php if (can('pages.delete')): ?><?= View::partial('partials/delete_button', ['action' => url('admin/pages/' . (int) $p['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $p['title_en']])]) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
