<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rate limiting sencillo basado en archivos (no requiere Redis), apto para
 * hosting compartido. Ventana deslizante por clave.
 */
final class RateLimit
{
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $dir = STORAGE_PATH . '/cache/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . md5($key) . '.json';
        $now = time();

        $data = ['count' => 0, 'reset' => $now + $windowSeconds];
        if (is_file($file)) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded) && ($decoded['reset'] ?? 0) > $now) {
                $data = $decoded;
            }
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return $data['count'] <= $maxAttempts;
    }

    public static function clear(string $key): void
    {
        $file = STORAGE_PATH . '/cache/ratelimit/' . md5($key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
