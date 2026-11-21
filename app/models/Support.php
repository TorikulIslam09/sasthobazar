<?php
declare(strict_types=1);

/**
 * Support tickets. Customers open tickets (optionally about one of their orders) and reply from My account;
 * staff answer from Admin → Support (replies or internal notes), assign, set priority and status.
 * Status: open -> in_progress -> waiting_customer -> resolved -> closed (a customer reply re-opens the ticket).
 */
final class Support
{
    public const CATEGORIES = ['order', 'payment', 'delivery', 'product', 'refund', 'prescription', 'account', 'other'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const STATUSES = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function newNo(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $no = 'TK' . date('ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (!self::db()->fetchColumn('SELECT 1 FROM support_tickets WHERE ticket_no = :n', ['n' => $no])) {
                return $no;
            }
        }
        return 'TK' . date('ymdHis');
    }

    /** @return int ticket id */
    public static function open(int $userId, string $subject, string $category, ?int $orderId, string $message): int
    {
        $db = self::db();
        $id = $db->transaction(static function (Database $db) use ($userId, $subject, $category, $orderId, $message): int {
            $no = self::newNo();
            $id = $db->insert('support_tickets', [
                'ticket_no'     => $no,
                'user_id'       => $userId,
                'order_id'      => $orderId,
                'subject'       => mb_substr($subject, 0, 255),
                'category'      => $category,
                'priority'      => in_array($category, ['refund', 'payment'], true) ? 'high' : 'medium',
                'status'        => 'open',
                'last_reply_at' => date('Y-m-d H:i:s'),
            ]);
            $db->insert('support_ticket_replies', ['ticket_id' => $id, 'user_id' => $userId, 'message' => $message, 'is_internal' => 0]);
            return $id;
        });
        $t = self::find($id);
        Audit::log('support.open', 'support_tickets', $id, null, ['ticket_no' => $t['ticket_no'], 'category' => $category]);
        Notification::toStaff(['support.view'], 'support', __('notif_ticket_new', ['no' => $t['ticket_no'], 'subject' => mb_substr($subject, 0, 80)]), null, url('admin/support/' . $id));
        return $id;
    }

    public static function find(int $id): ?array
    {
        return self::db()->fetch(
            'SELECT t.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email, o.order_no,
                    a.name AS assignee_name
               FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN orders o ON o.id = t.order_id
          LEFT JOIN users a ON a.id = t.assigned_to
              WHERE t.id = :id',
            ['id' => $id]
        );
    }

    public static function replies(int $ticketId, bool $withInternal): array
    {
        return self::db()->fetchAll(
            'SELECT r.*, u.name, u.user_type FROM support_ticket_replies r LEFT JOIN users u ON u.id = r.user_id
              WHERE r.ticket_id = :t' . ($withInternal ? '' : ' AND r.is_internal = 0') . ' ORDER BY r.id',
            ['t' => $ticketId]
        );
    }

    /** Customer reply: re-opens a waiting / resolved ticket. */
    public static function customerReply(array $t, int $userId, string $message): void
    {
        $db = self::db();
        $db->insert('support_ticket_replies', ['ticket_id' => (int) $t['id'], 'user_id' => $userId, 'message' => $message, 'is_internal' => 0]);
        $status = in_array($t['status'], ['waiting_customer', 'resolved'], true) ? 'open' : $t['status'];
        $db->update('support_tickets', ['status' => $status, 'last_reply_at' => date('Y-m-d H:i:s'), 'closed_at' => null], 'id = :id', ['id' => (int) $t['id']]);
        $msg = __('notif_ticket_reply_staff', ['no' => $t['ticket_no']]);
        if ($t['assigned_to']) {
            Notification::send((int) $t['assigned_to'], 'support', $msg, null, url('admin/support/' . (int) $t['id']));
        } else {
            Notification::toStaff(['support.view'], 'support', $msg, null, url('admin/support/' . (int) $t['id']));
        }
    }

    /** Staff reply or internal note; a public reply sets "waiting for customer" (unless another status is chosen). */
    public static function staffReply(array $t, int $staffId, string $message, bool $internal, ?string $status): void
    {
        $db = self::db();
        $db->insert('support_ticket_replies', ['ticket_id' => (int) $t['id'], 'user_id' => $staffId, 'message' => $message, 'is_internal' => $internal ? 1 : 0]);
        $data = [];
        if (!$internal) {
            $data['last_reply_at'] = date('Y-m-d H:i:s');
            $status ??= 'waiting_customer';
        }
        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $data['status'] = $status;
            $data['closed_at'] = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;
        }
        if (!$t['assigned_to']) {
            $data['assigned_to'] = $staffId;
        }
        if ($data) {
            $db->update('support_tickets', $data, 'id = :id', ['id' => (int) $t['id']]);
        }
        Audit::log($internal ? 'support.note' : 'support.reply', 'support_tickets', (int) $t['id'], ['status' => $t['status']], $data);
        if (!$internal && $t['user_id']) {
            Notification::send((int) $t['user_id'], 'support', __('notif_ticket_reply', ['no' => $t['ticket_no']]),
                Html::excerpt($message, 200), url('account/support/' . $t['ticket_no']), 'ticket_reply');
        }
    }

    public static function badge(string $status): string
    {
        $c = ['open' => 'primary', 'in_progress' => 'info', 'waiting_customer' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'][$status] ?? 'light';
        return '<span class="badge text-bg-' . $c . '">' . e(__('tk_' . $status)) . '</span>';
    }

    public static function priorityBadge(string $p): string
    {
        $c = ['low' => 'light border', 'medium' => 'secondary', 'high' => 'warning', 'urgent' => 'danger'][$p] ?? 'light';
        return '<span class="badge text-bg-' . $c . '">' . e(__('tkp_' . $p)) . '</span>';
    }
}
