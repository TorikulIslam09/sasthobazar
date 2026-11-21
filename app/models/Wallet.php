<?php
declare(strict_types=1);

/**
 * Vendor wallet + ledger (vendor_wallets, vendor_wallet_transactions, commissions).
 *
 *   pending_balance  earnings of delivered sub-orders still inside the hold period (payout_hold_days)
 *   balance          withdrawable; payout requests take money out of it immediately
 *   ledger           every change, balance_after = balance + pending_balance after the entry
 *
 * Entries on delivery of a vendor sub-order:  order_earning +sales, commission -commission  (into pending)
 * Return after delivery:                       refund -sales,       commission +commission
 * Hold period over:                            release (pending -> balance)
 * All writes lock the wallet row (SELECT ... FOR UPDATE) inside the caller's transaction.
 */
final class Wallet
{
    private static function db(): Database
    {
        return Database::getInstance();
    }

    /** Wallet row, created on first use; locked when $lock. */
    public static function forVendor(int $vendorId, bool $lock = false): array
    {
        $db = self::db();
        $db->query('INSERT IGNORE INTO vendor_wallets (vendor_id) VALUES (:v)', ['v' => $vendorId]);
        return $db->fetch('SELECT * FROM vendor_wallets WHERE vendor_id = :v' . ($lock ? ' FOR UPDATE' : ''), ['v' => $vendorId]);
    }

    /**
     * Change the wallet and write one ledger line.
     * $toPending true: the amount moves pending_balance, else balance.
     */
    private static function entry(int $vendorId, string $type, float $amount, bool $toPending, string $refType, ?int $refId, ?string $note, ?int $by): void
    {
        if (abs($amount) < 0.005) {
            return;
        }
        $db = self::db();
        $w = self::forVendor($vendorId, true);
        $col = $toPending ? 'pending_balance' : 'balance';
        $db->query("UPDATE vendor_wallets SET {$col} = {$col} + :a WHERE id = :id", ['a' => (string) round($amount, 2), 'id' => (int) $w['id']]);
        $after = round((float) $w['balance'] + (float) $w['pending_balance'] + $amount, 2);
        $db->insert('vendor_wallet_transactions', [
            'wallet_id'      => (int) $w['id'],
            'vendor_id'      => $vendorId,
            'type'           => $type,
            'amount'         => round($amount, 2),
            'balance_after'  => $after,
            'reference_type' => $refType,
            'reference_id'   => $refId,
            'note'           => $note !== null ? mb_substr($note, 0, 255) : null,
            'created_by'     => $by,
        ]);
    }

