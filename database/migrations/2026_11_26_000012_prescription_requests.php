<?php
/**
 * "Order by prescription": customers (or guests) upload a prescription without choosing products first;
 * the pharmacy team calls them, prepares the order and marks the request.
 *  - prescriptions.request_type   checkout (attached at checkout) | order_request (uploaded on /prescription)
 *  - prescriptions.group_code     one request can have several files (pages); rows of one request share the code (RX...)
 *  - prescriptions.address / district_id / upazila_id   where to deliver (order requests)
 *  - prescriptions.request_status new | contacted | ordered | cancelled  (order requests only)
 *  - prescriptions.order_no       the order created from the request
 *  - setting prescription_order_enabled (group prescription)
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $has = static fn(string $t, string $c): bool => (bool) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'"
    )->fetchColumn();

    if (!$has('prescriptions', 'request_type')) {
        $pdo->exec("ALTER TABLE prescriptions
            ADD COLUMN request_type ENUM('checkout','order_request') NOT NULL DEFAULT 'checkout' AFTER guest_phone,
            ADD COLUMN group_code VARCHAR(20) NULL AFTER request_type,
            ADD COLUMN address VARCHAR(255) NULL AFTER notes,
            ADD COLUMN district_id INT UNSIGNED NULL AFTER address,
            ADD COLUMN upazila_id INT UNSIGNED NULL AFTER district_id,
            ADD COLUMN request_status ENUM('new','contacted','ordered','cancelled') NULL AFTER upazila_id,
            ADD COLUMN order_no VARCHAR(30) NULL AFTER request_status,
            ADD KEY idx_prescriptions_request (request_type, request_status),
            ADD KEY idx_prescriptions_group (group_code)");
    }

    $pdo->prepare("INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES ('prescription', 'prescription_order_enabled', '1', 'bool', 'prescription_order_enabled', 0)")->execute();
};
