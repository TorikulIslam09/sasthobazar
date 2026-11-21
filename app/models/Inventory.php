<?php
declare(strict_types=1);

/**
 * Inventory - the ONLY place that changes stock.
 *
 * Model
 *  - Stock lives in inventory_batches (one row per product + variant + batch number).
 *  - Every change writes stock_movements rows (one per batch touched): +qty = in, -qty = out,
 *    balance_after = stock of that product/variant after the change.
 *  - products.stock_qty and product_variants.stock_qty are caches = SUM(quantity_available) of active batches.
 *
 * Concurrency
 *  Every operation runs in a transaction and locks the product row first (SELECT ... FOR UPDATE),
 *  then its batches. Two requests touching the same product are therefore serialised, and stock can
 *  never go negative (the DB CHECK on quantity_available is a second safety net).
 *  Callers handling several products (GRN) must call in ascending product_id order to avoid deadlocks.
 *
 * API (all throw InventoryException with a translated message when refused)
 *   Inventory::receive([...])          stock in / purchase (GRN) / customer return
 *   Inventory::remove([...])           sale / stock out / damage / expired / recall  (FEFO when no batch given)
 *   Inventory::adjustBatch($id, $qty)  physical count correction
 *   Inventory::writeOff($id, $type)    damage / expired / recall a batch
 *   Inventory::updateBatch($id, [...]) edit batch details (no stock change)
 */
final class Inventory
{
    public const IN_TYPES = ['purchase', 'stock_in', 'return', 'cancel'];
    public const OUT_TYPES = ['sale', 'stock_out', 'damage', 'expired', 'recall'];
    /** Types where already-expired batches must never be used */
    private const SELLABLE_OUT = ['sale', 'stock_out'];
    private const MAX_QTY = 1000000;

    private static function db(): Database
    {
        return Database::getInstance();
    }

    /* ======================================================================
     * Stock IN
     * ==================================================================== */

