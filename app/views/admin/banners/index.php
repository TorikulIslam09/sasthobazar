<?php /** Banners list. Vars: $banners, $position, $counts */
$now = date('Y-m-d H:i:s');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-images"></i> <?= e(__('banners')) ?></h1>
    <?php if (can('banners.create')): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/banners/create' . ($position ? '?position=' . $position : ''))) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('add_banner')) ?></a><?php endif; ?>
</div>
<ul class="nav nav-tabs mb-3 small">
    <li class="nav-item"><a class="nav-link<?= $position === '' ? ' active' : '' ?>" href="<?= e(url('admin/banners')) ?>"><?= e(__('all')) ?></a></li>
    <?php foreach (Banner::POSITIONS as $p): ?>
        <li class="nav-item"><a class="nav-link<?= $position === $p ? ' active' : '' ?>" href="<?= e(url('admin/banners?position=' . $p)) ?>"><?= e(__('bpos_' . $p)) ?> <span class="text-muted">(<?= e(num((int) ($counts[$p] ?? 0))) ?>)</span></a></li>
    <?php endforeach; ?>
</ul>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr>
                <th style="width: 140px"><?= e(__('image')) ?></th><th><?= e(__('title')) ?></th><th><?= e(__('position')) ?></th>
                <th><?= e(__('language')) ?></th><th><?= e(__('schedule')) ?></th><th class="text-center"><?= e(__('sort_order')) ?></th><th><?= e(__('status')) ?></th><th class="text-end"><?= e(__('actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$banners): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('no_records')) ?></td></tr><?php endif; ?>
            <?php foreach ($banners as $b):
                $state = !(int) $b['is_active'] ? [__('inactive'), 'secondary']
                    : (($b['ends_at'] && $b['ends_at'] <= $now) ? [__('offer_expired'), 'dark'] : (($b['starts_at'] && $b['starts_at'] > $now) ? [__('offer_scheduled'), 'info'] : [__('active'), 'success'])); ?>
                <tr>
                    <td><?php if ($b['image']): ?><img src="<?= e(url($b['image'])) ?>" alt="" style="width: 120px; height: 48px; object-fit: cover; border-radius: 6px"><?php else: ?><span class="text-muted"><i class="bi bi-card-text"></i> <?= e(__('text_only')) ?></span><?php endif; ?></td>
                    <td><div class="fw-semibold"><?= e($b['title'] ?: '—') ?></div><?php if ($b['link_url']): ?><div class="text-muted text-truncate" style="max-width: 260px"><i class="bi bi-link-45deg"></i> <?= e($b['link_url']) ?></div><?php endif; ?></td>
                    <td><?= e(__('bpos_' . $b['position'])) ?></td>
                    <td><?= e(__('blang_' . $b['lang'])) ?></td>
                    <td class="text-nowrap"><?= $b['starts_at'] ? e(dt($b['starts_at'], 'd M y')) : '—' ?> → <?= $b['ends_at'] ? e(dt($b['ends_at'], 'd M y')) : '∞' ?></td>
                    <td class="text-center"><?= e(num($b['sort_order'])) ?></td>
                    <td><span class="badge text-bg-<?= $state[1] ?>"><?= e($state[0]) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('banners.edit')): ?>
                            <form method="post" action="<?= e(url('admin/banners/' . (int) $b['id'] . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" title="<?= e(__((int) $b['is_active'] ? 'pause' : 'resume')) ?>"><i class="bi <?= (int) $b['is_active'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i></button>
                            </form>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/banners/' . (int) $b['id'] . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if (can('banners.delete')): ?>
                            <?= View::partial('partials/delete_button', ['action' => url('admin/banners/' . (int) $b['id'] . '/delete'), 'message' => __('confirm_delete_named', ['name' => $b['title'] ?: __('banner')])]) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-3"><i class="bi bi-info-circle"></i> <?= e(__('banner_sizes_help')) ?></p>
