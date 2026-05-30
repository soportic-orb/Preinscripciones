<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Actualiza pares clave=valor en config/.env preservando el resto del archivo.
 *
 * Se usa para que el administrador edite secretos de integraciones (SMTP,
 * Stripe, Git/OTA) desde el panel sin tocar el servidor. Mantiene permisos 0600.
 */
final class EnvWriter
{
    /** @param array<string,string> $pairs */
    public static function update(array $pairs): bool
    {
        $path = CONFIG_PATH . '/.env';
        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        $remaining = $pairs;

        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key] = explode('=', $line, 2);
            $key = trim($key);
            if (array_key_exists($key, $remaining)) {
                $lines[$i] = $key . '=' . self::quote($remaining[$key]);
                unset($remaining[$key]);
            }
        }

        // Añadir las claves que no existían.
        foreach ($remaining as $key => $value) {
            $lines[] = $key . '=' . self::quote($value);
        }

        $ok = @file_put_contents($path, implode("\n", $lines) . "\n") !== false;
        if ($ok) {
            @chmod($path, 0600);
            // Refrescar el entorno en memoria para la petición actual.
            Env::load($path);
        }
        return $ok;
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/[\s"#]/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }
        return $value;
    }
}
