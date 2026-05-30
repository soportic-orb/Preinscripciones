<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Motor de plantillas mínimo basado en archivos PHP.
 *
 * Las vistas viven en /views. Soporta layout, secciones y escape automático
 * mediante el helper e(). El idioma activo se inyecta para traducciones.
 */
final class View
{
    private static ?string $layout = null;
    /** @var array<string,string> */
    private static array $sections = [];
    private static ?string $currentSection = null;

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        self::$layout = $layout;
        self::$sections = [];

        $content = self::capture($template, $data);

        if (self::$layout === null) {
            return $content;
        }

        // El contenido principal queda disponible como sección "content".
        self::$sections['content'] = $content;
        return self::capture(self::$layout, $data);
    }

    /** @param array<string,mixed> $data */
    private static function capture(string $template, array $data): string
    {
        $file = VIEW_PATH . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista no encontrada: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function startSection(string $name): void
    {
        self::$currentSection = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        if (self::$currentSection !== null) {
            self::$sections[self::$currentSection] = (string) ob_get_clean();
            self::$currentSection = null;
        }
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function yield(string $name = 'content'): string
    {
        return self::$sections[$name] ?? '';
    }
}
