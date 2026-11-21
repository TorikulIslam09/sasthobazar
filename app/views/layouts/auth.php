<?php /** Centered card layout for login/register pages. */ ?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="auth-bg lang-<?= e(lang()) ?>">
<div class="container py-5" style="max-width: 460px">
    <div class="text-center mb-4">
        <a href="<?= e(url('/')) ?>" class="text-decoration-none brand-text fs-3 fw-bold">
            <i class="bi bi-heart-pulse-fill"></i> <?= e(__('app_name')) ?>
        </a>
        <div class="mt-2"><?= View::partial('partials/lang_switch', ['class' => 'btn btn-sm btn-outline-secondary']) ?></div>
    </div>
    <?= View::partial('partials/flash') ?>
    <?= $content ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
