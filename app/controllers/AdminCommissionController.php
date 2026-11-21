<?php
declare(strict_types=1);

/**
 * Commission rates: global default, per category, per vendor, per product.
 * Priority when an order is placed: product > category (nearest parent with a rate) > vendor > global.
 */
class AdminCommissionController extends Controller
{
    public function index(): void
    {
        $this->view('admin/commissions/index', [
            'title'      => __('commissions'),
            'global'     => Commission::globalRate(),
            'categories' => Category::tree(),
            'vendors'    => $this->db->fetchAll("SELECT id, shop_name, shop_name_bn, status, commission_rate FROM vendors WHERE deleted_at IS NULL AND status IN ('approved','suspended','pending') ORDER BY shop_name"),
            'products'   => $this->db->fetchAll(
                'SELECT p.id, p.name_en, p.sku, p.commission_rate, v.shop_name FROM products p LEFT JOIN vendors v ON v.id = p.vendor_id
                  WHERE p.deleted_at IS NULL AND p.commission_rate IS NOT NULL ORDER BY p.name_en LIMIT 300'
            ),
            'earned'     => $this->db->fetch(
                "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n FROM commissions WHERE status IN ('earned','settled')"
            ),
        ], 'admin');
    }

    private static function rate(mixed $v): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $v) || (float) $v > 100) {
            throw new OrderException(__('v_rate'));
        }
        return $v;
    }

    public function save(): void
    {
        $section = Helpers::str('section');
        try {
            if ($section === 'global') {
                $r = self::rate($_POST['global'] ?? '');
                if ($r === null) {
                    throw new OrderException(__('v_rate'));
                }
                $old = setting('default_commission_rate');
                $this->db->update('settings', ['value' => number_format((float) $r, 2, '.', '')], '`key` = :k', ['k' => 'default_commission_rate']);
                Helpers::clearSettingsCache();
                Audit::log('commissions.global', 'settings', null, ['default_commission_rate' => $old], ['default_commission_rate' => $r]);
            } elseif ($section === 'categories' || $section === 'vendors') {
                $table = $section;
                $changed = [];
                foreach ((array) ($_POST['rate'] ?? []) as $id => $val) {
                    $r = self::rate($val);
                    $cur = $this->db->fetchColumn("SELECT commission_rate FROM {$table} WHERE id = :id", ['id' => (int) $id]);
                    if ($cur === false) {
                        continue;
                    }
                    if (($cur === null ? null : (float) $cur) !== ($r === null ? null : (float) $r)) {
                        $this->db->update($table, ['commission_rate' => $r], 'id = :id', ['id' => (int) $id]);
                        $changed[(int) $id] = $r;
                    }
                }
                if ($section === 'categories') {
                    Category::clearCache();
                }
                if ($changed) {
                    Audit::log('commissions.' . $section, $table, null, null, $changed);
                }
            } elseif ($section === 'product') {
                $ref = trim(Helpers::str('product'));
                $pid = $this->db->fetchColumn('SELECT id FROM products WHERE deleted_at IS NULL AND (id = :id OR sku = :sku) LIMIT 1', ['id' => ctype_digit($ref) ? (int) $ref : 0, 'sku' => $ref]);
                if (!$pid) {
                    throw new OrderException(__('coupon_unknown_product', ['ref' => $ref]));
                }
                $r = self::rate(Helpers::str('product_rate'));
                $this->db->update('products', ['commission_rate' => $r, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $pid]);
                Audit::log('commissions.product', 'products', (int) $pid, null, ['commission_rate' => $r]);
            } else {
                throw new OrderException(__('v_in'));
            }
            flash('success', __('commission_saved'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/commissions#' . $section);
    }
}
