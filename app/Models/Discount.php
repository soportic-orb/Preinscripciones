<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Descuento, beca o código promocional.
 */
final class Discount extends Model
{
    protected static string $table = 'discounts';

    public const TYPES = ['percent', 'amount'];
    public const SCOPES = ['all', 'course', 'edition'];

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::instance()->fetchAll('SELECT * FROM {discounts} ORDER BY id DESC');
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {discounts} WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null descuento válido por código */
    public static function findValidByCode(string $code): ?array
    {
        $row = Database::instance()->fetch(
            'SELECT * FROM {discounts} WHERE code = ? AND is_active = 1 LIMIT 1',
            [strtoupper(trim($code))],
        );
        if ($row === null) {
            return null;
        }
        $now = time();
        if (!empty($row['valid_from']) && strtotime((string) $row['valid_from']) > $now) {
            return null;
        }
        if (!empty($row['valid_to']) && strtotime((string) $row['valid_to']) < $now) {
            return null;
        }
        if ((int) $row['max_uses'] > 0 && (int) $row['used_count'] >= (int) $row['max_uses']) {
            return null;
        }
        return $row;
    }

    /** Calcula el importe de descuento sobre una base. */
    public static function computeAmount(array $discount, float $base): float
    {
        if ($discount['type'] === 'percent') {
            return round($base * ((float) $discount['value'] / 100), 2);
        }
        return min($base, round((float) $discount['value'], 2));
    }

    /** ¿Aplica el descuento a un curso/edición concretos? */
    public static function appliesTo(array $discount, int $courseId, int $editionId): bool
    {
        return match ($discount['scope']) {
            'course' => (int) $discount['scope_id'] === $courseId,
            'edition' => (int) $discount['scope_id'] === $editionId,
            default => true,
        };
    }

    public static function store(array $data): int
    {
        return Database::instance()->insert('discounts', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }
}