    /**
     * @param array $p product_id, variant_id?, batch_no, quantity, type (stock_in|purchase|return|cancel),
     *                 manufacture_date?, expiry_date?, unit_cost?, supplier_id?, supplier_name?, invoice_no?,
     *                 grn_id?, reference_type?, reference_id?, note?
     * @return array{batch_id:int, movement_id:int, balance:int}
     */
    public static function receive(array $p): array
    {
        $type = $p['type'] ?? 'stock_in';
        if (!in_array($type, self::IN_TYPES, true)) {
            throw new InvalidArgumentException('Bad stock-in type: ' . $type);
        }
        $qty = self::qty($p['quantity'] ?? 0);
        $batchNo = self::batchNo($p['batch_no'] ?? '');
        [$mfg, $exp] = self::dates($p['manufacture_date'] ?? null, $p['expiry_date'] ?? null, $type);
        $cost = isset($p['unit_cost']) && $p['unit_cost'] !== '' && $p['unit_cost'] !== null ? round((float) $p['unit_cost'], 2) : null;
        if ($cost !== null && $cost < 0) {
            throw new InventoryException(__('v_gte', ['n' => 0]), 'unit_cost');
        }

        return self::db()->transaction(function (Database $db) use ($p, $type, $qty, $batchNo, $mfg, $exp, $cost) {
            $product = self::lockProduct((int) $p['product_id']);
            $variantId = self::checkVariant($product, $p['variant_id'] ?? null);
            if ($product['product_type'] === 'medicine' && $exp === null) {
                throw new InventoryException(__('inv_expiry_required'), 'expiry_date');
            }

            $batch = $db->fetch(
                'SELECT * FROM inventory_batches WHERE product_id = :p AND variant_key = :v AND batch_no = :b FOR UPDATE',
                ['p' => $product['id'], 'v' => (int) $variantId, 'b' => $batchNo]
            );

            if ($batch) {
                if ($batch['status'] === 'recalled') {
                    throw new InventoryException(__('inv_batch_recalled', ['batch' => $batchNo]), 'batch_no');
                }
                if ($exp !== null && $batch['expiry_date'] !== null && $batch['expiry_date'] !== $exp) {
                    throw new InventoryException(__('inv_batch_expiry_mismatch', ['batch' => $batchNo, 'date' => $batch['expiry_date']]), 'expiry_date');
                }
                // weighted average cost of what is on hand + what arrives
                $newCost = $batch['cost_price'];
                if ($cost !== null) {
                    $onHand = max(0, (int) $batch['quantity_available']);
                    $newCost = ($batch['cost_price'] === null || $onHand === 0)
                        ? $cost
                        : round(($onHand * (float) $batch['cost_price'] + $qty * $cost) / ($onHand + $qty), 2);
                }
                $db->query(
                    "UPDATE inventory_batches
                        SET quantity_received = quantity_received + :q1, quantity_available = quantity_available + :q2,
                            status = 'active', cost_price = :cost,
                            expiry_date = COALESCE(expiry_date, :exp), manufacture_date = COALESCE(manufacture_date, :mfg),
                            supplier_id = COALESCE(:sup, supplier_id)
                      WHERE id = :id",
                    ['q1' => $qty, 'q2' => $qty, 'cost' => $newCost, 'exp' => $exp, 'mfg' => $mfg,
                     'sup' => !empty($p['supplier_id']) ? (int) $p['supplier_id'] : null, 'id' => (int) $batch['id']]
                );
                $batchId = (int) $batch['id'];
            } else {
                $batchId = $db->insert('inventory_batches', [
                    'product_id'         => (int) $product['id'],
                    'variant_id'         => $variantId,
                    'vendor_id'          => $product['vendor_id'],
                    'batch_no'           => $batchNo,
                    'manufacture_date'   => $mfg,
                    'expiry_date'        => $exp,
                    'quantity_received'  => $qty,
                    'quantity_available' => $qty,
                    'cost_price'         => $cost,
                    'supplier_id'        => !empty($p['supplier_id']) ? (int) $p['supplier_id'] : null,
                    'supplier_name'      => isset($p['supplier_name']) ? mb_substr((string) $p['supplier_name'], 0, 150) : null,
                    'invoice_no'         => !empty($p['invoice_no']) ? mb_substr((string) $p['invoice_no'], 0, 100) : null,
                    'grn_id'             => !empty($p['grn_id']) ? (int) $p['grn_id'] : null,
                    'received_by'        => Auth::id(),
                    'status'             => 'active',
                ]);
            }

            $balance = self::refreshCaches((int) $product['id'], $variantId);
            $movementId = self::log($product, $variantId, $batchId, $type, $qty, $balance, $cost, $p);
            return ['batch_id' => $batchId, 'movement_id' => $movementId, 'balance' => $balance];
        });
    }

    /* ======================================================================
     * Stock OUT
     * ==================================================================== */

