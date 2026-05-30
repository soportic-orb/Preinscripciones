<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Config;
use App\Core\Settings;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Preinscription;

/**
 * Emisión y verificación de certificados/diplomas en PDF con código y QR.
 */
final class CertificateService
{
    /** Emite el certificado de una preinscripción matriculada (idempotente). */
    public function issue(int $preinscriptionId, ?int $actorId = null): ?int
    {
        $existing = Certificate::forPreinscription($preinscriptionId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $pre = Preinscription::findFull($preinscriptionId);
        if ($pre === null || $pre['status'] !== 'matriculado') {
            return null;
        }

        $code = strtoupper(bin2hex(random_bytes(6)));
        $courseName = Course::localized($pre['course_title'] ?? '');
        $id = Certificate::store([
            'preinscription_id' => $preinscriptionId,
            'user_id' => (int) $pre['user_id'],
            'code' => $code,
            'student_name' => (string) $pre['student_name'],
            'course_name' => $courseName,
            'edition_name' => (string) ($pre['edition_name'] ?? ''),
            'issued_at' => date('Y-m-d H:i:s'),
        ]);

        $cert = Certificate::find($id);
        $pdf = $this->renderPdf($cert);
        if ($pdf !== null) {
            \App\Core\Database::instance()->update('certificates', ['pdf_path' => $pdf], ['id' => $id]);
        }
        Audit::log('certificate.issued', $actorId, 'certificate', $id, ['code' => $code]);
        return $id;
    }

    public function verifyUrl(string $code): string
    {
        return url('verificar-certificado?code=' . $code);
    }

    /** Genera el PDF del diploma con QR de verificación. */
    private function renderPdf(array $cert): ?string
    {
        $verifyUrl = $this->verifyUrl((string) $cert['code']);
        $qr = $this->qrDataUri($verifyUrl);
        $accent = '#A11600';
        $academy = Settings::get('billing', 'academy_name', 'Institut d\'Estudis Mèdics');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:Helvetica,Arial,sans-serif;color:#333;text-align:center;padding:40px}'
            . '.frame{border:3px solid ' . $accent . ';padding:40px;border-radius:8px}'
            . 'h1{color:' . $accent . ';font-size:30px;margin:0 0 8px}'
            . '.name{font-size:26px;margin:24px 0;font-weight:700}'
            . '.muted{color:#888}'
            . '</style></head><body><div class="frame">'
            . '<div class="muted">' . e((string) $academy) . '</div>'
            . '<h1>' . e(__('certificates.diploma')) . '</h1>'
            . '<p>' . e(__('certificates.certifies_that')) . '</p>'
            . '<div class="name">' . e((string) $cert['student_name']) . '</div>'
            . '<p>' . e(__('certificates.has_completed')) . '</p>'
            . '<div style="font-size:20px;font-weight:700">' . e((string) $cert['course_name']) . '</div>'
            . '<div class="muted">' . e((string) ($cert['edition_name'] ?? '')) . '</div>'
            . '<p class="muted" style="margin-top:24px">' . e(__('certificates.issued_on')) . ' ' . e(substr((string) $cert['issued_at'], 0, 10)) . '</p>'
            . ($qr !== null ? '<img src="' . $qr . '" style="width:110px;height:110px">' : '')
            . '<div class="muted" style="font-size:11px">' . e(__('certificates.verification_code')) . ': ' . e((string) $cert['code']) . '<br>' . e($verifyUrl) . '</div>'
            . '</div></body></html>';

        $dir = STORAGE_PATH . '/uploads/certificates';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
                file_put_contents($dir . '/' . $cert['code'] . '.pdf', $dompdf->output());
                return 'certificates/' . $cert['code'] . '.pdf';
            } catch (\Throwable $e) {
                \App\Core\Logger::error('PDF certificado: ' . $e->getMessage());
            }
        }
        file_put_contents($dir . '/' . $cert['code'] . '.html', $html);
        return 'certificates/' . $cert['code'] . '.html';
    }

    /** Genera el QR como data-URI PNG si la librería está disponible. */
    private function qrDataUri(string $text): ?string
    {
        if (!class_exists(\chillerlan\QRCode\QRCode::class)) {
            return null;
        }
        try {
            $qr = new \chillerlan\QRCode\QRCode();
            return $qr->render($text); // data:image/svg+xml... o png según opciones por defecto
        } catch (\Throwable) {
            return null;
        }
    }

    public function absolutePath(string $relative): string
    {
        return STORAGE_PATH . '/uploads/' . $relative;
    }
}
