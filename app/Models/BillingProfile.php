<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Datos fiscales del pagador (estudiante o empresa).
 */
final class BillingProfile extends Model
{
    protected static string $table = 'billing_profiles';

    /** @return array<string,mixed>|null perfil del usuario (el último guardado) */
    public static function forUser(int $userId): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM {billing_profiles} WHERE user_id = ? ORDER BY id DESC LIMIT 1',
            [$userId],
        );
    }

    /** @param array<string,mixed> $data */
    public static function save(int $userId, array $data): int
    {
        $db = Database::instance();
        $existing = self::forUser($userId);
        if ($existing !== null) {
            $db->update('billing_profiles', $data + ['updated_at' => date('Y-m-d H:i:s')], ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }
        return $db->insert('billing_profiles', $data + ['user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
