<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Plantilla de email editable por evento e idioma (gestionada desde el panel).
 */
final class EmailTemplate extends Model
{
    protected static string $table = 'email_templates';

    /** Eventos disponibles para plantillas (coinciden con el grupo lang notifications). */
    public const EVENTS = [
        'preinscription_created', 'preinscription_accepted', 'preinscription_rejected',
        'preinscription_waitlisted', 'enrollment_available', 'payment_confirmed',
        'new_message', 'reminder_payment', 'reminder_documents', 'reminder_course_start',
        'reminder_deadline',
    ];

    /** @return array<string,mixed>|null plantilla activa para evento+idioma */
    public static function findActive(string $event, string $locale): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM {email_templates} WHERE event = ? AND locale = ? AND is_active = 1 LIMIT 1',
            [$event, $locale],
        );
    }

    /** @return array<string,mixed>|null */
    public static function findFor(string $event, string $locale): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM {email_templates} WHERE event = ? AND locale = ? LIMIT 1',
            [$event, $locale],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Database::instance()->fetchAll('SELECT * FROM {email_templates} ORDER BY event, locale');
    }

    public static function save(string $event, string $locale, string $subject, string $body, bool $active): void
    {
        $db = Database::instance();
        $existing = self::findFor($event, $locale);
        $data = [
            'subject' => $subject, 'body_html' => $body,
            'is_active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing !== null) {
            $db->update('email_templates', $data, ['id' => (int) $existing['id']]);
        } else {
            $db->insert('email_templates', $data + ['event' => $event, 'locale' => $locale, 'created_at' => date('Y-m-d H:i:s')]);
        }
    }
}
