<?php
declare(strict_types=1);

/**
 * Customer delivery addresses. Every query is scoped to the owner (user_id).
 * Exactly one address per customer is the default (the first one automatically; deleting the default
 * promotes the newest remaining address).
 */
class Address extends Model
{
    protected static string $table = 'addresses';

    public const MAX_PER_USER = 10;
    public const LABELS = ['home', 'office', 'other'];

    public static function forUser(int $userId): array
    {
        return static::db()->fetchAll(
            'SELECT a.*, dv.name_en AS division_en, dv.name_bn AS division_bn,
                    ds.name_en AS district_en, ds.name_bn AS district_bn,
                    up.name_en AS upazila_en, up.name_bn AS upazila_bn
               FROM addresses a
          LEFT JOIN locations dv ON dv.id = a.division_id
          LEFT JOIN locations ds ON ds.id = a.district_id
          LEFT JOIN locations up ON up.id = a.upazila_id
              WHERE a.user_id = :u
           ORDER BY a.is_default DESC, a.id DESC',
            ['u' => $userId]
        );
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        return static::db()->fetch('SELECT * FROM addresses WHERE id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
    }

    public static function countForUser(int $userId): int
    {
        return (int) static::db()->fetchColumn('SELECT COUNT(*) FROM addresses WHERE user_id = :u', ['u' => $userId]);
    }

    /** "Upazila, District, Division" in the current language. */
    public static function areaLine(array $a): string
    {
        $parts = [];
        foreach (['upazila', 'district', 'division'] as $lvl) {
            $v = tr($a[$lvl . '_bn'] ?? null, $a[$lvl . '_en'] ?? null);
            if ($v !== '') {
                $parts[] = $v;
            }
        }
        return implode(', ', $parts);
    }

    public static function labelText(?string $label): string
    {
        return in_array($label, self::LABELS, true) ? __('addr_label_' . $label) : (string) $label;
    }

    /**
     * Check that division > district > upazila form a real chain.
     * @return array field => error
     */
    public static function validateLocations(?int $division, ?int $district, ?int $upazila): array
    {
        $db = static::db();
        $errors = [];
        $get = static fn(?int $id) => $id ? $db->fetch('SELECT id, parent_id, type FROM locations WHERE id = :id AND is_active = 1', ['id' => $id]) : null;
        $dv = $get($division);
        if (!$dv || $dv['type'] !== 'division') {
            $errors['division_id'] = __('v_required');
            return $errors;
        }
        $ds = $get($district);
        if (!$ds || $ds['type'] !== 'district' || (int) $ds['parent_id'] !== (int) $dv['id']) {
            $errors['district_id'] = __('v_required');
            return $errors;
        }
        if ($upazila) {
            $up = $get($upazila);
            if (!$up || (int) $up['parent_id'] !== (int) $ds['id']) {
                $errors['upazila_id'] = __('v_in');
            }
        }
        return $errors;
    }

    /** Create (id null) or update an address. Handles the default flag in one transaction. */
    public static function save(int $userId, ?int $id, array $data, bool $makeDefault): int
    {
        return static::db()->transaction(static function (Database $db) use ($userId, $id, $data, $makeDefault): int {
            // lock this customer's address rows so concurrent saves keep exactly one default
            $db->fetchAll('SELECT id FROM addresses WHERE user_id = :u FOR UPDATE', ['u' => $userId]);
            $isFirst = !$db->fetchColumn('SELECT 1 FROM addresses WHERE user_id = :u' . ($id ? ' AND id <> :id' : '') . ' LIMIT 1',
                $id ? ['u' => $userId, 'id' => $id] : ['u' => $userId]);
            $default = $makeDefault || $isFirst;
            if ($default) {
                $db->query('UPDATE addresses SET is_default = 0 WHERE user_id = :u', ['u' => $userId]);
            }
            $data['is_default'] = $default ? 1 : 0;
            if ($id) {
                $old = self::findForUser($id, $userId);
                if (!$old) {
                    throw new RuntimeException('Address not found');
                }
                if (!$default && (int) $old['is_default'] === 1) {
                    $data['is_default'] = 1; // cannot un-default the only default; pick another one instead
                }
                $db->update('addresses', $data, 'id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
                Audit::log('addresses.update', 'addresses', $id);
                return $id;
            }
            $newId = $db->insert('addresses', $data + ['user_id' => $userId]);
            Audit::log('addresses.create', 'addresses', $newId);
            return $newId;
        });
    }

    public static function setDefault(int $id, int $userId): bool
    {
        return static::db()->transaction(static function (Database $db) use ($id, $userId): bool {
            $db->fetchAll('SELECT id FROM addresses WHERE user_id = :u FOR UPDATE', ['u' => $userId]);
            if (!self::findForUser($id, $userId)) {
                return false;
            }
            $db->query('UPDATE addresses SET is_default = (id = :id) WHERE user_id = :u', ['id' => $id, 'u' => $userId]);
            return true;
        });
    }

    public static function remove(int $id, int $userId): bool
    {
        return static::db()->transaction(static function (Database $db) use ($id, $userId): bool {
            $db->fetchAll('SELECT id FROM addresses WHERE user_id = :u FOR UPDATE', ['u' => $userId]);
            $a = self::findForUser($id, $userId);
            if (!$a) {
                return false;
            }
            $db->delete('addresses', 'id = :id AND user_id = :u', ['id' => $id, 'u' => $userId]);
            if ((int) $a['is_default'] === 1) {
                $next = $db->fetchColumn('SELECT id FROM addresses WHERE user_id = :u ORDER BY id DESC LIMIT 1', ['u' => $userId]);
                if ($next) {
                    $db->update('addresses', ['is_default' => 1], 'id = :id', ['id' => (int) $next]);
                }
            }
            Audit::log('addresses.delete', 'addresses', $id, ['recipient_name' => $a['recipient_name'], 'address_line' => $a['address_line']]);
            return true;
        });
    }
}
