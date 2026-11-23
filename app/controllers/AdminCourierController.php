<?php
declare(strict_types=1);

/** Courier companies: enable, API keys, mock mode, webhook URL. */
class AdminCourierController extends Controller
{
    /** Extra config fields per courier (stored in api_config). */
    public const FIELDS = [
        'pathao'    => ['client_id', 'username', 'password', 'store_id', 'webhook_secret', 'default_city_id', 'default_zone_id'],
        'steadfast' => [],
        'redx'      => ['delivery_area_id', 'pickup_store_id'],
        'paperfly'  => ['username', 'password', 'pickup_thana', 'pickup_district'],
        'ecourier'  => ['user_id', 'package_code'],
        'self'      => [],
    ];
    private const SECRET_FIELDS = ['password'];

    public function index(): void
    {
        $couriers = Courier::all();
        $stats = array_column($this->db->fetchAll(
            "SELECT courier_id, COUNT(*) AS n, SUM(status NOT IN ('delivered','partial_delivered','returned','cancelled','lost')) AS open
               FROM shipments WHERE courier_id IS NOT NULL GROUP BY courier_id"
        ), null, 'courier_id');
        $this->view('admin/couriers/index', [
            'title'    => __('couriers'),
            'couriers' => $couriers,
            'stats'    => $stats,
            'mockMode' => (bool) setting('courier_mock_mode', true),
        ], 'admin');
    }

    public function update(string $id): void
    {
        $c = Courier::find((int) $id);
        if (!$c) {
            $this->notFound();
        }
        $cfg = Courier::config($c);
        foreach (self::FIELDS[$c['code']] ?? [] as $f) {
            $v = trim(Helpers::str('cfg_' . $f));
            if (in_array($f, self::SECRET_FIELDS, true) && $v === '') {
                continue; // keep the stored secret when the box is left empty
            }
            $cfg[$f] = mb_substr($v, 0, 190);
        }
        $cfg['mock'] = Helpers::str('mock') === '1';
        if (Helpers::str('regen_token') === '1' || empty($cfg['webhook_token'])) {
            $cfg['webhook_token'] = bin2hex(random_bytes(20));
        }
        if ($c['code'] === 'pathao' && trim((string) ($cfg['webhook_secret'] ?? '')) === '') {
            $cfg['webhook_secret'] = bin2hex(random_bytes(16)); // paste this into Pathao merchant panel → Developers API → Webhook
        }
        $data = [
            'name'                  => mb_substr(trim(Helpers::str('name')) ?: $c['name'], 0, 100),
            'phone'                 => mb_substr(trim(Helpers::str('phone')), 0, 20) ?: null,
            'api_base_url'          => mb_substr(trim(Helpers::str('api_base_url')), 0, 255) ?: null,
            'tracking_url_template' => mb_substr(trim(Helpers::str('tracking_url_template')), 0, 255) ?: null,
            'is_active'             => Helpers::str('is_active') === '1' ? 1 : 0,
            'api_config'            => json_encode($cfg),
        ];
        if ($data['api_base_url'] !== null && !preg_match('#^https://#i', $data['api_base_url'])) {
            flash('error', __('v_https'));
            redirect('admin/couriers#c' . (int) $id);
        }
        foreach (['api_key', 'api_secret'] as $k) {
            $v = trim(Helpers::str($k));
            if ($v !== '') {
                $data[$k] = mb_substr($v, 0, 255);
            } elseif (Helpers::str('clear_' . $k) === '1') {
                $data[$k] = null;
            }
        }
        $this->db->update('couriers', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('couriers.update', 'couriers', (int) $id, null, ['is_active' => $data['is_active'], 'mock' => $cfg['mock'], 'keys_changed' => isset($data['api_key']) || isset($data['api_secret'])]);
        flash('success', __('courier_saved', ['name' => $data['name']]));
        redirect('admin/couriers#c' . (int) $id);
    }

    /** Test the courier login (live API, even in test mode). Pathao: also lists the merchant's stores and their IDs. */
    public function test(string $id): void
    {
        $c = Courier::find((int) $id);
        if (!$c) {
            $this->notFound();
        }
        $adapter = Courier::adapter($c);
        if ($c['code'] !== 'pathao' || !method_exists($adapter, 'stores')) {
            flash('info', __('courier_test_na'));
            redirect('admin/couriers#c' . (int) $id);
        }
        $cfg = Courier::config($c);
        if (trim((string) ($cfg['client_id'] ?? '')) === '' || (string) $c['api_secret'] === '' || trim((string) ($cfg['username'] ?? '')) === '' || (string) ($cfg['password'] ?? '') === '') {
            flash('error', __('pathao_test_need_keys'));
            redirect('admin/couriers#c' . (int) $id);
        }
        try {
            $stores = $adapter->stores();
            $_SESSION['pathao_stores'] = array_slice($stores, 0, 20);
            flash('success', __('pathao_test_ok', ['n' => num(count($stores))]));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        Audit::log('couriers.test', 'couriers', (int) $id);
        redirect('admin/couriers#c' . (int) $id);
    }

    public function mockMode(): void
    {
        $v = Helpers::str('courier_mock_mode') === '1' ? '1' : '0';
        $this->db->update('settings', ['value' => $v], '`key` = :k', ['k' => 'courier_mock_mode']);
        Helpers::clearSettingsCache();
        Audit::log('couriers.mock_mode', 'settings', null, null, ['courier_mock_mode' => $v]);
        flash('success', __('settings_saved'));
        redirect('admin/couriers');
    }
}
