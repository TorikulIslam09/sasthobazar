<?php /** Wide centred layout for long sign-up forms (vendor registration). */ ?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="auth-bg admin-body lang-<?= e(lang()) ?>">
<div class="container py-4" style="max-width: 900px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="<?= e(url('/')) ?>" class="text-decoration-none brand-text fs-4 fw-bold"><i class="bi bi-heart-pulse-fill"></i> <?= e(__('app_name')) ?></a>
        <?= View::partial('partials/lang_switch', ['class' => 'btn btn-sm btn-outline-secondary']) ?>
    </div>
    <?= View::partial('partials/flash') ?>
    <?= $content ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
