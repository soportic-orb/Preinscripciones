<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\I18n;

/**
 * Funciones de ayuda globales disponibles en vistas y controladores.
 */

if (!function_exists('e')) {
    /** Escapa para salida HTML segura (anti-XSS). */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    /** Traduce una clave i18n. @param array<string,string|int> $replace */
    function __(string $key, array $replace = []): string
    {
        return I18n::t($key, $replace);
    }
}

if (!function_exists('url')) {
    /** Construye una URL absoluta a partir de la URL base configurada. */
    function url(string $path = ''): string
    {
        $base = Config::app()['url'];
        $path = '/' . ltrim($path, '/');
        return $base !== '' ? $base . $path : $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    /** Recupera el valor antiguo de un campo tras un error de validación. */
    function old(string $key, string $default = ''): string
    {
        $old = App\Core\Session::get('_old', []);
        return is_array($old) && isset($old[$key]) ? (string) $old[$key] : $default;
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string
    {
        return I18n::locale();
    }
}
