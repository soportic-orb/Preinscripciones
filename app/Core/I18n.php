<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Internacionalización propia basada en archivos PHP por idioma.
 *
 * Estructura: lang/{locale}/{grupo}.php devuelve un array de claves.
 * Uso: I18n::t('auth.login_title'), con reemplazo de :variables.
 * Idiomas obligatorios: es, ca, en, pt.
 */
final class I18n
{
    private static string $locale = 'es';
    private static string $fallback = 'es';
    /** @var array<string,array<string,mixed>> cache por grupo */
    private static array $loaded = [];

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, Config::locales(), true)) {
            self::$locale = $locale;
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function setFallback(string $locale): void
    {
        self::$fallback = $locale;
    }

    /**
     * Traduce una clave "grupo.clave[.subclave]" con reemplazos opcionales.
     *
     * @param array<string,string|int> $replace
     */
    public static function t(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? self::$locale;
        $value = self::lookup($key, $locale);
        if ($value === null && $locale !== self::$fallback) {
            $value = self::lookup($key, self::$fallback);
        }
        if ($value === null) {
            return $key; // visible para detectar cadenas sin traducir.
        }
        foreach ($replace as $k => $v) {
            $value = str_replace(':' . $k, (string) $v, $value);
        }
        return $value;
    }

    private static function lookup(string $key, string $locale): ?string
    {
        $parts = explode('.', $key);
        $group = array_shift($parts);
        $data = self::loadGroup($locale, $group);
        $cursor = $data;
        foreach ($parts as $segment) {
            if (is_array($cursor) && array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];
            } else {
                return null;
            }
        }
        return is_string($cursor) ? $cursor : null;
    }

    /** @return array<string,mixed> */
    private static function loadGroup(string $locale, string $group): array
    {
        $cacheKey = $locale . '/' . $group;
        if (isset(self::$loaded[$cacheKey])) {
            return self::$loaded[$cacheKey];
        }
        $file = LANG_PATH . '/' . $locale . '/' . $group . '.php';
        $data = is_file($file) ? (require $file) : [];
        if (!is_array($data)) {
            $data = [];
        }
        self::$loaded[$cacheKey] = $data;
        return $data;
    }

    /** Nombre nativo de cada idioma para el selector. */
    public static function nativeName(string $locale): string
    {
        return [
            'es' => 'Español',
            'ca' => 'Català',
            'en' => 'English',
            'pt' => 'Português',
        ][$locale] ?? $locale;
    }
}
