<?php
declare(strict_types=1);

/**
 * Admin: Page Builder - add sections (banner, products, reviews, text, FAQ, video ...) to any storefront page,
 * at any position, with colours / spacing / width / device / schedule. Reorder, show / hide, duplicate, delete.
 * Permissions: page_builder.view / create / edit / delete.
 */
class AdminPageBuilderController extends Controller
{
    /** page key => label, CMS pages included. */
    private function pages(): array
    {
        $out = [];
        foreach (Section::PAGES as $k) {
            $out[$k] = __('pb_page_' . $k);
        }
        foreach ($this->db->fetchAll('SELECT slug, title_en, title_bn FROM pages ORDER BY sort_order, id') as $p) {
            $out['page:' . $p['slug']] = __('pb_page_cms', ['title' => tr($p['title_bn'], $p['title_en'])]);
        }
        return $out;
    }

    private function pageKey(): string
    {
        $k = Helpers::str('page') ?: 'home';
        return Section::validPage($k) ? $k : 'home';
    }

    /** Public URL of a page key (for "View page"). */
    public static function pageUrl(string $key): ?string
    {
        return match (true) {
            $key === 'home', $key === 'all' => url('/'),
            $key === 'products' => url('products'),
            $key === 'offers' => url('offers'),
            $key === 'cart' => url('cart'),
            $key === 'prescription' => url('prescription'),
            str_starts_with($key, 'page:') => url('page/' . substr($key, 5)),
            default => null,
        };
    }

    public function index(): void
    {
        $page = $this->pageKey();
        $rows = $this->db->fetchAll('SELECT * FROM page_sections WHERE page_key = :p ORDER BY sort_order, id', ['p' => $page]);
        $byPos = [];
        foreach (Section::positionsFor($page) as $pos) {
            $byPos[$pos] = [];
        }
        foreach ($rows as $r) {
            $byPos[$r['position']][] = $r;
        }
        $counts = array_column($this->db->fetchAll('SELECT page_key, COUNT(*) AS n FROM page_sections GROUP BY page_key'), 'n', 'page_key');
        $this->view('admin/page_builder/index', [
            'title'  => __('page_builder'),
            'page'   => $page,
            'pages'  => $this->pages(),
            'byPos'  => $byPos,
            'counts' => $counts,
            'viewUrl' => self::pageUrl($page),
        ], 'admin');
    }

    public function create(): void
    {
        $page = $this->pageKey();
        $positions = Section::positionsFor($page);
        $pos = in_array(Helpers::str('position'), $positions, true) ? Helpers::str('position') : 'bottom';
        $type = Helpers::str('type');
        if (!isset(Section::types()[$type])) {
            $this->view('admin/page_builder/types', ['title' => __('pb_add_section'), 'page' => $page, 'pages' => $this->pages(), 'position' => $pos], 'admin');
            return;
        }
        $this->form(['id' => null, 'page_key' => $page, 'position' => $pos, 'type' => $type, 'admin_label' => '', 'title_en' => '', 'title_bn' => '',
                     'settings' => '{}', 'style' => '{"card":1,"padding":"md","width":"boxed","show_on":"all"}', 'is_active' => 1, 'starts_at' => null, 'ends_at' => null]);
    }

    public function edit(string $id): void
    {
        $this->form($this->find($id));
    }

    private function find(string $id): array
    {
        $s = $this->db->fetch('SELECT * FROM page_sections WHERE id = :id', ['id' => (int) $id]);
        if (!$s) {
            $this->notFound();
        }
        return $s;
    }

