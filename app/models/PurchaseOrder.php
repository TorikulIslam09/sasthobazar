<?php
declare(strict_types=1);

/**
 * Purchase orders + goods received notes (GRN).
 *
 * Life cycle
 *   draft --place--> ordered --GRN--> partial --GRN--> received
 *     |                 |                |
 *   delete/cancel     cancel           close (stop waiting for the rest)
 *
 *  - Only drafts can be edited or deleted.
 *  - Cancel is allowed while nothing has been received; after a partial receipt use "close".
 *  - A GRN adds stock through Inventory::receive() (type "purchase", one batch per line) and updates
 *    received quantities - all in ONE transaction, so a failed line leaves nothing half-received.
 */
class PurchaseOrder extends Model
{
    protected static string $table = 'purchase_orders';

    public const STATUSES = ['draft', 'ordered', 'partial', 'received', 'closed', 'cancelled'];
    public const MAX_ITEMS = 200;

    /* ======================= Queries ======================= */

    private static function filterSql(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(po.po_no LIKE :q1 OR s.name LIKE :q2 OR EXISTS (SELECT 1 FROM grns g WHERE g.purchase_order_id = po.id AND (g.grn_no LIKE :q3 OR g.invoice_no LIKE :q4)))';
            $p += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        if (in_array($f['status'] ?? '', self::STATUSES, true)) {
            $w[] = 'po.status = :status';
            $p['status'] = $f['status'];
        } elseif (($f['status'] ?? '') === 'open') {
            $w[] = "po.status IN ('ordered','partial')";
        }
        if (!empty($f['supplier_id'])) {
            $w[] = 'po.supplier_id = :sup';
            $p['sup'] = (int) $f['supplier_id'];
        }
        if (($f['from'] ?? '') !== '') {
            $w[] = 'po.order_date >= :from';
            $p['from'] = $f['from'];
        }
        if (($f['to'] ?? '') !== '') {
            $w[] = 'po.order_date <= :to';
            $p['to'] = $f['to'];
        }
        return [' WHERE ' . implode(' AND ', $w), $p];
    }

    public static function countFiltered(array $f): int
    {
        [$w, $p] = self::filterSql($f);
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id' . $w, $p);
    }

    public static function filtered(array $f, int $limit, int $offset): array
    {
        [$w, $p] = self::filterSql($f);
        return static::db()->fetchAll(
            'SELECT po.*, s.name AS supplier_name, vd.shop_name AS vendor_name,
                    (SELECT COUNT(*) FROM purchase_order_items i WHERE i.purchase_order_id = po.id) AS items_count,
                    (SELECT COALESCE(SUM(i.quantity_ordered), 0) FROM purchase_order_items i WHERE i.purchase_order_id = po.id) AS qty_ordered,
                    (SELECT COALESCE(SUM(i.quantity_received), 0) FROM purchase_order_items i WHERE i.purchase_order_id = po.id) AS qty_received
               FROM purchase_orders po
               JOIN suppliers s ON s.id = po.supplier_id
          LEFT JOIN vendors vd ON vd.id = po.vendor_id' . $w . '
           ORDER BY po.id DESC LIMIT :lim OFFSET :off',
            $p + ['lim' => $limit, 'off' => $offset]
        );
    }

    public static function statusCounts(): array
    {
        $rows = static::db()->fetchAll('SELECT status, COUNT(*) n FROM purchase_orders GROUP BY status');
        return array_map('intval', array_column($rows, 'n', 'status'));
    }

    public static function findFull(int $id): ?array
    {
        return static::db()->fetch(
            'SELECT po.*, s.name AS supplier_name, s.phone AS supplier_phone, s.address AS supplier_address, s.email AS supplier_email,
                    vd.shop_name AS vendor_name, u.name AS created_by_name
               FROM purchase_orders po
               JOIN suppliers s ON s.id = po.supplier_id
          LEFT JOIN vendors vd ON vd.id = po.vendor_id
          LEFT JOIN users u ON u.id = po.created_by
              WHERE po.id = :id',
            ['id' => $id]
        );
    }

