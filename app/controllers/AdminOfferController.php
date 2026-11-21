<?php
declare(strict_types=1);

/**
 * Offers: price discounts (product / category / brand / vendor / all), buy-X-get-Y, first-order offer,
 * free delivery threshold. Vendor requests arrive as pending offers and are approved or rejected here.
 */
class AdminOfferController extends Controller
{
    public function index(): void
    {
        $status = Helpers::str('status');
        $kind = Helpers::str('kind');
        $q = mb_substr(Helpers::str('q'), 0, 60);
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status, Promo::STATUSES, true)) {
            $where[] = 'o.status = :st';
            $params['st'] = $status;
        }
        if (in_array($kind, Promo::KINDS, true)) {
            $where[] = 'o.kind = :k';
            $params['k'] = $kind;
        }
        if ($q !== '') {
            $where[] = '(o.title_en LIKE :q OR o.title_bn LIKE :q2)';
            $params['q'] = $params['q2'] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $w = implode(' AND ', $where);
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM offers o WHERE {$w}", $params), 25);
        $this->view('admin/offers/index', [
            'title'   => __('offers'),
            'status'  => $status,
            'kind'    => $kind,
            'q'       => $q,
            'pending' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM offers WHERE status = 'pending'"),
            'offers'  => $this->db->fetchAll(
                "SELECT o.*, v.shop_name, v.shop_name_bn FROM offers o LEFT JOIN vendors v ON v.id = o.vendor_id
                  WHERE {$w} ORDER BY o.status = 'pending' DESC, o.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
                $params
            ),
            'pager'   => $pager,
        ], 'admin');
    }

    public function create(): void
    {
        $kind = in_array(Helpers::str('kind'), Promo::KINDS, true) ? Helpers::str('kind') : 'price';
        $this->form(null, ['kind' => $kind, 'type' => 'percent', 'applies_to' => 'all', 'funded_by' => 'platform', 'vendor_share_percent' => '50',
                           'buy_qty' => '1', 'get_qty' => '1', 'priority' => '0', 'is_active' => '1', 'min_order_amount' => $kind === 'free_shipping' ? '999' : '0']);
    }

    public function edit(string $id): void
    {
        $o = $this->offer((int) $id);
        $this->form($o, OfferForm::input($o));
    }

    private function offer(int $id): array
    {
        $o = $this->db->fetch(
            'SELECT o.*, v.shop_name, v.shop_name_bn, v.user_id AS vendor_user_id, rq.name AS requested_name, rv.name AS reviewed_name
               FROM offers o LEFT JOIN vendors v ON v.id = o.vendor_id
          LEFT JOIN users rq ON rq.id = o.requested_by LEFT JOIN users rv ON rv.id = o.reviewed_by
              WHERE o.id = :id',
            ['id' => $id]
        );
        if (!$o) {
            $this->notFound();
        }
        return $o;
    }

    private function form(?array $offer, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/offers/form', [
            'title'      => __($offer ? 'edit_offer' : 'add_offer'),
            'offer'      => $offer,
            'input'      => is_array($old) ? $old : $defaults,
            'errors'     => $_SESSION['_errors'] ?? [],
            'categories' => Category::tree(),
            'brands'     => Brand::options(),
            'vendors'    => Vendor::options(),
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        [$data, $banner] = $this->validated(null, 'admin/offers/create');
        $data['slug'] = Promo::slugFor($data['title_en']);
        $data['status'] = 'approved';
        $data['reviewed_by'] = Auth::id();
        $data['reviewed_at'] = date('Y-m-d H:i:s');
        if ($banner) {
            $data['banner'] = $banner;
        }
        $id = $this->db->insert('offers', $data);
        Audit::log('offers.create', 'offers', $id, null, $data);
        flash('success', __('offer_saved'));
        redirect('admin/offers/' . $id . '/edit');
    }

    public function update(string $id): void
    {
        $o = $this->offer((int) $id);
        [$data, $banner] = $this->validated((int) $id, 'admin/offers/' . (int) $id . '/edit');
        if ($banner) {
            Upload::delete($o['banner']);
            $data['banner'] = $banner;
        } elseif (!empty($_POST['remove_banner'])) {
            Upload::delete($o['banner']);
            $data['banner'] = null;
        }
        if ($data['title_en'] !== $o['title_en']) {
            $data['slug'] = Promo::slugFor($data['title_en'], (int) $id);
        }
        $this->db->update('offers', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('offers.update', 'offers', (int) $id, $o, $data);
        flash('success', __('offer_saved'));
        redirect('admin/offers' . ($o['status'] === 'pending' ? '/' . (int) $id . '/edit' : ''));
    }

    /** Approve / reject a vendor request (funding may be changed in the same form). */
    public function review(string $id): void
    {
        $o = $this->offer((int) $id);
        $approve = ($_POST['decision'] ?? '') === 'approve';
        $note = mb_substr(trim((string) ($_POST['review_note'] ?? '')), 0, 255);
        if (!$approve && $note === '') {
            flash('error', __('reject_reason_required'));
            redirect('admin/offers/' . (int) $id . '/edit');
        }
        $data = [
            'status'      => $approve ? 'approved' : 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_note' => $note !== '' ? $note : null,
        ];
        $funded = (string) ($_POST['funded_by'] ?? '');
        if ($approve && in_array($funded, Promo::FUNDERS, true) && $o['kind'] !== 'free_shipping') {
            $share = (float) ($_POST['vendor_share_percent'] ?? 50);
            $data['funded_by'] = $funded;
            $data['vendor_share_percent'] = $funded === 'shared' ? max(1, min(99, $share)) : ($funded === 'vendor' ? 100 : 0);
        }
        $this->db->update('offers', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('offers.' . ($approve ? 'approve' : 'reject'), 'offers', (int) $id, ['status' => $o['status']], $data);
        if ($o['vendor_user_id'] && $o['requested_by']) {
            Notification::send((int) $o['vendor_user_id'], 'vendor',
                __($approve ? 'notif_offer_approved' : 'notif_offer_rejected', ['title' => $o['title_en']]),
                $note !== '' ? $note : null, url('vendor-panel/offers'));
        }
        flash('success', __($approve ? 'offer_approved' : 'offer_rejected'));
        redirect('admin/offers?status=pending');
    }

    public function toggle(string $id): void
    {
        $o = $this->offer((int) $id);
        $this->db->update('offers', ['is_active' => (int) $o['is_active'] ? 0 : 1], 'id = :id', ['id' => (int) $id]);
        Audit::log('offers.toggle', 'offers', (int) $id, ['is_active' => $o['is_active']], ['is_active' => (int) $o['is_active'] ? 0 : 1]);
        flash('success', __('offer_saved'));
        back();
    }

    public function destroy(string $id): void
    {
        $o = $this->offer((int) $id);
        $this->db->query('DELETE FROM offers WHERE id = :id', ['id' => (int) $id]);
        Upload::delete($o['banner']);
        Audit::log('offers.delete', 'offers', (int) $id, $o, null);
        flash('success', __('offer_deleted'));
        redirect('admin/offers');
    }

    /** @return array{0: array, 1: ?string} [data, new banner path] */
    private function validated(?int $id, string $formPath): array
    {
        $in = $this->postData(['applies_categorys', 'applies_brands', 'applies_vendors']);
        [$data, $errors] = OfferForm::parse($in);
        $banner = null;
        $files = Upload::files('banner');
        if ($files && ($e = Upload::validateImage($files[0]))) {
            $errors['banner'] = $e;
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        if ($files) {
            $banner = Upload::storeImage($files[0], 'offers');
        }
        return [$data, $banner];
    }
}