    /**
     * Sub-order delivered: book commission per item and the vendor's earning (into pending for the hold period).
     * Call inside the order transaction (split row locked).
     */
    public static function settleSplit(array $split, string $orderNo, ?int $by): void
    {
        if ($split['vendor_id'] === null || in_array($split['settlement_status'], ['pending', 'eligible', 'settled'], true)) {
            return;
        }
        $db = self::db();
        $vendorId = (int) $split['vendor_id'];
        $items = $db->fetchAll("SELECT * FROM order_items WHERE split_id = :s AND status <> 'cancelled' FOR UPDATE", ['s' => (int) $split['id']]);
        $sales = 0.0;
        $commission = 0.0;
        $vat = 0.0;
        foreach ($items as $it) {
            $base = Commission::itemBase($it);
            $vat += Commission::itemVatOnTop($it);
            $rate = (float) $it['commission_rate'];
            $amount = round($base * $rate / 100, 2);
            $sales += $base;
            $commission += $amount;
            $db->query(
                "INSERT INTO commissions (order_id, split_id, order_item_id, vendor_id, rule_scope, base_amount, rate_type, rate, amount, status)
                 VALUES (:o, :s, :i, :v, :sc, :b, 'percent', :r, :a, 'earned')
                 ON DUPLICATE KEY UPDATE base_amount = VALUES(base_amount), rate = VALUES(rate), amount = VALUES(amount), status = 'earned'",
                ['o' => (int) $it['order_id'], 's' => (int) $split['id'], 'i' => (int) $it['id'], 'v' => $vendorId,
                 'sc' => $it['commission_scope'] ?? null, 'b' => (string) $base, 'r' => (string) $rate, 'a' => (string) $amount]
            );
            $db->update('order_items', ['commission_amount' => $amount], 'id = :id', ['id' => (int) $it['id']]);
        }
        $sales = round($sales, 2);
        $commission = round($commission, 2);
        $vat = round($vat, 2);
        $earning = round($sales - $commission + $vat, 2);
        $holdDays = max(0, (int) setting('payout_hold_days', 7));
        $db->update('order_vendor_splits', [
            'commission_amount' => $commission,
            'vendor_earning'    => $earning,
            'settlement_status' => 'pending',
            'release_at'        => date('Y-m-d H:i:s', time() + $holdDays * 86400),
        ], 'id = :id', ['id' => (int) $split['id']]);
        self::entry($vendorId, 'order_earning', $sales, true, 'split', (int) $split['id'], __('ledger_sale', ['no' => $split['sub_order_no']]), $by);
        self::entry($vendorId, 'commission', -$commission, true, 'split', (int) $split['id'], __('ledger_commission', ['no' => $split['sub_order_no']]), $by);
        if ($vat > 0) {
            self::entry($vendorId, 'order_earning', $vat, true, 'split', (int) $split['id'], __('ledger_vat', ['no' => $split['sub_order_no']]), $by);
        }
        $db->query('UPDATE vendor_wallets SET total_earned = total_earned + :e WHERE vendor_id = :v', ['e' => (string) $earning, 'v' => $vendorId]);
        self::recalcOrderCommission((int) $split['order_id']);
    }

