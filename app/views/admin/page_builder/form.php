<?php
/**
 * Add / edit a section. Fields come from Section::types().
 * Vars: $s (row), $set (settings), $style, $old, $fields, $pages, $categories, $brands, $errors
 */
$isNew = $s['id'] === null;
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$ov = static fn(string $f, $d) => array_key_exists($f, $old) ? (string) $old[$f] : (string) ($d ?? '');
$sv = static fn(string $f) => (string) ($set[$f] ?? '');
$fid = static fn(string $f) => 'f_' . preg_replace('/[^a-z0-9_]/', '_', $f);
$action = $isNew ? url('admin/page-builder') : url('admin/page-builder/' . (int) $s['id']);
$typeIcon = Section::types()[$s['type']][0];
// sub-field input for repeaters
$subInput = static function (string $name, string $kind, string $value, string $sub): string {
    $label = e(__('pb_f_' . $sub));
    if ($kind === 'textarea') {
        return '<textarea class="form-control form-control-sm" name="' . e($name) . '" rows="2" placeholder="' . $label . '" aria-label="' . $label . '">' . e($value) . '</textarea>';
    }
    if ($kind === 'rating') {
        $o = '';
        foreach ([5, 4, 3, 2, 1, 0] as $n) {
            $o .= '<option value="' . $n . '"' . ((string) $n === ($value === '' ? '5' : $value) ? ' selected' : '') . '>' . ($n ? str_repeat('★', $n) : '—') . '</option>';
        }
        return '<select class="form-select form-select-sm" name="' . e($name) . '" aria-label="' . $label . '">' . $o . '</select>';
    }
    $ph = $kind === 'icon' ? 'bi-…' : ($kind === 'link' ? 'products / https://…' : $label);
    return '<input class="form-control form-control-sm' . ($kind === 'icon' || $kind === 'link' ? ' font-monospace' : '') . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="' . e($ph) . '" aria-label="' . $label . '" maxlength="200">';
};
?>
<div class="mb-3">
    <a href="<?= e(url('admin/page-builder?page=' . rawurlencode($s['page_key']))) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> <?= e(__('page_builder')) ?></a>
    <h1 class="h4 mb-0"><i class="bi <?= e($typeIcon) ?>"></i> <?= e(__('pb_type_' . $s['type'])) ?> <small class="text-muted fs-6">— <?= e($isNew ? __('pb_add_section') : __('pb_edit_section')) ?></small></h1>
    <div class="small text-muted"><?= e(__('pb_desc_' . $s['type'])) ?></div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($isNew): ?><input type="hidden" name="type" value="<?= e($s['type']) ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square"></i> <?= e(__('pb_content')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label small" for="tbn"><?= e(__('pb_heading')) ?> (বাংলা)</label><input class="form-control" id="tbn" name="title_bn" value="<?= e($ov('title_bn', $s['title_bn'])) ?>" maxlength="200"></div>
                    <div class="col-md-6"><label class="form-label small" for="ten"><?= e(__('pb_heading')) ?> (English)</label><input class="form-control" id="ten" name="title_en" value="<?= e($ov('title_en', $s['title_en'])) ?>" maxlength="200"></div>
                    <div class="col-12 form-text mt-0"><?= e(__('pb_heading_help')) ?></div>

                    <?php foreach ($fields as $name => $def): $kind = $def[0]; $extra = $def[1] ?? null; $label = __('pb_f_' . $name); $id = $fid($name); ?>
                        <?php if (in_array($kind, ['text_i18n', 'textarea_i18n', 'html_i18n'], true)): ?>
                            <?php foreach (['bn' => 'বাংলা', 'en' => 'English'] as $l => $ln): ?>
                                <div class="<?= $kind === 'text_i18n' ? 'col-md-6' : 'col-12' ?>">
                                    <label class="form-label small" for="<?= $id . '_' . $l ?>"><?= e($label) ?> (<?= $ln ?>)</label>
                                    <?php if ($kind === 'text_i18n'): ?>
                                        <input class="form-control" id="<?= $id . '_' . $l ?>" name="set[<?= e($name . '_' . $l) ?>]" value="<?= e($sv($name . '_' . $l)) ?>" maxlength="200">
                                    <?php else: ?>
                                        <textarea class="form-control<?= $kind === 'html_i18n' ? ' font-monospace small' : '' ?>" id="<?= $id . '_' . $l ?>" name="set[<?= e($name . '_' . $l) ?>]" rows="<?= $kind === 'html_i18n' ? 6 : 3 ?>"><?= e($sv($name . '_' . $l)) ?></textarea>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($kind === 'html_i18n'): ?><div class="col-12 form-text mt-0"><?= e(__('pb_html_help')) ?></div><?php endif; ?>
                        <?php elseif ($kind === 'image'): ?>
                            <div class="col-md-6">
                                <label class="form-label small" for="<?= $id ?>"><?= e($label) ?></label>
                                <?php if ($sv($name) !== ''): ?>
                                    <div class="mb-2"><img src="<?= e(url($sv($name))) ?>" alt="" class="img-thumbnail" style="max-height:110px"></div>
                                    <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="set[remove_<?= e($name) ?>]" value="1" id="<?= $id ?>_rm"><label class="form-check-label small" for="<?= $id ?>_rm"><?= e(__('pb_remove_image')) ?></label></div>
                                <?php endif; ?>
                                <input class="form-control form-control-sm<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" type="file" id="<?= $id ?>" name="img_<?= e($name) ?>" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text"><?= e(__('pb_f_' . $name . '_help') !== 'pb_f_' . $name . '_help' ? __('pb_f_' . $name . '_help') : __('pb_image_help')) ?></div>
                                <?= $err($name) ?>
                            </div>
                        <?php elseif ($kind === 'select'): ?>
                            <div class="col-md-4">
                                <label class="form-label small" for="<?= $id ?>"><?= e($label) ?></label>
                                <select class="form-select" id="<?= $id ?>" name="set[<?= e($name) ?>]" <?= $name === 'source' ? 'data-pb-source' : '' ?>>
                                    <?php foreach ($extra as $opt): ?><option value="<?= e($opt) ?>" <?= ($sv($name) !== '' ? $sv($name) : $extra[0]) === $opt ? 'selected' : '' ?>><?= e(__('pb_o_' . $name . '_' . $opt)) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($kind === 'number'): [$min, $max, $dflt] = $extra; ?>
                            <div class="col-md-4">
                                <label class="form-label small" for="<?= $id ?>"><?= e($label) ?></label>
                                <input class="form-control" type="number" id="<?= $id ?>" name="set[<?= e($name) ?>]" min="<?= $min ?>" max="<?= $max ?>" value="<?= e($sv($name) !== '' ? $sv($name) : (string) $dflt) ?>">
                            </div>
                        <?php elseif ($kind === 'checkbox'): ?>
                            <div class="col-md-4 pt-md-4">
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="<?= $id ?>" name="set[<?= e($name) ?>]" value="1" <?= $sv($name) ? 'checked' : '' ?>><label class="form-check-label small" for="<?= $id ?>"><?= e($label) ?></label></div>
                            </div>
                        <?php elseif ($kind === 'category'): ?>
                            <div class="col-md-4" data-pb-when="source:category">
                                <label class="form-label small" for="<?= $id ?>"><?= e($label) ?></label>
                                <select class="form-select" id="<?= $id ?>" name="set[<?= e($name) ?>]">
                                    <option value="0">—</option>
                                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $sv($name) === (int) $c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] !== null ? '&nbsp;&nbsp;— ' : '' ?><?= e(Category::label($c)) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($kind === 'brand'): ?>
                            <div class="col-md-4" data-pb-when="source:brand">
                                <label class="form-label small" for="<?= $id ?>"><?= e($label) ?></label>
                                <select class="form-select" id="<?= $id ?>" name="set[<?= e($name) ?>]">
                                    <option value="0">—</option>
                                    <?php foreach ($brands as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $sv($name) === (int) $b['id'] ? 'selected' : '' ?>><?= e(Brand::label($b)) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($kind === 'ids'): ?>
                            <div class="col-md-8" data-pb-when="source:ids">
                                <label class="form-label small" for="<?= $id ?>"><?= e($label) ?></label>
                                <input class="form-control font-monospace" id="<?= $id ?>" name="set[<?= e($name) ?>]" value="<?= e($sv($name)) ?>" placeholder="12, 45, 78">
                                <div class="form-text"><?= e(__('pb_ids_help')) ?></div>
                            </div>
                        <?php elseif ($kind === 'link' || $kind === 'video'): ?>
                            <div class="col-md-6">
                                <label class="form-label small" for="<?= $id ?>"><?= e($label) ?></label>
                                <input class="form-control font-monospace<?= isset($errors[$name]) ? ' is-invalid' : '' ?>" id="<?= $id ?>" name="set[<?= e($name) ?>]" value="<?= e($sv($name)) ?>" maxlength="255" placeholder="<?= $kind === 'video' ? 'https://www.youtube.com/watch?v=…' : 'products / page/about-us / https://…' ?>">
                                <?= $err($name) ?>
                            </div>
                        <?php elseif ($kind === 'repeater'): $items = array_values(array_filter((array) ($set[$name] ?? []), 'is_array')); if (!$items) { $items = [[]]; } ?>
                            <div class="col-12" data-repeater>
                                <label class="form-label small fw-semibold"><?= e($label) ?></label>
                                <div data-repeater-list>
                                    <?php foreach ($items as $i => $it): ?>
                                        <div class="border rounded p-2 mb-2 bg-light" data-repeater-item>
                                            <div class="row g-2">
                                                <?php foreach ($extra as $sub => $subKind): ?>
                                                    <div class="<?= $subKind === 'textarea' ? 'col-md-6' : 'col-md-' . ($subKind === 'rating' || $subKind === 'icon' ? 2 : 4) ?>"><?= $subInput('set[' . $name . '][' . $i . '][' . $sub . ']', $subKind, (string) ($it[$sub] ?? ''), $sub) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="text-end mt-1"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-repeater-remove><i class="bi bi-x-lg"></i> <?= e(__('pb_remove_item')) ?></button></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-repeater-add><i class="bi bi-plus-lg"></i> <?= e(__('pb_add_item')) ?></button>
                                <div class="form-text"><?= e(__('pb_repeater_help')) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-geo"></i> <?= e(__('pb_placement')) ?></div>
                <div class="card-body">
                    <label class="form-label small" for="pk"><?= e(__('pb_page')) ?></label>
                    <select class="form-select form-select-sm mb-2" id="pk" name="page_key" data-pb-page>
                        <?php foreach ($pages as $k => $label): ?><option value="<?= e($k) ?>" <?= $ov('page_key', $s['page_key']) === $k ? 'selected' : '' ?> data-positions="<?= e(implode(',', Section::positionsFor($k))) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label small" for="ps"><?= e(__('pb_position')) ?></label>
                    <select class="form-select form-select-sm mb-2" id="ps" name="position" data-pb-position>
                        <?php foreach (Section::HOME_POSITIONS as $p): ?><option value="<?= $p ?>" <?= $ov('position', $s['position']) === $p ? 'selected' : '' ?>><?= e(__('pb_pos_' . $p)) ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label small" for="al"><?= e(__('pb_admin_label')) ?></label>
                    <input class="form-control form-control-sm" id="al" name="admin_label" value="<?= e($ov('admin_label', $s['admin_label'])) ?>" maxlength="120" placeholder="<?= e(__('pb_admin_label_ph')) ?>">
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-brush"></i> <?= e(__('pb_style')) ?></div>
                <div class="card-body small">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="style[card]" value="1" id="stc" <?= !isset($style['card']) || !empty($style['card']) ? 'checked' : '' ?>><label class="form-check-label" for="stc"><?= e(__('pb_card')) ?></label></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="style[use_bg]" value="1" id="ubg" <?= !empty($style['bg']) ? 'checked' : '' ?>><label class="form-check-label" for="ubg"><?= e(__('pb_bg')) ?></label></div>
                            <input class="form-control form-control-color w-100" type="color" name="style[bg]" value="<?= e(!empty($style['bg']) ? $style['bg'] : '#ffffff') ?>" aria-label="<?= e(__('pb_bg')) ?>">
                        </div>
                        <div class="col-6">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="style[use_color]" value="1" id="ucl" <?= !empty($style['color']) ? 'checked' : '' ?>><label class="form-check-label" for="ucl"><?= e(__('pb_text_color')) ?></label></div>
                            <input class="form-control form-control-color w-100" type="color" name="style[color]" value="<?= e(!empty($style['color']) ? $style['color'] : '#1f2a2a') ?>" aria-label="<?= e(__('pb_text_color')) ?>">
                        </div>
                    </div>
                    <label class="form-label" for="spd"><?= e(__('pb_padding')) ?></label>
                    <select class="form-select form-select-sm mb-2" id="spd" name="style[padding]">
                        <?php foreach (array_keys(Section::PADDINGS) as $p): ?><option value="<?= $p ?>" <?= ($style['padding'] ?? 'md') === $p ? 'selected' : '' ?>><?= e(__('pb_pad_' . $p)) ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label" for="swd"><?= e(__('pb_width')) ?></label>
                    <select class="form-select form-select-sm mb-2" id="swd" name="style[width]">
                        <option value="boxed" <?= ($style['width'] ?? 'boxed') === 'boxed' ? 'selected' : '' ?>><?= e(__('pb_width_boxed')) ?></option>
                        <option value="full" <?= ($style['width'] ?? '') === 'full' ? 'selected' : '' ?>><?= e(__('pb_width_full')) ?></option>
                    </select>
                    <label class="form-label" for="sso"><?= e(__('pb_show_on')) ?></label>
                    <select class="form-select form-select-sm" id="sso" name="style[show_on]">
                        <?php foreach (['all', 'desktop', 'mobile'] as $o): ?><option value="<?= $o ?>" <?= ($style['show_on'] ?? 'all') === $o ? 'selected' : '' ?>><?= e(__('pb_show_' . $o)) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-event"></i> <?= e(__('pb_visibility')) ?></div>
                <div class="card-body small">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="act" <?= $ov('is_active', (string) $s['is_active']) === '1' ? 'checked' : '' ?>><label class="form-check-label" for="act"><?= e(__('pb_active')) ?></label></div>
                    <label class="form-label" for="sa"><?= e(__('pb_starts')) ?></label>
                    <input class="form-control form-control-sm mb-2" type="datetime-local" id="sa" name="starts_at" value="<?= e($s['starts_at'] ? date('Y-m-d\TH:i', strtotime($s['starts_at'])) : $ov('starts_at', '')) ?>">
                    <label class="form-label" for="ea"><?= e(__('pb_ends')) ?></label>
                    <input class="form-control form-control-sm<?= isset($errors['ends_at']) ? ' is-invalid' : '' ?>" type="datetime-local" id="ea" name="ends_at" value="<?= e($s['ends_at'] ? date('Y-m-d\TH:i', strtotime($s['ends_at'])) : $ov('ends_at', '')) ?>">
                    <?= $err('ends_at') ?>
                    <div class="form-text"><?= e(__('pb_schedule_help')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="position-sticky bottom-0 bg-light py-2 d-flex gap-2">
        <button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button>
        <button class="btn btn-outline-secondary" name="stay" value="1"><?= e(__('pb_save_stay')) ?></button>
        <a class="btn btn-link" href="<?= e(url('admin/page-builder?page=' . rawurlencode($s['page_key']))) ?>"><?= e(__('cancel')) ?></a>
    </div>
</form>
