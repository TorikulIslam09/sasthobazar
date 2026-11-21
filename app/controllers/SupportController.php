<?php
declare(strict_types=1);

/** My account → Support: customer tickets (list, open, conversation, reply, close). */
class SupportController extends Controller
{
    private function uid(): int
    {
        return (int) Auth::id();
    }

    public function index(): void
    {
        $this->view('account/support', [
            'title'   => __('my_tickets'),
            'tickets' => $this->db->fetchAll(
                'SELECT t.*, o.order_no, (SELECT COUNT(*) FROM support_ticket_replies r WHERE r.ticket_id = t.id AND r.is_internal = 0) AS replies
                   FROM support_tickets t LEFT JOIN orders o ON o.id = t.order_id
                  WHERE t.user_id = :u ORDER BY t.status IN (\'resolved\',\'closed\'), t.last_reply_at DESC, t.id DESC LIMIT 100',
                ['u' => $this->uid()]
            ),
        ]);
    }

    public function create(): void
    {
        $old = $_SESSION['_old'] ?? null;
        $orderNo = Helpers::str('order');
        $this->view('account/support_new', [
            'title'  => __('open_ticket'),
            'orders' => $this->db->fetchAll('SELECT id, order_no, placed_at, grand_total FROM orders WHERE user_id = :u ORDER BY id DESC LIMIT 30', ['u' => $this->uid()]),
            'input'  => is_array($old) ? $old : ['category' => Helpers::str('category') ?: 'order', 'order_no' => $orderNo],
            'errors' => $_SESSION['_errors'] ?? [],
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'subject'  => 'required|min:5|max:200',
            'category' => 'required|in:' . implode(',', Support::CATEGORIES),
            'message'  => 'required|min:10|max:3000',
        ]);
        $orderId = null;
        if (($in['order_no'] ?? '') !== '') {
            $orderId = $this->db->fetchColumn('SELECT id FROM orders WHERE order_no = :n AND user_id = :u', ['n' => $in['order_no'], 'u' => $this->uid()]);
            if (!$orderId) {
                $errors['order_no'] = __('v_in');
            }
        }
        if (!$errors && (int) $this->db->fetchColumn("SELECT COUNT(*) FROM support_tickets WHERE user_id = :u AND created_at >= (NOW() - INTERVAL 1 DAY)", ['u' => $this->uid()]) >= 10) {
            $errors['subject'] = __('ticket_too_many');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('account/support/new');
        }
        $id = Support::open($this->uid(), $in['subject'], $in['category'], $orderId ? (int) $orderId : null, $in['message']);
        $t = Support::find($id);
        flash('success', __('ticket_opened', ['no' => $t['ticket_no']]));
        redirect('account/support/' . $t['ticket_no']);
    }

    private function ticket(string $no): array
    {
        $id = $this->db->fetchColumn('SELECT id FROM support_tickets WHERE ticket_no = :n AND user_id = :u', ['n' => $no, 'u' => $this->uid()]);
        if (!$id) {
            $this->notFound();
        }
        return Support::find((int) $id);
    }

    public function show(string $no): void
    {
        $t = $this->ticket($no);
        $this->view('account/support_ticket', [
            'title'   => $t['ticket_no'] . ' · ' . $t['subject'],
            'ticket'  => $t,
            'replies' => Support::replies((int) $t['id'], false),
        ]);
    }

    public function reply(string $no): void
    {
        $t = $this->ticket($no);
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($t['status'] === 'closed') {
            flash('error', __('ticket_closed_msg'));
            redirect('account/support/' . $no);
        }
        if (mb_strlen($message) < 2 || mb_strlen($message) > 3000) {
            flash('error', __('ticket_message_length'));
            redirect('account/support/' . $no);
        }
        Support::customerReply($t, $this->uid(), $message);
        flash('success', __('ticket_reply_sent'));
        redirect('account/support/' . $no . '#last');
    }

    public function close(string $no): void
    {
        $t = $this->ticket($no);
        $this->db->update('support_tickets', ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $t['id']]);
        Audit::log('support.close', 'support_tickets', (int) $t['id'], ['status' => $t['status']], ['status' => 'closed', 'by' => 'customer']);
        flash('success', __('ticket_closed_ok'));
        redirect('account/support/' . $no);
    }
}
