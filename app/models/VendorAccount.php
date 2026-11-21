<?php
declare(strict_types=1);

/**
 * Vendor sign-up, documents, status changes (approve / reject / suspend / reactivate).
 * Documents (trade licence, NID, drug licence, BSTI, TIN - which ones depends on the vendor type) are private files in storage/private/vendor-docs.
 */
final class VendorAccount
{
    public const MFS = ['bkash', 'nagad', 'rocket', 'upay'];
    /** Documents a seller may upload (which ones are required depends on the vendor type - see VendorType). */
    public const DOCS = VendorType::DOCS;
    public const STATUSES = ['pending', 'approved', 'suspended', 'rejected'];
    public const BADGES = ['pending' => 'warning', 'approved' => 'success', 'suspended' => 'secondary', 'rejected' => 'danger'];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function docs(array $v): array
    {
        $d = json_decode((string) ($v['documents'] ?? ''), true);
        return is_array($d) ? $d : [];
    }

    /** [division, district, upazila] rows for a vendor's pickup location (any may be null). */
    public static function locationChain(?int $locationId): array
    {
        $chain = ['division' => null, 'district' => null, 'upazila' => null];
        $guard = 0;
        while ($locationId && $guard++ < 4) {
            $l = self::db()->fetch('SELECT id, parent_id, type, name_en, name_bn FROM locations WHERE id = :id', ['id' => $locationId]);
            if (!$l) {
                break;
            }
            if (array_key_exists($l['type'], $chain)) {
                $chain[$l['type']] = $l;
            }
            $locationId = $l['parent_id'] !== null ? (int) $l['parent_id'] : 0;
        }
        return $chain;
    }

