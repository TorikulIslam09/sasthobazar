<?php
declare(strict_types=1);

/** Admin: vendor payout requests (approve / reject / mark paid with reference). */
class AdminPayoutController extends Controller
{
    public function index(): void
    {
        $status = array_key_exists(Helpers::str('status'), Payout::BADGES) ? Helpers::str('status') : 'requested';
        $pager = new Paginator((int) $this->db->fetchColumn('SELECT COUNT(*) FROM vendor_payouts WHERE status = :s', ['s' => $status]), 25);
        $rows = $this->db->fetchAll(
            "SELECT p.*, v.shop_name, v.phone, w.balance, u.name AS processed_by_name
               FROM vendor_payouts p JOIN vendors v ON v.id = p.vendor_id LEFT JOIN vendor_wallets w ON w.vendor_id = p.vendor_id
          LEFT JOIN users u ON u.id = p.processed_by
              WHERE p.status = :s ORDER BY p.id " . ($status === 'requested' ? 'ASC' : 'DESC') . " LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            ['s' => $status]
        );
        $counts = array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n, SUM(amount) AS total FROM vendor_payouts GROUP BY status'), null, 'status');
        $this->view('admin/payouts/index', ['title' => __('vendor_payouts'), 'rows' => $rows, 'pager' => $pager, 'status' => $status, 'counts' => $counts], 'admin');
    }

    public function decide(string $id): void
    {
        $action = Helpers::str('action');
        if (!in_array($action, ['approve', 'reject', 'paid'], true)) {
            flash('error', __('v_in'));
            back();
        }
        $note = mb_substr(Helpers::str('note'), 0, 255) ?: null;
        if ($action === 'reject' && $note === null) {
            flash('error', __('vendor_reason_required'));
            back();
        }
        try {
            $p = Payout::decide((int) $id, $action, mb_substr(trim(Helpers::str('reference_no')), 0, 100) ?: null, $note, (int) Auth::id());
            flash('success', __('payout_' . $p['status'] . '_msg', ['amount' => money($p['amount'])]));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        back();
    }
}
