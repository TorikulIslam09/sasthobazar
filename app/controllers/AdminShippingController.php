<?php
declare(strict_types=1);

/** Shipping settings: zones, rates (standard / express), district -> zone mapping, warehouse & store pickup. */
class AdminShippingController extends Controller
{
    public function index(): void
    {
        $zones = $this->db->fetchAll('SELECT * FROM shipping_zones ORDER BY sort_order, id');
        $rates = $this->db->fetchAll('SELECT * FROM shipping_rates ORDER BY zone_id, service, rate');
        $byZone = [];
        foreach ($rates as $r) {
            $byZone[(int) $r['zone_id']][] = $r;
        }
        $districts = $this->db->fetchAll(
            "SELECT d.id, d.name_en, d.name_bn, d.shipping_zone_id, dv.name_en AS division_en, dv.name_bn AS division_bn
               FROM locations d JOIN locations dv ON dv.id = d.parent_id
              WHERE d.type = 'district' ORDER BY dv.name_en, d.name_en"
        );
        $this->view('admin/shipping/index', [
            'title'     => __('shipping_settings'),
            'zones'     => $zones,
            'rates'     => $byZone,
            'districts' => $districts,
            'edit'      => $this->db->fetch('SELECT * FROM shipping_rates WHERE id = :id', ['id' => (int) Helpers::str('rate')]),
            'errors'    => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
    }

    private static function money(mixed $v, bool $nullable = false): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';
        if ($v === '' && $nullable) {
            return null;
        }
        if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $v)) {
            throw new OrderException(__('v_decimal'));
        }
        return $v;
    }

    public function saveZones(): void
    {
        try {
            $this->db->transaction(function (Database $db) {
                foreach ((array) ($_POST['zone'] ?? []) as $id => $z) {
                    if (!is_array($z)) {
                        continue;
                    }
                    $name = trim((string) ($z['name'] ?? ''));
                    if ($name === '' || mb_strlen($name) > 100 || !in_array($z['zone_type'] ?? '', Shipping::ZONE_TYPES, true)) {
                        throw new OrderException(__('form_has_errors'));
                    }
                    $db->update('shipping_zones', [
                        'name'       => $name,
                        'name_bn'    => mb_substr(trim((string) ($z['name_bn'] ?? '')), 0, 100) ?: null,
                        'zone_type'  => $z['zone_type'],
                        'sort_order' => (int) ($z['sort_order'] ?? 0),
                        'is_active'  => !empty($z['is_active']) ? 1 : 0,
                    ], 'id = :id', ['id' => (int) $id]);
                }
                $new = trim(Helpers::str('new_zone'));
                if ($new !== '') {
                    $db->insert('shipping_zones', ['name' => mb_substr($new, 0, 100), 'zone_type' => in_array(Helpers::str('new_zone_type'), Shipping::ZONE_TYPES, true) ? Helpers::str('new_zone_type') : 'outside', 'sort_order' => 9]);
                }
            });
            Audit::log('shipping.zones', 'shipping_zones', null);
            flash('success', __('settings_saved'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/shipping#zones');
    }

    public function saveRate(): void
    {
        $id = (int) Helpers::str('id');
        try {
            $zone = (int) Helpers::str('zone_id');
            if (!$this->db->fetchColumn('SELECT 1 FROM shipping_zones WHERE id = :id', ['id' => $zone])) {
                throw new OrderException(__('v_in'));
            }
            $service = in_array(Helpers::str('service'), ['standard', 'express'], true) ? Helpers::str('service') : 'standard';
            $name = trim(Helpers::str('name'));
            if ($name === '' || mb_strlen($name) > 100) {
                throw new OrderException(__('v_required'));
            }
            $minW = (int) Helpers::str('min_weight_grams');
            $maxW = Helpers::str('max_weight_grams') === '' ? null : (int) Helpers::str('max_weight_grams');
            if ($minW < 0 || ($maxW !== null && $maxW < $minW)) {
                throw new OrderException(__('v_weight_range'));
            }
            $cod = self::money(Helpers::str('cod_charge_percent') ?: '0');
            if ((float) $cod > 20) {
                throw new OrderException(__('v_lte', ['n' => 20]));
            }
            $data = [
                'zone_id'                 => $zone,
                'service'                 => $service,
                'name'                    => mb_substr($name, 0, 100),
                'name_bn'                 => mb_substr(trim(Helpers::str('name_bn')), 0, 100) ?: null,
                'min_weight_grams'        => $minW,
                'max_weight_grams'        => $maxW,
                'min_order_amount'        => self::money(Helpers::str('min_order_amount') ?: '0'),
                'rate'                    => self::money(Helpers::str('rate')),
                'extra_per_kg'            => self::money(Helpers::str('extra_per_kg') ?: '0'),
                'free_shipping_threshold' => self::money(Helpers::str('free_shipping_threshold'), true),
                'cod_charge_percent'      => $cod,
                'est_days_min'            => Helpers::str('est_days_min') === '' ? null : max(0, min(30, (int) Helpers::str('est_days_min'))),
                'est_days_max'            => Helpers::str('est_days_max') === '' ? null : max(0, min(30, (int) Helpers::str('est_days_max'))),
                'is_active'               => Helpers::str('is_active') === '1' ? 1 : 0,
            ];
            if ($id) {
                $this->db->update('shipping_rates', $data, 'id = :id', ['id' => $id]);
            } else {
                $id = $this->db->insert('shipping_rates', $data);
            }
            Audit::log('shipping.rate', 'shipping_rates', $id, null, $data);
            flash('success', __('settings_saved'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
            redirect('admin/shipping?rate=' . $id . '#rates');
        }
        redirect('admin/shipping#rates');
    }

    public function deleteRate(string $id): void
    {
        $used = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM orders WHERE shipping_rate_id = :id', ['id' => (int) $id])
            + (int) $this->db->fetchColumn('SELECT COUNT(*) FROM shipments WHERE rate_id = :id', ['id' => (int) $id]);
        if ($used) {
            $this->db->update('shipping_rates', ['is_active' => 0], 'id = :id', ['id' => (int) $id]);
            flash('warning', __('rate_deactivated'));
        } else {
            $this->db->delete('shipping_rates', 'id = :id', ['id' => (int) $id]);
            flash('success', __('rate_deleted'));
        }
        Audit::log('shipping.rate_delete', 'shipping_rates', (int) $id);
        redirect('admin/shipping#rates');
    }

    public function saveDistricts(): void
    {
        $zones = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM shipping_zones'), 'id'));
        $n = 0;
        foreach ((array) ($_POST['district'] ?? []) as $id => $zone) {
            $zone = (int) $zone;
            if ($zone !== 0 && !in_array($zone, $zones, true)) {
                continue;
            }
            $n += $this->db->update('locations', ['shipping_zone_id' => $zone ?: null], "id = :id AND type = 'district'", ['id' => (int) $id]);
        }
        Audit::log('shipping.districts', 'locations', null, null, ['changed' => $n]);
        flash('success', __('districts_saved', ['n' => num($n)]));
        redirect('admin/shipping#districts');
    }

    public function saveGeneral(): void
    {
        $district = (int) Helpers::str('warehouse_district_id');
        if (!$this->db->fetchColumn("SELECT 1 FROM locations WHERE id = :id AND type = 'district'", ['id' => $district])) {
            flash('error', __('v_in'));
            redirect('admin/shipping#general');
        }
        foreach ([
            'warehouse_district_id' => [(string) $district, 'int'],
            'store_pickup_enabled'  => [Helpers::str('store_pickup_enabled') === '1' ? '1' : '0', 'bool'],
            'store_pickup_address'  => [mb_substr(trim(Helpers::str('store_pickup_address')), 0, 255), 'string'],
        ] as $k => [$v, $type]) {
            if ($this->db->fetchColumn('SELECT 1 FROM settings WHERE `key` = :k', ['k' => $k])) {
                $this->db->update('settings', ['value' => $v], '`key` = :k', ['k' => $k]);
            } else {
                $this->db->insert('settings', ['group' => 'shipping', 'key' => $k, 'value' => $v, 'type' => $type, 'label' => $k]);
            }
        }
        Helpers::clearSettingsCache();
        Audit::log('shipping.general', 'settings', null, null, ['warehouse_district_id' => $district]);
        flash('success', __('settings_saved'));
        redirect('admin/shipping#general');
    }
}
