<?php
declare(strict_types=1);

/**
 * Vendor payouts (vendor_payouts).
 *   requested -> approved -> paid        (money left the wallet at request time)
 *   requested / approved -> rejected     (money goes back to the wallet)
 * One open (requested / approved) payout per vendor at a time.
 */
final class Payout
{
    public const METHODS = ['bank', 'bkash', 'nagad', 'rocket'];
    public const BADGES = ['requested' => 'warning', 'approved' => 'info', 'paid' => 'success', 'rejected' => 'danger'];

    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function minAmount(): float
    {
        return (float) setting('payout_min_amount', 500);
    }

    /** Payout account text from the vendor's saved bank / mobile banking details. */
    public static function accountFor(array $v, string $method): ?string
    {
        if ($method === 'bank') {
            if (!$v['bank_account_no']) {
                return null;
            }
            return trim(implode(', ', array_filter([$v['bank_name'], $v['bank_branch'], $v['bank_account_name'], $v['bank_account_no'], $v['bank_routing_no'] ? 'Routing ' . $v['bank_routing_no'] : null])));
        }
        return ($v['mobile_banking_provider'] === $method && $v['mobile_banking_no']) ? $v['mobile_banking_no'] : null;
    }

    /** @throws OrderException */
    public static function request(array $vendor, float $amount, string $method, ?string $note): int
    {
        $vendorId = (int) $vendor['id'];
        Wallet::releaseDue($vendorId);
        $payoutId = self::db()->transaction(static function (Database $db) use ($vendor, $vendorId, $amount, $method, $note): int {
            $w = Wallet::forVendor($vendorId, true);
            if ($vendor['status'] !== 'approved') {
                throw new OrderException(__('payout_vendor_not_active'));
            }
            if ($db->fetchColumn("SELECT 1 FROM vendor_payouts WHERE vendor_id = :v AND status IN ('requested','approved') LIMIT 1", ['v' => $vendorId])) {
                throw new OrderException(__('payout_open_exists'));
            }
            $amount = round($amount, 2);
            if ($amount < self::minAmount()) {
                throw new OrderException(__('payout_min', ['amount' => money(self::minAmount())]), 'amount');
            }
            if ($amount > (float) $w['balance']) {
                throw new OrderException(__('payout_over_balance', ['amount' => money($w['balance'])]), 'amount');
            }
            $account = self::accountFor($vendor, $method);
            if ($account === null) {
                throw new OrderException(__('payout_no_account'), 'method');
            }
            $id = $db->insert('vendor_payouts', [
                'vendor_id'       => $vendorId,
                'amount'          => $amount,
                'method'          => $method,
                'account_details' => mb_substr($account, 0, 255),
                'status'          => 'requested',
                'vendor_note'     => $note,
            ]);
            Wallet::debitPayout($vendorId, $amount, $id, Auth::id());
            Audit::log('vendor_payouts.request', 'vendor_payouts', $id, null, ['amount' => $amount, 'method' => $method]);
            return $id;
        });
        Notification::toStaff(['vendor_payouts.approve'], 'payout', __('notif_payout_request', ['shop' => $vendor['shop_name'], 'amount' => money($amount)]), null, url('admin/payouts'));
        return $payoutId;
    }

    /**
     * Staff decision. $action: approve | reject | paid
     * @throws OrderException
     */
    public static function decide(int $id, string $action, ?string $reference, ?string $note, int $by): array
    {
        $p = self::db()->transaction(static function (Database $db) use ($id, $action, $reference, $note, $by): array {
            $p = $db->fetch('SELECT * FROM vendor_payouts WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$p) {
                throw new OrderException(__('payout_not_found'));
            }
            $now = date('Y-m-d H:i:s');
            $open = in_array($p['status'], ['requested', 'approved'], true);
            if ($action === 'approve' && $p['status'] === 'requested') {
                $db->update('vendor_payouts', ['status' => 'approved', 'admin_note' => $note, 'processed_by' => $by], 'id = :id', ['id' => $id]);
            } elseif ($action === 'reject' && $open) {
                Wallet::forVendor((int) $p['vendor_id'], true);
                $db->update('vendor_payouts', ['status' => 'rejected', 'admin_note' => $note, 'processed_by' => $by, 'processed_at' => $now], 'id = :id', ['id' => $id]);
                Wallet::creditPayoutBack((int) $p['vendor_id'], (float) $p['amount'], $id, $by);
            } elseif ($action === 'paid' && $open) {
                if ($reference === null || $reference === '') {
                    throw new OrderException(__('payout_ref_required'));
                }
                $db->update('vendor_payouts', ['status' => 'paid', 'reference_no' => $reference, 'admin_note' => $note, 'processed_by' => $by, 'processed_at' => $now], 'id = :id', ['id' => $id]);
                $db->query('UPDATE vendor_wallets SET total_withdrawn = total_withdrawn + :a WHERE vendor_id = :v', ['a' => $p['amount'], 'v' => (int) $p['vendor_id']]);
            } else {
                throw new OrderException(__('payout_bad_action'));
            }
            Audit::log('vendor_payouts.' . $action, 'vendor_payouts', $id, ['status' => $p['status']], ['status' => $action === 'paid' ? 'paid' : ($action === 'approve' ? 'approved' : 'rejected'), 'reference' => $reference]);
            return $db->fetch('SELECT * FROM vendor_payouts WHERE id = :id', ['id' => $id]);
        });
        $userId = self::db()->fetchColumn('SELECT user_id FROM vendors WHERE id = :v', ['v' => (int) $p['vendor_id']]);
        if ($userId) {
            Notification::send((int) $userId, 'payout', __('notif_payout_' . $p['status'], ['amount' => money($p['amount'])]), $note, url('vendor-panel/payouts'), 'payout_update');
        }
        return $p;
    }
}