    /**
     * Take stock out. With batch_id: only from that batch. Without: FEFO (earliest expiry first),
     * skipping expired batches for sale/stock_out.
     * @param array $p product_id, variant_id?, quantity, type (sale|stock_out|damage|expired|recall), batch_id?,
     *                 reference_type?, reference_id?, note?
     * @return array list of ['batch_id', 'batch_no', 'quantity', 'movement_id']
     */
    public static function remove(array $p): array
    {
        $type = $p['type'] ?? 'stock_out';
        if (!in_array($type, self::OUT_TYPES, true)) {
            throw new InvalidArgumentException('Bad stock-out type: ' . $type);
        }
        $qty = self::qty($p['quantity'] ?? 0);

        return self::db()->transaction(function (Database $db) use ($p, $type, $qty) {
            $product = self::lockProduct((int) $p['product_id']);
            $variantId = self::checkVariant($product, $p['variant_id'] ?? null);
            $sellable = in_array($type, self::SELLABLE_OUT, true);

            if (!empty($p['batch_id'])) {
                $batches = $db->fetchAll(
                    'SELECT * FROM inventory_batches WHERE id = :id AND product_id = :p AND variant_key = :v FOR UPDATE',
                    ['id' => (int) $p['batch_id'], 'p' => $product['id'], 'v' => (int) $variantId]
                );
                if (!$batches) {
                    throw new InventoryException(__('inv_batch_not_found'), 'batch_id');
                }
                $b = $batches[0];
                if ($b['status'] === 'recalled' && $type !== 'recall') {
                    throw new InventoryException(__('inv_batch_recalled', ['batch' => $b['batch_no']]), 'batch_id');
                }
                if ($sellable && $b['expiry_date'] !== null && $b['expiry_date'] < date('Y-m-d')) {
                    throw new InventoryException(__('inv_batch_expired', ['batch' => $b['batch_no']]), 'batch_id');
                }
            } else {
                $batches = $db->fetchAll(
                    "SELECT * FROM inventory_batches
                      WHERE product_id = :p AND variant_key = :v AND status = 'active' AND quantity_available > 0"
                      . ($sellable ? ' AND (expiry_date IS NULL OR expiry_date >= CURDATE())' : '') . '
                   ORDER BY expiry_date IS NULL, expiry_date, received_at, id
                      FOR UPDATE',
                    ['p' => $product['id'], 'v' => (int) $variantId]
                );
            }

            $available = array_sum(array_map(static fn($b) => (int) $b['quantity_available'], $batches));
            if ($available < $qty) {
                throw new InventoryException(__('inv_insufficient', ['available' => num($available), 'qty' => num($qty)]), 'quantity');
            }

            $out = [];
            $left = $qty;
            foreach ($batches as $b) {
                if ($left <= 0) {
                    break;
                }
                $take = min($left, (int) $b['quantity_available']);
                if ($take <= 0) {
                    continue;
                }
                $remaining = (int) $b['quantity_available'] - $take;
                $status = match (true) {
                    $type === 'recall'                       => 'recalled',
                    $remaining === 0 && $type === 'expired'  => 'expired',
                    $remaining === 0 && $b['status'] === 'active' => 'depleted',
                    default                                  => $b['status'],
                };
                $db->update('inventory_batches', ['quantity_available' => $remaining, 'status' => $status], 'id = :id', ['id' => (int) $b['id']]);
                $balance = self::refreshCaches((int) $product['id'], $variantId);
                $movementId = self::log($product, $variantId, (int) $b['id'], $type, -$take, $balance,
                    $b['cost_price'] !== null ? (float) $b['cost_price'] : null, $p);
                $out[] = ['batch_id' => (int) $b['id'], 'batch_no' => $b['batch_no'], 'quantity' => $take, 'movement_id' => $movementId];
                $left -= $take;
            }
            return $out;
        });
    }

    /* ======================================================================
     * Batch-level operations
     * ==================================================================== */

    /** Physical count: set the batch's available quantity to $counted. Logged as 'adjustment' (+/-). */
    public static function adjustBatch(int $batchId, int $counted, string $note): ?int
    {
        if ($counted < 0 || $counted > self::MAX_QTY) {
            throw new InventoryException(__('v_gte', ['n' => 0]), 'counted');
        }
        return self::db()->transaction(function (Database $db) use ($batchId, $counted, $note) {
            $product = self::lockProduct(self::productOfBatch($batchId)); // lock order: product -> batch
            $batch = self::lockBatch($batchId);
            if ($batch['status'] === 'recalled') {
                throw new InventoryException(__('inv_batch_recalled', ['batch' => $batch['batch_no']]), 'counted');
            }
            $delta = $counted - (int) $batch['quantity_available'];
            if ($delta === 0) {
                return null;
            }
            $status = $counted === 0 ? ($batch['status'] === 'active' ? 'depleted' : $batch['status'])
                                     : (in_array($batch['status'], ['depleted', 'expired'], true) ? 'active' : $batch['status']);
            $db->update('inventory_batches', [
                'quantity_available' => $counted,
                'quantity_received'  => max((int) $batch['quantity_received'], $counted),
                'status'             => $status,
            ], 'id = :id', ['id' => $batchId]);
            $variantId = $batch['variant_id'] !== null ? (int) $batch['variant_id'] : null;
            $balance = self::refreshCaches((int) $product['id'], $variantId);
            return self::log($product, $variantId, $batchId, 'adjustment', $delta, $balance,
                $batch['cost_price'] !== null ? (float) $batch['cost_price'] : null,
                ['note' => $note, 'reference_type' => 'count']);
        });
    }

