<?php
/** Page builder overview. Vars: $page, $pages (key => label), $byPos (position => sections), $counts, $viewUrl */
$types = Section::types();
$canAdd = can('page_builder.create');
$canEdit = can('page_builder.edit');
$canDel = can('page_builder.delete');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-grid-1x2"></i> <?= e(__('page_builder')) ?></h1>
    <?php if ($viewUrl): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('pb_view_page')) ?></a><?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold small"><?= e(__('pb_choose_page')) ?></div>
            <div class="list-group list-group-flush small">
                <?php foreach ($pages as $k => $label): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= $k === $page ? ' active' : '' ?>" href="<?= e(url('admin/page-builder?page=' . rawurlencode($k))) ?>">
                        <span><?= e($label) ?></span><?php if (!empty($counts[$k])): ?><span class="badge <?= $k === $page ? 'bg-light text-dark' : 'text-bg-secondary' ?>"><?= e(num($counts[$k])) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-white small text-muted"><?= e(__('pb_pages_help')) ?><?php if (can('pages.create')): ?> <a href="<?= e(url('admin/pages/create')) ?>"><?= e(__('pb_new_page')) ?></a><?php endif; ?></div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="alert alert-light border small mb-3"><i class="bi bi-info-circle"></i> <?= e(__('pb_help_' . (str_starts_with($page, 'page:') ? 'cms' : $page))) ?></div>
        <?php foreach ($byPos as $pos => $list): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold small"><i class="bi bi-layout-split"></i> <?= e(__('pb_pos_' . $pos)) ?></span>
                    <?php if ($canAdd): ?><a class="btn btn-brand btn-sm" href="<?= e(url('admin/page-builder/create?' . http_build_query(['page' => $page, 'position' => $pos]))) ?>"><i class="bi bi-plus-lg"></i> <?= e(__('pb_add_section')) ?></a><?php endif; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!$list): ?><li class="list-group-item small text-muted"><?= e(__('pb_empty_pos')) ?></li><?php endif; ?>
                    <?php foreach ($list as $i => $s):
                        $sid = (int) $s['id'];
                        $live = (int) $s['is_active'] && (!$s['starts_at'] || $s['starts_at'] <= date('Y-m-d H:i:s')) && (!$s['ends_at'] || $s['ends_at'] >= date('Y-m-d H:i:s')); ?>
                        <li class="list-group-item d-flex flex-wrap align-items-center gap-2 <?= (int) $s['is_active'] ? '' : 'bg-light text-muted' ?>" id="s<?= $sid ?>">
                            <?php if ($canEdit): ?>
                                <div class="d-flex flex-column">
                                    <form method="post" action="<?= e(url('admin/page-builder/' . $sid . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-link p-0 lh-1" <?= $i === 0 ? 'disabled' : '' ?> title="<?= e(__('appr_up')) ?>"><i class="bi bi-chevron-up"></i></button></form>
                                    <form method="post" action="<?= e(url('admin/page-builder/' . $sid . '/move')) ?>"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-link p-0 lh-1" <?= $i === count($list) - 1 ? 'disabled' : '' ?> title="<?= e(__('appr_down')) ?>"><i class="bi bi-chevron-down"></i></button></form>
                                </div>
                            <?php endif; ?>
                            <span class="fs-4 text-secondary"><i class="bi <?= e($types[$s['type']][0] ?? 'bi-square') ?>"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate"><?= e($s['admin_label'] ?: (Section::title($s) ?: __('pb_type_' . $s['type']))) ?></div>
                                <div class="small text-muted"><?= e(__('pb_type_' . $s['type'])) ?>
                                    <?php if ($s['starts_at'] || $s['ends_at']): ?> · <i class="bi bi-calendar-event"></i> <?= e($s['starts_at'] ? dt($s['starts_at'], 'd M') : '…') ?> – <?= e($s['ends_at'] ? dt($s['ends_at'], 'd M') : '…') ?><?php endif; ?>
                                </div>
                            </div>
                            <span class="badge text-bg-<?= $live ? 'success' : ((int) $s['is_active'] ? 'warning' : 'secondary') ?>"><?= e(__($live ? 'pb_live' : ((int) $s['is_active'] ? 'pb_scheduled' : 'pb_hidden'))) ?></span>
                            <div class="text-nowrap">
                                <?php if ($canEdit): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/page-builder/' . $sid . '/edit')) ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="<?= e(url('admin/page-builder/' . $sid . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" title="<?= e(__((int) $s['is_active'] ? 'pb_hide' : 'pb_show')) ?>"><i class="bi <?= (int) $s['is_active'] ? 'bi-eye' : 'bi-eye-slash' ?>"></i></button></form>
                                <?php endif; ?>
                                <?php if ($canAdd): ?><form method="post" action="<?= e(url('admin/page-builder/' . $sid . '/duplicate')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" title="<?= e(__('pb_duplicate')) ?>"><i class="bi bi-copy"></i></button></form><?php endif; ?>
                                <?php if ($canDel): ?><form method="post" action="<?= e(url('admin/page-builder/' . $sid . '/delete')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="<?= e(__('delete')) ?>" data-confirm-click="<?= e(__('pb_delete_confirm')) ?>"><i class="bi bi-trash"></i></button></form><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>
