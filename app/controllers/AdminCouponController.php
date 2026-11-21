<?php
declare(strict_types=1);

/** Coupons CRUD. A coupon that was already used is deactivated instead of deleted (order history keeps it). */
class AdminCouponController extends Controller
{
    public function index(): void
    {
        $q = mb_substr(Helpers::str('q'), 0, 50);
        $where = $q !== '' ? 'WHERE code LIKE :q OR description LIKE :q2' : '';
        $params = $q !== '' ? ['q' => '%' . addcslashes($q, '%_\\') . '%', 'q2' => '%' . addcslashes($q, '%_\\') . '%'] : [];
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM coupons {$where}", $params), 25);
        $this->view('admin/coupons/index', [
            'title'   => __('coupons'),
            'q'       => $q,
            'coupons' => $this->db->fetchAll("SELECT * FROM coupons {$where} ORDER BY id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}", $params),
            'pager'   => $pager,
        ], 'admin');
    }

    public function create(): void
    {
        $this->form(null, ['type' => 'percent', 'applies_to' => 'all', 'usage_limit_per_user' => '1', 'is_active' => '1', 'min_order_amount' => '0', 'funded_by' => 'platform', 'vendor_share_percent' => '50']);
    }

    public function edit(string $id): void
    {
        $c = Coupon::find((int) $id);
        if (!$c) {
            $this->notFound();
        }
        $c['applies_ids'] = implode(',', Coupon::appliesIds($c));
        $c['vendor_share_percent'] = (string) ((float) $c['vendor_share_percent'] + 0);
        foreach (['starts_at', 'ends_at'] as $k) {
            $c[$k] = $c[$k] ? date('Y-m-d\TH:i', strtotime($c[$k])) : '';
        }
        $this->form($c, $c);
    }

    private function form(?array $coupon, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/coupons/form', [
            'title'      => __($coupon ? 'edit_coupon' : 'add_coupon'),
            'coupon'     => $coupon,
            'input'      => is_array($old) ? $old : $defaults,
            'errors'     => $_SESSION['_errors'] ?? [],
            'categories' => Category::tree(),
            'brands'     => Brand::options(),
            'vendors'    => Vendor::options(),
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        $data = $this->validated(null, 'admin/coupons/create');
        $data['created_by'] = Auth::id();
        $id = Coupon::create($data);
        flash('success', __('coupon_saved'));
        redirect('admin/coupons/' . $id . '/edit');
    }

    public function update(string $id): void
    {
        if (!Coupon::find((int) $id)) {
            $this->notFound();
        }
        Coupon::updateById((int) $id, $this->validated((int) $id, 'admin/coupons/' . (int) $id . '/edit'));
        flash('success', __('coupon_saved'));
        redirect('admin/coupons');
    }