    private function form(array $s): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/page_builder/form', [
            'title'      => $s['id'] ? __('pb_edit_section') : __('pb_add_section'),
            's'          => $s,
            'set'        => is_array($old['_set'] ?? null) ? $old['_set'] : (json_decode((string) $s['settings'], true) ?: []),
            'style'      => is_array($old['_style'] ?? null) ? $old['_style'] : (json_decode((string) $s['style'], true) ?: []),
            'old'        => is_array($old) ? $old : [],
            'fields'     => Section::types()[$s['type']][1],
            'pages'      => $this->pages(),
            'categories' => Category::allById(),
            'brands'     => $this->db->fetchAll('SELECT id, name, name_bn FROM brands WHERE is_active = 1 ORDER BY name'),
            'errors'     => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    /**
     * Clean the posted settings for a type. Images: new upload replaces, "remove" clears, otherwise the old file stays.
     * @return array{0: array, 1: array, 2: array} [settings, errors, files to delete after save]
     */
    private function cleanSettings(string $type, array $current): array
    {
        $fields = Section::types()[$type][1];
        $in = $_POST['set'] ?? [];
        $in = is_array($in) ? $in : [];
        $str = static fn($v, int $max) => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $out = [];
        $errors = [];
        $drop = [];
        foreach ($fields as $name => $def) {
            [$kind] = $def;
            $extra = $def[1] ?? null;
            switch ($kind) {
                case 'text_i18n':
                case 'textarea_i18n':
                case 'html_i18n':
                    $max = $kind === 'text_i18n' ? 200 : ($kind === 'textarea_i18n' ? 1000 : 60000);
                    foreach (['bn', 'en'] as $l) {
                        $v = $str($in[$name . '_' . $l] ?? '', $max);
                        $out[$name . '_' . $l] = $kind === 'html_i18n' ? Html::clean($v) : $v;
                    }
                    break;
                case 'link':
                    $v = $str($in[$name] ?? '', 255);
                    if ($v !== '' && !Menu::validLink($v)) {
                        $errors[$name] = __('appr_bad_link');
                    }
                    $out[$name] = $v;
                    break;
                case 'video':
                    $v = $str($in[$name] ?? '', 255);
                    if ($v !== '' && Section::videoEmbed($v) === null) {
                        $errors[$name] = __('pb_bad_video');
                    }
                    $out[$name] = $v;
                    break;
                case 'select':
                    $v = (string) ($in[$name] ?? '');
                    $out[$name] = in_array($v, $extra, true) ? $v : $extra[0];
                    break;
                case 'number':
                    [$min, $max, $dflt] = $extra;
                    $out[$name] = isset($in[$name]) && is_numeric($in[$name]) ? max($min, min($max, (int) $in[$name])) : $dflt;
                    break;
                case 'checkbox':
                    $out[$name] = empty($in[$name]) ? 0 : 1;
                    break;
                case 'category':
                    $v = (int) ($in[$name] ?? 0);
                    $out[$name] = isset(Category::allById()[$v]) ? $v : 0;
                    break;
                case 'brand':
                    $out[$name] = (int) ($in[$name] ?? 0);
                    break;
                case 'ids':
                    $out[$name] = implode(',', array_slice(array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,]+/', $str($in[$name] ?? '', 500)))))), 0, 24));
                    break;
                case 'image':
                    $cur = (string) ($current[$name] ?? '');
                    $f = Upload::files('img_' . $name)[0] ?? null;
                    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        if ($e = Upload::validateImage($f, 4 * 1024 * 1024)) {
                            $errors[$name] = $e;
                            $out[$name] = $cur;
                        } else {
                            $out[$name] = Upload::storeImage($f, 'sections');
                            if ($cur !== '') {
                                $drop[] = $cur;
                            }
                        }
                    } elseif (!empty($in['remove_' . $name])) {
                        $out[$name] = '';
                        if ($cur !== '') {
                            $drop[] = $cur;
                        }
                    } else {
                        $out[$name] = $cur;
                    }
                    break;
                case 'repeater':
                    $items = [];
                    foreach (array_slice(is_array($in[$name] ?? null) ? $in[$name] : [], 0, 30) as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $item = [];
                        foreach ($extra as $sub => $subKind) {
                            $v = $str($row[$sub] ?? '', $subKind === 'textarea' ? 1500 : 200);
                            if ($subKind === 'rating') {
                                $v = max(0, min(5, (int) $v));
                            } elseif ($subKind === 'icon' && $v !== '' && !preg_match('/^bi-[a-z0-9-]{1,40}$/', $v)) {
                                $v = '';
                            } elseif ($subKind === 'link' && $v !== '' && !Menu::validLink($v)) {
                                $v = '';
                            }
                            $item[$sub] = $v;
                        }
                        if (implode('', array_map('strval', array_diff_key($item, ['rating' => 1, 'icon' => 1]))) !== '') {
                            $items[] = $item;
                        }
                    }
                    $out[$name] = $items;
                    break;
            }
        }
        if ($type === 'banner' && ($out['image'] ?? '') === '' && !isset($errors['image'])) {
            $errors['image'] = __('pb_need_image');
        }
        return [$out, $errors, $drop];
    }

