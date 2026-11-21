<?php /** Income / expense categories. Vars: $categories */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-tags"></i> <?= e(__('acc_categories')) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/accounting/entries')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('acc_entries')) ?></a>
</div>
<?= View::partial('admin/accounting/_nav', ['active' => 'entries']) ?>
<div class="row g-3">
    <?php foreach (['expense', 'income'] as $type): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><?= e(__('acc_' . $type)) ?></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($categories as $c): if ($c['type'] !== $type) { continue; } ?>
                        <li class="list-group-item">
                            <form method="post" action="<?= e(url('admin/accounting/categories')) ?>" class="d-flex flex-wrap gap-2 align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <input class="form-control form-control-sm" style="max-width: 200px" name="name_en" value="<?= e($c['name_en']) ?>" maxlength="100" aria-label="<?= e(__('name_en')) ?>" <?= can('accounting.edit') ? '' : 'disabled' ?>>
                                <input class="form-control form-control-sm" style="max-width: 200px" name="name_bn" value="<?= e($c['name_bn'] ?? '') ?>" maxlength="100" lang="bn" aria-label="<?= e(__('name_bn')) ?>" <?= can('accounting.edit') ? '' : 'disabled' ?>>
                                <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ca<?= (int) $c['id'] ?>" <?= (int) $c['is_active'] ? 'checked' : '' ?> <?= can('accounting.edit') ? '' : 'disabled' ?>><label class="form-check-label" for="ca<?= (int) $c['id'] ?>"><?= e(__('active')) ?></label></div>
                                <span class="text-muted ms-auto"><?= e(__('acc_n_entries', ['n' => num((int) $c['used'])])) ?></span>
                                <?php if (can('accounting.edit')): ?><button class="btn btn-sm btn-outline-primary" title="<?= e(__('save')) ?>"><i class="bi bi-check2"></i></button><?php endif; ?>
                            </form>
                            <?php if (can('accounting.delete') && !(int) $c['is_system'] && !(int) $c['used']): ?>
                                <form method="post" action="<?= e(url('admin/accounting/categories/' . (int) $c['id'] . '/delete')) ?>" class="mt-1" data-confirm="<?= e(__('confirm_delete_named', ['name' => $c['name_en']])) ?>"><?= csrf_field() ?>
                                    <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-trash"></i> <?= e(__('delete')) ?></button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (can('accounting.edit')): ?>
                    <form method="post" action="<?= e(url('admin/accounting/categories')) ?>" class="card-footer bg-white d-flex flex-wrap gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="<?= $type ?>">
                        <input class="form-control form-control-sm" style="max-width: 200px" name="name_en" maxlength="100" required placeholder="<?= e(__('name_en')) ?>" aria-label="<?= e(__('name_en')) ?>">
                        <input class="form-control form-control-sm" style="max-width: 200px" name="name_bn" maxlength="100" lang="bn" placeholder="<?= e(__('name_bn')) ?>" aria-label="<?= e(__('name_bn')) ?>">
                        <button class="btn btn-brand btn-sm"><i class="bi bi-plus-lg"></i> <?= e(__('add')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