    public function destroy(string $id): void
    {
        $c = Coupon::find((int) $id);
        if (!$c) {
            $this->notFound();
        }
        if ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = :c', ['c' => (int) $id]) > 0) {
            Coupon::updateById((int) $id, ['is_active' => 0]);
            flash('warning', __('coupon_deactivated'));
        } else {
            Coupon::deleteById((int) $id);
            flash('success', __('coupon_deleted'));
        }
        redirect('admin/coupons');
    }

    private function validated(?int $id, string $formPath): array
    {
        $in = $this->postData(['applies_list']);
        $in['code'] = strtoupper(trim((string) ($in['code'] ?? '')));
        $errors = $this->validate($in, [
            'code'                 => 'required|max:50',
            'description'          => 'max:255',
            'type'                 => 'required|in:' . implode(',', Coupon::TYPES),
            'value'                => 'decimal',
            'max_discount'         => 'decimal',
            'min_order_amount'     => 'decimal',
            'usage_limit'          => 'integer|gte:1',
            'usage_limit_per_user' => 'integer|gte:1',
            'applies_to'           => 'required|in:' . implode(',', Coupon::APPLIES),
            'vendor_id'            => 'integer',
            'funded_by'            => 'in:' . implode(',', Promo::FUNDERS),
        ]);
        if (($in['funded_by'] ?? '') === 'shared' && (!is_numeric($in['vendor_share_percent'] ?? '') || (float) $in['vendor_share_percent'] < 1 || (float) $in['vendor_share_percent'] > 99)) {
            $errors['vendor_share_percent'] = __('v_between', ['min' => 1, 'max' => 99]);
        }
        if (!isset($errors['code']) && !preg_match('/^[A-Z0-9_-]{3,50}$/', $in['code'])) {
            $errors['code'] = __('v_coupon_code');
        }
        if (!isset($errors['code']) && $this->db->fetchColumn('SELECT 1 FROM coupons WHERE code = :c AND id <> :id', ['c' => $in['code'], 'id' => (int) $id])) {
            $errors['code'] = __('duplicate_value');
        }
        if (!isset($errors['type']) && $in['type'] !== 'free_shipping') {
            if (($in['value'] ?? '') === '' || (float) $in['value'] <= 0) {
                $errors['value'] = __('v_gte', ['n' => '0.01']);
            } elseif ($in['type'] === 'percent' && (float) $in['value'] > 100) {
                $errors['value'] = __('v_lte', ['n' => 100]);
            }
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

        // what the coupon applies to
        $ids = [];
        $applies = $in['applies_to'] ?? 'all';
        if ($applies === 'product') {
            foreach (preg_split('/[\s,]+/', (string) ($in['applies_products'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $ref) {
                $pid = $this->db->fetchColumn('SELECT id FROM products WHERE deleted_at IS NULL AND (id = :id OR sku = :sku) LIMIT 1', ['id' => ctype_digit($ref) ? (int) $ref : 0, 'sku' => $ref]);
                if (!$pid) {
                    $errors['applies_products'] = __('coupon_unknown_product', ['ref' => $ref]);
                    break;
                }
                $ids[] = (int) $pid;
            }
        } elseif (in_array($applies, ['category', 'brand', 'vendor'], true)) {
            $ids = array_map('intval', array_filter((array) ($in['applies_list'] ?? []), 'ctype_digit'));
        }
        if ($applies !== 'all' && !$ids && !isset($errors['applies_products'])) {
            $errors[$applies === 'product' ? 'applies_products' : 'applies_list'] = __('v_required');
        }
        if (($in['vendor_id'] ?? '') !== '' && !isset($errors['vendor_id']) && !Vendor::existsActive((int) $in['vendor_id'])) {
            $errors['vendor_id'] = __('v_in');
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        $nul = static fn(string $k) => ($in[$k] ?? '') !== '' ? $in[$k] : null;
        return [
            'code'                 => $in['code'],
            'description'          => $nul('description'),
            'type'                 => $in['type'],
            'value'                => $in['type'] === 'free_shipping' ? 0 : $in['value'],
            'max_discount'         => $in['type'] === 'percent' ? $nul('max_discount') : null,
            'min_order_amount'     => $nul('min_order_amount') ?? 0,
            'usage_limit'          => $nul('usage_limit'),
            'usage_limit_per_user' => $nul('usage_limit_per_user'),
            'vendor_id'            => $nul('vendor_id'),
            'funded_by'            => $funded = in_array($in['funded_by'] ?? '', Promo::FUNDERS, true) ? $in['funded_by'] : ($nul('vendor_id') ? 'vendor' : 'platform'),
            'vendor_share_percent' => $funded === 'shared' ? (float) $in['vendor_share_percent'] : ($funded === 'vendor' ? 100 : 0),
            'applies_to'           => $applies,
            'applies_ids'          => $ids ? json_encode(array_values(array_unique($ids))) : null,
            'first_order_only'     => !empty($in['first_order_only']) ? 1 : 0,
            'starts_at'            => $dates['starts_at'] ?? null,
            'ends_at'              => $dates['ends_at'] ?? null,
            'is_active'            => !empty($in['is_active']) ? 1 : 0,
        ];
    }
}
