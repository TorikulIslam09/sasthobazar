<?php
/** CMS tabs. Vars: $active */
$tabs = [
    'pages' => ['admin/pages', 'bi-file-earmark-text', __('pages')],
    'faqs'  => ['admin/faqs', 'bi-question-circle', __('faqs')],
    'blog'  => ['admin/blog', 'bi-journal-richtext', __('blog')],
];
?>
<ul class="nav nav-pills small mb-3">
    <?php foreach ($tabs as $k => [$path, $icon, $label]): ?>
        <li class="nav-item"><a class="nav-link<?= $k === $active ? ' active' : '' ?>" href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
