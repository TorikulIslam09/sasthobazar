<?php /** Shared <head> contents. Optional: $title, $metaDescription. */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= CSRF::meta() ?>
<title><?= e(($title ?? '') !== '' ? $title . ' | ' . __('app_name') : __('app_name')) ?></title>
<meta name="description" content="<?= e(($metaDescription ?? '') !== '' ? mb_substr((string) $metaDescription, 0, 300) : setting('meta_description', '')) ?>">
<meta name="theme-color" content="#0e7673">
<?php if (($favicon = (string) setting('site_favicon', '')) !== ''): ?><link rel="icon" href="<?= e(url($favicon)) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(asset('assets/css/app.css')) ?>" rel="stylesheet">
