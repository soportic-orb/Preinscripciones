<?php

/**
 * Router para el servidor embebido de PHP (php -S). Sirve archivos estáticos
 * existentes y delega el resto al front controller. No se usa en producción.
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;

// Servir el instalador con su propio índice.
if (str_starts_with($uri, '/install')) {
    $installFile = __DIR__ . $uri;
    if (is_file($installFile)) {
        return false;
    }
    require __DIR__ . '/install/index.php';
    return true;
}

if ($uri !== '/' && is_file($file) && !is_dir($file)) {
    return false; // El servidor embebido sirve el archivo estático tal cual.
}

require __DIR__ . '/index.php';