    public static function items(int $poId): array
    {
        return static::db()->fetchAll(
            'SELECT i.*, (i.quantity_ordered - i.quantity_received) AS remaining,
                    p.name_en, p.name_bn, p.sku, p.strength, p.product_type, v.name_en AS variant_name, v.sku AS variant_sku
               FROM purchase_order_items i
               JOIN products p ON p.id = i.product_id
          LEFT JOIN product_variants v ON v.id = i.variant_id
              WHERE i.purchase_order_id = :po ORDER BY i.id',
            ['po' => $poId]
        );
    }

    public static function grns(int $poId): array
    {
        return static::db()->fetchAll(
            'SELECT g.*, u.name AS received_by_name FROM grns g LEFT JOIN users u ON u.id = g.received_by
              WHERE g.purchase_order_id = :po ORDER BY g.id',
            ['po' => $poId]
        );
    }

    public static function grnFull(int $grnId): ?array
    {
        $g = static::db()->fetch(
            'SELECT g.*, po.po_no, po.vendor_id, s.name AS supplier_name, s.phone AS supplier_phone, s.address AS supplier_address,
                    u.name AS received_by_name, vd.shop_name AS vendor_name
               FROM grns g
               JOIN purchase_orders po ON po.id = g.purchase_order_id
               JOIN suppliers s ON s.id = g.supplier_id
          LEFT JOIN users u ON u.id = g.received_by
          LEFT JOIN vendors vd ON vd.id = po.vendor_id
              WHERE g.id = :id',
            ['id' => $grnId]
        );
        if ($g) {
            $g['items'] = static::db()->fetchAll(
                'SELECT gi.*, p.name_en, p.sku, p.strength, v.name_en AS variant_name
                   FROM grn_items gi JOIN products p ON p.id = gi.product_id LEFT JOIN product_variants v ON v.id = gi.variant_id
                  WHERE gi.grn_id = :g ORDER BY gi.id',
                ['g' => $grnId]
            );
        }
        return $g;
    }

    /* ======================= Draft save ======================= */

    /**
     * Create or update a DRAFT with its lines (lines are replaced).
     * @param array $head supplier_id, vendor_id, order_date, expected_date, discount, tax, notes
     * @param array $lines each: product_id, variant_id, quantity, unit_cost  (already validated)
     */
    public static function saveDraft(?int $id, array $head, array $lines): int
    {
        return static::db()->transaction(function (Database $db) use ($id, $head, $lines) {
            $subtotal = 0.0;
            foreach ($lines as $l) {
                $subtotal += round((int) $l['quantity'] * (float) $l['unit_cost'], 2);
            }
            $discount = (float) (($head['discount'] ?? '') ?: 0);
            $tax = (float) (($head['tax'] ?? '') ?: 0);
            $vendor = (string) ($head['vendor_id'] ?? '');
            $row = [
                'supplier_id'   => (int) $head['supplier_id'],
                'vendor_id'     => ctype_digit($vendor) && (int) $vendor > 0 ? (int) $vendor : null,
                'order_date'    => $head['order_date'],
                'expected_date' => ($head['expected_date'] ?? '') ?: null,
                'subtotal'      => round($subtotal, 2),
                'discount'      => round($discount, 2),
                'tax'           => round($tax, 2),
                'total'         => round(max(0, $subtotal - $discount + $tax), 2),
                'notes'         => ($head['notes'] ?? '') ?: null,
            ];
            if ($id === null) {
                $row += ['status' => 'draft', 'created_by' => Auth::id(), 'po_no' => 'TMP-' . bin2hex(random_bytes(8))];
                $id = self::create($row);
                $db->update('purchase_orders', ['po_no' => self::number('PO', $id)], 'id = :id', ['id' => $id]);
            } else {
                $locked = $db->fetch('SELECT status FROM purchase_orders WHERE id = :id FOR UPDATE', ['id' => $id]);
                if (!$locked || $locked['status'] !== 'draft') {
                    throw new InventoryException(__('po_not_editable'));
                }
                self::updateById($id, $row);
                $db->delete('purchase_order_items', 'purchase_order_id = :po', ['po' => $id]);
            }
            foreach ($lines as $l) {
                $db->insert('purchase_order_items', [
                    'purchase_order_id' => $id,
                    'product_id'        => (int) $l['product_id'],
                    'variant_id'        => $l['variant_id'] ? (int) $l['variant_id'] : null,
                    'quantity_ordered'  => (int) $l['quantity'],
                    'unit_cost'         => round((float) $l['unit_cost'], 2),
                    'line_total'        => round((int) $l['quantity'] * (float) $l['unit_cost'], 2),
                ]);
            }
            return $id;
        });
    }

