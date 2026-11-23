<?php
declare(strict_types=1);

/**
 * Admin: delivery locations - Division > District > Upazila / Thana > Area.
 * Browse level by level, search, add, rename (English / Bangla), postcode, switch on/off (an "off" place is not offered at checkout),
 * delete (only when nothing uses it). Shipping zones per district stay on the Shipping page.
 * Permissions: locations.view / create / edit / delete.
 */
class AdminLocationController extends Controller
{
    private const CHILD = ['division' => 'district', 'district' => 'upazila', 'upazila' => 'area'];
    private const TYPES = ['division', 'district', 'upazila', 'area'];

    /** Breadcrumb chain from the top to this place. */
    private function chain(?int $id): array
    {
        $out = [];
        $guard = 0;
        while ($id && $guard++ < 5) {
            $l = $this->db->fetch('SELECT * FROM locations WHERE id = :id', ['id' => $id]);
            if (!$l) {
                break;
            }
            array_unshift($out, $l);
            $id = $l['parent_id'] !== null ? (int) $l['parent_id'] : null;
        }
        return $out;
    }

    public function index(): void
    {
        $parentId = (int) Helpers::str('parent');
        $parent = $parentId ? Location::find($parentId) : null;
        if ($parentId && (!$parent || !isset(self::CHILD[$parent['type']]))) {
            $this->notFound();
        }
        $q = mb_substr(Helpers::str('q'), 0, 60);
        $status = in_array(Helpers::str('status'), ['on', 'off'], true) ? Helpers::str('status') : '';
        $where = $parent ? 'l.parent_id = :p' : "l.type = 'division'";
        $params = $parent ? ['p' => (int) $parent['id']] : [];
        if ($q !== '') {
            // search everywhere (any level) by name or postcode
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where = '(l.name_en LIKE :q1 OR l.name_bn LIKE :q2 OR l.postcode LIKE :q3)';
            $params = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if ($status !== '') {
            $where .= ' AND l.is_active = ' . ($status === 'on' ? 1 : 0);
        }
        $rows = $this->db->fetchAll(
            "SELECT l.*, pl.name_en AS parent_en, pl.name_bn AS parent_bn, z.name AS zone_name,
                    (SELECT COUNT(*) FROM locations c WHERE c.parent_id = l.id) AS children,
                    (SELECT COUNT(*) FROM locations c WHERE c.parent_id = l.id AND c.is_active = 1) AS children_on
               FROM locations l LEFT JOIN locations pl ON pl.id = l.parent_id LEFT JOIN shipping_zones z ON z.id = l.shipping_zone_id
              WHERE {$where} ORDER BY l.name_en LIMIT 600",
            $params
        );
        $this->view('admin/locations/index', [
            'title'     => __('locations'),
            'parent'    => $parent,
            'chain'     => $parent ? $this->chain((int) $parent['id']) : [],
            'childType' => $parent ? self::CHILD[$parent['type']] : 'division',
            'rows'      => $rows,
            'q'         => $q,
            'status'    => $status,
            'counts'    => Location::countByType(),
            'errors'    => $_SESSION['_errors'] ?? [],
            'old'       => $_SESSION['_old'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    private function back(?int $parentId): string
    {
        return 'admin/locations' . ($parentId ? '?parent=' . $parentId : '');
    }

    /** Validated names / postcode, or redirect back with errors. */
    private function input(?int $parentId, ?int $selfId): array
    {
        $in = $this->postData();
        $row = [
            'name_en'  => mb_substr(trim((string) ($in['name_en'] ?? '')), 0, 100),
            'name_bn'  => mb_substr(trim((string) ($in['name_bn'] ?? '')), 0, 100) ?: null,
            'postcode' => mb_substr(trim(bn_to_en_digits((string) ($in['postcode'] ?? ''))), 0, 10) ?: null,
        ];
        $errors = [];
        if ($row['name_en'] === '') {
            $errors['name_en'] = __('v_required');
        }
        if ($row['postcode'] !== null && !preg_match('/^\d{4}$/', $row['postcode'])) {
            $errors['postcode'] = __('v_postcode');
        }
        if (!$errors) {
            $dup = $this->db->fetchColumn(
                'SELECT 1 FROM locations WHERE name_en = :n AND ' . ($parentId ? 'parent_id = :p' : 'parent_id IS NULL') . ' AND id <> :self',
                ['n' => $row['name_en'], 'self' => $selfId ?? 0] + ($parentId ? ['p' => $parentId] : [])
            );
            if ($dup) {
                $errors['name_en'] = __('loc_duplicate');
            }
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors + ['_for' => $selfId ?? 'new'];
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($this->back($parentId));
        }
        return $row;
    }

    public function store(): void
    {
        $parentId = (int) Helpers::str('parent_id') ?: null;
        $parent = $parentId ? Location::find($parentId) : null;
        if ($parentId && (!$parent || !isset(self::CHILD[$parent['type']]))) {
            $this->notFound();
        }
        $row = $this->input($parentId, null);
        $row += ['parent_id' => $parentId, 'type' => $parent ? self::CHILD[$parent['type']] : 'division', 'is_active' => 1];
        $id = $this->db->insert('locations', $row);
        Audit::log('locations.create', 'locations', $id, null, $row);
        flash('success', __('loc_added', ['name' => $row['name_en']]));
        redirect($this->back($parentId));
    }

    public function update(string $id): void
    {
        $l = Location::find((int) $id);
        if (!$l) {
            $this->notFound();
        }
        $parentId = $l['parent_id'] !== null ? (int) $l['parent_id'] : null;
        $row = $this->input($parentId, (int) $l['id']);
        $this->db->update('locations', $row, 'id = :id', ['id' => (int) $l['id']]);
        Audit::log('locations.update', 'locations', (int) $l['id'], array_intersect_key($l, $row), $row);
        flash('success', __('saved'));
        redirect($this->back($parentId));
    }

    /** On / off. Switching a place off also hides everything under it at checkout (the pickers only list active places). */
    public function toggle(string $id): void
    {
        $l = Location::find((int) $id);
        if (!$l) {
            $this->notFound();
        }
        $on = (int) $l['is_active'] ? 0 : 1;
        $this->db->update('locations', ['is_active' => $on], 'id = :id', ['id' => (int) $l['id']]);
        Audit::log('locations.toggle', 'locations', (int) $l['id'], ['is_active' => $l['is_active']], ['is_active' => $on]);
        flash('success', __($on ? 'loc_on' : 'loc_off', ['name' => $l['name_en']]));
        $back = Helpers::str('back');
        redirect(str_starts_with($back, 'admin/locations') ? $back : $this->back($l['parent_id'] !== null ? (int) $l['parent_id'] : null));
    }

    /** How many records use this place (delete is allowed only at 0). */
    private function usage(int $id): int
    {
        $n = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM locations WHERE parent_id = :id', ['id' => $id]);
        foreach ([['addresses', ['division_id', 'district_id', 'upazila_id']], ['orders', ['division_id', 'district_id', 'upazila_id']],
                  ['vendors', ['location_id']], ['prescriptions', ['district_id', 'upazila_id']]] as [$table, $cols]) {
            $params = [];
            $conds = [];
            foreach ($cols as $i => $c) {
                $conds[] = "`{$c}` = :id{$i}"; // each placeholder once (native prepared statements)
                $params['id' . $i] = $id;
            }
            $n += (int) $this->db->fetchColumn("SELECT COUNT(*) FROM `{$table}` WHERE " . implode(' OR ', $conds), $params);
        }
        return $n;
    }

    public function destroy(string $id): void
    {
        $l = Location::find((int) $id);
        if (!$l) {
            $this->notFound();
        }
        $parentId = $l['parent_id'] !== null ? (int) $l['parent_id'] : null;
        if ($n = $this->usage((int) $l['id'])) {
            flash('error', __('loc_in_use', ['name' => $l['name_en'], 'n' => num($n)]));
            redirect($this->back($parentId));
        }
        $this->db->delete('locations', 'id = :id', ['id' => (int) $l['id']]);
        Audit::log('locations.delete', 'locations', (int) $l['id'], $l, null);
        flash('success', __('loc_deleted', ['name' => $l['name_en']]));
        redirect($this->back($parentId));
    }
}
