<?php /** Minimal layout for error pages. */ ?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="auth-bg">
<div class="container py-5 text-center" style="max-width: 560px">
    <?= $content ?>
</div>
</body>
</html>
