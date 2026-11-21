<?php
declare(strict_types=1);

/**
 * Admin: vendor types (Pharmacy, Medical equipment, Beauty & personal care, Grocery ...).
 * A type sets the sign-up documents, drug licence, prescription-medicine permission, allowed categories and default commission.
 * Types in use are switched off instead of deleted.
 */
class AdminVendorTypeController extends Controller
{
    public function index(): void
    {
        $types = VendorType::all();
        $counts = array_column($this->db->fetchAll('SELECT vendor_type_id, COUNT(*) AS n FROM vendors WHERE deleted_at IS NULL GROUP BY vendor_type_id'), 'n', 'vendor_type_id');
        $this->view('admin/vendor_types/index', [
            'title'  => __('vendor_types'),
            'types'  => $types,
            'counts' => $counts,
        ], 'admin');
    }

    public function create(): void
    {
        $this->form(null);
    }

    public function edit(string $id): void
    {
        $t = VendorType::find((int) $id);
        if (!$t) {
            $this->notFound();
        }
        $this->form($t);
    }

    private function form(?array $t): void
    {
        $old = $_SESSION['_old'] ?? null;
        $input = $t ?? ['slug' => '', 'name_en' => '', 'name_bn' => '', 'description_en' => '', 'description_bn' => '', 'icon' => 'bi-shop',
                        'required_docs' => '["nid"]', 'optional_docs' => '["trade_license"]', 'needs_drug_license' => 0, 'can_sell_rx' => 0,
                        'commission_rate' => null, 'allowed_categories' => null, 'sort_order' => 100, 'is_active' => 1];
        if (is_array($old)) {
            $input = array_merge($input, $old);
            $input['required_docs'] = json_encode(array_values((array) ($old['required_docs'] ?? [])));
            $input['optional_docs'] = json_encode(array_values((array) ($old['optional_docs'] ?? [])));
            $input['allowed_categories'] = json_encode(array_map('intval', (array) ($old['allowed_categories'] ?? [])));
        }
        $this->view('admin/vendor_types/form', [
            'title'   => $t ? __('edit') . ': ' . VendorType::label($t) : __('vt_add'),
            't'       => $t,
            'input'   => $input,
            'roots'   => array_filter(Category::allById(), static fn($c) => $c['parent_id'] === null),
            'errors'  => $_SESSION['_errors'] ?? [],
            'inUse'   => $t ? VendorType::vendorCount((int) $t['id']) : 0,
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    /** Validated row from POST (or redirects back with errors). */
    private function input(?array $t): array
    {
        $in = $_POST;
        $str = static fn(string $k, int $max) => mb_substr(trim(is_scalar($in[$k] ?? null) ? (string) $in[$k] : ''), 0, $max);
        $list = static fn(string $k) => array_values(array_intersect(array_map('strval', is_array($in[$k] ?? null) ? $in[$k] : []), VendorType::DOCS));
        $row = [
            'name_en'        => $str('name_en', 100),
            'name_bn'        => $str('name_bn', 100) ?: null,
            'description_en' => $str('description_en', 255) ?: null,
            'description_bn' => $str('description_bn', 255) ?: null,
            'icon'           => $str('icon', 50) ?: 'bi-shop',
            'needs_drug_license' => empty($in['needs_drug_license']) ? 0 : 1,
            'can_sell_rx'    => empty($in['can_sell_rx']) ? 0 : 1,
            'sort_order'     => (int) $str('sort_order', 6),
            'is_active'      => empty($in['is_active']) ? 0 : 1,
        ];
        $req = $list('required_docs');
        $opt = array_values(array_diff($list('optional_docs'), $req));
        if ($row['needs_drug_license'] && !in_array('drug_license', $req, true)) {
            $req[] = 'drug_license';
        }
        $row['required_docs'] = json_encode($req);
        $row['optional_docs'] = json_encode($opt);
        $roots = array_keys(array_filter(Category::allById(), static fn($c) => $c['parent_id'] === null));
        $cats = array_values(array_intersect(array_map('intval', is_array($in['allowed_categories'] ?? null) ? $in['allowed_categories'] : []), $roots));
        $row['allowed_categories'] = $cats ? json_encode($cats) : null;
        $rate = $str('commission_rate', 6);

        $errors = [];
        if ($row['name_en'] === '') {
            $errors['name_en'] = __('v_required');
        }
        if (!preg_match('/^bi-[a-z0-9-]{1,40}$/', $row['icon'])) {
            $errors['icon'] = __('vt_icon_help');
        }
        if ($rate !== '' && (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $rate) || (float) $rate > 100)) {
            $errors['commission_rate'] = __('v_rate');
        }
        $row['commission_rate'] = $rate === '' ? null : $rate;
        if ($row['can_sell_rx'] && !$row['needs_drug_license']) {
            $errors['can_sell_rx'] = __('vt_rx_needs_licence');
        }
        if (!$req) {
            $errors['required_docs'] = __('vt_need_one_doc');
        }
        if ($t === null) {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($str('slug', 40) ?: Helpers::slugify($row['name_en']))), '_');
            if ($slug === '') {
                $errors['slug'] = __('v_required');
            } elseif ($this->db->fetchColumn('SELECT 1 FROM vendor_types WHERE slug = :s', ['s' => $slug])) {
                $errors['slug'] = __('v_unique');
            }
            $row['slug'] = $slug;
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld(array_merge($in, ['required_docs' => $req, 'optional_docs' => $opt, 'allowed_categories' => $cats]));
            flash('error', __('form_has_errors'));
            redirect($t ? 'admin/vendor-types/' . (int) $t['id'] . '/edit' : 'admin/vendor-types/create');
        }
        return $row;
    }

    public function store(): void
    {
        $row = $this->input(null);
        $id = VendorType::create($row);
        Audit::log('vendor_types.create', 'vendor_types', $id, null, $row);
        flash('success', __('saved'));
        redirect('admin/vendor-types');
    }

    public function update(string $id): void
    {
        $t = VendorType::find((int) $id);
        if (!$t) {
            $this->notFound();
        }
        $row = $this->input($t);
        $row['updated_at'] = date('Y-m-d H:i:s');
        VendorType::updateById((int) $t['id'], $row);
        Audit::log('vendor_types.update', 'vendor_types', (int) $t['id'], $t, $row);
        flash('success', __('saved'));
        redirect('admin/vendor-types');
    }

    public function toggle(string $id): void
    {
        $t = VendorType::find((int) $id);
        if (!$t) {
            $this->notFound();
        }
        $on = (int) $t['is_active'] ? 0 : 1;
        if (!$on && count(VendorType::active()) <= 1) {
            flash('error', __('vt_keep_one'));
            redirect('admin/vendor-types');
        }
        VendorType::updateById((int) $t['id'], ['is_active' => $on, 'updated_at' => date('Y-m-d H:i:s')]);
        Audit::log('vendor_types.toggle', 'vendor_types', (int) $t['id'], ['is_active' => $t['is_active']], ['is_active' => $on]);
        flash('success', __('saved'));
        redirect('admin/vendor-types');
    }
}
