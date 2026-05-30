<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\I18n;
use App\Core\Mailer;
use App\Models\User;

/**
 * Notificaciones por email en el idioma del destinatario.
 *
 * Las plantillas se resuelven con el grupo i18n 'notifications'. Cada evento
 * usa notifications.<evento>_subject y notifications.<evento>_body, con
 * reemplazo de :variables. El editor visual de plantillas llega en el Bloque D;
 * aquí enviamos HTML simple multiidioma.
 */
final class Notifier
{
    /** @param array<string,string|int> $vars */
    public static function toUser(User $user, string $event, array $vars = []): bool
    {
        $locale = $user->locale ?: I18n::locale();
        $subject = I18n::t('notifications.' . $event . '_subject', $vars, $locale);
        $body = I18n::t('notifications.' . $event . '_body', $vars, $locale);
        return Mailer::send($user->email, $user->name, $subject, self::wrap($subject, $body));
    }

    /** @param array<string,string|int> $vars */
    public static function toEmail(string $email, string $name, string $event, array $vars = [], ?string $locale = null): bool
    {
        $locale = $locale ?? I18n::locale();
        $subject = I18n::t('notifications.' . $event . '_subject', $vars, $locale);
        $body = I18n::t('notifications.' . $event . '_body', $vars, $locale);
        return Mailer::send($email, $name, $subject, self::wrap($subject, $body));
    }

    private static function wrap(string $title, string $body): string
    {
        return '<div style="font-family:Helvetica,Arial,sans-serif;color:#525252">'
            . '<h2 style="color:#A11600">' . e($title) . '</h2>'
            . '<p>' . nl2br(e($body)) . '</p>'
            . '<hr style="border:none;border-top:1px solid #e3e3e3">'
            . '<p style="font-size:12px;color:#8a8a8a">IEM · Institut d\'Estudis Mèdics</p></div>';
    }
}
