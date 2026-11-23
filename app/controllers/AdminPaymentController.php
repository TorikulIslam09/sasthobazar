<?php
declare(strict_types=1);

/**
 * Admin: every payment in one place (COD, bKash, Nagad, Rocket ...). Filter / search, totals,
 * verify or reject a waiting mobile-banking payment (same check as on the order page), CSV export.
 * Permissions: payments.view / edit / export.
 */
class AdminPaymentController extends Controller
{
    public const STATUSES = ['initiated', 'pending', 'success', 'failed', 'cancelled', 'refunded'];
    public const METHODS = ['cod', 'bkash', 'nagad', 'rocket', 'sslcommerz', 'card', 'bank', 'wallet'];

    private function filters(): array
    {
        $date = static fn(string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', Helpers::str($k)) ? Helpers::str($k) : '';
        return [
            'q'      => mb_substr(Helpers::str('q'), 0, 100),
            'status' => in_array(Helpers::str('status'), self::STATUSES, true) || Helpers::str('status') === 'to_verify' ? Helpers::str('status') : '',
            'method' => in_array(Helpers::str('method'), self::METHODS, true) ? Helpers::str('method') : '',
            'from'   => $date('from'),
            'to'     => $date('to'),
        ];
    }

    /** @return array{0: string, 1: array} */
    private function where(array $f): array
    {
        $w = ['1 = 1'];
        $p = [];
        if ($f['status'] === 'to_verify') {
            $w[] = "p.status = 'pending' AND p.gateway IS NOT NULL AND o.status <> 'cancelled'"; // customer sent money, TrxID to check
        } elseif ($f['status'] !== '') {
            $w[] = 'p.status = :st';
            $p['st'] = $f['status'];
        }
        if ($f['method'] !== '') {
            $w[] = 'p.method = :m';
            $p['m'] = $f['method'];
        }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(o.order_no LIKE :q1 OR p.transaction_id LIKE :q2 OR p.payer_account LIKE :q3 OR o.customer_phone LIKE :q4 OR o.customer_name LIKE :q5)';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }
        if ($f['from'] !== '') {
            $w[] = 'p.created_at >= :from';
            $p['from'] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $w[] = 'p.created_at <= :to';
            $p['to'] = $f['to'] . ' 23:59:59';
        }
        return [implode(' AND ', $w), $p];
    }

    private const FROM = 'FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN users r ON r.id = p.received_by';

    public function index(): void
    {
        $f = $this->filters();
        [$w, $p] = $this->where($f);
        $pager = new Paginator((int) $this->db->fetchColumn('SELECT COUNT(*) ' . self::FROM . " WHERE {$w}", $p), 30);
        $rows = $this->db->fetchAll(
            'SELECT p.*, o.order_no, o.status AS order_status, o.customer_name, o.customer_phone, o.grand_total, r.name AS receiver ' . self::FROM
            . " WHERE {$w} ORDER BY p.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            $p
        );
        $totals = $this->db->fetch(
            "SELECT COALESCE(SUM(CASE WHEN p.status = 'success' THEN p.amount END), 0) AS received,
                    COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount END), 0) AS waiting,
                    COALESCE(SUM(CASE WHEN p.status = 'refunded' THEN p.amount END), 0) AS refunded,
                    COALESCE(SUM(CASE WHEN p.status = 'success' THEN p.fee END), 0) AS fees " . self::FROM . " WHERE {$w}",
            $p
        );
        $byMethod = $this->db->fetchAll(
            "SELECT p.method, COUNT(*) AS n, COALESCE(SUM(CASE WHEN p.status = 'success' THEN p.amount END), 0) AS received " . self::FROM
            . " WHERE {$w} GROUP BY p.method ORDER BY received DESC",
            $p
        );
        $toVerify = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.status = 'pending' AND p.gateway IS NOT NULL AND o.status <> 'cancelled'"
        );
        $this->view('admin/payments/index', [
            'title'    => __('payments'),
            'f'        => $f,
            'rows'     => $rows,
            'pager'    => $pager,
            'totals'   => $totals,
            'byMethod' => $byMethod,
            'toVerify' => $toVerify,
        ], 'admin');
    }

    public function export(): void
    {
        $f = $this->filters();
        [$w, $p] = $this->where($f);
        $rows = $this->db->fetchAll(
            'SELECT p.*, o.order_no, o.customer_name, o.customer_phone, r.name AS receiver ' . self::FROM . " WHERE {$w} ORDER BY p.id DESC LIMIT 50000",
            $p
        );
        Audit::log('payments.export', 'payments', null, null, ['filters' => $f, 'rows' => count($rows)]);
        Helpers::csvDownload('payments-' . date('Ymd-His') . '.csv',
            ['ID', 'Date', 'Order', 'Customer', 'Mobile', 'Method', 'TrxID', 'Payer account', 'Amount', 'Fee', 'Status', 'Paid at', 'Received / checked by'],
            array_map(static fn($r) => [$r['id'], $r['created_at'], $r['order_no'], $r['customer_name'], $r['customer_phone'], $r['method'],
                $r['transaction_id'], $r['payer_account'], number_format((float) $r['amount'], 2, '.', ''), number_format((float) $r['fee'], 2, '.', ''),
                $r['status'], $r['paid_at'], $r['receiver']], $rows)
        );
    }

    /** Verify / reject a waiting payment (bKash / Nagad / Rocket TrxID) from the list. */
    public function verify(string $id): void
    {
        $pay = $this->db->fetch('SELECT id, order_id FROM payments WHERE id = :id', ['id' => (int) $id]);
        if (!$pay) {
            $this->notFound();
        }
        try {
            Order::verifyPayment((int) $pay['order_id'], (int) $pay['id'], Helpers::str('decision') === 'ok', mb_substr(Helpers::str('note'), 0, 255) ?: null, (int) Auth::id());
            flash('success', __('payment_saved'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        $back = Helpers::str('back');
        redirect(str_starts_with($back, 'admin/payments') ? $back : 'admin/payments');
    }
}