    /**
     * Write off a batch: damage / expired (qty or everything left) or recall (everything, batch blocked).
     * @return array same as remove()
     */
    public static function writeOff(int $batchId, string $type, ?int $qty, string $note): array
    {
        if (!in_array($type, ['damage', 'expired', 'recall'], true)) {
            throw new InvalidArgumentException('Bad write-off type');
        }
        $batch = self::db()->fetch('SELECT * FROM inventory_batches WHERE id = :id', ['id' => $batchId]);
        if (!$batch) {
            throw new InventoryException(__('inv_batch_not_found'));
        }
        $left = (int) $batch['quantity_available'];
        if ($type === 'recall') {
            $qty = $left;
        }
        if ($left <= 0) {
            if ($type === 'recall') { // nothing on hand - just block the batch
                self::db()->update('inventory_batches', ['status' => 'recalled'], 'id = :id', ['id' => $batchId]);
                Audit::log('inventory.recall', 'inventory_batches', $batchId, ['status' => $batch['status']], ['status' => 'recalled', 'note' => $note]);
                return [];
            }
            throw new InventoryException(__('inv_insufficient', ['available' => num(0), 'qty' => num((int) $qty)]), 'quantity');
        }
        return self::remove([
            'product_id' => (int) $batch['product_id'], 'variant_id' => $batch['variant_id'], 'batch_id' => $batchId,
            'quantity' => $qty ?? $left, 'type' => $type, 'reference_type' => 'write_off', 'note' => $note,
        ]);
    }

    /** Edit batch details (no stock change). Audited. */
    public static function updateBatch(int $batchId, array $data): void
    {
        self::db()->transaction(function (Database $db) use ($batchId, $data) {
            $product = self::lockProduct(self::productOfBatch($batchId)); // lock order: product -> batch
            $batch = self::lockBatch($batchId);
            $row = [
                'batch_no'         => self::batchNo($data['batch_no'] ?? $batch['batch_no']),
                'cost_price'       => ($data['cost_price'] ?? '') !== '' ? round((float) $data['cost_price'], 2) : null,
                'supplier_id'      => !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                'invoice_no'       => ($data['invoice_no'] ?? '') !== '' ? mb_substr($data['invoice_no'], 0, 100) : null,
            ];
            [$row['manufacture_date'], $row['expiry_date']] = self::dates($data['manufacture_date'] ?? null, $data['expiry_date'] ?? null, 'edit');
            if (($product['product_type'] ?? '') === 'medicine' && $row['expiry_date'] === null) {
                throw new InventoryException(__('inv_expiry_required'), 'expiry_date');
            }
            $dupe = $db->fetchColumn(
                'SELECT 1 FROM inventory_batches WHERE product_id = :p AND variant_key = :v AND batch_no = :b AND id <> :id',
                ['p' => $batch['product_id'], 'v' => (int) $batch['variant_key'], 'b' => $row['batch_no'], 'id' => $batchId]
            );
            if ($dupe) {
                throw new InventoryException(__('inv_batch_no_taken'), 'batch_no');
            }
            $db->update('inventory_batches', $row, 'id = :id', ['id' => $batchId]);
            [$o, $n] = Audit::diff($batch, $row);
            if ($n) {
                Audit::log('inventory.batch_update', 'inventory_batches', $batchId, $o, $n);
            }
        });
    }

    /* ======================================================================
     * Internals
     * ==================================================================== */

    private static function lockProduct(int $id): array
    {
        $p = self::db()->fetch(
            'SELECT id, vendor_id, product_type, name_en, track_stock, deleted_at FROM products WHERE id = :id FOR UPDATE',
            ['id' => $id]
        );
        if (!$p || $p['deleted_at'] !== null) {
            throw new InventoryException(__('inv_product_not_found'), 'product_id');
        }
        return $p;
    }

    private static function productOfBatch(int $batchId): int
    {
        $pid = self::db()->fetchColumn('SELECT product_id FROM inventory_batches WHERE id = :id', ['id' => $batchId]);
        if (!$pid) {
            throw new InventoryException(__('inv_batch_not_found'));
        }
        return (int) $pid;
    }

    private static function lockBatch(int $id): array
    {
        $b = self::db()->fetch('SELECT * FROM inventory_batches WHERE id = :id FOR UPDATE', ['id' => $id]);
        if (!$b) {
            throw new InventoryException(__('inv_batch_not_found'));
        }
        return $b;
    }