    private function cleanStyle(): array
    {
        $in = is_array($_POST['style'] ?? null) ? $_POST['style'] : [];
        $color = static fn($v) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : '';
        return [
            'card'    => empty($in['card']) ? 0 : 1,
            'bg'      => empty($in['use_bg']) ? '' : $color($in['bg'] ?? ''),
            'color'   => empty($in['use_color']) ? '' : $color($in['color'] ?? ''),
            'padding' => isset(Section::PADDINGS[$in['padding'] ?? '']) ? $in['padding'] : 'md',
            'width'   => ($in['width'] ?? '') === 'full' ? 'full' : 'boxed',
            'show_on' => in_array($in['show_on'] ?? '', ['all', 'desktop', 'mobile'], true) ? $in['show_on'] : 'all',
        ];
    }

    /** Common fields + validation; redirects back with errors. */
    private function save(?array $s): void
    {
        $type = $s ? $s['type'] : Helpers::str('type');
        if (!isset(Section::types()[$type])) {
            $this->notFound();
        }
        $page = (string) ($_POST['page_key'] ?? ($s['page_key'] ?? 'home'));
        if (!Section::validPage($page)) {
            $page = 'home';
        }
        $pos = (string) ($_POST['position'] ?? 'bottom');
        if (!in_array($pos, Section::positionsFor($page), true)) {
            $pos = 'bottom';
        }
        $current = $s ? (json_decode((string) $s['settings'], true) ?: []) : [];
        [$set, $errors, $drop] = $this->cleanSettings($type, $current);
        $style = $this->cleanStyle();
        $date = static function (string $k): ?string {
            $v = trim((string) ($_POST[$k] ?? ''));
            return $v !== '' && strtotime($v) ? date('Y-m-d H:i:s', strtotime($v)) : null;
        };
        $row = [
            'page_key'    => $page,
            'position'    => $pos,
            'admin_label' => mb_substr(trim((string) ($_POST['admin_label'] ?? '')), 0, 120) ?: null,
            'title_en'    => mb_substr(trim((string) ($_POST['title_en'] ?? '')), 0, 200) ?: null,
            'title_bn'    => mb_substr(trim((string) ($_POST['title_bn'] ?? '')), 0, 200) ?: null,
            'settings'    => json_encode($set, JSON_UNESCAPED_UNICODE),
            'style'       => json_encode($style),
            'is_active'   => empty($_POST['is_active']) ? 0 : 1,
            'starts_at'   => $date('starts_at'),
            'ends_at'     => $date('ends_at'),
        ];
        if ($row['starts_at'] && $row['ends_at'] && $row['ends_at'] <= $row['starts_at']) {
            $errors['ends_at'] = __('pb_bad_schedule');
        }
        if ($errors) {
            // images uploaded in this failed attempt are removed again; the form asks to choose them again
            foreach ($set as $k => $v) {
                if (is_string($v) && str_starts_with($v, 'uploads/sections/') && ($current[$k] ?? '') !== $v) {
                    Upload::delete($v);
                    $set[$k] = (string) ($current[$k] ?? '');
                }
            }
            $_SESSION['_errors'] = $errors;
            Helpers::setOld(array_merge(array_map(static fn($v) => is_scalar($v) ? (string) $v : '', $_POST), ['_set' => $set, '_style' => $style]));
            flash('error', __('form_has_errors'));
            if ($s) {
                redirect('admin/page-builder/' . (int) $s['id'] . '/edit');
            }
            redirect('admin/page-builder/create?' . http_build_query(['page' => $page, 'position' => $pos, 'type' => $type]));
        }
        if ($s) {
            $row['updated_at'] = date('Y-m-d H:i:s');
            if ($row['page_key'] !== $s['page_key'] || $row['position'] !== $s['position']) {
                $row['sort_order'] = $this->nextSort($row['page_key'], $row['position']);
            }
            $this->db->update('page_sections', $row, 'id = :id', ['id' => (int) $s['id']]);
            Audit::log('page_builder.update', 'page_sections', (int) $s['id'], ['type' => $s['type'], 'page' => $s['page_key']], ['page' => $page, 'position' => $pos]);
            $id = (int) $s['id'];
        } else {
            $row += ['type' => $type, 'sort_order' => $this->nextSort($page, $pos), 'created_by' => Auth::id()];
            $id = $this->db->insert('page_sections', $row);
            Audit::log('page_builder.create', 'page_sections', $id, null, ['type' => $type, 'page' => $page, 'position' => $pos]);
        }
        $this->deleteUnused($drop);
        flash('success', __('pb_saved'));
        redirect(!empty($_POST['stay']) ? 'admin/page-builder/' . $id . '/edit' : 'admin/page-builder?page=' . rawurlencode($page) . '#s' . $id);
    }

