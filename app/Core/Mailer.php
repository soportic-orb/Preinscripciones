<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envío de correo. Usa PHPMailer (vendor/) sobre SMTP si está disponible;
 * en su defecto recurre a mail() nativo. Las plantillas multiidioma se
 * resolverán mediante el módulo de notificaciones en fases posteriores.
 */
final class Mailer
{
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $cfg = Config::mail();

        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) && $cfg['host'] !== '') {
            return self::sendWithPhpMailer($cfg, $toEmail, $toName, $subject, $htmlBody, $textBody);
        }

        return self::sendNative($cfg, $toEmail, $subject, $htmlBody);
    }

    private static function sendWithPhpMailer(array $cfg, string $toEmail, string $toName, string $subject, string $html, ?string $text): bool
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->Port = (int) $cfg['port'];
            $mail->SMTPAuth = $cfg['user'] !== '';
            $mail->Username = $cfg['user'];
            $mail->Password = $cfg['pass'];
            if ($cfg['encryption'] === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($cfg['encryption'] === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($cfg['from_address'], $cfg['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text ?? strip_tags($html);
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            Logger::error('Fallo SMTP: ' . $e->getMessage(), [], 'mail');
            return false;
        }
    }

    private static function sendNative(array $cfg, string $toEmail, string $subject, string $html): bool
    {
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $cfg['from_name'], $cfg['from_address']),
        ]);
        // En entornos sin MTA esto registrará el contenido para depuración.
        if (Config::app()['env'] !== 'production') {
            Logger::info('Email (modo dev) a ' . $toEmail . ' — ' . $subject, ['body' => $html], 'mail');
            return true;
        }
        return @mail($toEmail, $subject, $html, $headers);
    }
}
