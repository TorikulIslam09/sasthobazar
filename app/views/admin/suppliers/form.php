<?php
/** Add / edit supplier. Vars: $supplier (null on create), $input, $errors, $usage */
$action = $supplier ? url('admin/suppliers/' . $supplier['id']) : url('admin/suppliers');
$v = static fn(string $k, string $d = '') => (string) ($input[$k] ?? $d);
$cls = static fn(string $k) => 'form-control' . (isset($errors[$k]) ? ' is-invalid' : '');
$err = static fn(string $k) => View::partial('partials/field_error', ['errors' => $errors, 'field' => $k]);
$field = static function (string $k, string $label, int $max, string $type = 'text', string $extra = '') use ($v, $cls, $err): string {
    return '<label class="form-label" for="' . $k . '">' . e($label) . '</label>'
        . '<input class="' . $cls($k) . '" type="' . $type . '" id="' . $k . '" name="' . $k . '" maxlength="' . $max . '" value="' . e($v($k)) . '" ' . $extra . '>' . $err($k);
};
$inUse = $usage && ($usage['pos'] > 0 || $usage['batches'] > 0);
?>
<h1 class="h4 mb-3">
    <a href="<?= e(url('admin/suppliers')) ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i></a>
    <?= e($title) ?><?= $supplier ? ': ' . e($supplier['name']) : '' ?>
</h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('form_has_errors')) ?></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="card border-0 shadow-sm" style="max-width: 900px" novalidate>
    <?= csrf_field() ?>
    <div class="card-body row g-3">
        <div class="col-md-6"><?= $field('name', __('sup_company_name') . ' *', 150, 'text', 'required') ?></div>
        <div class="col-md-6"><?= $field('contact_person', __('sup_contact_person'), 150) ?></div>
        <div class="col-md-6"><?= $field('phone', __('phone'), 20, 'tel', 'inputmode="tel" placeholder="01XXXXXXXXX"') ?></div>
        <div class="col-md-6"><?= $field('email', __('email'), 191, 'email') ?></div>
        <div class="col-12"><?= $field('address', __('address'), 255) ?></div>
        <div class="col-md-4"><?= $field('drug_license_no', __('sup_drug_license'), 100) ?></div>
        <div class="col-md-4"><?= $field('tin_no', __('sup_tin'), 50) ?></div>
        <div class="col-md-4"><?= $field('payment_terms', __('sup_payment_terms'), 100, 'text', 'list="termsList" placeholder="' . e(__('sup_terms_ph')) . '"') ?>
            <datalist id="termsList"><option value="Cash"><option value="7 days credit"><option value="15 days credit"><option value="30 days credit"></datalist>
        </div>
        <div class="col-12">
            <label class="form-label" for="notes"><?= e(__('note')) ?></label>
            <textarea class="<?= $cls('notes') ?>" id="notes" name="notes" rows="3" maxlength="5000"><?= e($v('notes')) ?></textarea>
            <?= $err('notes') ?>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= $v('is_active') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active"><?= e(__('active')) ?></label>
            </div>
            <div class="form-text"><?= e(__('sup_inactive_help')) ?></div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/suppliers')) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>

<?php if ($supplier && can('suppliers.delete')): ?>
    <div class="card border-danger-subtle mt-4" style="max-width: 900px">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small"><strong class="text-danger"><?= e(__('danger_zone')) ?></strong><br>
                <span class="text-muted"><?= e($inUse ? __('supplier_in_use', ['name' => $supplier['name'], 'pos' => num($usage['pos']), 'batches' => num($usage['batches'])]) : __('delete_permanent')) ?></span></div>
            <?= View::partial('partials/delete_button', [
                'action' => url('admin/suppliers/' . $supplier['id'] . '/delete'),
                'message' => __('confirm_delete_named', ['name' => $supplier['name']]),
                'blocked' => $inUse ? __('supplier_in_use_short') : '', 'label' => __('delete'),
            ]) ?>
        </div>
    </div>
<?php endif; ?>
