<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Modo mantenimiento basado en un archivo marcador. Durante una actualización
 * OTA el sitio entra en mantenimiento; los administradores pueden seguir
 * navegando para supervisar el proceso.
 */
final class Maintenance
{
    private static function file(): string
    {
        return STORAGE_PATH . '/cache/maintenance.flag';
    }

    public static function isActive(): bool
    {
        return is_file(self::file());
    }

    public static function enable(string $reason = ''): void
    {
        @file_put_contents(self::file(), json_encode(['since' => date('c'), 'reason' => $reason]));
    }

    public static function disable(): void
    {
        if (is_file(self::file())) {
            @unlink(self::file());
        }
    }
}
