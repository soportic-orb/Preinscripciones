<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Certificado/diploma emitido al finalizar un curso.
 */
final class Certificate extends Model
{
    protected static string $table = 'certificates';

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {certificates} WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public static function findByCode(string $code): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {certificates} WHERE code = ? LIMIT 1', [$code]);
    }

    /** @return array<string,mixed>|null */
    public static function forPreinscription(int $preinscriptionId): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {certificates} WHERE preinscription_id = ? LIMIT 1', [$preinscriptionId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        return Database::instance()->fetchAll('SELECT * FROM {certificates} WHERE user_id = ? ORDER BY issued_at DESC', [$userId]);
    }

    public static function store(array $data): int
    {
        return Database::instance()->insert('certificates', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }
}
