<?php /** Choose the type of the new section. Vars: $page, $pages, $position */ ?>
<div class="mb-3">
    <a href="<?= e(url('admin/page-builder?page=' . rawurlencode($page))) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('page_builder')) ?></a>
    <h1 class="h4 mb-0"><?= e(__('pb_add_section')) ?></h1>
    <div class="small text-muted"><?= e($pages[$page] ?? $page) ?> · <?= e(__('pb_pos_' . $position)) ?></div>
</div>
<div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-4">
    <?php foreach (Section::types() as $type => [$icon]): ?>
        <div class="col">
            <a class="card border-0 shadow-sm h-100 text-decoration-none pb-type" href="<?= e(url('admin/page-builder/create?' . http_build_query(['page' => $page, 'position' => $position, 'type' => $type]))) ?>">
                <div class="card-body">
                    <i class="bi <?= e($icon) ?> fs-2 text-brand"></i>
                    <div class="fw-semibold mt-1 text-body"><?= e(__('pb_type_' . $type)) ?></div>
                    <div class="small text-muted"><?= e(__('pb_desc_' . $type)) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
