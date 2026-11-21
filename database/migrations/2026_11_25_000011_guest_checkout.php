<?php
/**
 * Guest checkout
 *  - otp_codes: one-time codes for mobile (SMS) / email verification (codes are stored hashed)
 *  - orders.is_guest / orders.contact_verified: guest orders and whether the contact was verified by OTP
 *    (only verified guest orders are linked to an account later)
 *  - settings (group checkout): guest_checkout_enabled, guest_default_contact (phone|email), guest_otp_required
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $has = static fn(string $t, string $c): bool => (bool) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'"
    )->fetchColumn();

    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        channel ENUM('sms','email') NOT NULL,
        recipient VARCHAR(190) NOT NULL,
        purpose VARCHAR(30) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_otp_lookup (recipient, purpose, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!$has('orders', 'is_guest')) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN is_guest TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id,
                    ADD COLUMN contact_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = mobile verified by OTP, 2 = email verified' AFTER is_guest,
                    ADD KEY idx_orders_guest_phone (is_guest, customer_phone), ADD KEY idx_orders_guest_email (is_guest, customer_email)");
    }

    // prescriptions uploaded by guests (no account yet): owner is the verified mobile, linked later like orders
    if (!$has('prescriptions', 'guest_phone')) {
        $fk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn();
        if ($fk) {
            $pdo->exec("ALTER TABLE prescriptions DROP FOREIGN KEY `{$fk}`");
        }
        $pdo->exec("ALTER TABLE prescriptions MODIFY user_id BIGINT UNSIGNED NULL,
                    ADD COLUMN guest_name VARCHAR(150) NULL AFTER user_id,
                    ADD COLUMN guest_phone VARCHAR(20) NULL AFTER guest_name,
                    ADD KEY idx_prescriptions_guest_phone (guest_phone)");
        if ($fk) {
            $pdo->exec("ALTER TABLE prescriptions ADD CONSTRAINT `{$fk}` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE");
        }
    }

    $ins = $pdo->prepare("INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`, `label`, `is_public`) VALUES ('checkout', ?, ?, ?, ?, 0)");
    foreach ([['guest_checkout_enabled', '1', 'bool'], ['guest_default_contact', 'phone', 'string'], ['guest_otp_required', '1', 'bool']] as [$k, $v, $t]) {
        $ins->execute([$k, $v, $t, $k]);
    }
};
