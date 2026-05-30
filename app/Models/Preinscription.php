<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\PreinscriptionStatus;

/**
 * Preinscripción de un estudiante a una edición concreta.
 */
final class Preinscription extends Model
{
    protected static string $table = 'preinscriptions';

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {preinscriptions} WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null preinscripción + curso/edición */
    public static function findFull(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT p.*, e.name AS edition_name, e.modality, e.start_date, e.capacity,
                    c.title AS course_title, c.code AS course_code, c.id AS course_id,
                    u.name AS student_name, u.email AS student_email
             FROM {preinscriptions} p
             JOIN {course_editions} e ON e.id = p.edition_id
             JOIN {courses} c ON c.id = e.course_id
             JOIN {users} u ON u.id = p.user_id
             WHERE p.id = ?',
            [$id],
        );
    }

    /** @return array<int,array<string,mixed>> preinscripciones de un usuario */
    public static function forUser(int $userId): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.*, e.name AS edition_name, c.title AS course_title
             FROM {preinscriptions} p
             JOIN {course_editions} e ON e.id = p.edition_id
             JOIN {courses} c ON c.id = e.course_id
             WHERE p.user_id = ? ORDER BY p.created_at DESC',
            [$userId],
        );
    }

    /** Borrador en curso de un usuario para una edición, si existe. @return array<string,mixed>|null */
    public static function draftFor(int $userId, int $editionId): ?array
    {
        return Database::instance()->fetch(
            "SELECT * FROM {preinscriptions} WHERE user_id = ? AND edition_id = ? AND status = 'borrador' LIMIT 1",
            [$userId, $editionId],
        );
    }

    public static function existsActive(int $userId, int $editionId): bool
    {
        return (bool) Database::instance()->scalar(
            "SELECT 1 FROM {preinscriptions}
             WHERE user_id = ? AND edition_id = ? AND status NOT IN ('rechazado','cancelado','borrador') LIMIT 1",
            [$userId, $editionId],
        );
    }

    /** Plazas ocupadas de una edición (preinscripciones que cuentan para aforo). */
    public static function occupiedSeats(int $editionId): int
    {
        return (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM {preinscriptions}
             WHERE edition_id = ? AND status IN ('aceptado','pendiente_pago','pago_en_revision','matriculado')",
            [$editionId],
        );
    }

    /** @param array<string,mixed> $data */
    public static function store(array $data): int
    {
        return Database::instance()->insert('preinscriptions', $data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function update(int $id, array $data): void
    {
        Database::instance()->update('preinscriptions', $data + ['updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    /** Listado para gestión con filtros opcionales. @return array<int,array<string,mixed>> */
    public static function search(array $filters = []): array
    {
        $sql = 'SELECT p.*, e.name AS edition_name, c.title AS course_title,
                       u.name AS student_name, u.email AS student_email
                FROM {preinscriptions} p
                JOIN {course_editions} e ON e.id = p.edition_id
                JOIN {courses} c ON c.id = e.course_id
                JOIN {users} u ON u.id = p.user_id
                WHERE p.status != ?';
        $params = [PreinscriptionStatus::BORRADOR];
        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['edition_id'])) {
            $sql .= ' AND p.edition_id = ?';
            $params[] = (int) $filters['edition_id'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        $sql .= ' ORDER BY p.created_at DESC';
        return Database::instance()->fetchAll($sql, $params);
    }

    /** Siguiente en lista de espera de una edición. @return array<string,mixed>|null */
    public static function nextInWaitlist(int $editionId): ?array
    {
        return Database::instance()->fetch(
            "SELECT * FROM {preinscriptions}
             WHERE edition_id = ? AND status = 'en_lista_de_espera'
             ORDER BY waitlist_position ASC, created_at ASC LIMIT 1",
            [$editionId],
        );
    }
}
