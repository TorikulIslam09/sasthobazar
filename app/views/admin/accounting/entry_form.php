<?php
/** Income / expense entry form. Vars: $entry (null = new), $input, $errors, $categories */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$type = $val('type') === 'income' ? 'income' : 'expense';
$action = $entry ? url('admin/accounting/entries/' . (int) $entry['id']) : url('admin/accounting/entries');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-plus"></i> <?= e($title) ?><?= $entry ? ' <small class="text-muted">' . e($entry['entry_no']) . '</small>' : '' ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/accounting/entries')) ?>"><i class="bi bi-arrow-left"></i> <?= e(__('back')) ?></a>
</div>
<form method="post" action="<?= e($action) ?>" class="card border-0 shadow-sm" style="max-width: 760px" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-12">
            <div class="btn-group" role="group" aria-label="<?= e(__('type')) ?>">
                <input type="radio" class="btn-check" name="type" id="tyE" value="expense" <?= $type === 'expense' ? 'checked' : '' ?>>
                <label class="btn btn-outline-danger btn-sm" for="tyE"><i class="bi bi-dash-circle"></i> <?= e(__('acc_expense')) ?></label>
                <input type="radio" class="btn-check" name="type" id="tyI" value="income" <?= $type === 'income' ? 'checked' : '' ?>>
                <label class="btn btn-outline-success btn-sm" for="tyI"><i class="bi bi-plus-circle"></i> <?= e(__('acc_income')) ?></label>
            </div>
            <?= $err('type') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="cat"><?= e(__('category')) ?> *</label>
            <select class="<?= $cls('category_id', 'form-select') ?>" id="cat" name="category_id" required>
                <option value=""><?= e(__('select')) ?></option>
                <?php foreach (['expense', 'income'] as $t): ?>
                    <optgroup label="<?= e(__('acc_' . $t)) ?>" data-show-when="type:<?= $t ?>">
                        <?php foreach ($categories as $c): if ($c['type'] !== $t) { continue; } ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $val('category_id') === (string) $c['id'] ? 'selected' : '' ?>><?= e(tr($c['name_bn'], $c['name_en'])) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="form-text"><a href="<?= e(url('admin/accounting/categories')) ?>"><?= e(__('acc_manage_categories')) ?></a></div>
            <?= $err('category_id') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="amt"><?= e(__('amount')) ?> *</label>
            <input class="<?= $cls('amount') ?>" id="amt" name="amount" value="<?= e($val('amount')) ?>" inputmode="decimal" required>
            <?= $err('amount') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="dt"><?= e(__('date')) ?> *</label>
            <input class="<?= $cls('entry_date') ?>" type="date" id="dt" name="entry_date" value="<?= e($val('entry_date')) ?>" required>
            <?= $err('entry_date') ?>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="pm"><?= e(__('rp_method')) ?></label>
            <select class="<?= $cls('payment_method', 'form-select') ?>" id="pm" name="payment_method">
                <?php foreach (AdminAccountingController::PAY_METHODS as $m): ?><option value="<?= $m ?>" <?= $val('payment_method') === $m ? 'selected' : '' ?>><?= e(__('acc_pm_' . $m)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-8">
            <label class="form-label" for="ref"><?= e(__('reference')) ?></label>
            <input class="<?= $cls('reference') ?>" id="ref" name="reference" value="<?= e($val('reference')) ?>" maxlength="100" placeholder="<?= e(__('acc_reference_ph')) ?>">
            <?= $err('reference') ?>
        </div>
        <div class="col-12">
            <label class="form-label" for="desc"><?= e(__('description')) ?></label>
            <textarea class="<?= $cls('description') ?>" id="desc" name="description" rows="2" maxlength="500"><?= e($val('description')) ?></textarea>
            <?= $err('description') ?>
        </div>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap gap-2">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <?php if (!$entry): ?><button class="btn btn-outline-primary" name="add_another" value="1"><?= e(__('acc_save_add_another')) ?></button><?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/accounting/entries')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
