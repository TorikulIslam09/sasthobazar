<?php
/**
 * Header, Menus & Colours.
 * Vars: $tab (settings | header | mobile | footer_1 | footer_2), $input (settings), $items (links of the open menu), $counts, $errors, $old, $defaults
 */
$edit = can('appearance.edit');
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$sw = static function (string $name, string $label, ?string $help = null) use ($input): string {
    return '<div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" name="' . e($name) . '" value="1" id="sw_' . e($name) . '" '
        . (!empty($input[$name]) ? 'checked' : '') . '><label class="form-check-label" for="sw_' . e($name) . '">' . e($label) . '</label>'
        . ($help ? '<div class="form-text mt-0">' . e($help) . '</div>' : '') . '</div>';
};
$menuTabs = ['header' => ['bi-menu-button-wide', __('menu_loc_header')], 'mobile' => ['bi-phone', __('menu_loc_mobile')],
             'footer_1' => ['bi-layout-text-window-reverse', __('menu_loc_footer_1')], 'footer_2' => ['bi-layout-text-window-reverse', __('menu_loc_footer_2')]];
$errFor = $errors['_for'] ?? null;
$ov = static fn(string $f, $for, string $d = '') => (string) $errFor === (string) $for ? (string) ($old[$f] ?? $d) : $d;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-palette"></i> <?= e(__('appearance')) ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <?= e(__('appr_view_site')) ?></a>
</div>

<ul class="nav nav-pills small mb-3 flex-nowrap overflow-auto">
    <li class="nav-item"><a class="nav-link text-nowrap <?= $tab === 'settings' ? 'active' : '' ?>" href="<?= e(url('admin/appearance')) ?>"><i class="bi bi-window"></i> <?= e(__('appr_tab_header')) ?></a></li>
    <?php foreach ($menuTabs as $loc => [$icon, $label]): ?>
        <li class="nav-item"><a class="nav-link text-nowrap <?= $tab === $loc ? 'active' : '' ?>" href="<?= e(url('admin/appearance?tab=' . $loc)) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?> <span class="badge bg-light text-dark"><?= e(num($counts[$loc] ?? 0)) ?></span></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'settings'): ?>
