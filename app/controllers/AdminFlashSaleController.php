<?php
declare(strict_types=1);

/**
 * Flash sales: time-boxed sale prices with a countdown on the storefront.
 * Each sale has items (product or one variant, sale price, stock cap for the sale, max per order)
 * and a cost bearer (platform / vendor / shared) used by the commission calculation.
 */
class AdminFlashSaleController extends Controller
{
    public function index(): void
    {
        $pager = new Paginator((int) $this->db->fetchColumn('SELECT COUNT(*) FROM flash_sales'), 20);
        $this->view('admin/flash_sales/index', [
            'title' => __('flash_sales'),
            'sales' => $this->db->fetchAll(
                "SELECT fs.*, (SELECT COUNT(*) FROM flash_sale_items fi WHERE fi.flash_sale_id = fs.id) AS item_count,
                        (SELECT COALESCE(SUM(sold_qty), 0) FROM flash_sale_items fi WHERE fi.flash_sale_id = fs.id) AS sold
                   FROM flash_sales fs ORDER BY (fs.ends_at > NOW()) DESC, fs.starts_at DESC, fs.id DESC
                  LIMIT {$pager->perPage} OFFSET {$pager->offset()}"
            ),
            'pager' => $pager,
        ], 'admin');
    }

    public function create(): void
    {
        $start = strtotime('tomorrow');
        $this->form(null, ['starts_at' => date('Y-m-d\TH:i', $start), 'ends_at' => date('Y-m-d\TH:i', $start + 86400 - 60),
                           'funded_by' => 'platform', 'vendor_share_percent' => '50', 'is_active' => '1']);
    }

    public function edit(string $id): void
    {
        $s = $this->sale((int) $id);
        $in = $s;
        foreach (['starts_at', 'ends_at'] as $k) {
            $in[$k] = date('Y-m-d\TH:i', strtotime($s[$k]));
        }
        $in['vendor_share_percent'] = (string) ((float) $s['vendor_share_percent'] + 0);
        $this->form($s, $in);
    }

    private function sale(int $id): array
    {
        $s = $this->db->fetch('SELECT * FROM flash_sales WHERE id = :id', ['id' => $id]);
        if (!$s) {
            $this->notFound();
        }
        return $s;
    }