    /** "PO-2611-00042" - based on the auto-increment id, so it is always unique. */
    private static function number(string $prefix, int $id): string
    {
        return sprintf('%s-%s-%05d', $prefix, date('ym'), $id);
    }

    /* ======================= Status changes ======================= */

    /** Change status inside a locked transaction. $from = allowed current statuses. */
    private static function transition(int $id, array $from, string $to, array $extra = [], ?callable $check = null): void
    {
        static::db()->transaction(function (Database $db) use ($id, $from, $to, $extra, $check) {
            $po = $db->fetch('SELECT * FROM purchase_orders WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$po || !in_array($po['status'], $from, true)) {
                throw new InventoryException(__('po_bad_transition', ['status' => __('po_status_' . ($po['status'] ?? 'draft'))]));
            }
            if ($check) {
                $check($db, $po);
            }
            $db->update('purchase_orders', ['status' => $to] + $extra, 'id = :id', ['id' => $id]);
            Audit::log('purchase_orders.' . $to, 'purchase_orders', $id, ['status' => $po['status']], ['status' => $to]);
        });
    }

    public static function place(int $id): void
    {
        self::transition($id, ['draft'], 'ordered', ['ordered_at' => date('Y-m-d H:i:s')], static function (Database $db) use ($id) {
            if (!(int) $db->fetchColumn('SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = :po', ['po' => $id])) {
                throw new InventoryException(__('po_no_items'));
            }
        });
    }

    public static function cancel(int $id): void
    {
        self::transition($id, ['draft', 'ordered'], 'cancelled', ['closed_at' => date('Y-m-d H:i:s')], static function (Database $db) use ($id) {
            if ((int) $db->fetchColumn('SELECT COALESCE(SUM(quantity_received), 0) FROM purchase_order_items WHERE purchase_order_id = :po', ['po' => $id]) > 0) {
                throw new InventoryException(__('po_cancel_received'));
            }
        });
    }

    public static function close(int $id): void
    {
        self::transition($id, ['partial'], 'closed', ['closed_at' => date('Y-m-d H:i:s')]);
    }

    public static function deleteDraft(int $id): void
    {
        static::db()->transaction(function (Database $db) use ($id) {
            $po = $db->fetch('SELECT status FROM purchase_orders WHERE id = :id FOR UPDATE', ['id' => $id]);
            if (!$po || $po['status'] !== 'draft') {
                throw new InventoryException(__('po_delete_draft_only'));
            }
            self::deleteById($id); // items cascade
        });
    }

    /* ======================= Receive (GRN) ======================= */

