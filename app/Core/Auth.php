<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Autenticación basada en sesión. Gestiona login, logout y el usuario actual.
 */
final class Auth
{
    private const SESSION_KEY = '_auth_user_id';
    private const PENDING_2FA = '_auth_pending_2fa';
    private static ?User $cached = null;

    public static function attempt(string $email, string $password): ?User
    {
        $user = User::findByEmail($email);
        if ($user === null || $user->is_active !== 1) {
            return null;
        }
        if (!Hash::verify($password, $user->password_hash)) {
            return null;
        }
        if (Hash::needsRehash($user->password_hash)) {
            $user->save(['password_hash' => Hash::make($password)]);
        }
        return $user;
    }

    public static function login(User $user): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, $user->id);
        Session::forget(self::PENDING_2FA);
        self::$cached = $user;
        I18n::setLocale($user->locale);
    }

    /** Marca al usuario como pendiente de 2FA (segundo factor TOTP). */
    public static function markPending2fa(int $userId): void
    {
        Session::set(self::PENDING_2FA, $userId);
    }

    public static function pending2faUserId(): ?int
    {
        $id = Session::get(self::PENDING_2FA);
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public static function logout(): void
    {
        self::$cached = null;
        Session::forget(self::SESSION_KEY);
        Session::forget(self::PENDING_2FA);
        Session::regenerate();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);
        return is_numeric($id) ? (int) $id : null;
    }

    public static function user(): ?User
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $user = User::findById($id);
        if ($user === null || $user->is_active !== 1) {
            self::logout();
            return null;
        }
        self::$cached = $user;
        return $user;
    }
}
