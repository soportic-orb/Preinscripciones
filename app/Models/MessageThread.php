<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Hilo de mensajería entre un estudiante y el personal de gestión.
 */
final class MessageThread extends Model
{
    protected static string $table = 'message_threads';

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM {message_threads} WHERE id = ?', [$id]);
    }

    /** Hilos del estudiante. @return array<int,array<string,mixed>> */
    public static function forStudent(int $userId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM {message_threads} WHERE student_id = ? ORDER BY last_message_at DESC, id DESC',
            [$userId],
        );
    }

    /** Todos los hilos (gestión). @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::instance()->fetchAll(
            'SELECT t.*, u.name AS student_name, u.email AS student_email
             FROM {message_threads} t JOIN {users} u ON u.id = t.student_id
             ORDER BY t.last_message_at DESC, t.id DESC',
        );
    }

    public static function create(int $studentId, string $subject, ?int $preinscriptionId = null): int
    {
        return Database::instance()->insert('message_threads', [
            'student_id' => $studentId,
            'subject' => $subject,
            'preinscription_id' => $preinscriptionId,
            'last_message_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int,array<string,mixed>> mensajes del hilo */
    public static function messages(int $threadId): array
    {
        return Database::instance()->fetchAll(
            'SELECT m.*, u.name AS sender_name FROM {messages} m
             JOIN {users} u ON u.id = m.sender_id
             WHERE m.thread_id = ? ORDER BY m.id',
            [$threadId],
        );
    }
}
