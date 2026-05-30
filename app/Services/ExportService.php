<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Course;
use App\Models\CourseEdition;

/**
 * Exportaciones e integraciones: matriculados para el campus AlexiaEdu (CSV) y
 * calendario iCal de las fechas de una edición.
 */
final class ExportService
{
    /**
     * CSV de matriculados de una edición en formato compatible con AlexiaEdu.
     *
     * @return array{0:array<int,string>,1:array<int,array<int,string>>}
     */
    public function alexiaCsv(int $editionId): array
    {
        $rows = Database::instance()->fetchAll(
            "SELECT u.name AS student_name, u.email,
                    (SELECT value FROM {field_values} v JOIN {field_definitions} d ON d.id = v.field_id
                     WHERE d.field_key = 'fecha_nacimiento' AND v.entity_type = 'preinscription' AND v.entity_id = p.id LIMIT 1) AS dob,
                    (SELECT value FROM {field_values} v JOIN {field_definitions} d ON d.id = v.field_id
                     WHERE d.field_key = 'telefono' AND v.entity_type = 'preinscription' AND v.entity_id = p.id LIMIT 1) AS phone
             FROM {preinscriptions} p JOIN {users} u ON u.id = p.user_id
             WHERE p.edition_id = ? AND p.status = 'matriculado'",
            [$editionId],
        );
        $headers = ['Nombre', 'Email', 'FechaNacimiento', 'Telefono'];
        $data = array_map(fn ($r) => [
            (string) $r['student_name'], (string) $r['email'],
            (string) ($r['dob'] ?? ''), (string) ($r['phone'] ?? ''),
        ], $rows);
        return [$headers, $data];
    }

    /** Genera el contenido iCal (.ics) para las fechas de una edición. */
    public function ical(int $editionId): ?string
    {
        $edition = CourseEdition::findWithCourse($editionId);
        if ($edition === null || empty($edition['start_date'])) {
            return null;
        }
        $course = Course::localized($edition['course_title'] ?? '');
        $start = $this->icalDate((string) $edition['start_date']);
        $end = $this->icalDate((string) ($edition['end_date'] ?: $edition['start_date']));
        $uid = 'edition-' . $editionId . '@iem';
        $now = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IEM//Preinscripciones//ES',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $now,
            'DTSTART;VALUE=DATE:' . $start,
            'DTEND;VALUE=DATE:' . $end,
            'SUMMARY:' . $this->escape($course . ' — ' . (string) $edition['name']),
            'LOCATION:' . $this->escape((string) ($edition['location'] ?? '')),
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines) . "\r\n";
    }

    private function icalDate(string $date): string
    {
        $ts = strtotime($date) ?: time();
        return date('Ymd', $ts);
    }

    private function escape(string $text): string
    {
        return str_replace([',', ';', "\n"], ['\,', '\;', '\n'], $text);
    }
}
