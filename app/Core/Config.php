<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Acceso a la configuración derivada del entorno y de la base de datos (settings).
 *
 * Los valores estáticos provienen de .env; los valores configurables en caliente
 * (formas de pago, textos, etc.) se gestionan en la clase Settings (tabla settings).
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $cache = [];

    public static function app(): array
    {
        return [
            'name' => Env::get('APP_NAME', 'IEM Preinscripciones'),
            'env' => Env::get('APP_ENV', 'production'),
            'debug' => Env::bool('APP_DEBUG', false),
            'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
            'key' => Env::get('APP_KEY', ''),
            'locale' => Env::get('APP_LOCALE', 'es'),
            'timezone' => Env::get('APP_TIMEZONE', 'Europe/Madrid'),
            'force_https' => Env::bool('FORCE_HTTPS', true),
            'max_upload_mb' => Env::int('MAX_UPLOAD_MB', 50),
        ];
    }

    public static function db(): array
    {
        return [
            'driver' => Env::get('DB_DRIVER', 'mysql'),
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => Env::int('DB_PORT', 3306),
            'name' => Env::get('DB_NAME', ''),
            'user' => Env::get('DB_USER', ''),
            'pass' => Env::get('DB_PASS', ''),
            'prefix' => Env::get('DB_PREFIX', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    public static function mail(): array
    {
        return [
            'host' => Env::get('MAIL_HOST', ''),
            'port' => Env::int('MAIL_PORT', 587),
            'user' => Env::get('MAIL_USER', ''),
            'pass' => Env::get('MAIL_PASS', ''),
            'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
            'from_address' => Env::get('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
            'from_name' => Env::get('MAIL_FROM_NAME', 'IEM Preinscripciones'),
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$cache[$key] = $value;
    }

    /** Lista de idiomas soportados por la plataforma. */
    public static function locales(): array
    {
        return ['es', 'ca', 'en', 'pt'];
    }
}
