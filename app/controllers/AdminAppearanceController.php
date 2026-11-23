<?php
declare(strict_types=1);

/**
 * Admin: Header, Menus & Colours.
 *  - Header & colours: announcement bar, search placeholder, header buttons, category bar, brand colours, footer titles / copyright
 *  - Menus: links of the header bar, phone menu and the two footer columns (add / edit / reorder / hide / delete)
 * Logo, site name and contacts stay in Settings → General. Permissions: appearance.view / appearance.edit.
 */
class AdminAppearanceController extends Controller
{
    /** key => [type, max length] */
    private const SETTINGS = [
        'announce_enabled' => ['bool', 0], 'announce_show_hotline' => ['bool', 0], 'announce_show_lang' => ['bool', 0],
        'announce_text_en' => ['string', 160], 'announce_text_bn' => ['string', 160], 'announce_link' => ['link', 255],
        'search_placeholder_en' => ['string', 100], 'search_placeholder_bn' => ['string', 100],
        'header_show_rx' => ['bool', 0], 'header_show_offers' => ['bool', 0], 'header_show_wishlist' => ['bool', 0],
        'header_offers_label_en' => ['string', 40], 'header_offers_label_bn' => ['string', 40], 'header_offers_link' => ['link', 255],
        'header_show_categories' => ['bool', 0], 'header_category_count' => ['int', 0],
        'theme_brand' => ['color', 0], 'theme_brand_dark' => ['color', 0], 'theme_accent' => ['color', 0],
        'footer_1_title_en' => ['string', 60], 'footer_1_title_bn' => ['string', 60], 'footer_2_title_en' => ['string', 60], 'footer_2_title_bn' => ['string', 60],
        'footer_show_categories' => ['bool', 0], 'footer_track_enabled' => ['bool', 0],
        'footer_track_title_en' => ['string', 60], 'footer_track_title_bn' => ['string', 60], 'footer_copyright_en' => ['string', 160], 'footer_copyright_bn' => ['string', 160],
    ];
    private const DEFAULT_COLORS = ['theme_brand' => '#0e7673', 'theme_brand_dark' => '#0a5a58', 'theme_accent' => '#f0663a'];

