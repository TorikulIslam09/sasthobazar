<?php
declare(strict_types=1);

/** Save settings (insert missing keys), audit the changes and refresh the settings cache. */
final class Setting
{
    /**
     * @param array<string, array{0: string, 1: string, 2: string}> $values key => [value, group, type]
     * @return array changed keys => new value
     */
    public static function saveMany(array $values, bool $secretMask = true): array
    {
        $db = Database::getInstance();
        $old = $new = [];
        foreach ($values as $k => [$v, $group, $type]) {
            $cur = $db->fetchColumn('SELECT `value` FROM settings WHERE `key` = :k', ['k' => $k]);
            if ($cur === false) {
                $db->insert('settings', ['group' => $group, 'key' => $k, 'value' => $v, 'type' => $type, 'label' => $k, 'is_public' => 0, 'updated_by' => Auth::id()]);
            } elseif ((string) $cur !== (string) $v) {
                $db->update('settings', ['value' => $v, 'updated_by' => Auth::id()], '`key` = :k', ['k' => $k]);
            } else {
                continue;
            }
            $secret = $secretMask && preg_match('/password|api_key|secret/', $k);
            $old[$k] = $secret ? '***' : $cur;
            $new[$k] = $secret ? '***' : $v;
        }
        Helpers::clearSettingsCache();
        if ($new) {
            Audit::log('settings.update', 'settings', null, $old, $new);
        }
        return $new;
    }
}
