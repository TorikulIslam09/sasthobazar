<?php
declare(strict_types=1);

/**
 * Settings → Watermark: SasthoBazar watermark on product photos.
 * Form + live preview, apply to existing product photos (in batches), remove from all photos (restore originals).
 * Permissions: settings.view (see) / settings.edit (change, apply, remove).
 */
class AdminWatermarkController extends Controller
{
    private const BATCH = 150;

    public function index(): void
    {
        $total = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM product_images');
        $this->view('admin/settings/watermark', [
            'title'     => __('settings'),
            'cfg'       => Watermark::config(),
            'enabled'   => (bool) setting('watermark_enabled', false),
            'available' => Watermark::available(),
            'logo'      => (string) setting('watermark_logo', ''),
            'total'     => $total,
            'errors'    => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
    }

    public function save(): void
    {
        $in = $this->postData();
        $errors = [];
        $color = strtolower(trim((string) ($in['watermark_color'] ?? '')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $errors['watermark_color'] = __('appr_bad_color');
        }
        $values = [
            'watermark_enabled'  => [empty($in['watermark_enabled']) ? '0' : '1', 'watermark', 'bool'],
            'watermark_type'     => [($in['watermark_type'] ?? '') === 'logo' ? 'logo' : 'text', 'watermark', 'string'],
            'watermark_text'     => [mb_substr(trim((string) ($in['watermark_text'] ?? '')), 0, 40) ?: 'SasthoBazar', 'watermark', 'string'],
            'watermark_position' => [in_array($in['watermark_position'] ?? '', Watermark::POSITIONS, true) ? $in['watermark_position'] : 'bottom_right', 'watermark', 'string'],
            'watermark_opacity'  => [(string) max(5, min(100, (int) ($in['watermark_opacity'] ?? 45))), 'watermark', 'int'],
            'watermark_size'     => [(string) max(5, min(80, (int) ($in['watermark_size'] ?? 28))), 'watermark', 'int'],
            'watermark_color'    => [$errors ? '#0e7673' : $color, 'watermark', 'string'],
        ];
        $cur = (string) setting('watermark_logo', '');
        $f = Upload::files('logo')[0] ?? null;
        if ($f) {
            if ($e = Upload::validateImage($f, 2 * 1024 * 1024)) {
                $errors['logo'] = $e;
            } else {
                $values['watermark_logo'] = [Upload::storeImage($f, 'site'), 'watermark', 'string'];
                Upload::delete($cur);
            }
        } elseif (!empty($in['remove_logo'])) {
            $values['watermark_logo'] = ['', 'watermark', 'string'];
            Upload::delete($cur);
        }
        if ($values['watermark_type'][0] === 'logo' && !isset($values['watermark_logo']) && $cur === '' && !isset($errors['logo'])) {
            $errors['logo'] = __('wm_need_logo');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            flash('error', __('form_has_errors'));
            redirect('admin/settings/watermark');
        }
        Setting::saveMany($values);
        Helpers::clearSettingsCache();
        flash('success', __('wm_saved'));
        redirect('admin/settings/watermark');
    }

    /** GET: preview image (a product photo's original, or a sample) with the current - or the posted-in-query - settings. */
    public function preview(): void
    {
        if (!Watermark::available()) {
            http_response_code(404);
            exit;
        }
        $cfg = Watermark::config();
        // live preview from the form (values validated the same way as on save)
        $q = static fn(string $k) => Helpers::str($k);
        if ($q('position') !== '') {
            $cfg['position'] = in_array($q('position'), Watermark::POSITIONS, true) ? $q('position') : $cfg['position'];
            $cfg['opacity'] = max(5, min(100, (int) $q('opacity')));
            $cfg['size'] = max(5, min(80, (int) $q('size')));
            $cfg['color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $q('color')) ? $q('color') : $cfg['color'];
            $cfg['text'] = mb_substr(trim($q('text')), 0, 40) ?: 'SasthoBazar';
            $cfg['type'] = $q('type') === 'logo' && Watermark::logoPath() ? 'logo' : 'text';
        }
        $src = null;
        $row = $this->db->fetch('SELECT path FROM product_images ORDER BY is_primary DESC, id DESC LIMIT 1');
        if ($row) {
            $orig = Watermark::originalPath($row['path']);
            $src = is_file($orig) ? $orig : (is_file(ROOT_PATH . '/public/' . $row['path']) ? ROOT_PATH . '/public/' . $row['path'] : null);
        }
        $tmpDir = STORAGE_PATH . '/private/cache';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0770, true);
        }
        if ($src === null || !in_array((@getimagesize($src)['mime'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $src = $tmpDir . '/wm-sample.jpg';
            if (!is_file($src)) {
                $im = imagecreatetruecolor(800, 800);
                imagefill($im, 0, 0, imagecolorallocate($im, 246, 248, 250));
                imagefilledrectangle($im, 240, 170, 560, 630, imagecolorallocate($im, 14, 118, 115));
                imagefilledrectangle($im, 270, 300, 530, 420, imagecolorallocate($im, 255, 255, 255));
                imagejpeg($im, $src, 90);
                imagedestroy($im);
            }
        }
        $out = $tmpDir . '/wm-preview-' . bin2hex(random_bytes(6)) . '.' . pathinfo($src, PATHINFO_EXTENSION);
        if (!Watermark::render($src, $out, $cfg)) {
            http_response_code(500);
            exit;
        }
        header('Content-Type: ' . ((@getimagesize($out)['mime'] ?? '') ?: 'image/jpeg'));
        header('Cache-Control: no-store');
        readfile($out);
        @unlink($out);
        exit;
    }

    /** POST: (re)apply the watermark to existing product photos, BATCH at a time. */
    public function apply(): void
    {
        if (!Watermark::enabled()) {
            flash('error', __('wm_turn_on_first'));
            redirect('admin/settings/watermark');
        }
        @set_time_limit(120);
        $offset = max(0, (int) Helpers::str('offset'));
        $total = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM product_images');
        $rows = $this->db->fetchAll('SELECT path FROM product_images ORDER BY id LIMIT ' . self::BATCH . ' OFFSET ' . $offset);
        $done = 0;
        foreach ($rows as $r) {
            if (Watermark::applyToStored((string) $r['path'])) {
                $done++;
            }
        }
        $next = $offset + count($rows);
        Audit::log('watermark.apply', 'product_images', null, null, ['from' => $offset, 'to' => $next, 'done' => $done]);
        if ($next < $total) {
            $_SESSION['wm_continue'] = $next;
            flash('info', __('wm_progress', ['n' => num($next), 'total' => num($total)]));
        } else {
            unset($_SESSION['wm_continue']);
            flash('success', __('wm_applied_all', ['n' => num($total)]));
        }
        redirect('admin/settings/watermark');
    }

    /** POST: remove the watermark from every product photo (copy the kept originals back). */
    public function remove(): void
    {
        @set_time_limit(120);
        $n = 0;
        foreach ($this->db->fetchAll('SELECT path FROM product_images') as $r) {
            if (Watermark::restoreOriginal((string) $r['path'])) {
                $n++;
            }
        }
        unset($_SESSION['wm_continue']);
        Audit::log('watermark.remove', 'product_images', null, null, ['restored' => $n]);
        flash('success', __('wm_removed', ['n' => num($n)]));
        redirect('admin/settings/watermark');
    }
}