    /**
     * Items returned after delivery: take back the vendor's share, give back the commission.
     * $itemsBefore = order_items rows BEFORE returned_qty was increased; $qtys = item id => returned quantity.
     */
    public static function reverseReturn(array $split, array $itemsBefore, array $qtys, ?int $by): void
    {
        if ($split['vendor_id'] === null || !in_array($split['settlement_status'], ['pending', 'eligible', 'settled'], true)) {
            return; // nothing booked yet (not delivered) or in-house
        }
        $db = self::db();
        $vendorId = (int) $split['vendor_id'];
        $sales = 0.0;
        $commission = 0.0;
        foreach ($itemsBefore as $it) {
            $qty = (int) ($qtys[(int) $it['id']] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $unit = Commission::unitBase($it);
            $value = round($unit * $qty, 2);
            $comm = round($value * (float) $it['commission_rate'] / 100, 2);
            if (!(int) ($it['vat_in_price'] ?? 1) && (float) ($it['vat_amount'] ?? 0) > 0) {
                $value = round($value + (float) $it['vat_amount'] * $qty / max(1, (int) $it['quantity']), 2); // VAT on top goes back too
            }
            $sales += $value;
            $commission += $comm;
            $after = $it;
            $after['returned_qty'] = (int) $it['returned_qty'] + $qty;
            $base = Commission::itemBase($after);
            $db->query(
                "UPDATE commissions SET base_amount = :b, amount = :a, status = :st WHERE order_item_id = :i",
                ['b' => (string) $base, 'a' => (string) round($base * (float) $it['commission_rate'] / 100, 2),
                 'st' => $base <= 0 ? 'reversed' : 'earned', 'i' => (int) $it['id']]
            );
            $db->update('order_items', ['commission_amount' => round($base * (float) $it['commission_rate'] / 100, 2)], 'id = :id', ['id' => (int) $it['id']]);
        }
        $sales = round($sales, 2);
        $commission = round($commission, 2);
        if ($sales <= 0) {
            return;
        }
        // money still in the hold period comes out of pending, otherwise out of the available balance
        $fromPending = $split['settlement_status'] === 'pending';
        self::entry($vendorId, 'refund', -$sales, $fromPending, 'split', (int) $split['id'], __('ledger_return', ['no' => $split['sub_order_no']]), $by);
        self::entry($vendorId, 'commission', $commission, $fromPending, 'split', (int) $split['id'], __('ledger_commission_back', ['no' => $split['sub_order_no']]), $by);
        $db->query(
            'UPDATE order_vendor_splits SET commission_amount = GREATEST(commission_amount - :c, 0), vendor_earning = vendor_earning - :e WHERE id = :id',
            ['c' => (string) $commission, 'e' => (string) round($sales - $commission, 2), 'id' => (int) $split['id']]
        );
        $db->query('UPDATE vendor_wallets SET total_earned = total_earned - :e WHERE vendor_id = :v', ['e' => (string) round($sales - $commission, 2), 'v' => $vendorId]);
        self::recalcOrderCommission((int) $split['order_id']);
    }

    /** Move earnings whose hold period is over from pending to the withdrawable balance. */
    public static function releaseDue(int $vendorId): float
    {
        return self::db()->transaction(static function (Database $db) use ($vendorId): float {
            self::forVendor($vendorId, true);
            $due = $db->fetchAll(
                "SELECT id, sub_order_no, vendor_earning FROM order_vendor_splits
                  WHERE vendor_id = :v AND settlement_status = 'pending' AND release_at <= NOW() FOR UPDATE",
                ['v' => $vendorId]
            );
            $total = 0.0;
            foreach ($due as $s) {
                $amt = round((float) $s['vendor_earning'], 2);
                $db->update('order_vendor_splits', ['settlement_status' => 'eligible', 'settled_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $s['id']]);
                if ($amt != 0.0) {
                    $w = self::forVendor($vendorId, true);
                    $db->query('UPDATE vendor_wallets SET pending_balance = pending_balance - :a, balance = balance + :b WHERE id = :id',
                        ['a' => (string) $amt, 'b' => (string) $amt, 'id' => (int) $w['id']]);
                    $db->insert('vendor_wallet_transactions', [
                        'wallet_id' => (int) $w['id'], 'vendor_id' => $vendorId, 'type' => 'release', 'amount' => $amt,
                        'balance_after' => round((float) $w['balance'] + (float) $w['pending_balance'], 2),
                        'reference_type' => 'split', 'reference_id' => (int) $s['id'], 'note' => __('ledger_release', ['no' => $s['sub_order_no']]),
                    ]);
                }
                $total += $amt;
            }
            return round($total, 2);
        });
    }

    /** Payout requested (money leaves the available balance now). */
    public static function debitPayout(int $vendorId, float $amount, int $payoutId, ?int $by): void
    {
        self::entry($vendorId, 'payout', -$amount, false, 'payout', $payoutId, __('ledger_payout_request', ['id' => $payoutId]), $by);
    }

    /** Payout rejected: money goes back. */
    public static function creditPayoutBack(int $vendorId, float $amount, int $payoutId, ?int $by): void
    {
        self::entry($vendorId, 'adjustment', $amount, false, 'payout', $payoutId, __('ledger_payout_rejected', ['id' => $payoutId]), $by);
    }

    /** Manual correction by finance staff (+ / -). */
    public static function adjust(int $vendorId, float $amount, string $note, int $by): void
    {
        self::db()->transaction(static fn() => self::entry($vendorId, 'adjustment', $amount, false, 'manual', null, $note, $by));
    }

    /** orders.commission_total = sum of the order's booked commission. */
    public static function recalcOrderCommission(int $orderId): void
    {
        self::db()->query(
            "UPDATE orders SET commission_total = (SELECT COALESCE(SUM(commission_amount), 0) FROM order_vendor_splits
                 WHERE order_id = :o AND settlement_status IN ('pending','eligible','settled')) WHERE id = :id",
            ['o' => $orderId, 'id' => $orderId]
        );
    }

    public static function ledger(int $vendorId, int $limit, int $offset): array
    {
        return self::db()->fetchAll(
            "SELECT * FROM vendor_wallet_transactions WHERE vendor_id = :v ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            ['v' => $vendorId]
        );
    }

    public static function ledgerCount(int $vendorId): int
    {
        return (int) self::db()->fetchColumn('SELECT COUNT(*) FROM vendor_wallet_transactions WHERE vendor_id = :v', ['v' => $vendorId]);
    }
}
