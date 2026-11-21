<?php
declare(strict_types=1);

/** Return & refund requests across all orders (decisions are made on the order page). */
class AdminRefundController extends Controller
{
    public function index(): void
    {
        $status = in_array(Helpers::str('status'), ['requested', 'approved', 'processed', 'rejected'], true) ? Helpers::str('status') : 'requested';
        $total = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM refunds WHERE status = :s', ['s' => $status]);
        $pager = new Paginator($total, 25);
        $rows = $this->db->fetchAll(
            "SELECT rf.*, o.order_no, o.customer_name, o.customer_phone, oi.product_name, oi.variant_name
               FROM refunds rf JOIN orders o ON o.id = rf.order_id LEFT JOIN order_items oi ON oi.id = rf.order_item_id
              WHERE rf.status = :s ORDER BY rf.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            ['s' => $status]
        );
        $counts = array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM refunds GROUP BY status'), 'n', 'status');
        $this->view('admin/refunds/index', [
            'title'  => __('refunds'),
            'status' => $status,
            'rows'   => $rows,
            'pager'  => $pager,
            'counts' => $counts,
        ], 'admin');
    }
}
