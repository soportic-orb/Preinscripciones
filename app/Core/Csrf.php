<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protección CSRF basada en token por sesión, comparación en tiempo constante.
 */
final class Csrf
{
    private const KEY = '_csrf_token';
    public const FIELD = '_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return sprintf('<input type="hidden" name="%s" value="%s">', self::FIELD, htmlspecialchars(self::token(), ENT_QUOTES));
    }

    public static function check(?string $token): bool
    {
        $stored = Session::get(self::KEY);
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }

    /** Aborta con 419 si el token no es válido. */
    public static function verify(Request $request): void
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input(self::FIELD)
                ?? ($request->server['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!self::check(is_string($token) ? $token : null)) {
                if ($request->isAjax()) {
                    Response::json(['error' => 'CSRF token inválido'], 419);
                }
                Response::html('<h1>419 — Sesión expirada</h1><p>Vuelve atrás y reintenta.</p>', 419);
            }
        }
    }
}
