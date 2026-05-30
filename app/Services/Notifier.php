<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\I18n;
use App\Core\Mailer;
use App\Models\EmailTemplate;
use App\Models\User;

/**
 * Notificaciones por email en el idioma del destinatario.
 *
 * Resolución de la plantilla:
 *  1) Plantilla editable en BD (email_templates) para evento+idioma, si existe y
 *     está activa (editada desde Ajustes → Plantillas de email).
 *  2) Fallback al grupo i18n 'notifications' (<evento>_subject / <evento>_body).
 * En ambos casos se reemplazan las :variables.
 */
final class Notifier
{
    /** @param array<string,string|int> $vars */
    public static function toUser(User $user, string $event, array $vars = []): bool
    {
        $locale = $user->locale ?: I18n::locale();
        [$subject, $html] = self::resolve($event, $locale, $vars);
        return Mailer::send($user->email, $user->name, $subject, $html);
    }

    /** @param array<string,string|int> $vars */
    public static function toEmail(string $email, string $name, string $event, array $vars = [], ?string $locale = null): bool
    {
        $locale = $locale ?? I18n::locale();
        [$subject, $html] = self::resolve($event, $locale, $vars);
        return Mailer::send($email, $name, $subject, $html);
    }

    /**
     * Devuelve [asunto, htmlBody] resolviendo plantilla de BD o i18n.
     *
     * @param array<string,string|int> $vars
     * @return array{0:string,1:string}
     */
    private static function resolve(string $event, string $locale, array $vars): array
    {
        $tpl = null;
        try {
            $tpl = EmailTemplate::findActive($event, $locale)
                ?? EmailTemplate::findActive($event, 'es');
        } catch (\Throwable) {
            // tabla aún no migrada
        }

        if ($tpl !== null) {
            $subject = self::replace((string) $tpl['subject'], $vars);
            $body = self::replace((string) $tpl['body_html'], $vars);
            return [$subject, self::wrap($subject, $body, true)];
        }

        $subject = I18n::t('notifications.' . $event . '_subject', $vars, $locale);
        $body = I18n::t('notifications.' . $event . '_body', $vars, $locale);
        return [$subject, self::wrap($subject, $body, false)];
    }

    /** @param array<string,string|int> $vars */
    private static function replace(string $text, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $text = str_replace([':' . $k, '{{' . $k . '}}'], (string) $v, $text);
        }
        return $text;
    }

    private static function wrap(string $title, string $body, bool $isHtml): string
    {
        $content = $isHtml ? $body : '<p>' . nl2br(e($body)) . '</p>';
        return '<div style="font-family:Helvetica,Arial,sans-serif;color:#525252;max-width:600px;margin:0 auto">'
            . '<h2 style="color:#A11600">' . e($title) . '</h2>'
            . $content
            . '<hr style="border:none;border-top:1px solid #e3e3e3">'
            . '<p style="font-size:12px;color:#8a8a8a">IEM · Institut d\'Estudis Mèdics</p></div>';
    }
}
