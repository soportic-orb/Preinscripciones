<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Convocatoria / edición de un curso. La preinscripción se hace a una edición.
 */
final class CourseEdition extends Model
{
    protected static string $table = 'course_editions';

    public const MODALITIES = ['presencial', 'online', 'hibrido'];
    public const STATUS = ['draft', 'open', 'closed'];

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {course_editions} WHERE id = ?', [$id]);
    }

    /** Edición con datos del curso unidos. @return array<string,mixed>|null */
    public static function findWithCourse(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT e.*, c.title AS course_title, c.description AS course_description,
                    c.code AS course_code, c.price AS course_price, c.prerequisite_course_id
             FROM {course_editions} e JOIN {courses} c ON c.id = e.course_id WHERE e.id = ?',
            [$id],
        );
    }

    /** @return array<int,array<string,mixed>> ediciones de un curso */
    public static function forCourse(int $courseId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {course_editions} WHERE course_id = ? ORDER BY start_date DESC, id DESC',
            [$courseId],
        );
    }

    /** Ediciones abiertas a preinscripción (estado open y dentro de plazo). @return array<int,array<string,mixed>> */
    public static function openForPreinscription(): array
    {
        $now = date('Y-m-d H:i:s');
        return Database::instance()->fetchAll(
            "SELECT e.*, c.title AS course_title, c.code AS course_code
             FROM {course_editions} e JOIN {courses} c ON c.id = e.course_id
             WHERE e.status = 'open' AND c.is_active = 1
               AND (e.preinscription_open_at IS NULL OR e.preinscription_open_at <= ?)
               AND (e.preinscription_close_at IS NULL OR e.preinscription_close_at >= ?)
             ORDER BY e.start_date ASC",
            [$now, $now],
        );
    }

    public static function effectivePrice(array $edition): ?float
    {
        if ($edition['price'] !== null && $edition['price'] !== '') {
            return (float) $edition['price'];
        }
        return isset($edition['course_price']) && $edition['course_price'] !== null
            ? (float) $edition['course_price'] : null;
    }

    /** @param array<string,mixed> $data */
    public static function store(array $data): int
    {
        return Database::instance()->insert('course_editions', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function update(int $id, array $data): void
    {
        Database::instance()->update('course_editions', $data + ['updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    /** ¿Está dentro del periodo de preinscripción y abierta? */
    public static function isOpenNow(array $edition): bool
    {
        if (($edition['status'] ?? '') !== 'open') {
            return false;
        }
        $now = time();
        $open = $edition['preinscription_open_at'] ?? null;
        $close = $edition['preinscription_close_at'] ?? null;
        if ($open && strtotime((string) $open) > $now) {
            return false;
        }
        if ($close && strtotime((string) $close) < $now) {
            return false;
        }
        return true;
    }
}
