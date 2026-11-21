<?php
declare(strict_types=1);

class Location extends Model
{
    protected static string $table = 'locations';

    public static function divisions(): array
    {
        return static::db()->fetchAll(
            "SELECT id, name_en, name_bn FROM locations WHERE type = 'division' AND is_active = 1 ORDER BY name_en"
        );
    }

    public static function children(int $parentId): array
    {
        return static::db()->fetchAll(
            'SELECT id, name_en, name_bn, type FROM locations WHERE parent_id = :p AND is_active = 1 ORDER BY name_en',
            ['p' => $parentId]
        );
    }

    public static function countByType(): array
    {
        $rows = static::db()->fetchAll('SELECT type, COUNT(*) c FROM locations GROUP BY type');
        return array_column($rows, 'c', 'type');
    }
}
