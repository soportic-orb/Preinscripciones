<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Manejo centralizado de errores y excepciones.
 */
final class ErrorHandler
{
    public static function handleException(Throwable $e): void
    {
        Logger::error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $debug = Config::app()['debug'];
        http_response_code(500);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        if ($debug) {
            echo '<h1>Error</h1><pre>' . htmlspecialchars($e->getMessage()) . "\n\n"
                . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n"
                . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        } else {
            echo '<h1>500 — Error interno</h1><p>Ha ocurrido un error. Inténtalo de nuevo más tarde.</p>';
        }
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            Logger::error('Fatal: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
        }
    }
}
