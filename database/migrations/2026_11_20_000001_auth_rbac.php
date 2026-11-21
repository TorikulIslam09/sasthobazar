<?php
/**
 * Step 2 - Authentication & RBAC
 *  - rate_limits table (login throttling per IP / per account)
 *  - audit_logs: index on created_at
 *  - permissions: sync with config/modules.php (module x view/create/edit/delete/export + special actions)
 *    * old "<module>.manage" permissions are replaced by view/create/edit/delete and role grants are carried over
 *    * roles that had a whole module keep the whole module (incl. new actions such as export)
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `rate_limits` (
            `key`      CHAR(64) NOT NULL COMMENT "sha256 of the limiter key",
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `reset_at` DATETIME NOT NULL,
            PRIMARY KEY (`key`),
            KEY `idx_rate_limits_reset` (`reset_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $hasIdx = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_audit_created'"
    )->fetchColumn();
    if (!(int) $hasIdx) {
        $pdo->exec('ALTER TABLE `audit_logs` ADD INDEX `idx_audit_created` (`created_at`)');
    }

    // Snapshot BEFORE sync: roles holding every permission of a module, and roles holding "<module>.manage"
    $fullModules = $pdo->query(
        'SELECT rp.role_id, p.module
           FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
       GROUP BY rp.role_id, p.module
         HAVING COUNT(*) = (SELECT COUNT(*) FROM permissions p2 WHERE p2.module = p.module)'
    )->fetchAll(PDO::FETCH_ASSOC);
    $manageGrants = $pdo->query(
        "SELECT rp.role_id, p.module FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.action = 'manage'"
    )->fetchAll(PDO::FETCH_ASSOC);

    Permission::sync($pdo, true); // adds new keys, removes obsolete ones (e.g. *.manage)

    $grant = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE module = ?');
    foreach ($fullModules as $r) {
        $grant->execute([$r['role_id'], $r['module']]);
    }
    $grantCrud = $pdo->prepare(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT ?, id FROM permissions WHERE module = ? AND action IN ('view','create','edit','delete')"
    );
    foreach ($manageGrants as $r) {
        $grantCrud->execute([$r['role_id'], $r['module']]);
    }

    // Super Admin role: everything
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.slug = 'super-admin'");

    // Manager: everything except staff/roles/audit/settings.edit (incl. new export permissions)
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE r.slug = 'manager' AND p.module NOT IN ('employees','roles','audit_logs') AND p.`key` <> 'settings.edit'");

    // Accountant: financial exports
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r JOIN permissions p
                 WHERE r.slug = 'accountant'
                   AND p.`key` IN ('orders.export','commissions.export','payments.export','refunds.export','vendor_payouts.export','reports.export')");
};