    public static function areaText(?int $locationId): string
    {
        $parts = [];
        foreach (array_reverse(self::locationChain($locationId)) as $l) {
            if ($l) {
                $parts[] = tr($l['name_bn'], $l['name_en']);
            }
        }
        return implode(', ', $parts);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = mb_substr(Helpers::slugify($name), 0, 160);
        $slug = $base;
        $i = 2;
        while (self::db()->fetchColumn('SELECT 1 FROM vendors WHERE slug = :s', ['s' => $slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Create user + vendor + wallet in one transaction. $files = doc key => stored file info (already on disk).
     * @return int vendor id
     */
    public static function register(array $in, array $files, int $locationId): int
    {
        $auto = (bool) setting('vendor_auto_approve', false);
        return self::db()->transaction(static function (Database $db) use ($in, $files, $locationId, $auto): int {
            $roleId = $db->fetchColumn("SELECT id FROM roles WHERE slug = 'vendor'");
            $userId = $db->insert('users', [
                'role_id'       => $roleId ? (int) $roleId : null,
                'user_type'     => 'vendor',
                'name'          => $in['contact_person'],
                'phone'         => Auth::normalizePhone($in['phone']),
                'email'         => ($in['email'] ?? '') !== '' ? strtolower($in['email']) : null,
                'password_hash' => password_hash($in['password'], PASSWORD_DEFAULT),
                'lang'          => lang(),
                'status'        => 'active',
            ]);
            $vendorId = $db->insert('vendors', [
                'user_id'                 => $userId,
                'shop_name'               => $in['shop_name'],
                'shop_name_bn'            => ($in['shop_name_bn'] ?? '') ?: null,
                'slug'                    => self::uniqueSlug($in['shop_name']),
                'vendor_type'             => $in['vendor_type'],      // type slug (kept for older reports)
                'vendor_type_id'          => (int) $in['vendor_type_id'],
                'description'             => ($in['description'] ?? '') ?: null,
                'contact_person'          => $in['contact_person'],
                'phone'                   => Auth::normalizePhone($in['phone']),
                'email'                   => ($in['email'] ?? '') !== '' ? strtolower($in['email']) : null,
                'address'                 => $in['pickup_address'],
                'pickup_phone'            => ($in['pickup_phone'] ?? '') !== '' ? Auth::normalizePhone($in['pickup_phone']) : null,
                'location_id'             => $locationId,
                'trade_license_no'        => ($in['trade_license_no'] ?? '') ?: null,
                'drug_license_no'         => ($in['drug_license_no'] ?? '') ?: null,
                'drug_license_expiry'     => ($in['drug_license_expiry'] ?? '') ?: null,
                'tin_no'                  => ($in['tin_no'] ?? '') ?: null,
                'nid_no'                  => ($in['nid_no'] ?? '') ?: null,
                'documents'               => json_encode($files),
                'bank_name'               => ($in['bank_name'] ?? '') ?: null,
                'bank_branch'             => ($in['bank_branch'] ?? '') ?: null,
                'bank_account_name'       => ($in['bank_account_name'] ?? '') ?: null,
                'bank_account_no'         => ($in['bank_account_no'] ?? '') ?: null,
                'bank_routing_no'         => ($in['bank_routing_no'] ?? '') ?: null,
                'mobile_banking_provider' => ($in['mobile_banking_provider'] ?? '') ?: null,
                'mobile_banking_no'       => ($in['mobile_banking_no'] ?? '') !== '' ? Auth::normalizePhone($in['mobile_banking_no']) : null,
                'status'                  => $auto ? 'approved' : 'pending',
                'approved_at'             => $auto ? date('Y-m-d H:i:s') : null,
            ]);
            $db->insert('vendor_wallets', ['vendor_id' => $vendorId]);
            Audit::log('vendors.register', 'vendors', $vendorId, null, ['shop_name' => $in['shop_name'], 'status' => $auto ? 'approved' : 'pending'], $userId);
            return $vendorId;
        });
    }

    /**
     * approve | reject | suspend | reactivate
     * @throws OrderException
     */
    public static function changeStatus(int $vendorId, string $action, ?string $reason, int $by): array
    {
        $v = self::db()->transaction(static function (Database $db) use ($vendorId, $action, $reason, $by): array {
            $v = $db->fetch('SELECT * FROM vendors WHERE id = :id AND deleted_at IS NULL FOR UPDATE', ['id' => $vendorId]);
            if (!$v) {
                throw new OrderException(__('vendor_not_found'));
            }
            $from = $v['status'];
            $map = [
                'approve'    => [['pending', 'rejected'], 'approved'],
                'reject'     => [['pending'], 'rejected'],
                'suspend'    => [['approved'], 'suspended'],
                'reactivate' => [['suspended'], 'approved'],
            ];
            if (!isset($map[$action]) || !in_array($from, $map[$action][0], true)) {
                throw new OrderException(__('vendor_bad_action'));
            }
            if (in_array($action, ['reject', 'suspend'], true) && ($reason === null || trim($reason) === '')) {
                throw new OrderException(__('vendor_reason_required'));
            }
            $to = $map[$action][1];
            $set = ['status' => $to];
            if ($to === 'approved') {
                $set += ['approved_by' => $by, 'approved_at' => date('Y-m-d H:i:s'), 'rejection_reason' => null, 'suspension_reason' => null];
            } elseif ($to === 'rejected') {
                $set['rejection_reason'] = mb_substr((string) $reason, 0, 255);
            } elseif ($to === 'suspended') {
                $set['suspension_reason'] = mb_substr((string) $reason, 0, 255);
            }
            $db->update('vendors', $set, 'id = :id', ['id' => $vendorId]);
            Wallet::forVendor($vendorId);
            Audit::log('vendors.' . $action, 'vendors', $vendorId, ['status' => $from], ['status' => $to, 'reason' => $reason]);
            return $db->fetch('SELECT * FROM vendors WHERE id = :id', ['id' => $vendorId]);
        });
        Notification::send((int) $v['user_id'], 'vendor', __('notif_vendor_' . $v['status']), $reason, url('vendor-panel'), 'vendor_status');
        return $v;
    }

    /* ---------------- delete (soft) / restore ---------------- */

    /**
     * Why this vendor cannot be deleted yet (empty = OK): orders still running, money still owed, payouts waiting.
     * @return string[] translated reasons
     */
    public static function deleteBlockers(int $vendorId): array
    {
        $db = self::db();
        $out = [];
        $open = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM order_vendor_splits WHERE vendor_id = :v AND status NOT IN ('delivered','cancelled','returned','failed')",
            ['v' => $vendorId]
        );
        if ($open) {
            $out[] = __('vdel_open_orders', ['n' => num($open)]);
        }
        $w = $db->fetch('SELECT balance, pending_balance FROM vendor_wallets WHERE vendor_id = :v', ['v' => $vendorId]);
        if ($w && ((float) $w['balance'] != 0.0 || (float) $w['pending_balance'] != 0.0)) {
            $out[] = __('vdel_wallet', ['amount' => money((float) $w['balance'] + (float) $w['pending_balance'])]);
        }
        $payouts = (int) $db->fetchColumn("SELECT COUNT(*) FROM vendor_payouts WHERE vendor_id = :v AND status IN ('requested','approved')", ['v' => $vendorId]);
        if ($payouts) {
            $out[] = __('vdel_payouts', ['n' => num($payouts)]);
        }
        return $out;
    }

    /**
     * Soft delete: the shop disappears from the store and the admin lists, its products are hidden, its offers stopped,
     * its login is switched off. Orders, commissions, payouts and accounting keep their history (nothing is erased).
     * @throws OrderException
     */
    public static function softDelete(int $vendorId, string $reason, int $by): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new OrderException(__('vendor_reason_required'));
        }
        if ($blockers = self::deleteBlockers($vendorId)) {
            throw new OrderException(implode(' ', $blockers));
        }
        $v = self::db()->transaction(static function (Database $db) use ($vendorId, $reason, $by): array {
            $v = $db->fetch('SELECT * FROM vendors WHERE id = :id AND deleted_at IS NULL FOR UPDATE', ['id' => $vendorId]);
            if (!$v) {
                throw new OrderException(__('vendor_not_found'));
            }
            $now = date('Y-m-d H:i:s');
            $db->update('vendors', ['deleted_at' => $now, 'status' => 'suspended', 'suspension_reason' => mb_substr(__('vdel_deleted_prefix') . ' ' . $reason, 0, 255), 'is_featured' => 0], 'id = :id', ['id' => $vendorId]);
            // products deleted with the shop share its deleted_at, so a restore can bring back exactly these
            $products = $db->query('UPDATE products SET deleted_at = :t WHERE vendor_id = :v AND deleted_at IS NULL', ['t' => $now, 'v' => $vendorId])->rowCount();
            $db->query('DELETE c FROM carts c JOIN products p ON p.id = c.product_id WHERE p.vendor_id = :v', ['v' => $vendorId]);
            $db->query('UPDATE offers SET is_active = 0 WHERE vendor_id = :v', ['v' => $vendorId]);
            $db->update('users', ['status' => 'inactive'], 'id = :id', ['id' => (int) $v['user_id']]); // logged out on the next click
            Audit::log('vendors.delete', 'vendors', $vendorId, ['status' => $v['status']], ['deleted_at' => $now, 'reason' => $reason, 'products_hidden' => $products], $by);
            return $v;
        });
        Notifier::contact((string) $v['phone'], (string) $v['email'], 'vendor_status', __('vdel_notify_title', ['shop' => $v['shop_name']]), $reason);
    }

    /** Undo a soft delete: shop comes back SUSPENDED (review it, then Reactivate), login and its products return. */
    public static function restore(int $vendorId, int $by): void
    {
        self::db()->transaction(static function (Database $db) use ($vendorId, $by): void {
            $v = $db->fetch('SELECT * FROM vendors WHERE id = :id AND deleted_at IS NOT NULL FOR UPDATE', ['id' => $vendorId]);
            if (!$v) {
                throw new OrderException(__('vendor_not_found'));
            }
            $db->query('UPDATE products SET deleted_at = NULL WHERE vendor_id = :v AND deleted_at = :t', ['v' => $vendorId, 't' => $v['deleted_at']]);
            $db->update('vendors', ['deleted_at' => null, 'status' => 'suspended'], 'id = :id', ['id' => $vendorId]);
            $db->update('users', ['status' => 'active'], 'id = :id', ['id' => (int) $v['user_id']]);
            Audit::log('vendors.restore', 'vendors', $vendorId, ['deleted_at' => $v['deleted_at']], ['status' => 'suspended'], $by);
        });
    }

    /** Sales / commission summary of one vendor (all time). */
    public static function stats(int $vendorId): array
    {
        return self::db()->fetch(
            "SELECT COUNT(*) AS orders,
                    SUM(status = 'delivered') AS delivered,
                    SUM(status = 'cancelled') AS cancelled,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN subtotal - discount_total END), 0) AS sales,
                    COALESCE(SUM(CASE WHEN settlement_status IN ('pending','eligible','settled') THEN commission_amount END), 0) AS commission,
                    COALESCE(SUM(CASE WHEN settlement_status IN ('pending','eligible','settled') THEN vendor_earning END), 0) AS earning
               FROM order_vendor_splits WHERE vendor_id = :v",
            ['v' => $vendorId]
        ) ?: [];
    }
}
