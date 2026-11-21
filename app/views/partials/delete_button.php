<?php
/**
 * POST delete button with confirmation dialog (app.js shows a modal for [data-confirm] forms).
 * Vars: $action (url), $message (confirm text), $blocked (string reason => disabled button, optional),
 *       $label (optional button text), $class (optional extra classes)
 */
$blocked = $blocked ?? '';
?>
<form method="post" action="<?= e($action) ?>" class="d-inline" data-confirm="<?= e($message) ?>" data-confirm-danger="1">
    <?= csrf_field() ?>
    <button class="btn btn-sm btn-outline-danger <?= e($class ?? '') ?>" type="submit"
            title="<?= e($blocked !== '' ? $blocked : __('delete')) ?>" <?= $blocked !== '' ? 'disabled' : '' ?>>
        <i class="bi bi-trash"></i><?= isset($label) ? ' ' . e($label) : '' ?>
    </button>
</form>
