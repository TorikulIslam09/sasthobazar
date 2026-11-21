<?php
/** Renders and clears flash messages. */
$map = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
foreach (Helpers::getFlashes() as $type => $messages):
    foreach ($messages as $msg): ?>
    <div class="alert alert-<?= e($map[$type] ?? 'secondary') ?> alert-dismissible fade show" role="alert">
        <?= e($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; endforeach; ?>
