<?php
declare(strict_types=1);

/**
 * Vendor offer requests: price discounts or buy-X-get-Y on the vendor's own products.
 * A request is pending until an admin approves it (the admin may change who bears the cost).
 * Pending / rejected requests can be edited (a rejected one is sent again); approved ones can be paused or deleted.
 */
class VendorOfferController extends VendorBaseController
{
    public function index(): void
    {
        $v = $this->activeVendor();
        $vid = (int) $v['id'];
        $this->view('vendor/offers', [
            'title'  => __('my_offers'),
            'vendor' => $v,
            'offers' => $this->db->fetchAll('SELECT * FROM offers WHERE vendor_id = :v ORDER BY status = \'pending\' DESC, id DESC', ['v' => $vid]),
            'flash'  => $this->db->fetchAll(
                "SELECT fi.sale_price, fi.sold_qty, fi.quantity_limit, p.name_en, p.name_bn, p.price, pv.name_en AS variant_en, pv.price AS v_price,
                        fs.title_en, fs.title_bn, fs.starts_at, fs.ends_at, fs.funded_by, fs.vendor_share_percent
                   FROM flash_sale_items fi JOIN flash_sales fs ON fs.id = fi.flash_sale_id JOIN products p ON p.id = fi.product_id
              LEFT JOIN product_variants pv ON pv.id = fi.variant_id
                  WHERE p.vendor_id = :v AND fs.is_active = 1 AND fs.ends_at > NOW() ORDER BY fs.starts_at",
                ['v' => $vid]
            ),
            'platform' => array_values(array_filter(
                array_merge(...array_values(Promo::active())),
                static fn($o) => $o['vendor_id'] === null && $o['kind'] !== 'free_shipping'
                    && ($o['applies_to'] !== 'vendor' || in_array($vid, $o['ids'], true))
            )),
        ], 'vendor');
    }

    public function create(): void
    {
        $v = $this->activeVendor();
        $this->requireApproved($v);
        $kind = Helpers::str('kind') === 'bogo' ? 'bogo' : 'price';
        $this->form($v, null, ['kind' => $kind, 'type' => 'percent', 'applies_to' => 'vendor', 'funded_by' => 'vendor', 'vendor_share_percent' => '50',
                               'buy_qty' => '1', 'get_qty' => '1', 'is_active' => '1']);
    }

    public function edit(string $id): void
    {
        $v = $this->activeVendor();
        $o = $this->own($v, (int) $id);
        if ($o['status'] === 'approved') {
            flash('warning', __('offer_approved_locked'));
            redirect('vendor-panel/offers');
        }
        $this->form($v, $o, OfferForm::input($o));
    }

    private function own(array $v, int $id): array
    {
        $o = $this->db->fetch('SELECT * FROM offers WHERE id = :id AND vendor_id = :v', ['id' => $id, 'v' => (int) $v['id']]);
        if (!$o) {
            $this->notFound();
        }
        return $o;
    }

    private function form(array $v, ?array $offer, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('vendor/offer_form', [
            'title'  => __($offer ? 'edit_offer_request' : 'request_offer'),
            'vendor' => $v,
            'offer'  => $offer,
            'input'  => is_array($old) ? $old : $defaults,
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'vendor');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        $v = $this->activeVendor();
        $this->requireApproved($v);
        $data = $this->validated($v, 'vendor-panel/offers/create');
        $data += [
            'slug'         => Promo::slugFor($data['title_en']),
            'status'       => 'pending',
            'requested_by' => Auth::id(),
            'priority'     => 0,
        ];
        $id = $this->db->insert('offers', $data);
        Audit::log('offers.request', 'offers', $id, null, $data);
        $this->notifyStaff($v, $data['title_en'], $id);
        flash('success', __('offer_request_sent'));
        redirect('vendor-panel/offers');
    }

    public function update(string $id): void
    {
        $v = $this->activeVendor();
        $this->requireApproved($v);
        $o = $this->own($v, (int) $id);
        if ($o['status'] === 'approved') {
            flash('warning', __('offer_approved_locked'));
            redirect('vendor-panel/offers');
        }
        $data = $this->validated($v, 'vendor-panel/offers/' . (int) $id . '/edit');
        $data += ['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null];
        if ($data['title_en'] !== $o['title_en']) {
            $data['slug'] = Promo::slugFor($data['title_en'], (int) $id);
        }
        $this->db->update('offers', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('offers.request_update', 'offers', (int) $id, $o, $data);
        if ($o['status'] === 'rejected') {
            $this->notifyStaff($v, $data['title_en'], (int) $id);
        }
        flash('success', __('offer_request_sent'));
        redirect('vendor-panel/offers');
    }

    /** Pause / resume an approved offer. */
    public function toggle(string $id): void
    {
        $v = $this->activeVendor();
        $this->requireApproved($v);
        $o = $this->own($v, (int) $id);
        $this->db->update('offers', ['is_active' => (int) $o['is_active'] ? 0 : 1], 'id = :id', ['id' => (int) $id]);
        Audit::log('offers.toggle', 'offers', (int) $id, ['is_active' => $o['is_active']], ['is_active' => (int) $o['is_active'] ? 0 : 1]);
        flash('success', __((int) $o['is_active'] ? 'offer_paused_msg' : 'offer_resumed_msg'));
        redirect('vendor-panel/offers');
    }

    public function destroy(string $id): void
    {
        $v = $this->activeVendor();
        $o = $this->own($v, (int) $id);
        $this->db->query('DELETE FROM offers WHERE id = :id', ['id' => (int) $id]);
        Audit::log('offers.delete', 'offers', (int) $id, $o, null);
        flash('success', __('offer_deleted'));
        redirect('vendor-panel/offers');
    }

    private function validated(array $v, string $formPath): array
    {
        $in = $this->postData();
        [$data, $errors] = OfferForm::parse($in, (int) $v['id']);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        return $data;
    }

    private function notifyStaff(array $v, string $title, int $offerId): void
    {
        $staff = $this->db->fetchAll(
            "SELECT DISTINCT u.id FROM users u
              WHERE u.status = 'active' AND u.deleted_at IS NULL AND (u.user_type = 'super_admin' OR (u.user_type = 'employee' AND EXISTS (
                    SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
                     WHERE rp.role_id = u.role_id AND p.`key` = 'offers.approve')))
              LIMIT 50"
        );
        foreach ($staff as $s) {
            Notification::send((int) $s['id'], 'vendor', __('notif_offer_request', ['shop' => $v['shop_name'], 'title' => $title]), null, url('admin/offers/' . $offerId . '/edit'));
        }
    }
}