    private function form(?array $sale, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $items = $sale ? $this->db->fetchAll(
            'SELECT fi.*, p.name_en, p.name_bn, p.sku, p.price, p.mrp, p.vendor_id, p.stock_qty, p.track_stock,
                    v.name_en AS variant_en, v.sku AS variant_sku, v.price AS v_price, v.stock_qty AS v_stock, vd.shop_name
               FROM flash_sale_items fi JOIN products p ON p.id = fi.product_id
          LEFT JOIN product_variants v ON v.id = fi.variant_id LEFT JOIN vendors vd ON vd.id = p.vendor_id
              WHERE fi.flash_sale_id = :s ORDER BY fi.id',
            ['s' => (int) $sale['id']]
        ) : [];
        $this->view('admin/flash_sales/form', [
            'title'   => __($sale ? 'edit_flash_sale' : 'add_flash_sale'),
            'sale'    => $sale,
            'items'   => $items,
            'input'   => is_array($old) && empty($old['_item']) ? $old : $defaults,
            'itemOld' => is_array($old) && !empty($old['_item']) ? $old : [],
            'errors'  => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        [$data, $banner] = $this->validated('admin/flash-sales/create');
        if ($banner) {
            $data['banner'] = $banner;
        }
        $id = $this->db->insert('flash_sales', $data);
        Audit::log('flash_sales.create', 'flash_sales', $id, null, $data);
        flash('success', __('flash_sale_saved_add_items'));
        redirect('admin/flash-sales/' . $id . '/edit');
    }

    public function update(string $id): void
    {
        $s = $this->sale((int) $id);
        [$data, $banner] = $this->validated('admin/flash-sales/' . (int) $id . '/edit');
        if ($banner) {
            Upload::delete($s['banner']);
            $data['banner'] = $banner;
        } elseif (!empty($_POST['remove_banner'])) {
            Upload::delete($s['banner']);
            $data['banner'] = null;
        }
        $this->db->update('flash_sales', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('flash_sales.update', 'flash_sales', (int) $id, $s, $data);
        flash('success', __('flash_sale_saved'));
        redirect('admin/flash-sales/' . (int) $id . '/edit');
    }

    public function destroy(string $id): void
    {
        $s = $this->sale((int) $id);
        $sold = (int) $this->db->fetchColumn('SELECT COALESCE(SUM(sold_qty), 0) FROM flash_sale_items WHERE flash_sale_id = :s', ['s' => (int) $id]);
        if ($sold > 0) {
            $this->db->update('flash_sales', ['is_active' => 0], 'id = :id', ['id' => (int) $id]);
            flash('warning', __('flash_sale_deactivated'));
        } else {
            $this->db->transaction(function (Database $db) use ($id): void {
                $db->query('DELETE FROM flash_sale_items WHERE flash_sale_id = :s', ['s' => (int) $id]);
                $db->query('DELETE FROM flash_sales WHERE id = :s', ['s' => (int) $id]);
            });
            Upload::delete($s['banner']);
            flash('success', __('flash_sale_deleted'));
        }
        Audit::log('flash_sales.delete', 'flash_sales', (int) $id, $s, null);
        redirect('admin/flash-sales');
    }

    /* ---------------- items ---------------- */

    public function addItem(string $id): void
    {
        $s = $this->sale((int) $id);
        $in = $this->postData();
        $back = 'admin/flash-sales/' . (int) $id . '/edit#items';
        $errors = [];
        $ref = trim((string) ($in['product_ref'] ?? ''));
        $productId = $variantId = null;
        $regular = 0.0;
        if ($ref === '') {
            $errors['product_ref'] = __('v_required');
        } else {
            // a variant SKU picks that variant; a product id / SKU the whole product
            $v = $this->db->fetch(
                'SELECT v.id, v.product_id, v.price FROM product_variants v JOIN products p ON p.id = v.product_id
                  WHERE v.sku = :sku AND p.deleted_at IS NULL LIMIT 1',
                ['sku' => $ref]
            );
            if ($v) {
                [$productId, $variantId, $regular] = [(int) $v['product_id'], (int) $v['id'], (float) $v['price']];
            } else {
                $p = $this->db->fetch(
                    'SELECT id, price, has_variants FROM products WHERE deleted_at IS NULL AND (id = :id OR sku = :sku) LIMIT 1',
                    ['id' => ctype_digit($ref) ? (int) $ref : 0, 'sku' => $ref]
                );
                if (!$p) {
                    $errors['product_ref'] = __('coupon_unknown_product', ['ref' => mb_substr($ref, 0, 40)]);
                } else {
                    $productId = (int) $p['id'];
                    $regular = (int) $p['has_variants']
                        ? (float) $this->db->fetchColumn('SELECT MIN(price) FROM product_variants WHERE product_id = :p AND is_active = 1', ['p' => $productId])
                        : (float) $p['price'];
                }
            }
        }
        if ($productId && $this->db->fetchColumn(
            'SELECT 1 FROM flash_sale_items WHERE flash_sale_id = :s AND product_id = :p AND variant_id <=> :v',
            ['s' => (int) $id, 'p' => $productId, 'v' => $variantId]
        )) {
            $errors['product_ref'] = __('flash_item_exists');
        }
        $item = $this->itemFields($in, $regular, $errors);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in + ['_item' => 1]);
            flash('error', __('form_has_errors'));
            redirect($back);
        }
        $itemId = $this->db->insert('flash_sale_items', $item + ['flash_sale_id' => (int) $id, 'product_id' => $productId, 'variant_id' => $variantId, 'sold_qty' => 0]);
        Audit::log('flash_sales.item_add', 'flash_sale_items', $itemId, null, $item + ['product_id' => $productId, 'variant_id' => $variantId]);
        flash('success', __('flash_item_added'));
        redirect($back);
    }

    public function updateItem(string $id, string $item): void
    {
        $it = $this->item((int) $id, (int) $item);
        $in = $this->postData();
        $errors = [];
        $regular = (float) ($it['variant_id']
            ? $this->db->fetchColumn('SELECT price FROM product_variants WHERE id = :v', ['v' => (int) $it['variant_id']])
            : $this->db->fetchColumn('SELECT price FROM products WHERE id = :p', ['p' => (int) $it['product_id']]));
        $data = $this->itemFields($in, $regular, $errors);
        if ($errors) {
            flash('error', reset($errors));
            redirect('admin/flash-sales/' . (int) $id . '/edit#items');
        }
        $this->db->update('flash_sale_items', $data, 'id = :id', ['id' => (int) $item]);
        Audit::log('flash_sales.item_update', 'flash_sale_items', (int) $item, $it, $data);
        flash('success', __('flash_item_saved'));
        redirect('admin/flash-sales/' . (int) $id . '/edit#items');
    }

    public function removeItem(string $id, string $item): void
    {
        $it = $this->item((int) $id, (int) $item);
        $this->db->query('DELETE FROM flash_sale_items WHERE id = :id', ['id' => (int) $item]);
        Audit::log('flash_sales.item_remove', 'flash_sale_items', (int) $item, $it, null);
        flash('success', __('flash_item_removed'));
        redirect('admin/flash-sales/' . (int) $id . '/edit#items');
    }

    private function item(int $saleId, int $itemId): array
    {
        $it = $this->db->fetch('SELECT * FROM flash_sale_items WHERE id = :i AND flash_sale_id = :s', ['i' => $itemId, 's' => $saleId]);
        if (!$it) {
            $this->notFound();
        }
        return $it;
    }

    private function itemFields(array $in, float $regular, array &$errors): array
    {
        $price = trim((string) ($in['sale_price'] ?? ''));
        if ($price === '' || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $price) || (float) $price <= 0) {
            $errors['sale_price'] = __('v_gte', ['n' => '0.01']);
        } elseif ($regular > 0 && (float) $price >= $regular) {
            $errors['sale_price'] = __('flash_price_below', ['amount' => money($regular)]);
        }
        $lim = static function (string $k) use ($in, &$errors): ?int {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v === '') {
                return null;
            }
            if (!preg_match('/^\d{1,6}$/', $v) || (int) $v < 1) {
                $errors[$k] = __('v_gte', ['n' => 1]);
                return null;
            }
            return (int) $v;
        };
        return [
            'sale_price'     => $price,
            'quantity_limit' => $lim('quantity_limit'),
            'per_user_limit' => $lim('per_user_limit'),
        ];
    }

    /** @return array{0: array, 1: ?string} */
    private function validated(string $formPath): array
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'title_en'  => 'required|max:200',
            'title_bn'  => 'max:200',
            'funded_by' => 'required|in:' . implode(',', Promo::FUNDERS),
        ]);
        $dates = [];
        foreach (['starts_at', 'ends_at'] as $k) {
            $d = DateTime::createFromFormat('!Y-m-d\TH:i', (string) ($in[$k] ?? ''));
            if (!$d) {
                $errors[$k] = __('v_date');
            } else {
                $dates[$k] = $d->format('Y-m-d H:i:00');
            }
        }
        if (isset($dates['starts_at'], $dates['ends_at']) && $dates['ends_at'] <= $dates['starts_at']) {
            $errors['ends_at'] = __('v_end_after_start');
        }
        $share = 0.0;
        if (($in['funded_by'] ?? '') === 'shared') {
            $v = (string) ($in['vendor_share_percent'] ?? '');
            if (!is_numeric($v) || (float) $v < 1 || (float) $v > 99) {
                $errors['vendor_share_percent'] = __('v_between', ['min' => 1, 'max' => 99]);
            } else {
                $share = (float) $v;
            }
        } elseif (($in['funded_by'] ?? '') === 'vendor') {
            $share = 100.0;
        }
        $files = Upload::files('banner');
        if ($files && ($e = Upload::validateImage($files[0]))) {
            $errors['banner'] = $e;
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        return [[
            'title_en'             => $in['title_en'],
            'title_bn'             => ($in['title_bn'] ?? '') !== '' ? $in['title_bn'] : null,
            'starts_at'            => $dates['starts_at'],
            'ends_at'              => $dates['ends_at'],
            'funded_by'            => $in['funded_by'],
            'vendor_share_percent' => $share,
            'is_active'            => !empty($in['is_active']) ? 1 : 0,
        ], $files ? Upload::storeImage($files[0], 'flash') : null];
    }
}
