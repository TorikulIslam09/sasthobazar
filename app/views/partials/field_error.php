<?php /** Inline validation message. Vars: $errors (array), $field (string) */ ?>
<?php if (!empty($errors[$field])): ?>
    <div class="invalid-feedback d-block"><?= e($errors[$field]) ?></div>
<?php endif; ?>
