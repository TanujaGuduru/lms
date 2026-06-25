<?php

declare(strict_types=1);

namespace App\Core;

class Setting
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function loadAll(): void
    {
        if (static::$loaded) return;

        try {
            $db   = Database::getInstance();
            $rows = $db->select("SELECT `group`, `key`, `value` FROM settings");

            foreach ($rows as $row) {
                static::$cache["{$row['group']}.{$row['key']}"] = $row['value'];
            }
        } catch (\Throwable) {
            // DB not ready yet — use defaults silently
        }

        static::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!static::$loaded) static::loadAll();
        return static::$cache[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        [$group, $k] = explode('.', $key, 2);

        $db = Database::getInstance();
        $db->query(
            "UPDATE settings SET `value` = ?, `updated_at` = NOW() WHERE `group` = ? AND `key` = ?",
            [$value, $group, $k]
        );

        static::$cache[$key] = $value;
    }

    public static function group(string $group): array
    {
        if (!static::$loaded) static::loadAll();

        $result = [];
        $prefix = $group . '.';

        foreach (static::$cache as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $result[substr($key, strlen($prefix))] = $value;
            }
        }

        return $result;
    }

    public static function reload(): void
    {
        static::$loaded = false;
        static::$cache  = [];
        static::loadAll();
    }

    public static function isMaintenanceMode(): bool
    {
        return (bool)(int)static::get('general.maintenance_mode', 0);
    }
}
