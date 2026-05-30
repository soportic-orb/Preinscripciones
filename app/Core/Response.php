<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta HTTP: helpers para HTML, JSON y redirecciones.
 */
final class Response
{
    public static function html(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        // Solo rutas internas para evitar open redirect.
        if (!str_starts_with($to, '/')) {
            $to = '/';
        }
        http_response_code($status);
        header('Location: ' . $to);
        exit;
    }

    public static function noContent(int $status = 204): never
    {
        http_response_code($status);
        exit;
    }
}
