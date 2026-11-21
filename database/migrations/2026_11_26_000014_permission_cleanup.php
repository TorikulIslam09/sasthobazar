<?php
/**
 * Permissions = exactly what the system can do (config/modules.php):
 *  - adds vendor_types.view/create/edit, prescriptions.update_status and vendors.delete (soft delete / restore)
 *  - removes matrix entries that controlled nothing (vendors.create/export, coupons.export, refunds.create/export,
 *    prescriptions.export, commissions.export, vendor_payouts.export, couriers.create/delete, shipping.create, employees.delete)
 * New permissions are NOT given to any role: the Super Admin (who always has everything) grants them on Roles & Permissions.
 * Safe to re-run.
 */
declare(strict_types=1);

return function (PDO $pdo): void {
    Permission::sync($pdo, true); // role_permissions of removed keys go with them (FK cascade)
};
