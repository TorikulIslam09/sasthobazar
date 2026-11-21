<?php
/** Bangla / English toggle - keeps current path, swaps ?lang= */
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$other = lang() === 'bn' ? 'en' : 'bn';
?>
<a class="<?= e($class ?? 'btn btn-sm btn-outline-light') ?>" href="<?= e($path . '?lang=' . $other) ?>" title="<?= e(__('language')) ?>">
    <i class="bi bi-translate"></i> <?= $other === 'en' ? 'English' : 'বাংলা' ?>
</a>
