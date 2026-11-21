<?php
declare(strict_types=1);

/** Admin → Support: ticket queue, conversation, replies / internal notes, status, priority, assignment. */
class AdminSupportController extends Controller
{
    public function index(): void
    {
        $f = [
            'status'   => Helpers::str('status'),
            'category' => Helpers::str('category'),
            'priority' => Helpers::str('priority'),
            'mine'     => Helpers::str('mine') === '1',
            'q'        => mb_substr(Helpers::str('q'), 0, 60),
        ];
        $where = ['1 = 1'];
        $p = [];
        if ($f['status'] === 'active' || $f['status'] === '') {
            $where[] = "t.status IN ('open','in_progress','waiting_customer')";
        } elseif (in_array($f['status'], Support::STATUSES, true)) {
            $where[] = 't.status = :st';
            $p['st'] = $f['status'];
        }
        if (in_array($f['category'], Support::CATEGORIES, true)) {
            $where[] = 't.category = :c';
            $p['c'] = $f['category'];
        }
        if (in_array($f['priority'], Support::PRIORITIES, true)) {
            $where[] = 't.priority = :pr';
            $p['pr'] = $f['priority'];
        }
        if ($f['mine']) {
            $where[] = 't.assigned_to = :me';
            $p['me'] = (int) Auth::id();
        }
        if ($f['q'] !== '') {
            $where[] = '(t.ticket_no LIKE :q1 OR t.subject LIKE :q2 OR u.phone LIKE :q3 OR u.name LIKE :q4 OR o.order_no LIKE :q5)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }
        $w = implode(' AND ', $where);
        $from = 'FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN orders o ON o.id = t.order_id LEFT JOIN users a ON a.id = t.assigned_to';
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$w}", $p), 25);
        $this->view('admin/support/index', [
            'title'   => __('support_tickets'),
            'f'       => $f,
            'counts'  => array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM support_tickets GROUP BY status'), 'n', 'status'),
            'tickets' => $this->db->fetchAll(
                "SELECT t.*, u.name AS customer_name, u.phone AS customer_phone, o.order_no, a.name AS assignee_name {$from}
                  WHERE {$w} ORDER BY FIELD(t.priority, 'urgent','high','medium','low'), t.status = 'open' DESC, t.last_reply_at DESC
                  LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
                $p
            ),
            'pager'   => $pager,
        ], 'admin');
    }

    private function ticket(int $id): array
    {
        $t = Support::find($id);
        if (!$t) {
            $this->notFound();
        }
        return $t;
    }

    public function show(string $id): void
    {
        $t = $this->ticket((int) $id);
        $this->view('admin/support/show', [
            'title'   => $t['ticket_no'],
            'ticket'  => $t,
            'replies' => Support::replies((int) $t['id'], true),
            'staff'   => $this->db->fetchAll(
                "SELECT u.id, u.name FROM users u WHERE u.status = 'active' AND u.deleted_at IS NULL AND (u.user_type = 'super_admin' OR (u.user_type = 'employee' AND EXISTS (
                    SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = u.role_id AND p.`key` = 'support.reply'))) ORDER BY u.name"
            ),
            'refunds' => $t['order_id'] ? $this->db->fetchAll('SELECT r.refund_no, r.amount, r.status, r.created_at, oi.product_name FROM refunds r LEFT JOIN order_items oi ON oi.id = r.order_item_id WHERE r.order_id = :o ORDER BY r.id DESC', ['o' => (int) $t['order_id']]) : [],
            'history' => $t['user_id'] ? $this->db->fetchAll('SELECT id, ticket_no, subject, status, created_at FROM support_tickets WHERE user_id = :u AND id <> :id ORDER BY id DESC LIMIT 5', ['u' => (int) $t['user_id'], 'id' => (int) $t['id']]) : [],
            'orders'  => $t['user_id'] ? (int) $this->db->fetchColumn('SELECT COUNT(*) FROM orders WHERE user_id = :u', ['u' => (int) $t['user_id']]) : 0,
        ], 'admin');
    }

    public function reply(string $id): void
    {
        $t = $this->ticket((int) $id);
        $message = trim((string) ($_POST['message'] ?? ''));
        $internal = !empty($_POST['internal']);
        $status = in_array($_POST['status'] ?? '', Support::STATUSES, true) ? $_POST['status'] : null;
        if (mb_strlen($message) < 2 || mb_strlen($message) > 5000) {
            flash('error', __('ticket_message_length'));
            redirect('admin/support/' . (int) $id);
        }
        Support::staffReply($t, (int) Auth::id(), $message, $internal, $status);
        flash('success', __($internal ? 'ticket_note_saved' : 'ticket_reply_sent'));
        redirect('admin/support/' . (int) $id . '#last');
    }

    public function update(string $id): void
    {
        $t = $this->ticket((int) $id);
        $data = [];
        if (in_array($_POST['status'] ?? '', Support::STATUSES, true)) {
            $data['status'] = $_POST['status'];
            $data['closed_at'] = in_array($_POST['status'], ['resolved', 'closed'], true) ? ($t['closed_at'] ?? date('Y-m-d H:i:s')) : null;
        }
        if (in_array($_POST['priority'] ?? '', Support::PRIORITIES, true)) {
            $data['priority'] = $_POST['priority'];
        }
        if (in_array($_POST['category'] ?? '', Support::CATEGORIES, true)) {
            $data['category'] = $_POST['category'];
        }
        if (array_key_exists('assigned_to', $_POST) && can('support.assign')) {
            $a = (string) $_POST['assigned_to'];
            $data['assigned_to'] = ctype_digit($a) && $this->db->fetchColumn("SELECT 1 FROM users WHERE id = :id AND user_type IN ('super_admin','employee')", ['id' => (int) $a]) ? (int) $a : null;
            if ($data['assigned_to'] && $data['assigned_to'] !== (int) $t['assigned_to'] && $data['assigned_to'] !== (int) Auth::id()) {
                Notification::send($data['assigned_to'], 'support', __('notif_ticket_assigned', ['no' => $t['ticket_no']]), $t['subject'], url('admin/support/' . (int) $id));
            }
        }
        if ($data) {
            $this->db->update('support_tickets', $data, 'id = :id', ['id' => (int) $id]);
            Audit::log('support.update', 'support_tickets', (int) $id, array_intersect_key($t, $data), $data);
            if (isset($data['status']) && $data['status'] === 'resolved' && $t['status'] !== 'resolved' && $t['user_id']) {
                Notification::send((int) $t['user_id'], 'support', __('notif_ticket_resolved', ['no' => $t['ticket_no']]), null, url('account/support/' . $t['ticket_no']), 'ticket_reply');
            }
        }
        flash('success', __('ticket_updated'));
        redirect('admin/support/' . (int) $id);
    }

    public function export(): void
    {
        $rows = $this->db->fetchAll(
            'SELECT t.ticket_no, t.created_at, t.subject, t.category, t.priority, t.status, u.name, u.phone, o.order_no, a.name AS assignee, t.last_reply_at, t.closed_at
               FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN orders o ON o.id = t.order_id LEFT JOIN users a ON a.id = t.assigned_to
           ORDER BY t.id DESC LIMIT 5000'
        );
        Helpers::csvDownload('support-tickets-' . date('Ymd') . '.csv',
            ['Ticket', 'Created', 'Subject', 'Category', 'Priority', 'Status', 'Customer', 'Phone', 'Order', 'Assigned to', 'Last reply', 'Closed'],
            array_map('array_values', $rows));
    }
}
