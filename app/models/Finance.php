<?php
declare(strict_types=1);

/**
 * Profit & loss for a date range (accrual, on delivery - the same moment commission is booked).
 *
 * Income
 *   In-house sales      delivered in-house items: paid amount - VAT inside it, returned units removed
 *   Commission          commission booked in the period (commission ledger, reversals deducted)
 *   Delivery & COD      delivery + COD charges of the sub-orders delivered in the period
 *   Other income        manual income entries
 * Costs
 *   Cost of goods sold  in-house items' batch cost (FEFO) for the units kept
 *   Promotions paid to sellers  platform-funded discounts on sellers' items
 *   Courier charges     courier fees of shipments delivered / returned in the period
 *   Payment gateway fees
 *   Expenses            manual expense entries by category
 * Not income: VAT (in-house VAT is payable to the government), sellers' sales, customer money held for refunds.
 */
final class Finance
{
    private static function db(): Database
    {
        return Database::getInstance();
    }

    public static function profitLoss(string $from, string $to): array
    {
        $db = self::db();
        $f = $from . ' 00:00:00';
        $t = $to . ' 23:59:59';
        $kept = '(oi.quantity - oi.returned_qty) / oi.quantity';
        $items = $db->fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN oi.vendor_id IS NULL THEN ((oi.line_total - oi.discount_amount) - IF(oi.vat_in_price = 1, oi.vat_amount, 0)) * {$kept} END), 0) AS sales,
                COALESCE(SUM(CASE WHEN oi.vendor_id IS NULL THEN oi.vat_amount * {$kept} END), 0) AS vat,
                COALESCE(SUM(CASE WHEN oi.vendor_id IS NULL THEN COALESCE(oi.unit_cost, 0) * (oi.quantity - oi.returned_qty) END), 0) AS cogs,
                COALESCE(SUM(CASE WHEN oi.vendor_id IS NULL AND oi.unit_cost IS NULL THEN oi.quantity - oi.returned_qty END), 0) AS no_cost_units,
                COALESCE(SUM(CASE WHEN oi.vendor_id IS NOT NULL THEN oi.platform_funded * {$kept} END), 0) AS promo
               FROM order_items oi JOIN order_vendor_splits s ON s.id = oi.split_id
              WHERE s.status <> 'cancelled' AND oi.status <> 'cancelled' AND s.delivered_at BETWEEN :f AND :t",
            ['f' => $f, 't' => $t]
        );
        $commission = -(float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM vendor_wallet_transactions WHERE type = 'commission' AND created_at BETWEEN :f AND :t", ['f' => $f, 't' => $t]);
        $delivery = (float) $db->fetchColumn("SELECT COALESCE(SUM(shipping_total), 0) FROM order_vendor_splits WHERE status <> 'cancelled' AND delivered_at BETWEEN :f AND :t", ['f' => $f, 't' => $t]);
        $courier = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(delivery_charge), 0) FROM shipments WHERE courier_id IS NOT NULL
                AND (delivered_at BETWEEN :f AND :t OR (delivered_at IS NULL AND returned_at BETWEEN :f2 AND :t2))",
            ['f' => $f, 't' => $t, 'f2' => $f, 't2' => $t]
        );
        $gateway = (float) $db->fetchColumn("SELECT COALESCE(SUM(fee), 0) FROM payments WHERE status = 'success' AND paid_at BETWEEN :f AND :t", ['f' => $f, 't' => $t]);
        $entries = $db->fetchAll(
            "SELECT e.type, c.id, c.name_en, c.name_bn, SUM(e.amount) AS amount FROM account_entries e JOIN account_categories c ON c.id = e.category_id
              WHERE e.deleted_at IS NULL AND e.entry_date BETWEEN :f AND :t GROUP BY e.type, c.id, c.name_en, c.name_bn ORDER BY amount DESC",
            ['f' => $from, 't' => $to]
        );

        $income = [
            ['pl_inhouse_sales', (float) $items['sales']],
            ['pl_commission', $commission],
            ['pl_delivery_income', $delivery],
        ];
        $costs = [
            ['pl_cogs', (float) $items['cogs']],
            ['pl_promotions', (float) $items['promo']],
            ['pl_courier', $courier],
            ['pl_gateway', $gateway],
        ];
        $otherIncome = $expenses = [];
        foreach ($entries as $e) {
            $line = [tr($e['name_bn'], $e['name_en']), (float) $e['amount'], (int) $e['id']];
            if ($e['type'] === 'income') {
                $otherIncome[] = $line;
            } else {
                $expenses[] = $line;
            }
        }
        $totalIncome = array_sum(array_column($income, 1)) + array_sum(array_column($otherIncome, 1));
        $direct = (float) $items['cogs'] + (float) $items['promo'] + $courier + $gateway;
        $totalExpenses = array_sum(array_column($expenses, 1));
        $gross = $totalIncome - $direct;
        $net = $gross - $totalExpenses;

        return [
            'from'           => $from,
            'to'             => $to,
            'income'         => $income,
            'other_income'   => $otherIncome,
            'costs'          => $costs,
            'expenses'       => $expenses,
            'total_income'   => round($totalIncome, 2),
            'direct_costs'   => round($direct, 2),
            'gross_profit'   => round($gross, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit'     => round($net, 2),
            'margin'         => $totalIncome > 0 ? round($net / $totalIncome * 100, 1) : null,
            'no_cost_units'  => (int) $items['no_cost_units'],
            'memo'           => [
                ['pl_vat_payable', round((float) $items['vat'], 2)],
                ['pl_refunds_paid', (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'processed' AND processed_at BETWEEN :f AND :t", ['f' => $f, 't' => $t])],
                ['pl_payouts_paid', (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM vendor_payouts WHERE status = 'paid' AND processed_at BETWEEN :f AND :t", ['f' => $f, 't' => $t])],
                ['pl_cod_held', (float) $db->fetchColumn("SELECT COALESCE(SUM(collected_amount), 0) FROM shipments WHERE cod_status = 'collected'")],
                ['pl_seller_balances', (float) $db->fetchColumn('SELECT COALESCE(SUM(balance + pending_balance), 0) FROM vendor_wallets')],
            ],
        ];
    }

    /** P&L in the Reports array shape (for CSV / Excel / PDF). */
    public static function profitLossReport(array $pl): array
    {
        $rows = [];
        $add = static function (string $section, string $label, ?float $amount, bool $bold = false) use (&$rows) {
            $rows[] = ['section' => $section, 'line' => $label, 'amount' => $amount];
        };
        foreach ($pl['income'] as [$k, $v]) {
            $add(__('pl_income'), __($k), $v);
        }
        foreach ($pl['other_income'] as [$label, $v]) {
            $add(__('pl_income'), $label, $v);
        }
        $add(__('pl_income'), __('pl_total_income'), $pl['total_income']);
        foreach ($pl['costs'] as [$k, $v]) {
            $add(__('pl_direct_costs'), __($k), -$v);
        }
        $add(__('pl_direct_costs'), __('pl_gross_profit'), $pl['gross_profit']);
        foreach ($pl['expenses'] as [$label, $v]) {
            $add(__('pl_expenses'), $label, -$v);
        }
        $add(__('pl_expenses'), __('pl_total_expenses'), -$pl['total_expenses']);
        $memo = array_map(static fn($m) => ['line' => __($m[0]), 'amount' => $m[1]], $pl['memo']);
        return [
            'title'    => __('pl_title'),
            'subtitle' => __('rp_range', ['from' => dt($pl['from'], 'd M Y'), 'to' => dt($pl['to'], 'd M Y')]),
            'cards'    => [
                [__('pl_total_income'), $pl['total_income'], 'money'], [__('pl_gross_profit'), $pl['gross_profit'], 'money'],
                [__('pl_total_expenses'), $pl['total_expenses'], 'money'], [__('pl_net_profit'), $pl['net_profit'], 'money'],
            ],
            'tables'   => [
                ['title' => __('pl_title'), 'columns' => [['section', __('pl_section'), 'text'], ['line', __('description'), 'text'], ['amount', __('amount'), 'money']],
                 'rows' => $rows, 'totals' => ['section' => '', 'line' => __('pl_net_profit'), 'amount' => $pl['net_profit']]],
                ['title' => __('pl_memo'), 'columns' => [['line', __('description'), 'text'], ['amount', __('amount'), 'money']], 'rows' => $memo, 'totals' => null],
            ],
            'notes'    => [__('pl_note')],
        ];
    }
}
