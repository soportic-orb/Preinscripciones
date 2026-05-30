<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Hash;

/**
 * Modelo de usuario. Representa una fila de la tabla users como objeto tipado.
 */
final class User extends Model
{
    protected static string $table = 'users';

    public int $id = 0;
    public string $name = '';
    public string $email = '';
    public string $password_hash = '';
    public string $role = 'estudiante';
    public string $locale = 'es';
    public ?string $email_verified_at = null;
    public int $is_active = 1;
    public ?string $totp_secret = null;
    public int $totp_enabled = 0;
    public ?string $created_at = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $u = new self();
        $u->id = (int) ($row['id'] ?? 0);
        $u->name = (string) ($row['name'] ?? '');
        $u->email = (string) ($row['email'] ?? '');
        $u->password_hash = (string) ($row['password_hash'] ?? '');
        $u->role = (string) ($row['role'] ?? 'estudiante');
        $u->locale = (string) ($row['locale'] ?? 'es');
        $u->email_verified_at = $row['email_verified_at'] ?? null;
        $u->is_active = (int) ($row['is_active'] ?? 1);
        $u->totp_secret = $row['totp_secret'] ?? null;
        $u->totp_enabled = (int) ($row['totp_enabled'] ?? 0);
        $u->created_at = $row['created_at'] ?? null;
        return $u;
    }

    public static function findById(int $id): ?self
    {
        $row = self::findRow($id);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $row = Database::instance()->fetch(
            'SELECT * FROM {users} WHERE email = ? LIMIT 1',
            [mb_strtolower(trim($email))],
        );
        return $row ? self::fromRow($row) : null;
    }

    public static function emailExists(string $email): bool
    {
        return (bool) Database::instance()->scalar(
            'SELECT 1 FROM {users} WHERE email = ? LIMIT 1',
            [mb_strtolower(trim($email))],
        );
    }

    /** @param array<string,mixed> $attributes */
    public static function create(array $attributes): self
    {
        $attributes['email'] = mb_strtolower(trim((string) $attributes['email']));
        if (isset($attributes['password'])) {
            $attributes['password_hash'] = Hash::make((string) $attributes['password']);
            unset($attributes['password']);
        }
        $attributes['created_at'] = date('Y-m-d H:i:s');
        $id = Database::instance()->insert('users', $attributes);
        return self::findById($id) ?? throw new \RuntimeException('No se pudo crear el usuario.');
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function save(array $changes): void
    {
        Database::instance()->update('users', $changes, ['id' => $this->id]);
    }
}