    /** Variant must belong to the product; a product with variants needs one. */
    private static function checkVariant(array $product, mixed $variantId): ?int
    {
        $variantId = ($variantId === null || $variantId === '' || (int) $variantId === 0) ? null : (int) $variantId;
        $db = self::db();
        if ($variantId !== null) {
            $ok = $db->fetchColumn('SELECT 1 FROM product_variants WHERE id = :v AND product_id = :p', ['v' => $variantId, 'p' => $product['id']]);
            if (!$ok) {
                throw new InventoryException(__('inv_variant_invalid'), 'variant_id');
            }
            return $variantId;
        }
        $hasVariants = (int) $db->fetchColumn('SELECT COUNT(*) FROM product_variants WHERE product_id = :p AND is_active = 1', ['p' => $product['id']]);
        if ($hasVariants > 0) {
            throw new InventoryException(__('inv_variant_required'), 'variant_id');
        }
        return null;
    }

    /** Recompute cached stock; returns the balance of the product/variant scope. */
    private static function refreshCaches(int $productId, ?int $variantId): int
    {
        $db = self::db();
        if ($variantId !== null) {
            $db->query(
                "UPDATE product_variants SET stock_qty = (SELECT COALESCE(SUM(quantity_available), 0) FROM inventory_batches
                  WHERE variant_id = :v AND status = 'active') WHERE id = :v2",
                ['v' => $variantId, 'v2' => $variantId]
            );
        }
        $db->query(
            "UPDATE products SET stock_qty = (SELECT COALESCE(SUM(quantity_available), 0) FROM inventory_batches
              WHERE product_id = :p AND status = 'active') WHERE id = :p2",
            ['p' => $productId, 'p2' => $productId]
        );
        return (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(quantity_available), 0) FROM inventory_batches WHERE product_id = :p AND variant_key = :v AND status = 'active'",
            ['p' => $productId, 'v' => (int) $variantId]
        );
    }

    private static function log(array $product, ?int $variantId, int $batchId, string $type, int $qty, int $balance, ?float $cost, array $p): int
    {
        return self::db()->insert('stock_movements', [
            'product_id'     => (int) $product['id'],
            'variant_id'     => $variantId,
            'batch_id'       => $batchId,
            'vendor_id'      => $product['vendor_id'],
            'type'           => $type,
            'quantity'       => $qty,
            'balance_after'  => $balance,
            'unit_cost'      => $cost,
            'reference_type' => isset($p['reference_type']) ? mb_substr((string) $p['reference_type'], 0, 50) : 'manual',
            'reference_id'   => !empty($p['reference_id']) ? (int) $p['reference_id'] : null,
            'note'           => !empty($p['note']) ? mb_substr((string) $p['note'], 0, 255) : null,
            'created_by'     => Auth::id(),
        ]);
    }

    private static function qty(mixed $q): int
    {
        if (!is_numeric($q) || (int) $q != $q || (int) $q <= 0 || (int) $q > self::MAX_QTY) {
            throw new InventoryException(__('inv_qty_invalid', ['max' => num(self::MAX_QTY)]), 'quantity');
        }
        return (int) $q;
    }

    private static function batchNo(string $b): string
    {
        $b = strtoupper(trim($b));
        if ($b === '' || mb_strlen($b) > 100 || !preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $b)) {
            throw new InventoryException(__('inv_batch_no_invalid'), 'batch_no');
        }
        return $b;
    }

    /** @return array{0: ?string, 1: ?string} validated [manufacture_date, expiry_date] */
    private static function dates(?string $mfg, ?string $exp, string $type): array
    {
        $parse = static function (?string $d, string $field): ?string {
            $d = trim((string) $d);
            if ($d === '') {
                return null;
            }
            $dt = DateTime::createFromFormat('!Y-m-d', $d);
            if (!$dt || $dt->format('Y-m-d') !== $d) {
                throw new InventoryException(__('inv_date_invalid'), $field);
            }
            return $d;
        };
        $mfg = $parse($mfg, 'manufacture_date');
        $exp = $parse($exp, 'expiry_date');
        $today = date('Y-m-d');
        if ($mfg !== null && $mfg > $today) {
            throw new InventoryException(__('inv_mfg_future'), 'manufacture_date');
        }
        if ($mfg !== null && $exp !== null && $exp <= $mfg) {
            throw new InventoryException(__('inv_exp_before_mfg'), 'expiry_date');
        }
        if ($exp !== null && $exp < $today && in_array($type, ['stock_in', 'purchase'], true) && setting('block_expired_stock_in', true)) {
            throw new InventoryException(__('inv_already_expired'), 'expiry_date');
        }
        return [$mfg, $exp];
    }
}
