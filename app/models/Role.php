<?php
declare(strict_types=1);

class Role extends Model
{
    protected static string $table = 'roles';

    /** Slugs of system roles that are not admin-panel roles. */
    public const NON_STAFF = ['vendor', 'customer'];
    public const SUPER_ADMIN = 'super-admin';

    /** Super Admin + employee roles, with user and permission counts. */
    public static function staffRoles(): array
    {
        return static::db()->fetchAll(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS users_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permissions_count
               FROM roles r
              WHERE r.slug NOT IN ('vendor', 'customer')
           ORDER BY r.is_system DESC, r.name"
        );
    }

    /** Roles that can be assigned to employees (non-system). */
    public static function assignable(): array
    {
        return static::db()->fetchAll('SELECT id, name, name_bn FROM roles WHERE is_system = 0 ORDER BY name');
    }

    public static function isAssignable(int $roleId): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM roles WHERE id = :id AND is_system = 0', ['id' => $roleId]);
    }

    /** @return int[] */
    public static function permissionIds(int $roleId): array
    {
        return array_map('intval', array_column(
            static::db()->fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = :r', ['r' => $roleId]),
            'permission_id'
        ));
    }

    /** @return string[] */
    public static function permissionKeys(int $roleId): array
    {
        return array_column(static::db()->fetchAll(
            'SELECT p.`key` FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r ORDER BY p.sort_order',
            ['r' => $roleId]
        ), 'key');
    }

    /** Replace a role's permissions (transaction) and audit the change. */
    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        $db = static::db();
        $before = self::permissionKeys($roleId);
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        $db->transaction(function (Database $db) use ($roleId, $permissionIds) {
            $db->query('DELETE FROM role_permissions WHERE role_id = :r', ['r' => $roleId]);
            if ($permissionIds) {
                $values = implode(',', array_fill(0, count($permissionIds), '(?, ?)'));
                $params = [];
                foreach ($permissionIds as $pid) {
                    $params[] = $roleId;
                    $params[] = $pid;
                }
                $db->query("INSERT INTO role_permissions (role_id, permission_id) VALUES {$values}", $params);
            }
        });

        $after = self::permissionKeys($roleId);
        $removed = array_values(array_diff($before, $after));
        $added = array_values(array_diff($after, $before));
        if ($added || $removed) {
            Audit::log('roles.permissions', 'roles', $roleId, ['removed' => $removed], ['added' => $added]);
        }
    }

    public static function slugExists(string $slug, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM roles WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $exceptId]);
    }

    public static function uniqueSlug(string $name, int $exceptId = 0): string
    {
        $base = Helpers::slugify($name);
        $slug = $base;
        $i = 2;
        while (self::slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public static function usersCount(int $roleId): int
    {
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM users WHERE role_id = :r AND deleted_at IS NULL', ['r' => $roleId]);
    }

    public static function label(array $r): string
    {
        return tr($r['name_bn'] ?? null, $r['name'] ?? '');
    }
}
