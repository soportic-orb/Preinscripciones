<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * KPIs e informes para el dashboard de gestión y exportaciones CSV.
 */
final class ReportService
{
    /** @return array<string,mixed> */
    public function kpis(): array
    {
        $db = Database::instance();
        $byStatus = [];
        foreach ($db->fetchAll("SELECT status, COUNT(*) AS n FROM {preinscriptions} WHERE status != 'borrador' GROUP BY status") as $row) {
            $byStatus[(string) $row['status']] = (int) $row['n'];
        }

        $totalPre = array_sum($byStatus);
        $enrolled = $byStatus['matriculado'] ?? 0;
        // Ingresos = pagos cobrados (los reembolsados ya no están en estado 'pagado').
        $income = (float) ($db->scalar("SELECT COALESCE(SUM(amount - discount_amount),0) FROM {payments} WHERE status = 'pagado'") ?? 0);
        $pending = (float) ($db->scalar("SELECT COALESCE(SUM(amount - discount_amount),0) FROM {payments} WHERE status IN ('pendiente','pago_en_revision')") ?? 0);

        return [
            'total_preinscriptions' => $totalPre,
            'by_status' => $byStatus,
            'enrolled' => $enrolled,
            'income' => round($income, 2),
            'pending_payments' => round($pending, 2),
            'conversion_rate' => $totalPre > 0 ? round($enrolled / $totalPre * 100, 1) : 0.0,
            'pending_docs' => (int) ($db->scalar("SELECT COUNT(*) FROM {preinscription_documents} WHERE status = 'pendiente'") ?? 0),
            'in_waitlist' => $byStatus['en_lista_de_espera'] ?? 0,
        ];
    }

    /** Ocupación por edición (plazas y lista de espera). @return array<int,array<string,mixed>> */
    public function editionOccupancy(): array
    {
        $db = Database::instance();
        $rows = $db->fetchAll(
            "SELECT e.id, e.name, e.capacity, c.title AS course_title,
                    (SELECT COUNT(*) FROM {preinscriptions} p WHERE p.edition_id = e.id AND p.status IN ('aceptado','pendiente_pago','pago_en_revision','matriculado')) AS occupied,
                    (SELECT COUNT(*) FROM {preinscriptions} p WHERE p.edition_id = e.id AND p.status = 'en_lista_de_espera') AS waitlist
             FROM {course_editions} e JOIN {courses} c ON c.id = e.course_id
             ORDER BY e.id DESC",
        );
        return $rows;
    }

    /** Datos para exportación CSV. @return array{0:array<int,string>,1:array<int,array<int,string>>} */
    public function export(string $type): array
    {
        $db = Database::instance();
        return match ($type) {
            'payments' => [
                ['ID', 'Estudiante', 'Email', 'Concepto', 'Importe', 'Descuento', 'Estado', 'Método', 'Fecha'],
                array_map(fn ($r) => [
                    (string) $r['id'], (string) $r['student_name'], (string) $r['student_email'],
                    (string) $r['concept'], (string) $r['amount'], (string) $r['discount_amount'],
                    (string) $r['status'], (string) ($r['method'] ?? ''), (string) ($r['paid_at'] ?? ''),
                ], $db->fetchAll(
                    'SELECT p.*, u.name AS student_name, u.email AS student_email
                     FROM {payments} p JOIN {users} u ON u.id = p.user_id ORDER BY p.id',
                )),
            ],
            'pending_docs' => [
                ['Preinscripción', 'Estudiante', 'Documento', 'Estado'],
                array_map(fn ($r) => [
                    (string) $r['preinscription_id'], (string) $r['student_name'],
                    (string) $r['original_name'], (string) $r['status'],
                ], $db->fetchAll(
                    "SELECT d.*, u.name AS student_name FROM {preinscription_documents} d
                     JOIN {preinscriptions} p ON p.id = d.preinscription_id
                     JOIN {users} u ON u.id = p.user_id WHERE d.status = 'pendiente' ORDER BY d.id",
                )),
            ],
            default => [ // students by edition
                ['Preinscripción', 'Estudiante', 'Email', 'Curso', 'Convocatoria', 'Estado'],
                array_map(fn ($r) => [
                    (string) $r['id'], (string) $r['student_name'], (string) $r['student_email'],
                    \App\Models\Course::localized($r['course_title'] ?? ''), (string) $r['edition_name'], (string) $r['status'],
                ], $db->fetchAll(
                    "SELECT p.id, p.status, u.name AS student_name, u.email AS student_email,
                            c.title AS course_title, e.name AS edition_name
                     FROM {preinscriptions} p
                     JOIN {course_editions} e ON e.id = p.edition_id
                     JOIN {courses} c ON c.id = e.course_id
                     JOIN {users} u ON u.id = p.user_id
                     WHERE p.status != 'borrador' ORDER BY p.id",
                )),
            ],
        };
    }
}
