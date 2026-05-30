<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Generación y verificación de tokens de un solo uso (verificación email,
 * recuperación de contraseña). Se almacena solo el hash en la base de datos.
 */
final class Token
{
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Crea un token para un usuario en la tabla dada y devuelve el token en claro.
     */
    public static function issue(string $table, int $userId, int $ttlSeconds = 86400): string
    {
        $token = self::generate();
        $db = Database::instance();
        $db->run('DELETE FROM {' . $table . '} WHERE user_id = ?', [$userId]);
        $db->insert($table, [
            'user_id' => $userId,
            'token_hash' => self::hash($token),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /** @return array<string,mixed>|null fila válida o null si no existe/expiró */
    public static function consume(string $table, string $token): ?array
    {
        $db = Database::instance();
        $row = $db->fetch(
            'SELECT * FROM {' . $table . '} WHERE token_hash = ? LIMIT 1',
            [self::hash($token)],
        );
        if ($row === null) {
            return null;
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            $db->run('DELETE FROM {' . $table . '} WHERE id = ?', [$row['id']]);
            return null;
        }
        $db->run('DELETE FROM {' . $table . '} WHERE id = ?', [$row['id']]);
        return $row;
    }
}
