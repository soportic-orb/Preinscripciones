<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Middlewares de la aplicación: auth, rbac por rol, csrf, locale, throttle.
 */
final class Middleware
{
    public static function run(string $name, Request $request): void
    {
        // Soporta parámetros: "role:admin,gestor" o "throttle:5,60".
        [$key, $argString] = array_pad(explode(':', $name, 2), 2, '');
        $args = $argString === '' ? [] : explode(',', $argString);

        match ($key) {
            'auth' => self::auth($request),
            'guest' => self::guest($request),
            'role' => self::role($request, $args),
            'csrf' => Csrf::verify($request),
            'throttle' => self::throttle($request, $args),
            default => throw new \RuntimeException("Middleware desconocido: {$key}"),
        };
    }

    private static function auth(Request $request): void
    {
        if (!Auth::check()) {
            if ($request->isAjax()) {
                Response::json(['error' => 'No autenticado'], 401);
            }
            Flash::warning(__('auth.login_required'));
            Response::redirect('/login');
        }
    }

    private static function guest(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect('/panel');
        }
    }

    /** @param array<int,string> $roles */
    private static function role(Request $request, array $roles): void
    {
        self::auth($request);
        $user = Auth::user();
        if ($user === null || !Rbac::hasAnyRole($user, $roles)) {
            if ($request->isAjax()) {
                Response::json(['error' => 'No autorizado'], 403);
            }
            Response::html(View::render('errors/403', [], 'layouts/app'), 403);
        }
    }

    /** @param array<int,string> $args [maxIntentos, ventanaSegundos] */
    private static function throttle(Request $request, array $args): void
    {
        $max = (int) ($args[0] ?? 10);
        $window = (int) ($args[1] ?? 60);
        $bucket = 'route:' . md5($request->path()) . ':' . $request->ip();
        if (!RateLimit::attempt($bucket, $max, $window)) {
            if ($request->isAjax()) {
                Response::json(['error' => __('common.too_many_requests')], 429);
            }
            Response::html('<h1>429 — ' . e(__('common.too_many_requests')) . '</h1>', 429);
        }
    }
}
