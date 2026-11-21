<?php
declare(strict_types=1);

/** Admin: vendor list, details, documents, approve / reject / suspend / reactivate, commission rate, wallet adjustment. */
class AdminVendorController extends Controller
{
    public function index(): void
    {
        $status = in_array(Helpers::str('status'), VendorAccount::STATUSES, true) || Helpers::str('status') === 'deleted' ? Helpers::str('status') : '';
        $q = mb_substr(Helpers::str('q'), 0, 100);
        $w = $status === 'deleted' ? 'v.deleted_at IS NOT NULL' : 'v.deleted_at IS NULL';
        $p = [];
        if ($status !== '' && $status !== 'deleted') {
            $w .= ' AND v.status = :s';
            $p['s'] = $status;
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $w .= ' AND (v.shop_name LIKE :q1 OR v.shop_name_bn LIKE :q2 OR v.phone LIKE :q3 OR v.trade_license_no LIKE :q4 OR v.contact_person LIKE :q5)';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM vendors v WHERE {$w}", $p), 25);
        $rows = $this->db->fetchAll(
            "SELECT v.*, w.balance, w.pending_balance,
                    (SELECT COUNT(*) FROM products pr WHERE pr.vendor_id = v.id AND pr.deleted_at IS NULL) AS products,
                    (SELECT COUNT(*) FROM order_vendor_splits s WHERE s.vendor_id = v.id) AS orders
               FROM vendors v LEFT JOIN vendor_wallets w ON w.vendor_id = v.id
              WHERE {$w} ORDER BY v.status = 'pending' DESC, v.id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
            $p
        );
        $counts = array_column($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM vendors WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status');
        $deleted = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM vendors WHERE deleted_at IS NOT NULL');
        $this->view('admin/vendors/index', ['title' => __('vendors_label'), 'rows' => $rows, 'pager' => $pager, 'status' => $status, 'q' => $q, 'counts' => $counts, 'deleted' => $deleted], 'admin');
    }

    private function find(string $id, bool $withDeleted = false): array
    {
        $v = $this->db->fetch('SELECT v.*, u.name AS user_name, u.last_login_at FROM vendors v JOIN users u ON u.id = v.user_id WHERE v.id = :id'
            . ($withDeleted ? '' : ' AND v.deleted_at IS NULL'), ['id' => (int) $id]);
        if (!$v) {
            $this->notFound();
        }
        return $v;
    }

    public function show(string $id): void
    {
        $v = $this->find($id, true);
        $vid = (int) $v['id'];
        Wallet::releaseDue($vid);
        $this->view('admin/vendors/show', [
            'title'    => $v['shop_name'],
            'blockers' => $v['deleted_at'] === null ? VendorAccount::deleteBlockers((int) $v['id']) : [],
            'v'        => $v,
            'docs'     => VendorAccount::docs($v),
            'area'     => VendorAccount::areaText($v['location_id'] !== null ? (int) $v['location_id'] : null),
            'stats'    => VendorAccount::stats($vid),
            'wallet'   => Wallet::forVendor($vid),
            'ledger'   => Wallet::ledger($vid, 15, 0),
            'payouts'  => $this->db->fetchAll('SELECT * FROM vendor_payouts WHERE vendor_id = :v ORDER BY id DESC LIMIT 10', ['v' => $vid]),
            'products' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM products WHERE vendor_id = :v AND deleted_at IS NULL', ['v' => $vid]),
            'recent'   => $this->db->fetchAll(
                'SELECT s.*, o.order_no, o.placed_at FROM order_vendor_splits s JOIN orders o ON o.id = s.order_id WHERE s.vendor_id = :v ORDER BY s.id DESC LIMIT 10',
                ['v' => $vid]
            ),
        ], 'admin');
    }

    public function status(string $id): void
    {
        $v = $this->find($id);
        try {
            $new = VendorAccount::changeStatus((int) $v['id'], Helpers::str('action'), mb_substr(Helpers::str('reason'), 0, 255) ?: null, (int) Auth::id());
            flash('success', __('vendor_status_saved', ['status' => __('vs_' . $new['status'])]));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/vendors/' . (int) $v['id']);
    }

    public function commission(string $id): void
    {
        $v = $this->find($id);
        $rate = Helpers::str('commission_rate');
        if ($rate !== '' && (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $rate) || (float) $rate > 100)) {
            flash('error', __('v_rate'));
            redirect('admin/vendors/' . (int) $v['id']);
        }
        Vendor::updateById((int) $v['id'], ['commission_rate' => $rate === '' ? null : $rate]);
        flash('success', __('commission_saved'));
        redirect('admin/vendors/' . (int) $v['id']);
    }

    /** Soft delete (needs vendors.delete): history is kept, the shop / products / login are switched off. */
    public function destroy(string $id): void
    {
        $v = $this->find($id);
        if (Helpers::str('confirm_name') !== $v['shop_name']) {
            flash('error', __('vdel_confirm_wrong'));
            redirect('admin/vendors/' . (int) $v['id'] . '#delete');
        }
        try {
            VendorAccount::softDelete((int) $v['id'], mb_substr(Helpers::str('reason'), 0, 200), (int) Auth::id());
            flash('success', __('vdel_done', ['shop' => $v['shop_name']]));
            redirect('admin/vendors?status=deleted');
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
            redirect('admin/vendors/' . (int) $v['id'] . '#delete');
        }
    }

    public function restore(string $id): void
    {
        $v = $this->find($id, true);
        try {
            VendorAccount::restore((int) $v['id'], (int) Auth::id());
            flash('success', __('vdel_restored'));
        } catch (OrderException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/vendors/' . (int) $v['id']);
    }

    /** Change the seller's type (documents / rules / commission default follow the type). */
    public function type(string $id): void
    {
        $v = $this->find($id);
        $type = VendorType::get((int) Helpers::str('vendor_type_id'));
        if (!$type) {
            flash('error', __('v_in'));
            redirect('admin/vendors/' . (int) $v['id']);
        }
        Vendor::updateById((int) $v['id'], ['vendor_type_id' => (int) $type['id'], 'vendor_type' => $type['slug']]);
        Audit::log('vendors.type', 'vendors', (int) $v['id'], ['vendor_type_id' => $v['vendor_type_id']], ['vendor_type_id' => (int) $type['id']]);
        $blocked = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM products WHERE vendor_id = :v AND deleted_at IS NULL AND requires_prescription = 1',
            ['v' => (int) $v['id']]
        );
        flash('success', __('saved'));
        if ($blocked && !(int) $type['can_sell_rx']) {
            flash('warning', __('vt_rx_products_warning', ['n' => num($blocked)]));
        }
        redirect('admin/vendors/' . (int) $v['id']);
    }

    public function adjust(string $id): void
    {
        $v = $this->find($id);
        $amount = Helpers::str('amount');
        $note = trim(Helpers::str('note'));
        if (!preg_match('/^-?\d{1,9}(\.\d{1,2})?$/', $amount) || (float) $amount == 0.0 || $note === '') {
            flash('error', __('v_adjustment'));
            redirect('admin/vendors/' . (int) $v['id']);
        }
        Wallet::adjust((int) $v['id'], (float) $amount, mb_substr($note, 0, 255), (int) Auth::id());
        Audit::log('vendors.wallet_adjust', 'vendors', (int) $v['id'], null, ['amount' => $amount, 'note' => $note]);
        flash('success', __('wallet_adjusted'));
        redirect('admin/vendors/' . (int) $v['id']);
    }

    public function document(string $id, string $doc): void
    {
        $v = $this->find($id, true);
        $file = VendorAccount::docs($v)[$doc] ?? null;
        if (!$file || !in_array($doc, VendorAccount::DOCS, true)) {
            $this->notFound();
        }
        Audit::log('vendors.view_document', 'vendors', (int) $v['id'], null, ['doc' => $doc]);
        PrivateFile::stream($file['path'], $v['slug'] . '-' . $doc);
    }
}
