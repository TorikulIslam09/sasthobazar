<?php /** Printable documents (PO, GRN): no navigation, white background. Views add their own container. */ ?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<?= View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="bg-white">
    <div class="text-center pt-3 small text-muted"><strong class="text-dark fs-5"><?= e(__('app_name')) ?></strong></div>
    <?= $content ?>
</body>
</html>
