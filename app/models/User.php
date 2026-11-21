<?php
declare(strict_types=1);

class User extends Model
{
    protected static string $table = 'users';

    public const STAFF_TYPES = ['super_admin', 'employee'];

    public static function findByEmail(string $email): ?array
    {
        return static::db()->fetch('SELECT * FROM users WHERE email = :e AND deleted_at IS NULL', ['e' => strtolower(trim($email))]);
    }

    public static function findByPhone(string $phone): ?array
    {
        return static::db()->fetch('SELECT * FROM users WHERE phone = :p AND deleted_at IS NULL', ['p' => Auth::normalizePhone($phone)]);
    }

    public static function phoneExists(string $phone, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM users WHERE phone = :p AND id <> :id', ['p' => Auth::normalizePhone($phone), 'id' => $exceptId]);
    }

    public static function emailExists(string $email, int $exceptId = 0): bool
    {
        return (bool) static::db()->fetchColumn('SELECT 1 FROM users WHERE email = :e AND id <> :id', ['e' => strtolower(trim($email)), 'id' => $exceptId]);
    }

    public static function createCustomer(string $name, string $phone, ?string $email, string $password): int
    {
        $roleId = static::db()->fetchColumn("SELECT id FROM roles WHERE slug = 'customer'");
        return static::create([
            'role_id'       => $roleId ? (int) $roleId : null,
            'user_type'     => 'customer',
            'name'          => $name,
            'phone'         => Auth::normalizePhone($phone),
            'email'         => $email ? strtolower(trim($email)) : null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'lang'          => lang(),
            'status'        => 'active',
        ]);
    }

    /* ---------------- Staff (super admins + employees) ---------------- */

    /** Builds WHERE for the employees list. */
    private static function staffWhere(array $f): array
    {
        $where = ["u.user_type IN ('super_admin','employee')", 'u.deleted_at IS NULL'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR u.phone LIKE :q3)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (!empty($f['role_id'])) {
            $where[] = 'u.role_id = :role';
            $params['role'] = (int) $f['role_id'];
        }
        if (in_array($f['status'] ?? '', ['active', 'inactive'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $f['status'];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function countStaff(array $filters): int
    {
        [$w, $p] = self::staffWhere($filters);
        return (int) static::db()->fetchColumn("SELECT COUNT(*) FROM users u WHERE {$w}", $p);
    }

    public static function staff(array $filters, int $limit, int $offset): array
    {
        [$w, $p] = self::staffWhere($filters);
        return static::db()->fetchAll(
            "SELECT u.id, u.name, u.email, u.phone, u.user_type, u.status, u.role_id, u.last_login_at, u.created_at,
                    u.locked_until, r.name AS role_name, r.name_bn AS role_name_bn
               FROM users u LEFT JOIN roles r ON r.id = u.role_id
              WHERE {$w}
           ORDER BY u.user_type = 'super_admin' DESC, u.name
              LIMIT {$limit} OFFSET {$offset}",
            $p
        );
    }

    public static function findStaff(int $id): ?array
    {
        return static::db()->fetch(
            "SELECT * FROM users WHERE id = :id AND user_type IN ('super_admin','employee') AND deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public static function activeSuperAdminCount(): int
    {
        return (int) static::db()->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE user_type = 'super_admin' AND status = 'active' AND deleted_at IS NULL"
        );
    }
}
