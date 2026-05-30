<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Rbac;
use App\Models\MessageThread;
use App\Models\User;

/**
 * Mensajería interna estudiante ↔ gestión. Notifica por email al recibir un
 * mensaje nuevo y gestiona los contadores de no leídos.
 */
final class MessageService
{
    /** Añade un mensaje a un hilo y notifica a la otra parte. */
    public function post(int $threadId, User $sender, string $body): void
    {
        $isStaff = Rbac::isStaff($sender);
        $db = Database::instance();
        $db->insert('messages', [
            'thread_id' => $threadId,
            'sender_id' => $sender->id,
            'is_staff' => $isStaff ? 1 : 0,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $db->update('message_threads', ['last_message_at' => date('Y-m-d H:i:s')], ['id' => $threadId]);
        $this->markRead($threadId, $sender->id);

        $thread = MessageThread::find($threadId);
        if ($thread === null) {
            return;
        }

        if ($isStaff) {
            // Notificar al estudiante.
            $student = User::findById((int) $thread['student_id']);
            if ($student !== null) {
                Notifier::toUser($student, 'new_message', ['name' => $student->name]);
            }
        } else {
            // Notificar a los gestores.
            $staff = Database::instance()->fetchAll("SELECT * FROM {users} WHERE role IN ('owner','admin','gestor') AND is_active = 1");
            foreach ($staff as $row) {
                Notifier::toEmail((string) $row['email'], (string) $row['name'], 'new_message', ['name' => (string) $row['name']], (string) $row['locale']);
            }
        }
    }

    /** Crea un hilo nuevo con su primer mensaje. */
    public function start(User $student, string $subject, string $body, ?int $preinscriptionId = null): int
    {
        $threadId = MessageThread::create($student->id, $subject, $preinscriptionId);
        $this->post($threadId, $student, $body);
        return $threadId;
    }

    public function markRead(int $threadId, int $userId): void
    {
        $db = Database::instance();
        $lastMsgId = (int) ($db->scalar('SELECT COALESCE(MAX(id),0) FROM {messages} WHERE thread_id = ?', [$threadId]) ?? 0);
        $exists = $db->scalar('SELECT id FROM {thread_reads} WHERE thread_id = ? AND user_id = ?', [$threadId, $userId]);
        $data = ['last_read_message_id' => $lastMsgId, 'last_read_at' => date('Y-m-d H:i:s')];
        if ($exists) {
            $db->update('thread_reads', $data, ['id' => (int) $exists]);
        } else {
            $db->insert('thread_reads', $data + ['thread_id' => $threadId, 'user_id' => $userId]);
        }
    }

    /** Nº de hilos con mensajes no leídos para un usuario. */
    public function unreadThreads(User $user): int
    {
        $db = Database::instance();
        if (Rbac::isStaff($user)) {
            $threads = $db->fetchAll('SELECT id FROM {message_threads}');
        } else {
            $threads = $db->fetchAll('SELECT id FROM {message_threads} WHERE student_id = ?', [$user->id]);
        }
        $count = 0;
        foreach ($threads as $t) {
            if ($this->hasUnread((int) $t['id'], $user->id)) {
                $count++;
            }
        }
        return $count;
    }

    public function hasUnread(int $threadId, int $userId): bool
    {
        $db = Database::instance();
        $lastReadId = (int) ($db->scalar('SELECT last_read_message_id FROM {thread_reads} WHERE thread_id = ? AND user_id = ?', [$threadId, $userId]) ?? 0);
        // ¿Existe algún mensaje de otra persona posterior al último leído?
        return (bool) $db->scalar(
            'SELECT 1 FROM {messages} WHERE thread_id = ? AND sender_id != ? AND id > ? LIMIT 1',
            [$threadId, $userId, $lastReadId],
        );
    }

    public function canAccess(array $thread, User $user): bool
    {
        return (int) $thread['student_id'] === $user->id || Rbac::isStaff($user);
    }
}
