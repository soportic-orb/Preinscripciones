<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\I18n;

/**
 * Curso (reglado o no reglado). Los textos (title, description,
 * access_requirements) se almacenan como JSON multiidioma {es,ca,en,pt}.
 */
final class Course extends Model
{
    protected static string $table = 'courses';

    public const TYPES = ['reglado', 'no_reglado'];

    /** Texto localizado de un campo JSON multiidioma. */
    public static function localized(?string $json): string
    {
        if (!is_string($json) || $json === '') {
            return '';
        }
        $bag = json_decode($json, true);
        if (!is_array($bag)) {
            return $json; // texto plano heredado
        }
        $loc = I18n::locale();
        return $bag[$loc] ?? ($bag['es'] ?? (reset($bag) ?: ''));
    }

    /** @return array<int,array<string,mixed>> */
    public static function allActive(): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {courses} WHERE is_active = 1 ORDER BY id DESC',
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::instance()->fetchAll('SELECT * FROM {courses} ORDER BY id DESC');
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {courses} WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $data */
    public static function store(array $data): int
    {
        return Database::instance()->insert('courses', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }
}
