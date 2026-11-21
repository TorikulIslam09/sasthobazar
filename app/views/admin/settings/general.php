<?php
/** General settings. Vars: $input, $errors */
$err = static fn(string $f) => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$cls = static fn(string $f, string $base = 'form-control') => $base . (isset($errors[$f]) ? ' is-invalid' : '');
$val = static fn(string $f) => (string) ($input[$f] ?? '');
$ro = !can('settings.edit');
$logo = (string) setting('site_logo', '');
$fav = (string) setting('site_favicon', '');
$field = static function (string $name, string $label, string $type = 'text', array $attr = []) use ($cls, $val, $err): string {
    $a = '';
    foreach ($attr as $k => $v) {
        $a .= ' ' . $k . '="' . e((string) $v) . '"';
    }
    return '<label class="form-label" for="f_' . $name . '">' . e($label) . '</label>'
        . '<input class="' . $cls($name) . '" type="' . $type . '" id="f_' . $name . '" name="' . $name . '" value="' . e($val($name)) . '"' . $a . '>' . $err($name);
};
?>
<?= View::partial('admin/settings/_nav', ['active' => 'general']) ?>
<form method="post" action="<?= e(url('admin/settings/general')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <fieldset <?= $ro ? 'disabled' : '' ?>>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><?= e(__('set_site_info')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><?= $field('site_name', __('site_name_en') . ' *', 'text', ['maxlength' => 60, 'required' => 'required']) ?></div>
                    <div class="col-md-6"><?= $field('site_name_bn', __('site_name_bn'), 'text', ['maxlength' => 60, 'lang' => 'bn']) ?></div>
                    <div class="col-md-6"><?= $field('site_tagline_en', __('tagline_en'), 'text', ['maxlength' => 160]) ?></div>
                    <div class="col-md-6"><?= $field('site_tagline', __('tagline_bn'), 'text', ['maxlength' => 160, 'lang' => 'bn']) ?></div>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><?= e(__('contact_us')) ?></div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><?= $field('contact_phone', __('hotline'), 'text', ['maxlength' => 30, 'inputmode' => 'tel']) ?></div>
                    <div class="col-md-6"><?= $field('contact_email', __('email'), 'email', ['maxlength' => 120]) ?></div>
                    <div class="col-12"><?= $field('contact_address', __('address'), 'text', ['maxlength' => 255]) ?></div>
                    <div class="col-md-6"><?= $field('facebook_url', 'Facebook', 'url', ['maxlength' => 255, 'placeholder' => 'https://facebook.com/...']) ?></div>
                    <div class="col-md-6"><?= $field('youtube_url', 'YouTube', 'url', ['maxlength' => 255, 'placeholder' => 'https://youtube.com/...']) ?></div>
                    <div class="col-md-6"><?= $field('instagram_url', 'Instagram', 'url', ['maxlength' => 255, 'placeholder' => 'https://instagram.com/...']) ?></div>
                    <div class="col-md-6"><?= $field('whatsapp_number', 'WhatsApp', 'text', ['maxlength' => 20, 'placeholder' => '8801XXXXXXXXX']) ?></div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">SEO</div>
                <div class="card-body row g-3">
                    <div class="col-12"><?= $field('meta_title', __('meta_title'), 'text', ['maxlength' => 160]) ?></div>
                    <div class="col-12"><?= $field('meta_description', __('meta_description'), 'text', ['maxlength' => 300]) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><?= e(__('logo_favicon')) ?></div>
                <div class="card-body">
                    <label class="form-label" for="logo"><?= e(__('site_logo')) ?></label>
                    <?php if ($logo !== ''): ?>
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 border rounded bg-light"><img src="<?= e(url($logo)) ?>" alt="" style="max-height: 48px; max-width: 200px">
                            <div class="form-check ms-auto"><input class="form-check-input" type="checkbox" name="remove_site_logo" value="1" id="rl"><label class="form-check-label small" for="rl"><?= e(__('remove')) ?></label></div></div>
                    <?php endif; ?>
                    <input class="<?= $cls('logo') ?>" type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
                    <div class="form-text"><?= e(__('logo_help')) ?></div>
                    <?= $err('logo') ?>
                    <hr>
                    <label class="form-label" for="fav"><?= e(__('site_favicon')) ?></label>
                    <?php if ($fav !== ''): ?>
                        <div class="d-flex align-items-center gap-2 mb-2"><img src="<?= e(url($fav)) ?>" alt="" style="width: 32px; height: 32px">
                            <div class="form-check ms-auto"><input class="form-check-input" type="checkbox" name="remove_site_favicon" value="1" id="rf"><label class="form-check-label small" for="rf"><?= e(__('remove')) ?></label></div></div>
                    <?php endif; ?>
                    <input class="<?= $cls('favicon') ?>" type="file" id="fav" name="favicon" accept="image/png,image/jpeg,image/webp,image/gif">
                    <div class="form-text"><?= e(__('favicon_help')) ?></div>
                    <?= $err('favicon') ?>
                </div>
            </div>
            <div class="alert alert-light border small"><i class="bi bi-info-circle"></i> <?= e(__('site_name_help')) ?></div>
        </div>
    </div>
    <?php if (!$ro): ?><button class="btn btn-brand mt-3"><i class="bi bi-check2"></i> <?= e(__('save')) ?></button><?php endif; ?>
    </fieldset>
</form>
