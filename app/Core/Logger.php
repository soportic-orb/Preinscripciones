<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro de logs en archivos dentro de storage/logs.
 */
final class Logger
{
    public static function write(string $channel, string $level, string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        @file_put_contents($dir . '/' . $channel . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write($channel, 'info', $message, $context);
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::write($channel, 'error', $message, $context);
    }
}