<form method="post" action="<?= e(url('admin/appearance')) ?>" novalidate>
    <?= csrf_field() ?>
    <fieldset <?= $edit ? '' : 'disabled' ?>>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-megaphone"></i> <?= e(__('appr_announce')) ?></div>
                <div class="card-body">
                    <?= $sw('announce_enabled', __('appr_announce_on')) ?>
                    <?= $sw('announce_show_hotline', __('appr_announce_hotline')) ?>
                    <?= $sw('announce_show_lang', __('appr_announce_lang')) ?>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label small" for="atb"><?= e(__('appr_announce_text')) ?> (বাংলা)</label><input class="form-control form-control-sm" id="atb" name="announce_text_bn" value="<?= e($val('announce_text_bn')) ?>" maxlength="160"></div>
                        <div class="col-md-6"><label class="form-label small" for="ate"><?= e(__('appr_announce_text')) ?> (English)</label><input class="form-control form-control-sm" id="ate" name="announce_text_en" value="<?= e($val('announce_text_en')) ?>" maxlength="160"></div>
                        <div class="col-12 form-text mt-0"><?= e(__('appr_announce_text_help')) ?></div>
                        <div class="col-12"><label class="form-label small" for="al"><?= e(__('appr_link_opt')) ?></label><input class="<?= $cls('announce_link', 'form-control form-control-sm') ?>" id="al" name="announce_link" value="<?= e($val('announce_link')) ?>" maxlength="255" placeholder="offers"><?= $err('announce_link') ?></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-search"></i> <?= e(__('appr_search')) ?></div>
                <div class="card-body row g-2">
                    <div class="col-md-6"><label class="form-label small" for="spb"><?= e(__('appr_search_ph')) ?> (বাংলা)</label><input class="form-control form-control-sm" id="spb" name="search_placeholder_bn" value="<?= e($val('search_placeholder_bn')) ?>" maxlength="100" placeholder="<?= e(Helpers::transIn('search_placeholder', 'bn')) ?>"></div>
                    <div class="col-md-6"><label class="form-label small" for="spe"><?= e(__('appr_search_ph')) ?> (English)</label><input class="form-control form-control-sm" id="spe" name="search_placeholder_en" value="<?= e($val('search_placeholder_en')) ?>" maxlength="100" placeholder="<?= e(Helpers::transIn('search_placeholder', 'en')) ?>"></div>
                    <div class="col-12 form-text mt-0"><?= e(__('appr_empty_default')) ?></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-ui-radios"></i> <?= e(__('appr_buttons')) ?></div>
                <div class="card-body">
                    <?= $sw('header_show_rx', __('appr_btn_rx')) ?>
                    <?= $sw('header_show_wishlist', __('appr_btn_wishlist')) ?>
                    <?= $sw('header_show_offers', __('appr_btn_offers')) ?>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4"><label class="form-label small" for="olb"><?= e(__('appr_btn_label')) ?> (বাংলা)</label><input class="form-control form-control-sm" id="olb" name="header_offers_label_bn" value="<?= e($val('header_offers_label_bn')) ?>" maxlength="40" placeholder="<?= e(Helpers::transIn('todays_offers', 'bn')) ?>"></div>
                        <div class="col-md-4"><label class="form-label small" for="ole"><?= e(__('appr_btn_label')) ?> (English)</label><input class="form-control form-control-sm" id="ole" name="header_offers_label_en" value="<?= e($val('header_offers_label_en')) ?>" maxlength="40" placeholder="<?= e(Helpers::transIn('todays_offers', 'en')) ?>"></div>
                        <div class="col-md-4"><label class="form-label small" for="oli"><?= e(__('appr_btn_link')) ?></label><input class="<?= $cls('header_offers_link', 'form-control form-control-sm') ?>" id="oli" name="header_offers_link" value="<?= e($val('header_offers_link')) ?>" maxlength="255" placeholder="offers"><?= $err('header_offers_link') ?></div>
                    </div>
                    <hr>
                    <?= $sw('header_show_categories', __('appr_cat_bar')) ?>
                    <div class="d-flex align-items-center gap-2 mt-1 small"><label for="hcc"><?= e(__('appr_cat_count')) ?></label><input class="form-control form-control-sm" style="max-width:80px" type="number" min="0" max="20" id="hcc" name="header_category_count" value="<?= e($val('header_category_count')) ?>"></div>
                    <div class="form-text"><?= e(__('appr_cat_help')) ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-droplet-half"></i> <?= e(__('appr_colors')) ?></div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach (['theme_brand' => 'appr_color_brand', 'theme_brand_dark' => 'appr_color_dark', 'theme_accent' => 'appr_color_accent'] as $k => $lbl): ?>
                            <div class="col-4">
                                <label class="form-label small" for="c_<?= $k ?>"><?= e(__($lbl)) ?></label>
                                <input class="<?= $cls($k, 'form-control form-control-color w-100') ?>" type="color" id="c_<?= $k ?>" name="<?= $k ?>" value="<?= e($val($k)) ?>">
                                <div class="small text-muted font-monospace"><?= e($val($k)) ?></div>
                                <?= $err($k) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text"><?= e(__('appr_colors_help')) ?></div>
                    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="reset_colors" value="1" id="rc"><label class="form-check-label small" for="rc"><?= e(__('appr_reset_colors')) ?></label></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-layout-text-window-reverse"></i> <?= e(__('appr_footer')) ?></div>
                <div class="card-body row g-2">
                    <div class="col-md-6"><label class="form-label small" for="f1b"><?= e(__('appr_col_title', ['n' => num(1)])) ?> (বাংলা)</label><input class="form-control form-control-sm" id="f1b" name="footer_1_title_bn" value="<?= e($val('footer_1_title_bn')) ?>" maxlength="60" placeholder="<?= e(Helpers::transIn('quick_links', 'bn')) ?>"></div>
                    <div class="col-md-6"><label class="form-label small" for="f1e"><?= e(__('appr_col_title', ['n' => num(1)])) ?> (English)</label><input class="form-control form-control-sm" id="f1e" name="footer_1_title_en" value="<?= e($val('footer_1_title_en')) ?>" maxlength="60" placeholder="<?= e(Helpers::transIn('quick_links', 'en')) ?>"></div>
                    <div class="col-md-6"><label class="form-label small" for="f2b"><?= e(__('appr_col_title', ['n' => num(2)])) ?> (বাংলা)</label><input class="form-control form-control-sm" id="f2b" name="footer_2_title_bn" value="<?= e($val('footer_2_title_bn')) ?>" maxlength="60" placeholder="<?= e(Helpers::transIn('information', 'bn')) ?>"></div>
                    <div class="col-md-6"><label class="form-label small" for="f2e"><?= e(__('appr_col_title', ['n' => num(2)])) ?> (English)</label><input class="form-control form-control-sm" id="f2e" name="footer_2_title_en" value="<?= e($val('footer_2_title_en')) ?>" maxlength="60" placeholder="<?= e(Helpers::transIn('information', 'en')) ?>"></div>
                    <div class="col-md-6"><label class="form-label small" for="fcb"><?= e(__('appr_copyright')) ?> (বাংলা)</label><input class="form-control form-control-sm" id="fcb" name="footer_copyright_bn" value="<?= e($val('footer_copyright_bn')) ?>" maxlength="160" placeholder="<?= e(Helpers::transIn('all_rights', 'bn')) ?>"></div>
                    <div class="col-md-6"><label class="form-label small" for="fce"><?= e(__('appr_copyright')) ?> (English)</label><input class="form-control form-control-sm" id="fce" name="footer_copyright_en" value="<?= e($val('footer_copyright_en')) ?>" maxlength="160" placeholder="<?= e(Helpers::transIn('all_rights', 'en')) ?>"></div>
                    <div class="col-12 mt-2"><?= $sw('footer_show_categories', __('appr_footer_cats')) ?></div>
                    <div class="col-12"><?= $sw('footer_track_enabled', __('appr_footer_track'), __('appr_footer_track_help')) ?></div>
                    <div class="col-md-6"><input class="form-control form-control-sm" name="footer_track_title_bn" value="<?= e($val('footer_track_title_bn')) ?>" maxlength="60" placeholder="<?= e(Helpers::transIn('track_order', 'bn')) ?>" aria-label="<?= e(__('appr_track_title')) ?> (বাংলা)"></div>
                    <div class="col-md-6"><input class="form-control form-control-sm" name="footer_track_title_en" value="<?= e($val('footer_track_title_en')) ?>" maxlength="60" placeholder="<?= e(Helpers::transIn('track_order', 'en')) ?>" aria-label="<?= e(__('appr_track_title')) ?> (English)"></div>
                    <div class="col-12 form-text"><?= e(__('appr_footer_help')) ?></div>
                </div>
            </div>

            <div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('appr_logo_note')) ?> <?php if (can('settings.view')): ?><a href="<?= e(url('admin/settings/general')) ?>"><?= e(__('set_general')) ?></a><?php endif; ?></div>
        </div>
    </div>
    </fieldset>
    <?php if ($edit): ?><div class="position-sticky bottom-0 bg-light py-2"><button class="btn btn-brand"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button></div><?php endif; ?>
