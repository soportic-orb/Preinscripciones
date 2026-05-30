<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuración en caliente persistida en la tabla settings.
 *
 * A diferencia de Env (.env, estático y con secretos), Settings guarda
 * configuración editable desde el panel: formas de pago activas, idioma por
 * defecto editable, opciones de la plataforma, etc. Cachea en memoria por petición.
 */
final class Settings
{
    /** @var array<string,array<string,string>>|null */
    private static ?array $cache = null;

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            $rows = Database::instance()->fetchAll('SELECT group_name, key_name, value FROM {settings}');
            foreach ($rows as $row) {
                self::$cache[$row['group_name']][$row['key_name']] = (string) ($row['value'] ?? '');
            }
        } catch (\Throwable) {
            // Tabla aún no migrada: dejar caché vacía.
        }
    }

    public static function get(string $group, string $key, ?string $default = null): ?string
    {
        self::load();
        return self::$cache[$group][$key] ?? $default;
    }

    public static function bool(string $group, string $key, bool $default = false): bool
    {
        $v = self::get($group, $key);
        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<string,string> */
    public static function group(string $group): array
    {
        self::load();
        return self::$cache[$group] ?? [];
    }

    public static function set(string $group, string $key, ?string $value): void
    {
        $db = Database::instance();
        $exists = $db->scalar(
            'SELECT id FROM {settings} WHERE group_name = ? AND key_name = ?',
            [$group, $key],
        );
        if ($exists) {
            $db->update('settings', ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int) $exists]);
        } else {
            $db->insert('settings', [
                'group_name' => $group,
                'key_name' => $key,
                'value' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        self::$cache[$group][$key] = (string) $value;
    }

    /** @param array<string,string|null> $pairs */
    public static function setMany(string $group, array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::set($group, $key, $value);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
