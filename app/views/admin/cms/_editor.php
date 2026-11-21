<?php
/**
 * Simple HTML content editor: formatting toolbar + textarea + live preview. Vars: $name, $value, $label, $id, optional $error, $rows, $lang
 * The server cleans the HTML (Html::clean), so only safe tags are kept.
 */
$rows = $rows ?? 14;
$buttons = [
    ['<h2>', '</h2>', 'bi-type-h2', 'H2'], ['<h3>', '</h3>', 'bi-type-h3', 'H3'], ['<p>', '</p>', 'bi-paragraph', 'P'],
    ['<strong>', '</strong>', 'bi-type-bold', 'B'], ['<em>', '</em>', 'bi-type-italic', 'I'],
    ["<ul>\n<li>", "</li>\n</ul>", 'bi-list-ul', 'UL'], ["<ol>\n<li>", "</li>\n</ol>", 'bi-list-ol', 'OL'],
    ['<blockquote>', '</blockquote>', 'bi-quote', 'Q'], ['link', '', 'bi-link-45deg', 'A'], ['image', '', 'bi-image', 'IMG'],
];
?>
<div class="cms-editor" data-editor>
    <div class="d-flex justify-content-between align-items-end mb-1 flex-wrap gap-1">
        <label class="form-label mb-0" for="<?= e($id) ?>"><?= e($label) ?></label>
        <div class="btn-toolbar gap-1" role="toolbar" aria-label="<?= e(__('formatting')) ?>">
            <div class="btn-group btn-group-sm">
                <?php foreach ($buttons as [$open, $close, $icon, $title]): ?>
                    <button type="button" class="btn btn-outline-secondary" data-ed-open="<?= e($open) ?>" data-ed-close="<?= e($close) ?>" title="<?= e($title) ?>" aria-label="<?= e($title) ?>"><i class="bi <?= e($icon) ?>"></i></button>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-ed-preview><i class="bi bi-eye"></i> <?= e(__('preview')) ?></button>
        </div>
    </div>
    <textarea class="form-control font-monospace small<?= !empty($error) ? ' is-invalid' : '' ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int) $rows ?>" <?= !empty($lang) ? 'lang="' . e($lang) . '"' : '' ?>><?= e((string) $value) ?></textarea>
    <div class="cms-preview border rounded p-3 bg-white" data-ed-output hidden></div>
    <?php if (!empty($error)): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
    <div class="form-text"><?= e(__('editor_help')) ?></div>
</div>
