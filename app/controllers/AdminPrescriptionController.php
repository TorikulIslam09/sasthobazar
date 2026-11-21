<?php
declare(strict_types=1);

/**
 * Prescriptions: the queue of every uploaded prescription (checkout uploads are reviewed on their order page;
 * "order by prescription" requests are handled here: call the customer, prepare the order, mark the request),
 * plus the secure file viewer.
 */
class AdminPrescriptionController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected'];
    private const TYPES = ['checkout', 'order_request'];

    public function index(): void
    {
        $status = Helpers::str('status');
        $status = in_array($status, self::STATUSES, true) || $status === 'all' ? $status : 'pending';
        $type = in_array(Helpers::str('type'), self::TYPES, true) ? Helpers::str('type') : '';
        $q = mb_substr(Helpers::str('q'), 0, 50);

        // one row per request (files of one request share group_code) or per checkout upload
        $where = ['(pr.group_code IS NULL OR pr.id = (SELECT MIN(y.id) FROM prescriptions y WHERE y.group_code = pr.group_code))'];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'pr.status = :s';
            $params['s'] = $status;
        }
        if ($type !== '') {
            $where[] = 'pr.request_type = :t';
            $params['t'] = $type;
        }
        if ($q !== '') {
            $where[] = '(pr.group_code LIKE :q1 OR pr.guest_phone LIKE :q2 OR pr.guest_name LIKE :q3 OR u.phone LIKE :q4 OR u.name LIKE :q5)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) {
                $params[$k] = '%' . $q . '%';
            }
        }
        $w = implode(' AND ', $where);
        $from = 'FROM prescriptions pr LEFT JOIN users u ON u.id = pr.user_id LEFT JOIN users r ON r.id = pr.reviewed_by';
        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$w}", $params);
        $pager = new Paginator($total, 25);
        $rows = $this->db->fetchAll(
            "SELECT pr.*, u.name AS customer, u.phone, r.name AS reviewer,
                    (SELECT COUNT(*) FROM prescriptions x WHERE x.group_code = pr.group_code) AS files,
                    (SELECT o.id FROM orders o WHERE o.prescription_id = pr.id ORDER BY o.id DESC LIMIT 1) AS order_id,
                    (SELECT o.order_no FROM orders o WHERE o.prescription_id = pr.id ORDER BY o.id DESC LIMIT 1) AS order_no_linked,
                    (SELECT o.status FROM orders o WHERE o.prescription_id = pr.id ORDER BY o.id DESC LIMIT 1) AS order_status
               {$from} WHERE {$w}
              ORDER BY pr.id " . ($status === 'pending' ? 'ASC' : 'DESC') . "
              LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            $params
        );
        $counts = array_column($this->db->fetchAll(
            'SELECT status, COUNT(*) AS n FROM prescriptions WHERE group_code IS NULL OR id = (SELECT MIN(y.id) FROM prescriptions y WHERE y.group_code = prescriptions.group_code) GROUP BY status'
        ), 'n', 'status');
        $newRequests = (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT group_code) FROM prescriptions WHERE request_type = 'order_request' AND request_status = 'new'");
        $this->view('admin/prescriptions/index', [
            'title'  => __('prescriptions'),
            'status' => $status,
            'type'   => $type,
            'q'      => $q,
            'rows'   => $rows,
            'pager'  => $pager,
            'counts' => $counts,
            'newRequests' => $newRequests,
        ], 'admin');
    }

    /** An "order by prescription" request: files, contact, address, actions. */
    public function show(string $id): void
    {
        $row = Prescription::find((int) $id);
        if (!$row || $row['request_type'] !== 'order_request') {
            $this->notFound();
        }
        $files = Prescription::group((string) $row['group_code']);
        $row = $files[0] ?? $row;
        $user = $row['user_id'] ? User::find((int) $row['user_id']) : null;
        $this->view('admin/prescriptions/show', [
            'title'    => __('rx_request') . ' ' . $row['group_code'],
            'r'        => $row,
            'files'    => $files,
            'user'     => $user,
            'area'     => OrderView::area(['upazila_id' => $row['upazila_id'], 'district_id' => $row['district_id'], 'division_id' => null]),
            'reviewer' => $row['reviewed_by'] ? $this->db->fetchColumn('SELECT name FROM users WHERE id = :id', ['id' => $row['reviewed_by']]) : null,
            'errors'   => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
    }

    /** POST: pharmacist review + request progress for an order request. */
    public function update(string $id): void
    {
        $row = Prescription::find((int) $id);
        if (!$row || $row['request_type'] !== 'order_request') {
            $this->notFound();
        }
        $in = $this->postData();
        $back = 'admin/prescriptions/' . (int) $row['id'];
        $status = in_array($in['status'] ?? '', self::STATUSES, true) ? $in['status'] : $row['status'];
        $reqStatus = in_array($in['request_status'] ?? '', Prescription::REQUEST_STATUSES, true) ? $in['request_status'] : $row['request_status'];
        $note = mb_substr(trim((string) ($in['review_note'] ?? '')), 0, 500);
        $orderNo = strtoupper(mb_substr(trim((string) ($in['order_no'] ?? '')), 0, 30));
        // approve / reject = prescriptions.verify (pharmacist); call progress + order no = prescriptions.update_status
        if (!can('prescriptions.verify')) {
            $status = $row['status'];
        }
        if (!can('prescriptions.update_status')) {
            $reqStatus = $row['request_status'];
            $orderNo = (string) $row['order_no'];
        }
        $errors = [];
        if ($status === 'rejected' && $note === '') {
            $errors['review_note'] = __('rx_reject_reason');
        }
        if ($orderNo !== '' && !Order::findByNo($orderNo)) {
            $errors['order_no'] = __('order_not_found');
        }
        if ($reqStatus === 'ordered' && $orderNo === '') {
            $errors['order_no'] = __('rx_req_need_order_no');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            flash('error', __('form_has_errors'));
            redirect($back);
        }
        $set = ['request_status' => $reqStatus, 'order_no' => $orderNo ?: null, 'review_note' => $note ?: null, 'updated_at' => date('Y-m-d H:i:s')];
        if ($status !== $row['status']) {
            $set += ['status' => $status, 'reviewed_by' => Auth::id(), 'reviewed_at' => date('Y-m-d H:i:s')];
        }
        $this->db->update('prescriptions', $set, 'group_code = :g', ['g' => (string) $row['group_code']]);
        if ($orderNo !== '' && $row['user_id'] === null) {
            // the order was made for the same guest: let it follow the request when they create an account
            $this->db->query('UPDATE orders SET prescription_id = COALESCE(prescription_id, :p) WHERE order_no = :n', ['p' => (int) $row['id'], 'n' => $orderNo]);
        }
        Audit::log('prescriptions.request_update', 'prescriptions', (int) $row['id'],
            ['status' => $row['status'], 'request_status' => $row['request_status'], 'order_no' => $row['order_no']],
            ['status' => $status, 'request_status' => $reqStatus, 'order_no' => $orderNo ?: null]);

        // tell the customer when the pharmacist decides or the order is ready
        if ($status !== $row['status'] || ($reqStatus !== $row['request_status'] && in_array($reqStatus, ['ordered', 'cancelled'], true))) {
            $title = __('rx_req_update_title', ['code' => $row['group_code'], 'status' => __($reqStatus === 'ordered' ? 'rxr_ordered' : 'rx_status_' . $status)]);
            $msg = $note !== '' ? $note : null;
            if ($row['user_id'] !== null) {
                Notification::send((int) $row['user_id'], 'prescription', $title, $msg, url('account/prescriptions'), 'prescription');
            } else {
                Notifier::contact((string) $row['guest_phone'], '', 'prescription', $title, $msg);
            }
        }
        flash('success', __('saved'));
        redirect($back);
    }

    public function file(string $id): void
    {
        $row = Prescription::find((int) $id);
        if (!$row) {
            $this->notFound();
        }
        Audit::log('prescriptions.view_file', 'prescriptions', (int) $id);
        Prescription::stream($row);
    }
}