    /** Delete uploaded image files that no section uses any more (copies made with "Duplicate" share files). */
    private function deleteUnused(array $paths): void
    {
        foreach ($paths as $v) {
            if (!is_string($v) || !str_starts_with($v, 'uploads/sections/')) {
                continue;
            }
            $needle = '%' . addcslashes(trim((string) json_encode($v), '"'), '%_\\') . '%';
            if (!$this->db->fetchColumn('SELECT 1 FROM page_sections WHERE settings LIKE :p', ['p' => $needle])) {
                Upload::delete($v);
            }
        }
    }

    private function nextSort(string $page, string $pos): int
    {
        return (int) $this->db->fetchColumn('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM page_sections WHERE page_key = :p AND position = :o', ['p' => $page, 'o' => $pos]);
    }

    public function store(): void
    {
        $this->save(null);
    }

    public function update(string $id): void
    {
        $this->save($this->find($id));
    }

    public function toggle(string $id): void
    {
        $s = $this->find($id);
        $this->db->update('page_sections', ['is_active' => (int) $s['is_active'] ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $s['id']]);
        Audit::log('page_builder.toggle', 'page_sections', (int) $s['id'], ['is_active' => $s['is_active']], ['is_active' => (int) $s['is_active'] ? 0 : 1]);
        redirect('admin/page-builder?page=' . rawurlencode($s['page_key']) . '#s' . (int) $s['id']);
    }

    /** Up / down within its page + position (renumbers 10, 20, 30 ...). */
    public function move(string $id): void
    {
        $s = $this->find($id);
        $ids = array_map('intval', array_column($this->db->fetchAll(
            'SELECT id FROM page_sections WHERE page_key = :p AND position = :o ORDER BY sort_order, id',
            ['p' => $s['page_key'], 'o' => $s['position']]
        ), 'id'));
        $pos = array_search((int) $s['id'], $ids, true);
        $to = Helpers::str('dir') === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && $to >= 0 && $to < count($ids)) {
            [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
            $this->db->transaction(function (Database $db) use ($ids): void {
                foreach ($ids as $i => $sid) {
                    $db->update('page_sections', ['sort_order' => ($i + 1) * 10], 'id = :id', ['id' => $sid]);
                }
            });
        }
        redirect('admin/page-builder?page=' . rawurlencode($s['page_key']) . '#s' . (int) $s['id']);
    }

    /** Copy (hidden until switched on). Images are shared by path, so deleting one copy's image never removes the other's file. */
    public function duplicate(string $id): void
    {
        $s = $this->find($id);
        $copy = $s;
        unset($copy['id'], $copy['created_at'], $copy['updated_at']);
        $copy['admin_label'] = mb_substr(($s['admin_label'] ?: __('pb_type_' . $s['type'])) . ' (' . __('pb_copy') . ')', 0, 120);
        $copy['is_active'] = 0;
        $copy['sort_order'] = $this->nextSort($s['page_key'], $s['position']);
        $copy['created_by'] = Auth::id();
        $newId = $this->db->insert('page_sections', $copy);
        Audit::log('page_builder.duplicate', 'page_sections', $newId, ['from' => (int) $s['id']], null);
        flash('success', __('pb_duplicated'));
        redirect('admin/page-builder?page=' . rawurlencode($s['page_key']) . '#s' . $newId);
    }

    public function destroy(string $id): void
    {
        $s = $this->find($id);
        $this->db->delete('page_sections', 'id = :id', ['id' => (int) $s['id']]);
        $this->deleteUnused(array_filter(json_decode((string) $s['settings'], true) ?: [], 'is_string'));
        Audit::log('page_builder.delete', 'page_sections', (int) $s['id'], ['type' => $s['type'], 'page' => $s['page_key']], null);
        flash('success', __('pb_deleted'));
        redirect('admin/page-builder?page=' . rawurlencode($s['page_key']));
    }
}
