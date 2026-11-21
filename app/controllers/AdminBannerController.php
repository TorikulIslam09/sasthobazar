<?php
declare(strict_types=1);

/** Banners (home slider, home rows, category top, sidebar) and the storefront popup. */
class AdminBannerController extends Controller
{
    public function index(): void
    {
        $position = Helpers::str('position');
        $where = in_array($position, Banner::POSITIONS, true) ? 'WHERE position = :p' : '';
        $params = $where ? ['p' => $position] : [];
        $counts = array_column($this->db->fetchAll('SELECT position, COUNT(*) AS n FROM banners GROUP BY position'), 'n', 'position');
        $this->view('admin/banners/index', [
            'title'    => __('banners'),
            'position' => $position,
            'counts'   => $counts,
            'banners'  => $this->db->fetchAll("SELECT * FROM banners {$where} ORDER BY FIELD(position, 'home_slider', 'popup', 'home_middle', 'home_bottom', 'category_top', 'sidebar'), sort_order, id", $params),
        ], 'admin');
    }

    public function create(): void
    {
        $pos = in_array(Helpers::str('position'), Banner::POSITIONS, true) ? Helpers::str('position') : 'home_slider';
        $this->form(null, ['position' => $pos, 'lang' => 'all', 'sort_order' => '0', 'popup_delay' => '3', 'is_active' => '1']);
    }

    public function edit(string $id): void
    {
        $b = $this->banner((int) $id);
        $in = $b;
        foreach (['starts_at', 'ends_at'] as $k) {
            $in[$k] = $b[$k] ? date('Y-m-d\TH:i', strtotime($b[$k])) : '';
        }
        $this->form($b, $in);
    }

    private function banner(int $id): array
    {
        $b = Banner::find($id);
        if (!$b) {
            $this->notFound();
        }
        return $b;
    }

    private function form(?array $banner, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/banners/form', [
            'title'  => __($banner ? 'edit_banner' : 'add_banner'),
            'banner' => $banner,
            'input'  => is_array($old) ? $old : $defaults,
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        [$data, $images] = $this->validated(null, 'admin/banners/create');
        $id = Banner::create($data + $images);
        flash('success', __('banner_saved'));
        redirect('admin/banners?position=' . $data['position']);
    }

    public function update(string $id): void
    {
        $b = $this->banner((int) $id);
        [$data, $images] = $this->validated($b, 'admin/banners/' . (int) $id . '/edit');
        foreach (['image', 'image_mobile'] as $k) {
            if (isset($images[$k])) {
                Upload::delete($b[$k]);
            } elseif ($k === 'image_mobile' && !empty($_POST['remove_mobile'])) {
                Upload::delete($b[$k]);
                $images[$k] = null;
            } elseif ($k === 'image' && !empty($_POST['remove_image']) && $data['position'] === 'popup') {
                Upload::delete($b[$k]);
                $images[$k] = '';
            }
        }
        Banner::updateById((int) $id, $data + $images);
        flash('success', __('banner_saved'));
        redirect('admin/banners?position=' . $data['position']);
    }

    public function toggle(string $id): void
    {
        $b = $this->banner((int) $id);
        Banner::updateById((int) $id, ['is_active' => (int) $b['is_active'] ? 0 : 1]);
        flash('success', __('banner_saved'));
        back();
    }

    public function destroy(string $id): void
    {
        $b = $this->banner((int) $id);
        Banner::deleteById((int) $id);
        foreach (['image', 'image_mobile'] as $k) {
            if (!str_starts_with((string) $b[$k], 'uploads/demo/')) { // demo files are removed by database/demo.php --remove
                Upload::delete($b[$k]);
            }
        }
        flash('success', __('banner_deleted'));
        redirect('admin/banners?position=' . $b['position']);
    }

    /** @return array{0: array, 1: array} [data, uploaded image paths] */
    private function validated(?array $banner, string $formPath): array
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'title'       => 'max:200',
            'title_bn'    => 'max:200',
            'subtitle'    => 'max:255',
            'subtitle_bn' => 'max:255',
            'link_url'    => 'max:255',
            'button_text' => 'max:60',
            'position'    => 'required|in:' . implode(',', Banner::POSITIONS),
            'lang'        => 'required|in:' . implode(',', Banner::LANGS),
            'sort_order'  => 'integer',
            'popup_delay' => 'integer|gte:0|lte:120',
        ]);
        $link = trim((string) ($in['link_url'] ?? ''));
        if ($link !== '' && !isset($errors['link_url'])
            && !preg_match('/^https?:\/\/[^\s<>"]+$/i', $link) && !preg_match('/^\/?[A-Za-z0-9\-._~\/?&=%+#]*$/', $link)) {
            $errors['link_url'] = __('v_banner_link');
        }
        if (str_starts_with(strtolower($link), 'javascript:')) {
            $errors['link_url'] = __('v_banner_link');
        }
        $dates = [];
        foreach (['starts_at', 'ends_at'] as $k) {
            $v = (string) ($in[$k] ?? '');
            if ($v === '') {
                $dates[$k] = null;
                continue;
            }
            $d = DateTime::createFromFormat('!Y-m-d\TH:i', $v);
            if (!$d) {
                $errors[$k] = __('v_date');
            } else {
                $dates[$k] = $d->format('Y-m-d H:i:00');
            }
        }
        if (!empty($dates['starts_at']) && !empty($dates['ends_at']) && $dates['ends_at'] <= $dates['starts_at']) {
            $errors['ends_at'] = __('v_end_after_start');
        }

        $images = [];
        foreach (['image', 'image_mobile'] as $k) {
            $files = Upload::files($k);
            if ($files && ($e = Upload::validateImage($files[0]))) {
                $errors[$k] = $e;
            }
        }
        $position = $in['position'] ?? 'home_slider';
        $hasImage = Upload::files('image') || ($banner && $banner['image'] !== '' && empty($_POST['remove_image']));
        if ($position !== 'popup' && !$hasImage && !isset($errors['image'])) {
            $errors['image'] = __('banner_image_required');
        }
        if ($position === 'popup' && !$hasImage && trim((string) ($in['title'] ?? '')) === '' && !isset($errors['image'])) {
            $errors['title'] = __('popup_needs_content');
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        foreach (['image', 'image_mobile'] as $k) {
            if ($files = Upload::files($k)) {
                $images[$k] = Upload::storeImage($files[0], 'banners');
            }
        }
        if (!$banner && !isset($images['image'])) {
            $images['image'] = ''; // popup without picture (column is NOT NULL)
        }
        $nul = static fn(string $k) => trim((string) ($in[$k] ?? '')) !== '' ? trim((string) $in[$k]) : null;
        return [[
            'title'       => $nul('title'),
            'title_bn'    => $nul('title_bn'),
            'subtitle'    => $nul('subtitle'),
            'subtitle_bn' => $nul('subtitle_bn'),
            'link_url'    => $link !== '' ? $link : null,
            'button_text' => $nul('button_text'),
            'popup_delay' => (int) ($in['popup_delay'] ?? 3),
            'position'    => $position,
            'lang'        => $in['lang'],
            'sort_order'  => (int) ($in['sort_order'] ?? 0),
            'starts_at'   => $dates['starts_at'],
            'ends_at'     => $dates['ends_at'],
            'is_active'   => !empty($in['is_active']) ? 1 : 0,
        ], $images];
    }
}