</form>

<?php else: ?>
    <div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('menu_help_' . $tab)) ?> <?= e(__('menu_link_help')) ?></div>
    <div class="card border-0 shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead class="table-light"><tr><th style="width:70px"><?= e(__('appr_order')) ?></th><th><?= e(__('appr_label')) ?></th><th><?= e(__('appr_link')) ?></th><th><?= e(__('status')) ?></th><th class="text-end"></th></tr></thead>
                <tbody>
                <?php if (!$items): ?><tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('menu_empty')) ?></td></tr><?php endif; ?>
                <?php foreach ($items as $i => $m): $mid = (int) $m['id']; $editing = (string) $errFor === (string) $mid; ?>
                    <tr class="<?= (int) $m['is_active'] ? '' : 'text-muted' ?>">
                        <td class="text-nowrap">
                            <?php if ($edit): ?>
                                <form method="post" action="<?= e(url('admin/appearance/links/' . $mid . '/move')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-link p-0" <?= $i === 0 ? 'disabled' : '' ?> title="<?= e(__('appr_up')) ?>"><i class="bi bi-arrow-up"></i></button></form>
                                <form method="post" action="<?= e(url('admin/appearance/links/' . $mid . '/move')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-link p-0" <?= $i === count($items) - 1 ? 'disabled' : '' ?> title="<?= e(__('appr_down')) ?>"><i class="bi bi-arrow-down"></i></button></form>
                            <?php endif; ?>
                        </td>
                        <td><?php if ($m['icon']): ?><i class="bi <?= e($m['icon']) ?>"></i> <?php endif; ?><b><?= e($m['label_bn'] ?: $m['label_en']) ?></b> <span class="text-muted">/ <?= e($m['label_en']) ?></span></td>
                        <td class="font-monospace"><?= e($m['url']) ?><?= (int) $m['new_tab'] ? ' <i class="bi bi-box-arrow-up-right" title="' . e(__('appr_new_tab')) . '"></i>' : '' ?>
                            <?php if (!Menu::validLink((string) $m['url'])): ?><span class="badge text-bg-danger"><?= e(__('appr_bad_link')) ?></span><?php endif; ?></td>
                        <td><span class="badge text-bg-<?= (int) $m['is_active'] ? 'success' : 'secondary' ?>"><?= e(__((int) $m['is_active'] ? 'active' : 'inactive')) ?></span></td>
                        <td class="text-end text-nowrap">
                            <?php if ($edit): ?>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#me<?= $mid ?>" title="<?= e(__('edit')) ?>"><i class="bi bi-pencil"></i></button>
                                <form method="post" action="<?= e(url('admin/appearance/links/' . $mid . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" title="<?= e(__((int) $m['is_active'] ? 'deactivate' : 'activate')) ?>"><i class="bi <?= (int) $m['is_active'] ? 'bi-eye' : 'bi-eye-slash' ?>"></i></button></form>
                                <form method="post" action="<?= e(url('admin/appearance/links/' . $mid . '/delete')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="<?= e(__('delete')) ?>" data-confirm-click="<?= e(__('appr_delete_confirm')) ?>"><i class="bi bi-trash"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($edit): ?>
                        <tr class="collapse<?= $editing ? ' show' : '' ?>" id="me<?= $mid ?>"><td colspan="5" class="bg-light">
                            <?= View::partial('admin/appearance/_link_form', ['action' => url('admin/appearance/links/' . $mid), 'm' => $editing ? array_merge($m, $old) : $m, 'location' => $tab, 'errors' => $editing ? $errors : []]) ?>
                        </td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($edit): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-lg"></i> <?= e(__('appr_add_link')) ?></div>
            <div class="card-body">
                <?= View::partial('admin/appearance/_link_form', ['action' => url('admin/appearance/links'), 'm' => $errFor === 'new' ? $old : ['new_tab' => 0], 'location' => $tab, 'errors' => $errFor === 'new' ? $errors : []]) ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
