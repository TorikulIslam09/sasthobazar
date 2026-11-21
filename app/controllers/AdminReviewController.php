<?php
declare(strict_types=1);

/** Review moderation: approve / reject / delete (one or many), filters, auto-approve switch. Product ratings follow. */
class AdminReviewController extends Controller
{
    public function index(): void
    {
        $status = in_array(Helpers::str('status'), ['pending', 'approved', 'rejected'], true) ? Helpers::str('status') : '';
        $rating = (int) Helpers::str('rating');
        $q = mb_substr(Helpers::str('q'), 0, 60);
        $where = ['1 = 1'];
        $p = [];
        if ($status !== '') {
            $where[] = 'r.status = :st';
            $p['st'] = $status;
        }
        if ($rating >= 1 && $rating <= 5) {
            $where[] = 'r.rating = :ra';
            $p['ra'] = $rating;
        }
        if ($q !== '') {
            $where[] = '(pr.name_en LIKE :q1 OR r.comment LIKE :q2 OR r.title LIKE :q3 OR u.name LIKE :q4 OR u.phone LIKE :q5)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }
        $w = implode(' AND ', $where);
        $from = 'FROM reviews r JOIN products pr ON pr.id = r.product_id LEFT JOIN users u ON u.id = r.user_id LEFT JOIN vendors v ON v.id = r.vendor_id';
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) {$from} WHERE {$w}", $p), 25);
        $this->view('admin/reviews/index', [
            'title'   => __('reviews'),
            'status'  => $status,
            'rating'  => $rating,
            'q'       => $q,
            'counts'  => array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM reviews GROUP BY status'), 'n', 'status'),
            'reviews' => $this->db->fetchAll(
                "SELECT r.*, pr.name_en AS product_name, pr.slug AS product_slug, u.name AS customer_name, u.phone AS customer_phone, v.shop_name
                 {$from} WHERE {$w} ORDER BY r.status = 'pending' DESC, r.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
                $p
            ),
            'pager'   => $pager,
            'auto'    => (bool) setting('review_auto_approve', true),
        ], 'admin');
    }

    /** POST action = approve | reject | delete, ids[] = review ids */
    public function moderate(): void
    {
        $action = (string) ($_POST['action'] ?? '');
        $ids = array_values(array_unique(array_map('intval', array_filter((array) ($_POST['ids'] ?? []), static fn($v) => ctype_digit((string) $v)))));
        if (!$ids || !in_array($action, ['approve', 'reject', 'delete'], true)) {
            flash('error', __('reviews_pick'));
            back();
        }
        if ($action === 'delete' && !can('reviews.delete')) {
            Auth::forbidden();
        }
        $in = implode(',', $ids);
        $products = array_map('intval', array_column($this->db->fetchAll("SELECT DISTINCT product_id FROM reviews WHERE id IN ({$in})"), 'product_id'));
        if ($action === 'delete') {
            $this->db->query("DELETE FROM reviews WHERE id IN ({$in})");
        } else {
            $this->db->query("UPDATE reviews SET status = :st WHERE id IN ({$in})", ['st' => $action === 'approve' ? 'approved' : 'rejected']);
        }
        foreach ($products as $pid) {
            Review::recalculate($pid);
        }
        Audit::log('reviews.' . $action, 'reviews', count($ids) === 1 ? $ids[0] : null, null, ['ids' => $ids]);
        flash('success', __('reviews_done_' . $action, ['n' => num(count($ids))]));
        back();
    }

    public function settings(): void
    {
        $v = !empty($_POST['review_auto_approve']) ? '1' : '0';
        $this->db->query("UPDATE settings SET value = :v WHERE `key` = 'review_auto_approve'", ['v' => $v]);
        Helpers::clearSettingsCache();
        Audit::log('settings.update', 'settings', null, null, ['review_auto_approve' => $v]);
        flash('success', __('settings_saved'));
        redirect('admin/reviews');
    }
}
