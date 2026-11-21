<?php
declare(strict_types=1);

/** Cash on delivery: to collect, collected by courier (awaiting payment to us), settlements. */
class AdminCodController extends Controller
{
    public function index(): void
    {
        $courier = (int) Helpers::str('courier');
        $summary = $this->db->fetchAll(
            "SELECT c.id, c.name,
                    SUM(sh.cod_status = 'pending') AS pending_n, COALESCE(SUM(CASE WHEN sh.cod_status = 'pending' THEN sh.cod_amount END), 0) AS pending_amt,
                    SUM(sh.cod_status = 'collected') AS collected_n, COALESCE(SUM(CASE WHEN sh.cod_status = 'collected' THEN sh.collected_amount END), 0) AS collected_amt,
                    COALESCE(SUM(CASE WHEN sh.cod_status = 'collected' THEN sh.delivery_charge END), 0) AS fees
               FROM couriers c JOIN shipments sh ON sh.courier_id = c.id
              GROUP BY c.id, c.name ORDER BY collected_amt DESC"
        );
        $unbooked = $this->db->fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(cod_amount), 0) AS amt FROM shipments WHERE cod_status = 'pending' AND courier_id IS NULL AND status NOT IN ('cancelled')"
        );
        $collected = $courier ? $this->db->fetchAll(
            "SELECT sh.*, o.order_no, s.sub_order_no, o.shipping_name FROM shipments sh JOIN orders o ON o.id = sh.order_id LEFT JOIN order_vendor_splits s ON s.id = sh.split_id
              WHERE sh.courier_id = :c AND sh.cod_status = 'collected' ORDER BY sh.delivered_at, sh.id",
            ['c' => $courier]
        ) : [];
        $this->view('admin/cod/index', [
            'title'       => __('cod_management'),
            'summary'     => $summary,
            'unbooked'    => $unbooked,
            'courier'     => $courier,
            'courierRow'  => $courier ? Courier::find($courier) : null,
            'collected'   => $collected,
            'settlements' => $this->db->fetchAll(
                'SELECT cs.*, c.name AS courier_name, u.name AS by_name FROM cod_settlements cs JOIN couriers c ON c.id = cs.courier_id
              LEFT JOIN users u ON u.id = cs.created_by ORDER BY cs.id DESC LIMIT 30'
            ),
        ], 'admin');
    }

    public function settle(): void
    {
        $courier = (int) Helpers::str('courier');
        $ref = trim(Helpers::str('reference_no'));
        $amount = Helpers::str('amount_received');
        $date = Helpers::str('received_at');
        if ($ref === '' || !preg_match('/^\d{1,9}(\.\d{1,2})?$/', $amount) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            flash('error', __('cod_settle_invalid'));
            redirect('admin/cod?courier=' . $courier);
        }
        try {
            Shipment::settleCod((array) ($_POST['ids'] ?? []), $courier, $ref, (float) $amount, mb_substr(Helpers::str('note'), 0, 255) ?: null, $date, (int) Auth::id());
            flash('success', __('cod_settled_msg', ['amount' => money($amount)]));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/cod?courier=' . $courier);
    }
}
