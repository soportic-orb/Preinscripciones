<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Flash;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Controlador base con utilidades de render y redirección.
 */
abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): never
    {
        Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $to): never
    {
        Response::redirect($to);
    }

    /** Guarda los valores antiguos del formulario para repintarlos tras un error. */
    protected function withOld(array $data): void
    {
        Session::set('_old', $data);
    }

    protected function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $path = $referer !== '' ? (parse_url($referer, PHP_URL_PATH) ?: $fallback) : $fallback;
        Response::redirect($path);
    }

    protected function flashErrors(array $errors): void
    {
        foreach ($errors as $messages) {
            foreach ((array) $messages as $message) {
                Flash::error($message);
            }
        }
    }
}
