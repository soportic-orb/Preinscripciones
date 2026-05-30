<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Control de acceso basado en roles (RBAC).
 *
 * Roles: owner (super-admin), admin, gestor, estudiante.
 * - owner/admin: configuran toda la plataforma.
 * - gestor: gestiona el proceso, sin acceso a configuración de plataforma.
 * - estudiante: solo su panel.
 */
final class Rbac
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const GESTOR = 'gestor';
    public const ESTUDIANTE = 'estudiante';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [self::OWNER, self::ADMIN, self::GESTOR, self::ESTUDIANTE];
    }

    /** @return array<int,string> roles con acceso al panel de gestión */
    public static function staffRoles(): array
    {
        return [self::OWNER, self::ADMIN, self::GESTOR];
    }

    /** @return array<int,string> roles con acceso a la configuración de plataforma */
    public static function adminRoles(): array
    {
        return [self::OWNER, self::ADMIN];
    }

    /** @param array<int,string> $roles */
    public static function hasAnyRole(User $user, array $roles): bool
    {
        return in_array($user->role, $roles, true);
    }

    public static function isStaff(User $user): bool
    {
        return self::hasAnyRole($user, self::staffRoles());
    }

    public static function isAdmin(User $user): bool
    {
        return self::hasAnyRole($user, self::adminRoles());
    }

    public static function isStudent(User $user): bool
    {
        return $user->role === self::ESTUDIANTE;
    }
}
