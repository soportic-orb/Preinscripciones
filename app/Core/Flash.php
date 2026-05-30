<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Mensajes flash para toasts (zona superior central).
 *
 * Tipos: success | error | warning | info. Se consumen una sola vez.
 */
final class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        $flashes = Session::get(self::KEY, []);
        $flashes[] = ['type' => $type, 'message' => $message];
        Session::set(self::KEY, $flashes);
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function pull(): array
    {
        $flashes = Session::get(self::KEY, []);
        Session::forget(self::KEY);
        return is_array($flashes) ? $flashes : [];
    }
}
