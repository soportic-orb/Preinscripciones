<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Course;
use App\Models\User;

/**
 * Recordatorios automáticos (ejecutados por cron): pago/plazo pendiente,
 * documentación incompleta, inicio de curso próximo y cierre de plazo de
 * preinscripción. Idempotente mediante la tabla reminders_sent.
 */
final class ReminderService
{
    /** Ejecuta todos los recordatorios. Devuelve el total enviado. */
    public function run(): int
    {
        return $this->paymentDue()
            + $this->incompleteDocuments()
            + $this->courseStartingSoon()
            + $this->deadlineClosing();
    }

    private function alreadySent(string $key): bool
    {
        $db = Database::instance();
        if ($db->scalar('SELECT 1 FROM {reminders_sent} WHERE reminder_key = ?', [$key])) {
            return true;
        }
        $db->insert('reminders_sent', ['reminder_key' => $key, 'sent_at' => date('Y-m-d H:i:s')]);
        return false;
    }

    /** Pagos pendientes con vencimiento en los próximos 3 días o vencidos. */
    private function paymentDue(): int
    {
        $db = Database::instance();
        $rows = $db->fetchAll(
            "SELECT * FROM {payments}
             WHERE status = 'pendiente' AND due_date IS NOT NULL AND due_date <= ?",
            [date('Y-m-d H:i:s', strtotime('+3 days'))],
        );
        $n = 0;
        foreach ($rows as $p) {
            $key = 'payment_due:' . $p['id'] . ':' . date('Y-m-d', strtotime((string) $p['due_date']));
            if ($this->alreadySent($key)) {
                continue;
            }
            $user = User::findById((int) $p['user_id']);
            if ($user !== null) {
                Notifier::toUser($user, 'reminder_payment', [
                    'name' => $user->name,
                    'amount' => number_format((float) $p['amount'] - (float) $p['discount_amount'], 2),
                ]);
                $n++;
            }
        }
        return $n;
    }

    /** Preinscripciones con documentación pendiente/rechazada. */
    private function incompleteDocuments(): int
    {
        $db = Database::instance();
        $rows = $db->fetchAll(
            "SELECT DISTINCT p.id, p.user_id FROM {preinscriptions} p
             JOIN {preinscription_documents} d ON d.preinscription_id = p.id
             WHERE p.status IN ('preinscrito','documentacion_en_revision')
               AND d.status IN ('pendiente','rechazado')",
        );
        $n = 0;
        foreach ($rows as $p) {
            $key = 'docs_incomplete:' . $p['id'] . ':' . date('Y-W');
            if ($this->alreadySent($key)) {
                continue;
            }
            $user = User::findById((int) $p['user_id']);
            if ($user !== null) {
                Notifier::toUser($user, 'reminder_documents', ['name' => $user->name]);
                $n++;
            }
        }
        return $n;
    }

    /** Cursos que empiezan en los próximos 7 días. */
    private function courseStartingSoon(): int
    {
        $db = Database::instance();
        $rows = $db->fetchAll(
            "SELECT p.user_id, c.title AS course_title, e.name AS edition_name, e.id AS edition_id, e.start_date
             FROM {preinscriptions} p
             JOIN {course_editions} e ON e.id = p.edition_id
             JOIN {courses} c ON c.id = e.course_id
             WHERE p.status = 'matriculado' AND e.start_date IS NOT NULL
               AND e.start_date BETWEEN ? AND ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+7 days'))],
        );
        $n = 0;
        foreach ($rows as $r) {
            $key = 'course_start:' . $r['edition_id'] . ':' . $r['user_id'];
            if ($this->alreadySent($key)) {
                continue;
            }
            $user = User::findById((int) $r['user_id']);
            if ($user !== null) {
                Notifier::toUser($user, 'reminder_course_start', [
                    'name' => $user->name,
                    'course' => Course::localized($r['course_title'] ?? ''),
                    'edition' => (string) $r['edition_name'],
                ]);
                $n++;
            }
        }
        return $n;
    }

    /** Convocatorias cuyo plazo de preinscripción cierra en 3 días (a gestores). */
    private function deadlineClosing(): int
    {
        $db = Database::instance();
        $rows = $db->fetchAll(
            "SELECT e.id, e.name, c.title AS course_title, e.preinscription_close_at
             FROM {course_editions} e JOIN {courses} c ON c.id = e.course_id
             WHERE e.status = 'open' AND e.preinscription_close_at IS NOT NULL
               AND e.preinscription_close_at BETWEEN ? AND ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+3 days'))],
        );
        $n = 0;
        $staff = $db->fetchAll("SELECT * FROM {users} WHERE role IN ('owner','admin','gestor') AND is_active = 1");
        foreach ($rows as $r) {
            $key = 'deadline:' . $r['id'] . ':' . date('Y-m-d', strtotime((string) $r['preinscription_close_at']));
            if ($this->alreadySent($key)) {
                continue;
            }
            foreach ($staff as $row) {
                Notifier::toEmail((string) $row['email'], (string) $row['name'], 'reminder_deadline', [
                    'course' => Course::localized($r['course_title'] ?? ''),
                    'edition' => (string) $r['name'],
                ], (string) $row['locale']);
                $n++;
            }
        }
        Logger::info('Recordatorios de cierre de plazo procesados.', ['editions' => count($rows)], 'cron');
        return $n;
    }
}
