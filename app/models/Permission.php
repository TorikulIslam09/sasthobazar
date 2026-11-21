<?php
declare(strict_types=1);

class Permission extends Model
{
    protected static string $table = 'permissions';

    /**
     * Sync the `permissions` table with config/modules.php.
     * Inserts missing permissions, refreshes labels/order.
     * @return array{added:int, obsolete:string[], removed:int}
     */
    public static function sync(PDO $pdo, bool $removeObsolete = false): array
    {
        $reg = config('modules');
        $stmt = $pdo->prepare(
            'INSERT INTO permissions (module, action, `key`, label_en, label_bn, sort_order) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE module = VALUES(module), action = VALUES(action), label_en = VALUES(label_en),
                                     label_bn = VALUES(label_bn), sort_order = VALUES(sort_order)'
        );
        $sort = 0;
        $added = 0;
        $keys = [];
        foreach ($reg['modules'] as $module => $def) {
            foreach ($def['actions'] as $action) {
                $sort++;
                [$aBn, $aEn] = $reg['actions'][$action] ?? [$action, ucfirst($action)];
                $key = $module . '.' . $action;
                $keys[] = $key;
                $stmt->execute([$module, $action, $key, $aEn . ' ' . $def['en'], $def['bn'] . ' ' . $aBn, $sort]);
                if ($stmt->rowCount() === 1) {
                    $added++;
                }
            }
        }

        $in = implode(',', array_fill(0, count($keys), '?'));
        $q = $pdo->prepare("SELECT `key` FROM permissions WHERE `key` NOT IN ($in)");
        $q->execute($keys);
        $obsolete = $q->fetchAll(PDO::FETCH_COLUMN);

        $removed = 0;
        if ($removeObsolete && $obsolete) {
            $del = $pdo->prepare("DELETE FROM permissions WHERE `key` NOT IN ($in)"); // role_permissions cascade
            $del->execute($keys);
            $removed = $del->rowCount();
        }
        return ['added' => $added, 'obsolete' => $obsolete, 'removed' => $removed];
    }

    /**
     * Data for the role permission matrix, in registry order:
     * [groupKey => ['label' => ..., 'modules' => [module => ['label','icon','actions' => [action => ['id','key','label']]]]]]
     */
    public static function matrix(): array
    {
        $reg = config('modules');
        $rows = static::db()->fetchAll('SELECT id, `key`, module, action FROM permissions');
        $byKey = array_column($rows, null, 'key');

        $out = [];
        foreach ($reg['modules'] as $module => $def) {
            $actions = [];
            foreach ($def['actions'] as $action) {
                $key = $module . '.' . $action;
                if (!isset($byKey[$key])) {
                    continue; // not synced yet
                }
                [$aBn, $aEn] = $reg['actions'][$action] ?? [$action, $action];
                $actions[$action] = ['id' => (int) $byKey[$key]['id'], 'key' => $key, 'label' => tr($aBn, $aEn)];
            }
            if (!$actions) {
                continue;
            }
            $g = $def['group'];
            $out[$g]['label'] ??= tr($reg['groups'][$g][0] ?? $g, $reg['groups'][$g][1] ?? $g);
            $out[$g]['modules'][$module] = ['label' => tr($def['bn'], $def['en']), 'icon' => $def['icon'], 'actions' => $actions];
        }
        return $out;
    }

    /** @return array<int,string> id => key */
    public static function idKeyMap(): array
    {
        return array_column(static::db()->fetchAll('SELECT id, `key` FROM permissions'), 'key', 'id');
    }
}