    /**
     * Post a GRN.
     * @param array $head invoice_no, received_date, notes
     * @param array $lines po_item_id => [quantity, batch_no, manufacture_date, expiry_date, unit_cost] (qty > 0 only)
     * @return int GRN id
     * @throws InventoryException field = "lines.<po_item_id>.<field>" for line problems
     */
    public static function receive(int $poId, array $head, array $lines): int
    {
        return static::db()->transaction(function (Database $db) use ($poId, $head, $lines) {
            $po = $db->fetch('SELECT * FROM purchase_orders WHERE id = :id FOR UPDATE', ['id' => $poId]);
            if (!$po || !in_array($po['status'], ['ordered', 'partial'], true)) {
                throw new InventoryException(__('po_not_receivable'));
            }
            $items = array_column($db->fetchAll(
                'SELECT * FROM purchase_order_items WHERE purchase_order_id = :po FOR UPDATE', ['po' => $poId]
            ), null, 'id');
            if (!$lines) {
                throw new InventoryException(__('grn_nothing'));
            }

            $grnId = $db->insert('grns', [
                'grn_no'            => 'TMP-' . bin2hex(random_bytes(8)),
                'purchase_order_id' => $poId,
                'supplier_id'       => (int) $po['supplier_id'],
                'invoice_no'        => ($head['invoice_no'] ?? '') ?: null,
                'received_date'     => $head['received_date'],
                'notes'             => ($head['notes'] ?? '') ?: null,
                'received_by'       => Auth::id(),
            ]);
            $grnNo = self::number('GRN', $grnId);
            $supplierName = (string) $db->fetchColumn('SELECT name FROM suppliers WHERE id = :s', ['s' => $po['supplier_id']]);

            // ascending product id = consistent lock order with other stock operations
            uksort($lines, static fn($a, $b) => ((int) ($items[$a]['product_id'] ?? 0) <=> (int) ($items[$b]['product_id'] ?? 0)) ?: ($a <=> $b));

            $totalQty = 0;
            $totalCost = 0.0;
            foreach ($lines as $itemId => $l) {
                $item = $items[$itemId] ?? null;
                if (!$item) {
                    throw new InventoryException(__('grn_bad_line'), "lines.{$itemId}.quantity");
                }
                $remaining = (int) $item['quantity_ordered'] - (int) $item['quantity_received'];
                $qty = (int) $l['quantity'];
                if ($qty > $remaining) {
                    throw new InventoryException(__('grn_over_receive', ['n' => num($remaining)]), "lines.{$itemId}.quantity");
                }
                try {
                    $r = Inventory::receive([
                        'product_id'       => (int) $item['product_id'],
                        'variant_id'       => $item['variant_id'],
                        'batch_no'         => $l['batch_no'],
                        'manufacture_date' => $l['manufacture_date'],
                        'expiry_date'      => $l['expiry_date'],
                        'quantity'         => $qty,
                        'unit_cost'        => $l['unit_cost'],
                        'type'             => 'purchase',
                        'supplier_id'      => (int) $po['supplier_id'],
                        'supplier_name'    => $supplierName,
                        'invoice_no'       => $head['invoice_no'] ?? null,
                        'grn_id'           => $grnId,
                        'reference_type'   => 'grn',
                        'reference_id'     => $grnId,
                        'note'             => $grnNo . ' / ' . $po['po_no'],
                    ]);
                } catch (InventoryException $e) {
                    throw new InventoryException($e->getMessage(), "lines.{$itemId}." . ($e->field ?: 'quantity'));
                }
                $lineTotal = round($qty * (float) $l['unit_cost'], 2);
                $db->insert('grn_items', [
                    'grn_id'           => $grnId,
                    'po_item_id'       => (int) $itemId,
                    'product_id'       => (int) $item['product_id'],
                    'variant_id'       => $item['variant_id'],
                    'batch_id'         => $r['batch_id'],
                    'batch_no'         => strtoupper(trim($l['batch_no'])),
                    'manufacture_date' => $l['manufacture_date'] ?: null,
                    'expiry_date'      => $l['expiry_date'] ?: null,
                    'quantity'         => $qty,
                    'unit_cost'        => round((float) $l['unit_cost'], 2),
                    'line_total'       => $lineTotal,
                ]);
                $db->query('UPDATE purchase_order_items SET quantity_received = quantity_received + :q WHERE id = :id', ['q' => $qty, 'id' => (int) $itemId]);
                $totalQty += $qty;
                $totalCost += $lineTotal;
            }

            $db->update('grns', ['grn_no' => $grnNo, 'total_quantity' => $totalQty, 'total_cost' => round($totalCost, 2)], 'id = :id', ['id' => $grnId]);
            $left = (int) $db->fetchColumn('SELECT COALESCE(SUM(quantity_ordered - quantity_received), 0) FROM purchase_order_items WHERE purchase_order_id = :po', ['po' => $poId]);
            $status = $left === 0 ? 'received' : 'partial';
            $db->update('purchase_orders', ['status' => $status] + ($left === 0 ? ['closed_at' => date('Y-m-d H:i:s')] : []), 'id = :id', ['id' => $poId]);
            Audit::log('purchase_orders.receive', 'purchase_orders', $poId, ['status' => $po['status']],
                ['status' => $status, 'grn' => $grnNo, 'quantity' => $totalQty, 'cost' => round($totalCost, 2)]);
            return $grnId;
        });
    }
}