    public function index(): void
    {
        $tab = in_array(Helpers::str('tab'), Menu::LOCATIONS, true) ? Helpers::str('tab') : 'settings'; // settings = header & colours form
        $values = [];
        foreach (self::SETTINGS as $k => [$type]) {
            $values[$k] = setting($k, $type === 'bool' ? true : ($type === 'color' ? self::DEFAULT_COLORS[$k] : ''));
        }
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/appearance/index', [
            'title'  => __('appearance'),
            'tab'    => $tab,
            'input'  => is_array($old) && $tab === 'settings' ? array_merge($values, $old) : $values,
            'items'  => $tab !== 'settings' ? $this->db->fetchAll('SELECT * FROM menu_items WHERE location = :l ORDER BY sort_order, id', ['l' => $tab]) : [],
            'counts' => array_column($this->db->fetchAll('SELECT location, COUNT(*) AS n FROM menu_items GROUP BY location'), 'n', 'location'),
            'errors' => $_SESSION['_errors'] ?? [],
            'old'    => is_array($old) ? $old : [],
            'defaults' => self::DEFAULT_COLORS,
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function saveHeader(): void
    {
        $in = $this->postData();
        if (!empty($in['reset_colors'])) {
            foreach (self::DEFAULT_COLORS as $k => $v) {
                $in[$k] = $v;
            }
        }
        $errors = [];
        $values = [];
        foreach (self::SETTINGS as $k => [$type, $max]) {
            $v = $in[$k] ?? '';
            switch ($type) {
                case 'bool':
                    $v = empty($v) ? '0' : '1';
                    break;
                case 'int':
                    $v = (string) max(0, min(20, (int) $v));
                    break;
                case 'color':
                    $v = strtolower(trim((string) $v));
                    if (!preg_match('/^#[0-9a-f]{6}$/', $v)) {
                        $errors[$k] = __('appr_bad_color');
                    }
                    break;
                case 'link':
                    $v = mb_substr(trim((string) $v), 0, $max);
                    if ($v !== '' && !Menu::validLink($v)) {
                        $errors[$k] = __('appr_bad_link');
                    }
                    break;
                default:
                    $v = mb_substr(trim((string) $v), 0, $max);
            }
            $values[$k] = [$v, 'appearance', $type === 'link' || $type === 'color' ? 'string' : $type];
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('admin/appearance');
        }
        Setting::saveMany($values);
        Helpers::clearSettingsCache();
        flash('success', __('settings_saved'));
        redirect('admin/appearance');
    }

    /* ---------------- menu links ---------------- */

    private function linkInput(?array $item): array
    {
        $in = $this->postData();
        $location = $item ? $item['location'] : (string) ($in['location'] ?? '');
        $row = [
            'label_en' => mb_substr(trim((string) ($in['label_en'] ?? '')), 0, 100),
            'label_bn' => mb_substr(trim((string) ($in['label_bn'] ?? '')), 0, 100) ?: null,
            'url'      => mb_substr(trim((string) ($in['url'] ?? '')), 0, 255),
            'icon'     => mb_substr(trim((string) ($in['icon'] ?? '')), 0, 50) ?: null,
            'new_tab'  => empty($in['new_tab']) ? 0 : 1,
        ];
        $errors = [];
        if (!in_array($location, Menu::LOCATIONS, true)) {
            $errors['location'] = __('v_in');
        }
        if ($row['label_en'] === '' && $row['label_bn'] === null) {
            $errors['label_en'] = __('v_required');
        }
        if ($row['label_en'] === '') {
            $row['label_en'] = (string) $row['label_bn'];
        }
        if ($row['url'] === '' || !Menu::validLink($row['url'])) {
            $errors['url'] = __('appr_bad_link');
        }
        if ($row['icon'] !== null && !preg_match('/^bi-[a-z0-9-]{1,40}$/', $row['icon'])) {
            $errors['icon'] = __('vt_icon_help');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors + ['_for' => $item ? (int) $item['id'] : 'new'];
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('admin/appearance' . (in_array($location, Menu::LOCATIONS, true) ? '?tab=' . $location : ''));
        }
        return [$location, $row];
    }

    public function storeLink(): void
    {
        [$location, $row] = $this->linkInput(null);
        $row['location'] = $location;
        $row['sort_order'] = (int) $this->db->fetchColumn('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM menu_items WHERE location = :l', ['l' => $location]);
        $id = $this->db->insert('menu_items', $row);
        Audit::log('menus.create', 'menu_items', $id, null, $row);
        flash('success', __('appr_link_added'));
        redirect('admin/appearance?tab=' . $location);
    }

    private function item(string $id): array
    {
        $m = $this->db->fetch('SELECT * FROM menu_items WHERE id = :id', ['id' => (int) $id]);
        if (!$m) {
            $this->notFound();
        }
        return $m;
    }

    public function updateLink(string $id): void
    {
        $m = $this->item($id);
        [, $row] = $this->linkInput($m);
        $row['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('menu_items', $row, 'id = :id', ['id' => (int) $m['id']]);
        Audit::log('menus.update', 'menu_items', (int) $m['id'], $m, $row);
        flash('success', __('saved'));
        redirect('admin/appearance?tab=' . $m['location']);
    }

    public function toggleLink(string $id): void
    {
        $m = $this->item($id);
        $this->db->update('menu_items', ['is_active' => (int) $m['is_active'] ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $m['id']]);
        Audit::log('menus.toggle', 'menu_items', (int) $m['id'], ['is_active' => $m['is_active']], ['is_active' => (int) $m['is_active'] ? 0 : 1]);
        redirect('admin/appearance?tab=' . $m['location']);
    }

    /** Move one place up / down: renumbers the location's links 10, 20, 30 ... */
    public function moveLink(string $id): void
    {
        $m = $this->item($id);
        $ids = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM menu_items WHERE location = :l ORDER BY sort_order, id', ['l' => $m['location']]), 'id'));
        $pos = array_search((int) $m['id'], $ids, true);
        $to = Helpers::str('dir') === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && $to >= 0 && $to < count($ids)) {
            [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
            $this->db->transaction(function (Database $db) use ($ids): void {
                foreach ($ids as $i => $lid) {
                    $db->update('menu_items', ['sort_order' => ($i + 1) * 10], 'id = :id', ['id' => $lid]);
                }
            });
        }
        redirect('admin/appearance?tab=' . $m['location']);
    }

    public function deleteLink(string $id): void
    {
        $m = $this->item($id);
        $this->db->delete('menu_items', 'id = :id', ['id' => (int) $m['id']]);
        Audit::log('menus.delete', 'menu_items', (int) $m['id'], $m, null);
        flash('success', __('appr_link_deleted'));
        redirect('admin/appearance?tab=' . $m['location']);
    }
}
