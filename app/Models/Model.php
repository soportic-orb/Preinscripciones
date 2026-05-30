<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo base ligero (active-record minimal) sobre la capa Database.
 */
abstract class Model
{
    protected static string $table = '';

    protected static function db(): Database
    {
        return Database::instance();
    }

    /** @return array<string,mixed>|null */
    public static function findRow(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM {' . static::$table . '} WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function allRows(string $orderBy = 'id ASC'): array
    {
        return self::db()->fetchAll('SELECT * FROM {' . static::$table . '} ORDER BY ' . $orderBy);
    }
}
