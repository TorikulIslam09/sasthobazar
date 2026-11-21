<?php
declare(strict_types=1);

/**
 * Staff notifications: the bell (AJAX polling every 30 s), the full list, open (= mark read + go to the link),
 * mark all read. Notifications are created by orders, low stock, seller registrations / offer requests,
 * payout requests, support tickets and pending reviews (Notification::toStaff).
 */
class AdminNotificationController extends Controller
{
    /** JSON for the bell: unread count + latest items; ?since=ID also returns the new ones for a toast. */
    public function poll(): void
    {
        $uid = (int) Auth::id();
        $since = max(0, (int) Helpers::str('since'));
        $fmt = static fn(array $n) => [
            'id'    => (int) $n['id'],
            'title' => $n['title'],
            'text'  => $n['message'] ? Html::excerpt($n['message'], 90) : '',
            'icon'  => Notification::icon((string) $n['type']),
            'url'   => url('admin/notifications/' . (int) $n['id'] . '/open'),
            'time'  => dt($n['created_at'], 'd M, h:i A'),
            'read'  => $n['read_at'] !== null,
        ];
        header('Cache-Control: no-store');
        $latest = Notification::latest($uid, 8);
        $this->json([
            'unread' => Notification::unreadCount($uid),
            'max_id' => $latest ? (int) $latest[0]['id'] : $since,
            'items'  => array_map($fmt, $latest),
            'new'    => $since > 0 ? array_map($fmt, array_reverse(Notification::since($uid, $since, 5))) : [],
        ]);
    }

    public function index(): void
    {
        $uid = (int) Auth::id();
        $unreadOnly = Helpers::str('unread') === '1';
        $where = 'user_id = :u' . ($unreadOnly ? ' AND read_at IS NULL' : '');
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE {$where}", ['u' => $uid]), 30);
        $this->view('admin/notifications', [
            'title'      => __('notifications'),
            'items'      => $this->db->fetchAll("SELECT * FROM notifications WHERE {$where} ORDER BY id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}", ['u' => $uid]),
            'pager'      => $pager,
            'unreadOnly' => $unreadOnly,
            'unread'     => Notification::unreadCount($uid),
        ], 'admin');
    }

    public function open(string $id): void
    {
        $n = Notification::markRead((int) Auth::id(), (int) $id);
        if (!$n) {
            $this->notFound();
        }
        $link = (string) $n['link'];
        // only our own paths (never an outside site)
        if ($link !== '' && str_starts_with($link, '/') && !str_starts_with($link, '//')) {
            redirect($link);
        }
        redirect('admin/notifications');
    }

    public function readAll(): void
    {
        Notification::markAllRead((int) Auth::id());
        if (Helpers::isAjax()) {
            $this->json(['ok' => true, 'unread' => 0]);
        }
        back();
    }
}
